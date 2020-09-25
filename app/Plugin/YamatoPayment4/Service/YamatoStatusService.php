<?php

namespace Plugin\YamatoPayment4\Service;

use Doctrine\ORM\EntityManagerInterface;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\ShippingRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\OrderStateMachine;

use Plugin\YamatoPayment4\Entity\YamatoOrder;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Util\PluginUtil;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Service\Client\DeferredClientService;
use Plugin\YamatoPayment4\Service\Client\UtilClientService;
use Plugin\YamatoPayment4\Service\YamatoMailService;
use Plugin\YamatoPayment4\Util\CommonUtil;


/**
 * 決済状況管理クラス
 */
class YamatoStatusService
{
    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var EccubeConfig */
    protected $eccubeConfig;

    /** @var OrderRepository */
    protected $orderRepository;

    /** @var OrderStatusRepository */
    protected $orderStatusRepository;

    /** @var ShippingRepository */
    protected $shippingRepository;

    /** @var OrderStateMachine */
    protected $orderStateMachine;

    /** @var PluginUtil */
    protected $pluginUtil;

    /** @var ConfigRepository */
    protected $configRepository;

    /** @var YamatoOrderRepository */
    protected $yamatoOrderRepository;

    /** @var YamatoPaymentMethodRepository */
    protected $yamatoPaymentMethodRepository;

    /** @var DeferredClientService */
    protected $deferredClientService;

    /** @var UtilClientService */
    protected $utilClientService;

    /** @var YamatoMailService */
    protected $yamatoMailService;

    public function __construct(
        EntityManagerInterface $entityManager,
        EccubeConfig $eccubeConfig,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        ShippingRepository $shippingRepository,
        OrderStateMachine $orderStateMachine,
        PluginUtil $pluginUtil,
        ConfigRepository $configRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        DeferredClientService $deferredClientService,
        UtilClientService $utilClientService,
        YamatoMailService $yamatoMailService
    ) {
        $this->entityManager = $entityManager;
        $this->eccubeConfig = $eccubeConfig;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->shippingRepository = $shippingRepository;
        $this->orderStateMachine = $orderStateMachine;
        $this->pluginUtil = $pluginUtil;
        $this->configRepository = $configRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->deferredClientService = $deferredClientService;
        $this->utilClientService = $utilClientService;
        $this->yamatoMailService = $yamatoMailService;
    }

    /**
     * ページネーションされた受注情報を走査して計算した最終与信日を返す。
     *
     * @param PaginatorInterface $pagenation ページネーションされた受注情報
     * @param str $payment_method 決済方法(クレカ or 後払い)
     *
     * @return array $lastAuthDates 最後に与信が下りた日を格納した配列。
     */
    public function calculateLastAuthDates($pagination, $payment_method)
    {
        // 決済方法によって与信OKのステータスコードが違う。
        $status_code_auth_ok = null;
        if ($payment_method == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
            $status_code_auth_ok = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH;
        } elseif ($payment_method == $this->eccubeConfig['YAMATO_PAYID_DEFERRED']) {
            $status_code_auth_ok = YamatoPaymentStatus::DEFERRED_STATUS_AUTH_OK;
        } elseif ($payment_method == $this->eccubeConfig['YAMATO_PAYID_CVS']) {
            $status_code_auth_ok = YamatoPaymentStatus::YAMATO_ACTION_STATUS_SEND_REQUEST;
        }

        $lastAuthDates = [];

        $arrPageItems = $pagination->getItems();
        foreach ($arrPageItems as $Order) {
            $orderId = $Order->getId();

            /**
             * $arrYamatoOrderLogs: 1つの受注に対するログの履歴を格納した配列
             * $row ログを表現した連想配列 row['日付'] => ['プロパティ' => 値]
             */
            $arrYamatoOrderLogs = $Order->getYamatoOrder()->get(0)->getMemo09();
            foreach ($arrYamatoOrderLogs as $row) {
                $last_auth = null;
                foreach ($row as $datetime => $col) {
                    if (array_key_exists('action_status', $col) && $col['action_status'] == $status_code_auth_ok) {
                        $last_auth = $datetime;
                        break;
                    }
                }
                if ($last_auth !== null) {
                    break;
                }
            }
            $lastAuthDates[$orderId] = $last_auth;
        }

        return $lastAuthDates;
    }

