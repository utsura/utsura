<?php

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
use Plugin\YamatoPayment4\Service\YamatoStatusService;
use Plugin\YamatoPayment4\Service\YamatoMailService;
use Plugin\YamatoPayment4\Service\Client as YamatoClient;
use Plugin\YamatoPayment4\Service\Method\Deferred;
use Plugin\YamatoPayment4\Service\Method\DeferredSms;

/**
 * 決済状況管理
 */
class DeferredStatusController extends AbstractController
{

    protected $yamatoStatusService;

    protected $orderRepository;
    protected $paymentRepository;
    protected $shippingRepository;
    protected $pageMaxRepository;
    protected $orderStatusRepository;
    protected $yamatoOrderRepository;
    protected $yamatoPaymentMethodRepository;
    protected $yamatoPaymentStatusRepository;

    /**
     * DeferredStatusController constructor.
     *
     * @param OrderStatusRepository $orderStatusRepository
     */
    public function __construct(
        YamatoMailService $yamatoMailService,
        YamatoClient\UtilClientService $utilClientService,
        YamatoClient\DeferredClientService $deferredClientService,
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
        $this->yamatoMailService = $yamatoMailService;
        $this->utilClientService = $utilClientService;
        $this->deferredClientService = $deferredClientService;
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
     * 後払い決済状況一覧画面
     *
     * @Route("/%eccube_admin_route%/yamato_payment4/deferred_status", name="yamato_payment4_admin_deferred_status")
     * @Route("/%eccube_admin_route%/yamato_payment4/deferred_status/{page_no}", requirements={"page_no" = "\d+"}, name="yamato_payment4_admin_deferred_status_pageno")
     * @Template("@YamatoPayment4/admin/deferred_status.twig")
     */
    public function index(Request $request, PaginatorInterface $paginator, $page_no = null)
    {
        $searchForm = $this->createForm(SearchPaymentType::class);

        /**
         * ページの表示件数は, 以下の順に優先される.
         * - リクエストパラメータ
         * - セッション
         * - デフォルト値
         * また, セッションに保存する際は mtb_page_maxと照合し, 一致した場合のみ保存する.
         **/
        $page_count = $this->session->get(
            'yamato_payment.admin.payment_status.search.page_count',
            $this->eccubeConfig->get('eccube_default_page_count')
        );

        $page_count_param = (int)$request->get('page_count');
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
                    $this->session->set('yamato_payment.admin.payment_status.search.page_no', (int)$page_no);
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

        // ページネーションされた受注情報を取得する
        $qb = $this->createQueryBuilder($searchData);
        $pagination = $paginator->paginate($qb, $page_no, $page_count);

        $lastAuthDates = $this->yamatoStatusService->calculateLastAuthDates($pagination, 'deferred');

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
     * @Route("/%eccube_admin_route%/yamato_payment4/deferred_status/request_action/", name="yamato_payment4_admin_deferred_status_request", methods={"POST"})
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
                // 決済処理
            case 'change_payment_status':
                list($count, $error) = $this->changeYamatoStatus($Orders, $requestMode);
                break;
                // 対応状況更新
            case 'change_status':
                list($count, $error) = $this->doChangeOrderStatus($Orders, $requestMode);
                break;
        }

        if ($count > 0) {
            $this->addSuccess(trans('yamato_payment.admin.payment_status.bulk_action.success', ['%count%' => $count]), 'admin');
        }

        if ($error) {
            $this->addError(implode(', ', $error), 'admin');
        }

        return $this->redirectToRoute('yamato_payment4_admin_deferred_status_pageno', ['resume' => Constant::ENABLED]);
    }

    private function createQueryBuilder(array $searchData)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('o')
            ->from(Order::class, 'o')
            ->innerJoin('\Plugin\YamatoPayment4\Entity\YamatoOrder', 'yo', 'WITH', 'o = yo.Order')
            ->orderBy('o.order_date', 'DESC')
            ->addOrderBy('o.id', 'DESC');

        // 後払い,SMS後払いのみ抽出する
        $Payment = $this->paymentRepository->findBy([
            'method_class' => [
                Deferred::class,
                DeferredSms::class,
            ],
        ]);

        $qb->andWhere('o.Payment IN (:Payment)')    // 支払方法
            ->setParameter('Payment', $Payment)

            ->andWhere('o.OrderStatus NOT IN (:OrderStatus)')    // 対応状況
            ->setParameter('OrderStatus', [
                OrderStatus::PROCESSING,
                OrderStatus::PENDING,
            ])
            ->andWhere("yo.memo04 <> ''");

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

    private function doChangeOrderStatus($Orders, $newStatus)
    {
        $count = 0;
        $changePaymentStatusErrors = $this->yamatoStatusService->checkErrorOrder($Orders);
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

    /**
     * 決済状況変更
     *
     * @param array $Orders 対象受注
     * @param string $mode 決済モード
     * @return array エラーメッセージ
     */
    public function changeYamatoStatus($Orders, $mode)
    {
        $listErrorMsg = [];
        $count = 0;

        $listErrorMsg = $this->yamatoStatusService->checkErrorOrder($Orders);
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
            } else {
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
                            $this->orderRepository->changeStatus($orderId, $OrderStatus);

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
                        $this->orderRepository->changeStatus($orderId, $OrderStatus);

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
                    $listErrorMsg[] = '※ 決済操作エラー が発生しました。';
                    $listErrorMsg[] = '注文番号 ' . $orderId;
                    $errors = $this->utilClientService->getError();
                    foreach ($errors as $error) {
                        $listErrorMsg[] = $error;
                    }
                    $this->utilClientService->clear();
                    return [$count, $listErrorMsg];
                }
            }
        }
        return [$count, $listErrorMsg];
    }
}
