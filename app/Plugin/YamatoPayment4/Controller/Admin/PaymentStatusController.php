<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\YamatoPayment4\Controller\Admin;

use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\ShippingRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Util\FormUtil;

use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Form\Type\Admin\SearchPaymentType;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository;
use Plugin\YamatoPayment4\Service\Client AS YamatoClient;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Service\YamatoStatusService;
use Plugin\YamatoPayment4\Util\CommonUtil;

/**
 * 決済状況管理
 */
class PaymentStatusController extends AbstractController
{
    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;
    protected $paymentRepository;
    protected $shippingRepository;
    protected $orderStatusRepository;
    protected $yamatoOrderRepository;
    protected $yamatoPaymentMethodRepository;
    protected $yamatoPaymentStatusRepository;
    protected $yamatoStatusService;

    /**
     * PaymentStatusController constructor.
     *
     * @param OrderStatusRepository $orderStatusRepository
     */
    public function __construct(
        YamatoClient\UtilClientService $utilClientService,
        PageMaxRepository $pageMaxRepository,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        ShippingRepository $shippingRepository,
        OrderStatusRepository $orderStatusRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        YamatoPaymentStatusRepository $yamatoPaymentStatusRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        YamatoStatusService $yamatoStatusService
    ) {
        $this->utilClientService = $utilClientService;
        $this->pageMaxRepository = $pageMaxRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->shippingRepository = $shippingRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;
        $this->yamatoPaymentStatusRepository = $yamatoPaymentStatusRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->yamatoStatusService = $yamatoStatusService;
    }

