<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperAdmin;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrderController extends AbstractController
{
    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperAdmin
     */
    protected $PaymentHelperAdmin;

    /**
     * コンストラクタ
     *
     * @param PaymentHelperAdmin $PaymentHelperAdmin
     */
    public function __construct (
        PaymentHelperAdmin $PaymentHelperAdmin
    ) {
        $this->PaymentHelperAdmin = $PaymentHelperAdmin;
    }

    /**
     * 受注編集 > 売上確定(実売上)実行
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/order/commit/{id}", requirements={"id" = "\d+"}, name="gmo_payment_gateway_admin_order_commit")
     */
    public function commit(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            $prefix = 'gmo_payment_gateway.admin.order_edit.';
            $myname = trans($prefix . 'button.commit');

            PaymentUtil::logInfo($myname . " start");

            // 注文に GMO-PG 情報を付加する
            $Order = $this->PaymentHelperAdmin->prepareGmoInfoForOrder($Order);

            $r = $this->PaymentHelperAdmin->doCommitOrder($Order);
            $this->setCompleteMessage($myname, $r);

            PaymentUtil::logInfo($myname . " end");

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * 受注編集 > 取消(返品)実行
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/order/cancel/{id}", requirements={"id" = "\d+"}, name="gmo_payment_gateway_admin_order_cancel")
     */
    public function cancel(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            $prefix = 'gmo_payment_gateway.admin.order_edit.';
            $myname = trans($prefix . 'button.cancel');

            PaymentUtil::logInfo($myname . " start");

            // 注文に GMO-PG 情報を付加する
            $Order = $this->PaymentHelperAdmin->prepareGmoInfoForOrder($Order);

            $r = $this->PaymentHelperAdmin->doCancelOrder($Order);
            $this->setCompleteMessage($myname, $r);

            PaymentUtil::logInfo($myname . " end");

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * 受注編集 > 決済金額変更
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/order/change/{id}", requirements={"id" = "\d+"}, name="gmo_payment_gateway_admin_order_change")
     */
    public function change(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            $prefix = 'gmo_payment_gateway.admin.order_edit.';
            $myname = trans($prefix . 'button.change');

            PaymentUtil::logInfo($myname . " start");

            // 注文に GMO-PG 情報を付加する
            $Order = $this->PaymentHelperAdmin->prepareGmoInfoForOrder($Order);

            $r = $this->PaymentHelperAdmin->doChangeOrder($Order);
            $this->setCompleteMessage($myname, $r);

            PaymentUtil::logInfo($myname . " end");

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * 受注編集 > 再オーソリ実行
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/order/reauth/{id}", requirements={"id" = "\d+"}, name="gmo_payment_gateway_admin_order_reauth")
     */
    public function reauth(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            $prefix = 'gmo_payment_gateway.admin.order_edit.';
            $myname = trans($prefix . 'button.reauth');

            PaymentUtil::logInfo($myname . " start");

            // 注文に GMO-PG 情報を付加する
            $Order = $this->PaymentHelperAdmin->prepareGmoInfoForOrder($Order);

            $r = $this->PaymentHelperAdmin->doReauthOrder($Order);
            $this->setCompleteMessage($myname, $r);

            PaymentUtil::logInfo($myname . " end");

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * 受注編集 > 決済状態確認・反映
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/order/status/{id}", requirements={"id" = "\d+"}, name="gmo_payment_gateway_admin_order_status")
     */
    public function status(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            $prefix = 'gmo_payment_gateway.admin.order_edit.';
            $myname = trans($prefix . 'button.status');

            PaymentUtil::logInfo($myname . " start");

            // 注文に GMO-PG 情報を付加する
            $Order = $this->PaymentHelperAdmin->prepareGmoInfoForOrder($Order);

            $r = $this->PaymentHelperAdmin->doStatusOrder($Order);
            $this->setCompleteMessage($myname, $r);

            PaymentUtil::logInfo($myname . " end");

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * 処理結果からメッセージを作成する
     *
     * @param string $myname 処理名称
     * @param boolean $result 処理結果
     */
    private function setCompleteMessage($myname, $result)
    {
        $prefix = 'gmo_payment_gateway.admin.order_edit.';

        if (!$result) {
            $errors = $this->PaymentHelperAdmin->getError();
            if (empty($errors)) {
                $message = trans($prefix . 'action_error');
                $this->addDanger($message, 'admin');
                PaymentUtil::logError("[" . $myname . "] " . $message);
            } else {
                foreach ($errors as $errMess) {
                    $this->addDanger($errMess, 'admin');
                    PaymentUtil::logError("[" . $myname . "] " . $errMess);
                }
            }

            return;
        }

        $message = trans($prefix . 'action_msg');
        $this->addSuccess($message, 'admin');
        PaymentUtil::logInfo("[" . $myname . "] " . $message);
    }
}