    /**
     * 選択された受注の
     *
     * @param array $Orders 対象受注ID
     * @return array エラーメッセージ
     */
    public function checkErrorOrder($Orders)
    {
        $listErrMsg = [];

        $arrEnableMethodTypeId = $this->configRepository->parseEnablePayments();
        $YamatoEnablePayments = $this->yamatoPaymentMethodRepository->findBy(['memo03' => $arrEnableMethodTypeId]);

        foreach ($YamatoEnablePayments as $YamatoEnablePayment) {
            $enablePaymentIds[] = $YamatoEnablePayment->getPayment()->getId();
        }
        foreach ($Orders as $Order) {
            $orderId = $Order->getId();

            $msg = '注文番号 ' . $orderId . ' : ';

            // 受注ID から 支払方法ID を取得
            $paymentId = $Order->getPayment()->getId();
            // 対応支払方法チェック
            if (in_array($paymentId, $enablePaymentIds, true) === false) {
                $listErrMsg[] = $msg . '操作に対応していない決済です。';
                continue;
            }
        }

        return $listErrMsg;
    }

    /**
     * クレジットカード決済状況変更
     *
     * @param Order[] $Orders 対象受注
     * @param string $mode 決済モード
     * @return array エラーメッセージ
     */
    public function doChangeCreditPaymentStatus($Orders, $mode)
    {
        $listErrorMsg = [];
        $count = 0;

        $listErrorMsg = $this->checkErrorOrder($Orders);
        if ($listErrorMsg) {
            return [$count, $listErrorMsg];
        }

        foreach ($Orders as $Order) {

            // 決済状況変更処理
            $ret = false;

            // 決済情報を取得
            $orderId = $Order->getId();
            $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
            if (!$YamatoOrder instanceof YamatoOrder) {
                $this->utilClientService->setError('操作に対応していない注文です。');
                return $this->getErrorWithPaymentOperations($orderId, $count);
            }

            $orderPaymentId = $YamatoOrder->getMemo03();

            // 出荷情報登録処理
            if ($mode == 'commit') {
                // 出荷登録
                if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                    $errorMsg = $this->checkErrorShipmentEntryForCredit($Order);
                    if (!empty($errorMsg)) {
                        $listErrorMsg[] = $errorMsg;
                        continue;
                    }
                    // webコレクト決済用
                    list($ret, $listSuccessSlip) = $this->utilClientService->doShipmentEntry($Order, $this->shippingRepository);
                    if ($ret) {
                        $pluginData = $this->utilClientService->getSendResults();
                        $pluginData['order_id'] = $orderId;
                        $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_WAIT_SETTLEMENT;
                        $send = $this->utilClientService->getSendedData();
                        if ($send['delivery_service_code'] == '00') {
                            $pluginData['slip'] = $pluginData['slipUrlPc'];
                        } else {
                            $pluginData['slip'] = CommonUtil::serializeData($listSuccessSlip);
                        }
                        $this->utilClientService->updatePluginPurchaseLog($pluginData);

                        // 対応状況をクレジットカード出荷登録済みにする(エラーが出るため「発送済み」に変更)
                        //$order_status = $this->eccubeConfig['ORDER_SHIPPING_REGISTERED'];
                        $order_status = OrderStatus::DELIVERED;
                        $OrderStatus = $this->orderStatusRepository->find($order_status);
                        // 対応状況の変更はOrderStateMachineを利用する
                        //$orderRepository = $this->orderRepository->changeStatus($orderId, $OrderStatus);
                        $TargetOrder = $this->orderRepository->find($orderId);
                        // ステータスを遷移させることができるかどうか
                        if ($this->orderStateMachine->can($TargetOrder, $OrderStatus)) {
                            // ステータスの遷移
                            $this->orderStateMachine->apply($TargetOrder, $OrderStatus);
                        }
                        $this->entityManager->persist($TargetOrder);
                        $this->entityManager->flush();

                        // 他社配送で送り状番号が空なら登録する
                        if ($send['delivery_service_code'] != '00') {
                            foreach ($listSuccessSlip as $shippingId => $slip) {
                                $Shipping = $this->shippingRepository->find($shippingId);
                                if (empty($Shipping->getTrackingNumber())) {
                                    $Shipping->setTrackingNumber($slip);
                                    $this->entityManager->persist($Shipping);
                                    $this->entityManager->flush();
                                }
                            }
                        }

                        $count++;
                    } else {
                        //複数配送時出荷情報登録ロールバック
                        $this->utilClientService->doShipmentRollback($Order, $listSuccessSlip);
                    }
                }
            } elseif ($mode === 'clear') {

                // 出荷登録取消
                if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                    // 受注の送り状番号を取得
                    $Shippings = $this->shippingRepository->findBy(['Order' => $Order]);
                    $listSuccessSlip = [];
                    $this->utilClientService->setSetting($Order);
                    $moduleSettings = $this->utilClientService->getModuleSetting();
                    foreach ($Shippings as $Shipping) {
                        if ($moduleSettings['delivery_service_code'] == '00') {
                            $slip = $Shipping->getTrackingNumber();
                        } else {
                            $slip = $orderId;
                        }
                        if (strlen($slip)) {
                            $listSuccessSlip[] = $slip;
                        }
                    }

                    $ret = $this->utilClientService->doShipmentRollback($Order, $listSuccessSlip);
                    if ($ret) {
                        // 与信完了に戻す
                        $pluginData = $this->utilClientService->getSendResults();
                        $pluginData['order_id'] = $orderId;
                        $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH;
                        $this->utilClientService->updatePluginPurchaseLog($pluginData);

                        $count++;
                    }
                }
            } elseif ($mode === 'amount') {
                if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                    $ret = $this->utilClientService->doCreditChangePrice($Order);
                    if ($ret) {
                        $pluginData = [];
                        $pluginData['order_id'] = $orderId;
                        $pluginData['status'] = $YamatoOrder->getMemo04();
                        $pluginData['settle_price'] = $Order->getPaymentTotal();
                        $this->utilClientService->updatePluginPurchaseLog($pluginData);
                        $count++;
                    } else {
                        $result = $this->utilClientService->getResults();
                        if ($result['errorCode'] == 'A074000001') {
                            // 金額変更なしの場合
                            $pluginData = [];
                            $pluginData['order_id'] = $orderId;
                            $pluginData['status'] = $YamatoOrder->getMemo04();
                            $pluginData['settle_price'] = $Order->getPaymentTotal();
                            $this->utilClientService->updatePluginPurchaseLog($pluginData);
                        }
                    }
                }
            } elseif ($mode === 'inquiry') {
                // 取引照会
                $ret = $this->doInquiry($Order);
                if ($ret) {
                    $count++;
                }
            } elseif ($mode === 'cancel') {
                // 取消
                if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                    // webコレクト決済用
                    $ret = $this->utilClientService->doCreditCancel($Order);
                    if ($ret) {
                        $pluginData = $this->utilClientService->getSendResults();
                        $pluginData['order_id'] = $orderId;
                        $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_CANCEL;
                        $this->utilClientService->updatePluginPurchaseLog($pluginData);

                        // 対応状況をキャンセルにする
                        $order_status = OrderStatus::CANCEL;
                        $OrderStatus = $this->orderStatusRepository->find($order_status);
                        // 対応状況の変更はOrderStateMachineを利用する
                        //$orderRepository = $this->orderRepository->changeStatus($orderId, $OrderStatus);
                        $TargetOrder = $this->orderRepository->find($orderId);
                        // ステータスを遷移させることができるかどうか
                        if ($this->orderStateMachine->can($TargetOrder, $OrderStatus)) {
                            // ステータスの遷移
                            $this->orderStateMachine->apply($TargetOrder, $OrderStatus);
                        }
                        $this->entityManager->persist($TargetOrder);
                        $this->entityManager->flush();

                        $count++;
                    }
                }
            } elseif ($mode == 'reauth') {
                // 再与信
                if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                    $memo04 = $YamatoOrder->getMemo04();
                    if ($memo04 == YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH || $memo04 == YamatoPaymentStatus::YAMATO_ACTION_STATUS_CANCEL) {
                        $ret = $this->utilClientService->doReauth($Order);
                        if ($ret) {
                            // 与信完了に戻す
                            $pluginData = $this->utilClientService->getSendResults();
                            $pluginData['order_id'] = $orderId;
                            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH;
                            $this->utilClientService->updatePluginPurchaseLog($pluginData);

                            // 対応状況を新規受付にする
                            $order_status = OrderStatus::NEW;
                            $OrderStatus = $this->orderStatusRepository->find($order_status);
                            $this->orderRepository->changeStatus($orderId, $OrderStatus);

                            $count++;
                        }
                    } else {
                        $this->utilClientService->setError('再与信に対応していない決済状況です。');
                    }
                } else {
                    $this->utilClientService->setError('再与信に対応していない支払方法です。');
                }
            }

            if (!$ret) {
                return $this->getErrorWithPaymentOperations($orderId, $count);
            }
        }

        return [$count, $listErrorMsg];
    }

    /**
     * ヤマト後払い決済状況変更
     *
     * @param array $Orders 対象受注
     * @param string $mode 決済モード
     * @return array エラーメッセージ
     */
    public function doChangeDeferredPaymentStatus($Orders, $mode)
    {
        $listErrorMsg = [];
        $count = 0;

        $listErrorMsg = $this->checkErrorOrder($Orders);
        if ($listErrorMsg) {
            return [$count, $listErrorMsg];
        }

        foreach ($Orders as $Order) {

            // 決済状況変更処理
            $ret = false;

            // 決済情報を取得
            $orderId = $Order->getId();
            $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
            if (is_null($YamatoOrder)) {
                $this->utilClientService->setError('操作に対応していない注文です。');
                return $this->getErrorWithPaymentOperations($orderId, $count);
            }

            switch ($mode) {
                    // 出荷情報登録処理
                case 'commit':
                    list($ret, $listSuccessSlip) = $this->deferredClientService->doShipmentEntry($Order, $this->shippingRepository);
                    if ($ret) {
                        $pluginData = $this->deferredClientService->getResults();
                        $pluginData['order_id'] = $pluginData['orderNo'];
                        $pluginData['status'] = YamatoPaymentStatus::DEFERRED_STATUS_REGIST_DELIV_SLIP;

                        unset($pluginData['errorCode']);
                        $this->utilClientService->updatePluginPurchaseLog($pluginData);

                        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::DELIVERED);
                        // 対応状況の変更はOrderStateMachineを利用する
                        //$this->orderRepository->changeStatus($orderId, $OrderStatus);
                        $TargetOrder = $this->orderRepository->find($orderId);
                        // ステータスを遷移させることができるかどうか
                        if ($this->orderStateMachine->can($TargetOrder, $OrderStatus)) {
                            // ステータスの遷移
                            $this->orderStateMachine->apply($TargetOrder, $OrderStatus);
                        }
                        $this->entityManager->persist($TargetOrder);
                        $this->entityManager->flush();

                        $userSettings = $this->utilClientService->getUserSettings();
                        foreach ($listSuccessSlip as $shippingId => $slip) {
                            $Shipping = $this->shippingRepository->find($shippingId);
                            // 送り状番号、前回送り状番号の登録
                            if (!empty($Shipping->getTrackingNumber())) {
                                $Shipping->setPreTrackingNumber($slip);
                            } else if ($userSettings['delivery_service_code'] != '00') {
                                $Shipping->setTrackingNumber($slip);
                            }

                            // 配送追跡サービスURLの表示設定
                            if ($userSettings['delivery_service_code'] == '00' && $userSettings['ycf_deliv_disp'] == 0) {
                                $Shipping->setTrackingInformation('配送追跡サービスURL:' . $this->eccubeConfig['DEFERRED_DELIV_SLIP_URL']);
                            }

                            $this->entityManager->persist($Shipping);
                            $this->entityManager->flush();
                        }


                        $count++;
                    } else {
                        //複数配送時出荷情報登録ロールバック
                        $this->utilClientService->doShipmentRollback($Order, $listSuccessSlip);
                    }
                    break;
                    // 金額変更
                case 'amount':
                    $ret = $this->deferredClientService->doChangePrice($Order);
                    if ($ret) {
                        $pluginData = array();
                        $pluginData['order_id'] = $orderId;
                        $pluginData['status'] = $YamatoOrder->getMemo04();
                        $pluginData['settle_price'] = $Order->getPaymentTotal();
                        $this->utilClientService->updatePluginPurchaseLog($pluginData);
                        $count++;
                    } else {
                        $result = $this->deferredClientService->getResults();
                        // エラー時、金額変更なしエラーの場合のみログを更新する
                        if ($result['errorCode'] == $this->eccubeConfig['ERR_DEF_AMOUNT_NOT_CHANGE']) {
                            $pluginData = array();
                            $pluginData['order_id'] = $orderId;
                            $pluginData['status'] = $YamatoOrder->getMemo04();
                            $pluginData['settle_price'] = $Order->getPaymentTotal();
                            $this->utilClientService->updatePluginPurchaseLog($pluginData);
                        }
                    }
                    break;
                    // 与信取消
                case 'cancel':
                    $ret = $this->deferredClientService->doCancel($Order);
                    // 決済状況変更
                    $pluginData = $this->utilClientService->getSendResults();
                    $pluginData['order_id'] = $orderId;
                    $pluginData['status'] = YamatoPaymentStatus::DEFERRED_STATUS_AUTH_CANCEL;
                    $this->utilClientService->updatePluginPurchaseLog($pluginData);

                    // 対応状況変更
                    $order_status = OrderStatus::CANCEL;
                    $OrderStatus = $this->orderStatusRepository->find($order_status);
                    // 対応状況の変更はOrderStateMachineを利用する
                    //$this->orderRepository->changeStatus($orderId, $OrderStatus);
                    $TargetOrder = $this->orderRepository->find($orderId);
                    // ステータスを遷移させることができるかどうか
                    if ($this->orderStateMachine->can($TargetOrder, $OrderStatus)) {
                        // ステータスの遷移
                        $this->orderStateMachine->apply($TargetOrder, $OrderStatus);
                    }
                    $this->entityManager->persist($TargetOrder);
                    $this->entityManager->flush();

                    $count++;
                    break;
                    // 与信結果取得
                case 'get_auth':
                    $ret = $this->deferredClientService->doGetAuth($Order);
                    $arrResults = $this->deferredClientService->getResults();
                    if ($arrResults['result'] != $this->eccubeConfig['DEFERRED_AVAILABLE']) {
                        $order_status = OrderStatus::PROCESSING;
                        // 対応状況変更
                        $OrderStatus = $this->orderStatusRepository->find($order_status);
                        $this->orderRepository->changeStatus($orderId, $OrderStatus);
                    }

                    $count++;
                    break;
                case 'get_trade_info':
                    $ret = $this->deferredClientService->doGetTradeInfo($Order);
                    // 決済状況変更
                    $arrResults = $this->deferredClientService->getResults();
                    $pluginData['order_id'] = $arrResults['orderNo'];
                    $pluginData['status'] = $arrResults['result'];

                    $this->utilClientService->updatePluginPurchaseLog($pluginData);

                    // ヤマト側の取引ステータスに合わせてEC側の対応状況を更新する
                    switch ($pluginData['status']) {
                        case YamatoPaymentStatus::DEFERRED_STATUS_AUTH_OK:
                            $order_status = OrderStatus::NEW;
                            break;
                        case YamatoPaymentStatus::DEFERRED_STATUS_AUTH_CANCEL:
                            $order_status = OrderStatus::CANCEL;
                            break;
                        case YamatoPaymentStatus::DEFERRED_STATUS_RESEARCH_DELIV:
                        case YamatoPaymentStatus::DEFERRED_STATUS_SEND_WARNING:
                            $order_status = OrderStatus::IN_PROGRESS;
                            break;
                        case YamatoPaymentStatus::DEFERRED_STATUS_REGIST_DELIV_SLIP:
                        case YamatoPaymentStatus::DEFERRED_STATUS_SALES_OK:
                        case YamatoPaymentStatus::DEFERRED_STATUS_SEND_BILL:
                            $order_status = OrderStatus::DELIVERED;
                            break;
                        case YamatoPaymentStatus::DEFERRED_STATUS_PAID:
                            $order_status = OrderStatus::PAID;
                            break;
                    }
                    $OrderStatus = $this->orderStatusRepository->find($order_status);
                    $this->orderRepository->changeStatus($orderId, $OrderStatus);

                    $count++;
                    break;
                case 'invoice_reissue':
                    $ret = $this->deferredClientService->doInvoiceReissue($Order, $mode);
                    if ($ret == true) {
                        $userSettings = $this->utilClientService->getUserSettings();
                        $this->yamatoMailService->sendInvoiceReissueMail($Order, $this->container, $userSettings);
                    }

                    $count++;
                    break;
                case 'withdraw_reissue':
                    $ret = $this->deferredClientService->doInvoiceReissue($Order, $mode);

                    $count++;
                    break;
            }

            if (!$ret) {
                $this->getErrorWithPaymentOperations($orderId, $count);
            }
        }
        return [$count, $listErrorMsg];
    }

    /**
     * コンビニ決済状況変更
     *
     * @param array $Orders 対象受注
     * @param string $mode 決済モード
     * @return array エラーメッセージ
     */
    public function doChangeCvsPaymentStatus($Orders, $mode)
    {
        $listErrorMsg = [];
        $count = 0;

        $listErrorMsg = $this->checkErrorOrder($Orders);
        if ($listErrorMsg) {
            return [$count, $listErrorMsg];
        }

        foreach ($Orders as $Order) {
            // 決済状況変更処理
            $ret = false;

            // 決済情報を取得
            $orderId = $Order->getId();
            $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
            if (is_null($YamatoOrder)) {
                $this->utilClientService->setError('操作に対応していない注文です。');
                return $this->getErrorWithPaymentOperations($orderId, $count);
            }

            if ($mode === 'inquiry') {
                // 取引照会
                $ret = $this->doInquiry($Order);
                if ($ret) {
                    $count++;
                }
            }

            if (!$ret) {
                $this->getErrorWithPaymentOperations($orderId, $count);
            }
        }
        return [$count, $listErrorMsg];
    }

    /**
     * 取引照会
     *
     * @param Order $Order
     * @return bool
     */
    public function doInquiry($Order)
    {
        $ret = $this->utilClientService->doGetTradeInfo($Order);
        if ($ret) {
            $pluginData = $this->utilClientService->getSendResults();
            $pluginData['order_id'] = $Order->getId();
            if (isset($pluginData['settlePrice'])) {
                if ($Order->getPaymentTotal() != $pluginData['settlePrice']) {
                    // 金額に変更があった場合
                    $pluginData['settle_price'] = $pluginData['settlePrice'];
                }
            }
            $pluginData['status'] = $pluginData['action_status'];
            $this->utilClientService->updatePluginPurchaseLog($pluginData);

            if (isset($pluginData['action_status'])) {
                switch ($pluginData['action_status']) {
                    case YamatoPaymentStatus::YAMATO_ACTION_STATUS_WAIT_SETTLEMENT:
                        $order_status = OrderStatus::DELIVERED;
                        break;

                    case YamatoPaymentStatus::YAMATO_ACTION_STATUS_CANCEL:
                        $order_status = OrderStatus::CANCEL;
                        break;

                    default:
                        $order_status = null;
                }
                if (is_null($order_status) == false) {
                    $OrderStatus = $this->orderStatusRepository->find($order_status);
                    $this->orderRepository->changeStatus($Order->getId(), $OrderStatus);
                }
            }
        }

        return $ret;
    }

    /**
     * 決済操作エラーを返す
     *
     * @param integer $orderId
     * @param integer $count
     * @return array 件数, エラー情報
     */
    public function getErrorWithPaymentOperations($orderId, $count)
    {
        $listErrorMsg[] = '※ 決済操作エラー が発生しました。';
        $listErrorMsg[] = '注文番号 ' . $orderId;
        $errors = $this->utilClientService->getError();
        foreach ($errors as $error) {
            $listErrorMsg[] = $error;
        }
        $this->utilClientService->clear();
        return [$count, $listErrorMsg];
    }

    /**
     * 受注ステータスを変更する
     *
     * @param Order[] $Orders
     * @param integer $newStatus
     * @return array 件数, エラー情報
     */
    public function doChangeOrderStatus($Orders, $newStatus)
    {
        $count = 0;
        $changePaymentStatusErrors = $this->checkErrorOrder($Orders);
        if (empty($changePaymentStatusErrors)) {
            foreach ($Orders as $Order) {
                $orderId = $Order->getId();
                $OrderStatus = $this->orderStatusRepository->find($newStatus);
                $this->orderRepository->changeStatus($orderId, $OrderStatus);
                $count++;
            }
        }
        return [$count, $changePaymentStatusErrors];
    }

    public function doChangeScheduledShippingDate($order_id, $scheduled_shipping_date)
    {
        $listErrorMsg = [];
        $count = 0;

        $Order = $this->orderRepository->find($order_id);
        $listErrorMsg = $this->checkErrorOrder([$Order]);
        if ($listErrorMsg) {
            return [$count, $listErrorMsg];
        }

        if (!$scheduled_shipping_date) {
            $listErrorMsg[] = '※ 出荷予定日が入力されておりません。';
        }

        if ($listErrorMsg) {
            $listErrorMsg[] = '注文番号 ' . $order_id;
            return [$count, $listErrorMsg];
        }

        $date = new \DateTime($scheduled_shipping_date);
        $ret = $this->utilClientService->doChangeDate($Order, $date);
        if ($ret) {
            $count++;
            $Order->setScheduledShippingDate($date);
            $this->entityManager->persist($Order);
            $this->entityManager->flush();
        } else {
            $listErrorMsg[] = '※ 決済操作エラー が発生しました。';
            $listErrorMsg[] = '注文番号 ' . $order_id;
            $errors = $this->utilClientService->getError();
            foreach ($errors as $error) {
                $listErrorMsg[] = $error;
            }
            $this->utilClientService->clear();
        }

        return [$count, $listErrorMsg];
    }

    /**
     * 出荷情報登録エラーチェック（クレジット決済）
     *
     * @param Order $Order 受注ID
     * @return string エラーメッセージ
     */
    public function checkErrorShipmentEntryForCredit($Order)
    {
        // 受注決済情報取得
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);

        $Shippings = $Order->getShippings();

        // 支払い方法チェック
        if (!$this->isCreditOrder($YamatoOrder)) {
            return "※ 操作に対応していない決済です。注文番号 {$Order->getId()}";
        }
        // 取引状況チェック（与信完了）
        if ($YamatoOrder->getMemo04() != $this->eccubeConfig['YAMATO_ACTION_STATUS_COMP_AUTH']) {
            return "※ 操作に対応していない取引状況です。注文番号 {$Order->getId()}";
        }
        // 複数配送送り先上限チェック（99件まで）
        if ($Shippings->count() > $this->eccubeConfig['YAMATO_DELIV_ADDR_MAX']) {
            return "※ 1つの注文に対する出荷情報の上限（' . $this->eccubeConfig['YAMATO_DELIV_ADDR_MAX'] . '件）を超えております。注文番号 {$Order->getId()}";
        }

        return null;
    }

    /**
     * クレジット決済判定
     *
     * @param YamatoOrder $YamatoOrder
     * @return bool クレジット決済の場合、true
     */
    public function isCreditOrder($YamatoOrder)
    {
        if (
            !is_null($YamatoOrder)
            && $YamatoOrder->getMemo03() == $this->eccubeConfig['YAMATO_PAYID_CREDIT']
        ) {
            return true;
        }
        return false;
    }
}