    /**
     * クレジットカード決済状況一覧画面
     *
     * @Route("/%eccube_admin_route%/yamato_payment4/payment_status", name="yamato_payment4_admin_payment_status")
     * @Route("/%eccube_admin_route%/yamato_payment4/payment_status/{page_no}", requirements={"page_no" = "\d+"}, name="yamato_payment4_admin_payment_status_pageno")
     * @Template("@YamatoPayment4/admin/payment_status.twig")
     */
    public function index(Request $request, PaginatorInterface $paginator, YamatoStatusService $YamatoStatusService, $page_no = null)
    {
        $searchForm = $this->createForm(SearchPaymentType::class);

        /**
         * ページの表示件数は, 以下の順に優先される.
         * - リクエストパラメータ
         * - セッション
         * - デフォルト値
         * また, セッションに保存する際は mtb_page_maxと照合し, 一致した場合のみ保存する.
         **/
        $page_count = $this->session->get('yamato_payment.admin.payment_status.search.page_count',
            $this->eccubeConfig->get('eccube_default_page_count'));

        $page_count_param = (int) $request->get('page_count');
        $pageMaxis = $this->pageMaxRepository->findAll();

        if ($page_count_param) {
            foreach ($pageMaxis as $pageMax) {
                if ($page_count_param == $pageMax->getName()) {
                    $page_count = $pageMax->getName();
                    $this->session->set('yamato_payment.admin.payment_status.search.page_count', $page_count);
                    break;
                }
            }
        }

        if ($request->getMethod() === 'POST') {
            $searchForm->handleRequest($request);

            if ($searchForm->isValid()) {
                /**
                 * 検索が実行された場合は, セッションに検索条件を保存する.
                 * ページ番号は最初のページ番号に初期化する.
                 */
                $page_no = 1;
                $searchData = $searchForm->getData();

                // 検索条件, ページ番号をセッションに保持.
                $this->session->set('yamato_payment.admin.payment_status.search', FormUtil::getViewData($searchForm));
                $this->session->set('yamato_payment.admin.payment_status.search.page_no', $page_no);
            } else {
                // 検索エラーの際は, 詳細検索枠を開いてエラー表示する.
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $page_count,
                    'has_errors' => true,
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                /*
                 * ページ送りの場合または、他画面から戻ってきた場合は, セッションから検索条件を復旧する.
                 */
                if ($page_no) {
                    // ページ送りで遷移した場合.
                    $this->session->set('yamato_payment.admin.payment_status.search.page_no', (int) $page_no);
                } else {
                    // 他画面から遷移した場合.
                    $page_no = $this->session->get('yamato_payment.admin.payment_status.search.page_no', 1);
                }
                $viewData = $this->session->get('yamato_payment.admin.payment_status.search', []);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
            } else {
                /**
                 * 初期表示の場合.
                 */
                $page_no = 1;
                $searchData = [];

                // セッション中の検索条件, ページ番号を初期化.
                $this->session->set('yamato_payment.admin.payment_status.search', $searchData);
                $this->session->set('yamato_payment.admin.payment_status.search.page_no', $page_no);
            }
        }

        $qb = $this->createQueryBuilder($searchData);
        $pagination = $paginator->paginate($qb, $page_no, $page_count);

        $lastAuthDates = $this->yamatoStatusService->calculateLastAuthDates($pagination, 'credit');

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false,
            'lastAuthDates' => $lastAuthDates,
        ];
    }

    /**
     * 一括処理.
     *
     * @Route("/%eccube_admin_route%/yamato_payment4/payment_status/request_action/", name="yamato_payment4_admin_payment_status_request", methods={"POST"})
     */
    public function requestAction(Request $request)
    {
        $requestType = $request->get('yamato_request');
        $requestMode = $request->get('yamato_mode');

        if (!isset($requestType)) {
            throw new BadRequestHttpException();
        }

        $this->isTokenValid();

        $ids = $request->get('ids');

        /** @var Order[] $Orders */
        $Orders = $this->orderRepository->findBy(['id' => $ids]);
        $count = 0;
        switch ($requestType) {
            case 'change_payment_status' :
                // 決済処理
                list($count, $error) = $this->doChangePaymentStatus($Orders, $requestMode);
                break;
            case 'change_status' :
                // 対応状況更新
                list($count, $error) = $this->doChangeOrderStatus($Orders, $requestMode);
                break;
        }

        if($count) {
            $this->addSuccess(trans('yamato_payment.admin.payment_status.bulk_action.success', ['%count%' => $count]),'admin');
        }

        if($error) {
            $this->addError(implode(', ', $error),'admin');
        }

        return $this->redirectToRoute('yamato_payment4_admin_payment_status_pageno', ['resume' => Constant::ENABLED]);
    }

    private function createQueryBuilder(array $searchData)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('o')
            ->from(Order::class, 'o')
            ->orderBy('o.order_date', 'DESC')
            ->addOrderBy('o.id', 'DESC');

        // クレカのみ
        $Payment = $this->paymentRepository->findOneBy(['method_class' => Credit::class]);

        $qb ->andWhere('o.Payment = :Payment')    // 支払方法
            ->setParameter('Payment', $Payment)

            ->andWhere('o.OrderStatus != :OrderStatus')    // 対応状況
            ->setParameter('OrderStatus', OrderStatus::PROCESSING)

            // YamatoOrderが紐づいていない受注を除く
            ->andWhere('EXISTS(SELECT YO.id FROM \Plugin\YamatoPayment4\Entity\YamatoOrder AS YO WHERE YO.Order = o.id)')
        ;

        if (!empty($searchData['OrderStatuses']) && count($searchData['OrderStatuses']) > 0) {
            $qb->andWhere($qb->expr()->in('o.OrderStatus', ':OrderStatuses'))
                ->setParameter('OrderStatuses', $searchData['OrderStatuses']);
        }
        
        // oreder_date
        if (!empty($searchData['order_date_starts']) && $searchData['order_date_starts']) {
            $date = $searchData['order_date_starts'];
            $qb
                ->andWhere('o.order_date >= :order_date_starts')
                ->setParameter('order_date_starts', $date);
        }
        if (!empty($searchData['order_date_ends']) && $searchData['order_date_ends']) {
            $date = clone $searchData['order_date_ends'];
            $date = $date
                ->modify('+1 days');
            $qb
                ->andWhere('o.order_date < :order_date_ends')
                ->setParameter('order_date_ends', $date);
        }

        return $qb;
    }

    private function doChangeOrderStatus($Orders, $newStatus) {
        $count = 0;
        $changePaymentStatusErrors = $this->yamatoStatusService->checkErrorOrder($Orders);
        if(empty($changePaymentStatusErrors)) {
            foreach ($Orders as $Order) {
                $orderId = $Order->getId();
                $OrderStatus = $this->orderStatusRepository->find($newStatus);
                $orderRepository = $this->orderRepository->changeStatus($orderId, $OrderStatus);
                $count++;
            }
        }
        return [$count, $changePaymentStatusErrors];
    }

    /**
     * 決済状況変更
     *
     * @param array $Orders 対象受注
     * @param string $mode 決済モード
     * @return array エラーメッセージ
     */
    public function doChangePaymentStatus($Orders, $mode)
    {
        $listErrorMsg = [];
        $count = 0;

        $listErrorMsg = $this->yamatoStatusService->checkErrorOrder($Orders);
        if($listErrorMsg) {
            return [$count, $listErrorMsg];
        }

        foreach ($Orders as $Order) {

            // 決済状況変更処理
            $ret = false;

            // 決済情報を取得
            $orderId = $Order->getId();
            $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
            if(is_null($YamatoOrder)) {
                $this->utilClientService->setError('操作に対応していない注文です。');
            } else {
                $orderPaymentId = $YamatoOrder->getMemo03();

                // 出荷情報登録処理
                if ($mode == 'commit') {
                    // 出荷登録
                    if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                        // webコレクト決済用
                        list($ret, $listSuccessSlip) = $this->utilClientService->doShipmentEntry($Order, $this->shippingRepository);
                        if($ret) {
                            $pluginData = $this->utilClientService->getSendResults();
                            $pluginData['order_id'] = $orderId;
                            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_WAIT_SETTLEMENT;
                            $send = $this->utilClientService->getSendedData();
                            if($send['delivery_service_code'] == '00') {
                                $pluginData['slip'] = $pluginData['slipUrlPc'];
                            } else {
                                $pluginData['slip'] = CommonUtil::serializeData($listSuccessSlip);
                            }
                            $this->utilClientService->updatePluginPurchaseLog($pluginData);

                            // 対応状況をクレジットカード出荷登録済みにする(エラーが出るため「発送済み」に変更)
                            //$order_status = $this->eccubeConfig['ORDER_SHIPPING_REGISTERED'];
                            $order_status = OrderStatus::DELIVERED;
                            $OrderStatus = $this->orderStatusRepository->find($order_status);
                            $orderRepository = $this->orderRepository->changeStatus($orderId, $OrderStatus);

                            // 他社配送で送り状番号が空なら登録する
                            if($send['delivery_service_code'] != '00') {
                                foreach($listSuccessSlip as $shippingId => $slip) {
                                    $Shipping = $this->shippingRepository->find($shippingId);
                                    if(empty($Shipping->getTrackingNumber())) {
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
                } else if ($mode === 'clear') {

                    // 出荷登録取消
                    if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                        // 受注の送り状番号を取得
                        $Shippings = $this->shippingRepository->findBy(['Order' => $Order]);
                        $listSuccessSlip = [];
                        $this->utilClientService->setSetting($Order);
                        $moduleSettings = $this->utilClientService->getModuleSetting();
                        foreach($Shippings as $Shipping) {
                            if($moduleSettings['delivery_service_code'] == '00') {
                                $slip = $Shipping->getTrackingNumber();
                            } else {
                                $slip = $orderId;
                            }
                            if(strlen($slip)) {
                                $listSuccessSlip[] = $slip;
                            }
                        }

                        $ret = $this->utilClientService->doShipmentRollback($Order, $listSuccessSlip);
                        if($ret) {
                            // 与信完了に戻す
                            $pluginData = $this->utilClientService->getSendResults();
                            $pluginData['order_id'] = $orderId;
                            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH;
                            $this->utilClientService->updatePluginPurchaseLog($pluginData);

                            $count++;
                        }
                    }
                } else if ($mode === 'amount') {
                    if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                        $ret = $this->utilClientService->doCreditChangePrice($Order);
                        if($ret) {
                            $pluginData = [];
                            $pluginData['order_id'] = $orderId;
                            $pluginData['status'] = $YamatoOrder->getMemo04();
                            $pluginData['settle_price'] = $Order->getPaymentTotal();
                            $this->utilClientService->updatePluginPurchaseLog($pluginData);
                            $count++;
                        } else {
                            $result = $this->utilClientService->getResults();
                            if($result['errorCode'] == 'A074000001') {
                                // 金額変更なしの場合
                                $pluginData = [];
                                $pluginData['order_id'] = $orderId;
                                $pluginData['status'] = $YamatoOrder->getMemo04();
                                $pluginData['settle_price'] = $Order->getPaymentTotal();
                                $this->utilClientService->updatePluginPurchaseLog($pluginData);
                            }
                        }
                    }
                } else if ($mode === 'inquiry') {
                    // 取引照会
                    $ret = $this->utilClientService->doGetTradeInfo($Order);
                    if($ret) {
                        $pluginData = $this->utilClientService->getSendResults();
                        $pluginData['order_id'] = $orderId;
                        if(isset($pluginData['settlePrice'])) {
                            if($Order->getPaymentTotal() != $pluginData['settlePrice']) {
                                // 金額に変更があった場合
                                $pluginData['settle_price'] = $pluginData['settlePrice'];
                            }
                        }
                        $pluginData['status'] = $pluginData['action_status'];
                        $this->utilClientService->updatePluginPurchaseLog($pluginData);

                        if(isset($pluginData['action_status'])) {
                            switch($pluginData['action_status']) {
                                case YamatoPaymentStatus::YAMATO_ACTION_STATUS_WAIT_SETTLEMENT :
                                    $order_status = OrderStatus::DELIVERED;
                                    break;

                                case YamatoPaymentStatus::YAMATO_ACTION_STATUS_CANCEL :
                                    $order_status = OrderStatus::CANCEL;
                                    break;

                                default :
                                    $order_status = null;
                            }
                            if(is_null($order_status) == false) {
                                $OrderStatus = $this->orderStatusRepository->find($order_status);
                                $orderRepository = $this->orderRepository->changeStatus($orderId, $OrderStatus);
                            }
                        }

                        $count++;
                    }
                } else if ($mode === 'cancel') {
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
                            $orderRepository = $this->orderRepository->changeStatus($orderId, $OrderStatus);

                            $count++;
                        }
                    }
                } else if ($mode == 'reauth') {
                    // 再与信
                    if ($orderPaymentId == $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
                        $memo04 = $YamatoOrder->getMemo04();
                        if($memo04 == YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH || $memo04 == YamatoPaymentStatus::YAMATO_ACTION_STATUS_CANCEL) {
                            $ret = $this->utilClientService->doReauth($Order);
                            if($ret) {
                                // 与信完了に戻す
                                $pluginData = $this->utilClientService->getSendResults();
                                $pluginData['order_id'] = $orderId;
                                $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH;
                                $this->utilClientService->updatePluginPurchaseLog($pluginData);

                                // 対応状況を新規受付にする
                                $order_status = OrderStatus::NEW;
                                $OrderStatus = $this->orderStatusRepository->find($order_status);
                                $orderRepository = $this->orderRepository->changeStatus($orderId, $OrderStatus);

                                $count++;
                            }
                        } else {
                            $this->utilClientService->setError('再与信に対応していない決済状況です。');
                        }
                    } else {
                        $this->utilClientService->setError('再与信に対応していない支払方法です。');
                    }
                }
            }

            if(!$ret){
                $listErrorMsg[] = '※ 決済操作エラー が発生しました。';
                $listErrorMsg[] = '注文番号 ' . $orderId;
                $errors = $this->utilClientService->getError();
                foreach($errors as $error) {
                    $listErrorMsg[] = $error;
                }
                $this->utilClientService->clear();
                return [$count, $listErrorMsg];
            }
        }

        return [$count, $listErrorMsg];
    }
}
