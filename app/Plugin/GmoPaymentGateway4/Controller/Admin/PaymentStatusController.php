<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Controller\Admin;

use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\PaginatorInterface;
use Plugin\GmoPaymentGateway4\Entity\GmoOrderPayment;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod;
use Plugin\GmoPaymentGateway4\Form\Type\Admin\SearchPaymentType;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperAdmin;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperAdmin
     */
    protected $PaymentHelperAdmin;

    /**
     * @var array
     */
    protected $bulkActions = [];

    /**
     * PaymentController constructor.
     *
     * @param OrderStatusRepository $orderStatusRepository
     */
    public function __construct(
        PageMaxRepository $pageMaxRepository,
        OrderRepository $orderRepository,
        PaymentHelperAdmin $PaymentHelperAdmin
    ) {
        $this->pageMaxRepository = $pageMaxRepository;
        $this->orderRepository = $orderRepository;
        $this->PaymentHelperAdmin = $PaymentHelperAdmin;

        $this->bulkActions = [
            ['id' => 1, 'name' => trans('gmo_payment_gateway.admin.' .
                                        'payment_status.bulk_action_commit')],
            ['id' => 2, 'name' => trans('gmo_payment_gateway.admin.' .
                                        'payment_status.bulk_action_cancel')],
            ['id' => 3, 'name' => trans('gmo_payment_gateway.admin.' .
                                        'payment_status.bulk_action_reauth')],
        ];
    }

    /**
     * 決済状況一覧画面
     *
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/payment_status", name="gmo_payment_gateway_admin_payment_status")
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/payment_status/{page_no}", requirements={"page_no" = "\d+"}, name="gmo_payment_gateway_admin_payment_status_pageno")
     * @Template("@GmoPaymentGateway4/admin/payment_status.twig")
     */
    public function index
        (Request $request, $page_no = null, PaginatorInterface $paginator)
    {
        $searchForm = $this->createForm(SearchPaymentType::class);

        // 決済状況の配列を取得
        $paymentStatuses = $this->PaymentHelperAdmin->getPaymentStatuses();

        /**
         * ページの表示件数は, 以下の順に優先される.
         * - リクエストパラメータ
         * - セッション
         * - デフォルト値
         * また, セッションに保存する際は mtb_page_maxと照合し,
         * 一致した場合のみ保存する.
         **/
        $page_count = $this->session->get
            ('gmo_payment_gateway.admin.payment_status.search.page_count',
             $this->eccubeConfig->get('eccube_default_page_count'));

        $page_count_param = (int) $request->get('page_count');
        $pageMaxis = $this->pageMaxRepository->findAll();

        if ($page_count_param) {
            foreach ($pageMaxis as $pageMax) {
                if ($page_count_param == $pageMax->getName()) {
                    $page_count = $pageMax->getName();
                    $this->session->set
                        ('gmo_payment_gateway.admin.' .
                         'payment_status.search.page_count', $page_count);
                    break;
                }
            }
        }

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);

            if ($searchForm->isValid()) {
                /**
                 * 検索が実行された場合は, セッションに検索条件を保存する.
                 * ページ番号は最初のページ番号に初期化する.
                 */
                $page_no = 1;
                $searchData = $searchForm->getData();

                // 検索条件, ページ番号をセッションに保持.
                $this->session->set
                    ('gmo_payment_gateway.admin.' .
                     'payment_status.search',
                     FormUtil::getViewData($searchForm));
                $this->session->set
                    ('gmo_payment_gateway.admin.' .
                     'payment_status.search.page_no', $page_no);
            } else {
                // 検索エラーの際は, 詳細検索枠を開いてエラー表示する.
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $page_count,
                    'has_errors' => true,
                    'paymentStatuses' => $paymentStatuses,
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                /*
                 * ページ送りの場合または、他画面から戻ってきた場合は,
                 * セッションから検索条件を復旧する.
                 */
                if ($page_no) {
                    // ページ送りで遷移した場合.
                    $this->session->set
                        ('gmo_payment_gateway.admin.' .
                         'payment_status.search.page_no', (int) $page_no);
                } else {
                    // 他画面から遷移した場合.
                    $page_no = $this->session->get
                        ('gmo_payment_gateway.admin.' .
                         'payment_status.search.page_no', 1);
                }
                $viewData = $this->session->get
                    ('gmo_payment_gateway.admin.payment_status.search', []);
                $searchData =
                    FormUtil::submitAndGetData($searchForm, $viewData);
            } else {
                /**
                 * 初期表示の場合.
                 */
                $page_no = 1;
                $searchData = [];

                // セッション中の検索条件, ページ番号を初期化.
                $this->session->set
                    ('gmo_payment_gateway.admin.' .
                     'payment_status.search', $searchData);
                $this->session->set
                    ('gmo_payment_gateway.admin.' .
                     'payment_status.search.page_no', $page_no);
            }
        }

        $qb = $this->createQueryBuilder($searchData);
        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $page_count
        );

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false,
            'bulkActions' => $this->bulkActions,
            'paymentStatuses' => $paymentStatuses,
        ];
    }

    /**
     * アクションの検査
     *
     * @param integer $id アクションID
     * @return boolean true: ok, false: ng
     */
    private function checkAction($id)
    {
        foreach ($this->bulkActions as $action) {
            if ($action['id'] == $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * 一括処理.
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/payment_status/bulk_action/{id}", requirements={"id" = "\d+"}, name="gmo_payment_gateway_admin_payment_status_bulk_action")
     */
    public function bulkAction(Request $request, $id)
    {
        PaymentUtil::logInfo("PaymentStatusController::bulkAction start.");

        if (!$this->checkAction($id)) {
            PaymentUtil::logError("action id = " . $id);
            PaymentUtil::logError($this->bulkActions);
            throw new BadRequestHttpException();
        }

        $this->isTokenValid();

        PaymentUtil::logInfo($request->get('ids'));

        /** @var Order[] $Orders */
        $Orders = $this->orderRepository
            ->findBy(['id' => $request->get('ids')]);
        $count = 0;

        $prefix = 'gmo_payment_gateway.admin.payment_status.';

        foreach ($Orders as $Order) {
            $r = true;
            $myname = "";

            // 注文に GMO-PG 情報を付加する
            $Order = $this->PaymentHelperAdmin->prepareGmoInfoForOrder($Order);

            switch ($id) {
            // 一括売上
            case 1:
                $myname = trans($prefix . 'bulk_action_commit');
                PaymentUtil::logInfo($myname . " start");
                $r = $this->PaymentHelperAdmin->doCommitOrder($Order);
                break;
            // 一括取消
            case 2:
                $myname = trans($prefix . 'bulk_action_cancel');
                PaymentUtil::logInfo($myname . " start");
                $r = $this->PaymentHelperAdmin->doCancelOrder($Order);
                break;
            // 一括再オーソリ
            case 3:
                $myname = trans($prefix . 'bulk_action_reauth');
                PaymentUtil::logInfo($myname . " start");
                $r = $this->PaymentHelperAdmin->doReauthOrder($Order);
                break;
            }

            $this->setCompleteMessage($myname, $r, $Order->getId());
            PaymentUtil::logInfo($myname . " end");

            $this->entityManager->flush();

            if ($r) {
                $count++;
            }
        }

        $message = trans('gmo_payment_gateway.admin.' .
                         'payment_status.bulk_action.success',
                         ['%count%' => $count]);
        $this->addSuccess($message, 'admin');
        PaymentUtil::logInfo($message);

        PaymentUtil::logInfo("PaymentStatusController::bulkAction end.");

        return $this->redirectToRoute
            ('gmo_payment_gateway_admin_payment_status_pageno',
             ['resume' => Constant::ENABLED]);
    }

    private function createQueryBuilder(array $searchData)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('o')
            ->from(Order::class, 'o')
            ->innerJoin('o.Payment', 'p')
            ->innerJoin(GmoPaymentMethod::class, 'm',
                        'WITH', 'p.id = m.payment_id')
            ->innerJoin(GmoOrderPayment::class, 'g',
                        'WITH', 'o.id = g.order_id')
            ->where($qb->expr()->andx(
                $qb->expr()->notIn('o.OrderStatus', [OrderStatus::PENDING,
                                                     OrderStatus::PROCESSING])
            ))
            ->orderBy('o.order_date', 'DESC')
            ->addOrderBy('o.id', 'DESC');

        if (!empty($searchData['Payments']) &&
            count($searchData['Payments']) > 0) {
            $qb->andWhere($qb->expr()->in('o.Payment', ':Payments'))
                ->setParameter('Payments', $searchData['Payments']);
        }

        if (!empty($searchData['OrderStatuses']) &&
            count($searchData['OrderStatuses']) > 0) {
            $qb->andWhere($qb->expr()->in('o.OrderStatus', ':OrderStatuses'))
                ->setParameter('OrderStatuses', $searchData['OrderStatuses']);
        }

        if (!empty($searchData['PaymentStatuses']) &&
            count($searchData['PaymentStatuses']) > 0) {
            $qb->andWhere
                ($qb->expr()->in
                 ('g.memo04', ':PaymentStatuses'))
                ->setParameter('PaymentStatuses',
                               $searchData['PaymentStatuses']);
        }

        return $qb;
    }

    /**
     * 処理結果からメッセージを作成する
     *
     * @param string $myname 処理名称
     * @param boolean $result 処理結果
     * @param integer $order_id 注文番号
     */
    private function setCompleteMessage($myname, $result, $order_id)
    {
        $orderTitle = trans('gmo_payment_gateway.admin.' .
                            'payment_status.col_order_no');
        $prefix = 'gmo_payment_gateway.admin.order_edit.';

        if (!$result) {
            $errors = $this->PaymentHelperAdmin->getError();
            if (empty($errors)) {
                $message = trans($prefix . 'action_error');
                $message = $myname . " [" . $orderTitle . ": " .
                    $order_id . "] " . $message;
                $this->addDanger($message, 'admin');
                PaymentUtil::logError($message);
            } else {
                foreach ($errors as $errMess) {
                    $errMess = $myname . " [" . $orderTitle . ": " .
                        $order_id . "] " . $errMess;
                    $this->addDanger($errMess, 'admin');
                    PaymentUtil::logError($errMess);
                }
            }

            $this->PaymentHelperAdmin->resetError();

            return;
        }

        $message = trans($prefix . 'action_msg');
        $message = $myname . " [" . $orderTitle . ": " .
            $order_id . "] " . $message;
        PaymentUtil::logInfo($message);
    }
}
