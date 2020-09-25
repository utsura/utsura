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
use Eccube\Service\OrderStateMachine;
use Eccube\Util\FormUtil;

use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use Plugin\YamatoPayment4\Form\Type\Admin\SearchPaymentType;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Service\Method\Deferred;
use Plugin\YamatoPayment4\Service\Method\DeferredSms;
use Plugin\YamatoPayment4\Service\YamatoStatusService;

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
    protected $orderStateMachine;
    protected $orderStatusRepository;
    protected $yamatoPaymentMethodRepository;
    protected $yamatoStatusService;

    /**
     * PaymentStatusController constructor.
     *
     * @param OrderStateMachine $orderStateMachine
     * @param OrderStatusRepository $orderStatusRepository
     */
    public function __construct(
        PageMaxRepository $pageMaxRepository,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        ShippingRepository $shippingRepository,
        OrderStateMachine $orderStateMachine,
        OrderStatusRepository $orderStatusRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        YamatoStatusService $yamatoStatusService
    ) {
        $this->pageMaxRepository = $pageMaxRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->shippingRepository = $shippingRepository;
        $this->orderStateMachine = $orderStateMachine;
        $this->orderStatusRepository = $orderStatusRepository;
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
        $page_count = $this->session->get(
            'yamato_payment.admin.payment_status.search.page_count',
            $this->eccubeConfig->get('eccube_default_page_count')
        );

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

        $payment_method = $searchData['payment_method'] ?? $this->eccubeConfig['YAMATO_PAYID_CREDIT'];
        $lastAuthDates = $this->yamatoStatusService->calculateLastAuthDates($pagination, $payment_method);

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false,
            'lastAuthDates' => $lastAuthDates,
            'payment_method' => $payment_method,
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
        $requestPaymentMethod = $request->get('yamato_payment_method');

        if (!isset($requestType)) {
            throw new BadRequestHttpException();
        }

        $this->isTokenValid();

        $ids = $request->get('ids');

        /** @var Order[] $Orders */
        $Orders = $this->orderRepository->findBy(['id' => $ids]);
        $count = 0;
        switch ($requestType) {
            case 'change_payment_status':
                // 決済処理
                switch ($requestPaymentMethod) {
                    case $this->eccubeConfig['YAMATO_PAYID_CREDIT']:
                        // クレジットカード
                        list($count, $error) = $this->yamatoStatusService->doChangeCreditPaymentStatus($Orders, $requestMode);
                        break;

                    case $this->eccubeConfig['YAMATO_PAYID_DEFERRED']:
                        // 後払い決済
                        list($count, $error) = $this->yamatoStatusService->doChangeDeferredPaymentStatus($Orders, $requestMode);
                        break;

                    case $this->eccubeConfig['YAMATO_PAYID_CVS']:
                        // コンビニ決済
                        list($count, $error) = $this->yamatoStatusService->doChangeCvsPaymentStatus($Orders, $requestMode);
                        break;

                    default:
                        break;
                }
                break;
            case 'change_status':
                // 対応状況更新
                list($count, $error) = $this->yamatoStatusService->doChangeOrderStatus($Orders, $requestMode);
                break;
                // 出荷予定日変更
            case 'change_scheduled_shipping_date':
                list($count, $error) = $this->yamatoStatusService->doChangeScheduledShippingDate($request->get('order_id'), $request->get('scheduled_shipping_date'));
                break;
        }

        if ($count) {
            $this->addSuccess(trans('yamato_payment.admin.payment_status.bulk_action.success', ['%count%' => $count]), 'admin');
        }

        if ($error) {
            $this->addError(implode(', ', $error), 'admin');
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

        // クレカ
        $Payment = $this->paymentRepository->findOneBy(['method_class' => Credit::class]);
        if (!empty($searchData['payment_method']) && $searchData['payment_method']) {
            $YamatoPaymentMethod = $this->yamatoPaymentMethodRepository->findOneBy(['memo03' => $searchData['payment_method']]);
            $Payment = $YamatoPaymentMethod->getPayment();
        }

        if ($Payment->getMethodClass() === Deferred::class) {
            $Payment = $this->paymentRepository->findBy([
                'method_class' => [
                    Deferred::class,
                    DeferredSms::class,
                ],
            ]);
            $qb->andWhere('o.Payment IN (:Payment)')    // 支払方法
                ->setParameter('Payment', $Payment);
        } else {
            $qb->andWhere("o.Payment = :Payment")
                ->setParameter('Payment', $Payment);
        }

        $qb->andWhere('o.OrderStatus NOT IN (:OrderStatus)')    // 対応状況
            ->setParameter('OrderStatus', [
                OrderStatus::PROCESSING,
                OrderStatus::PENDING,
            ])
            // YamatoOrderが紐づいていない受注を除く
            ->andWhere('EXISTS(SELECT YO.id FROM \Plugin\YamatoPayment4\Entity\YamatoOrder AS YO WHERE YO.Order = o.id)');

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
}
