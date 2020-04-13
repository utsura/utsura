<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentResult;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * キャリア決済処理を行う基底クラス
 */
abstract class PaymentHelperCarrier extends PaymentHelper
{
    /**
     * キャリア側の決済画面へリダイレクトする情報を返す
     *
     * @return PaymentResult
     */
    public function redirectToCarrierPage()
    {
        PaymentUtil::logInfo
            ('PaymentHelperCarrier::redirectToCarrierPage start.');

        $sendData = [];
        $results = $this->getResults();

        $sendData['StartURL'] = $results['StartURL'];
        $sendData['AccessID'] = $results['AccessID'];
        $sendData['Token'] = $results['Token'];

        $template = '@GmoPaymentGateway4/payments/carrier_redirect.twig';
        $contents = $this->twig->render($template, ['sendData' => $sendData]);

        $result = new PaymentResult();
        $result->setSuccess(true);
        $result->setResponse(Response::create($contents));

        PaymentUtil::logInfo
            ('PaymentHelperCredit::redirectToCarrierPage end.');

        return $result;
    }

    /**
     * [オーバーライド] 決済毎に購入完了画面およびメールに
     * 表示する内容を生成する
     *
     * @param Order $Order
     * @return array 表示データ配列
     */
    protected function makeOrderCompleteMessages(Order $Order)
    {
        $data = [];

        // 決済完了案内メール
        if (isset($this->gmoPaymentMethodConfig['order_mail_title1']) &&
            isset($this->gmoPaymentMethodConfig['order_mail_body1'])) {
            $data['order_mail_title1']['name'] =
                $this->gmoPaymentMethodConfig['order_mail_title1'];
            $data['order_mail_title1']['value'] =
                $this->gmoPaymentMethodConfig['order_mail_body1'];
        }

        return $data;
    }

    /**
     * キャンセルをチェックする
     * スタータスにAUTHPROCESSがセットされている場合はキャンセル扱いとする
     *
     * @param array $receiveData 受信データ配列
     * @return string エラーメッセージ
     */
    public function checkCancelStatus(array $receiveData)
    {
        if ($receiveData['Status'] != 'AUTHPROCESS') {
            return '';
        }

        $m = trans('gmo_payment_gateway.payment_helper.carrier.cancel');
        PaymentUtil::logError('PaymentHelperCarrier::checkCancelStatus ' . $m);

        return $m;
    }
}
