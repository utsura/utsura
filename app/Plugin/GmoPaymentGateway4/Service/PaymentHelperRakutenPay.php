<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentResult;
use Plugin\GmoPaymentGateway4\Service\Method\RakutenPay;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * 楽天ペイ決済処理を行うクラス
 */
class PaymentHelperRakutenPay extends PaymentHelper
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        return RakutenPay::class;
    }

    /**
     * 楽天ペイ決済を行う
     *
     * @param Order $Order 注文
     * @param array $sendData 送信データ
     * @return boolean
     */
    public function doRequest(Order $Order, array $sendData)
    {
        PaymentUtil::logInfo('PaymentHelperRakutenPay::doRequest start.');

        $const = $this->eccubeConfig;

        $url = $this->GmoConfig->getServerUrl() . 'EntryTranRakutenId.idPass';
        $paramNames = [
            'Version',
            'ShopID',
            'ShopPass',
            'OrderID',
            'JobCd',
            'Amount',
            'Tax',
        ];

        $sendData['action_status'] =
            $const['gmo_payment_gateway.action_status.entry_request'];
        $sendData['pay_status'] =
            $const['gmo_payment_gateway.pay_status.unsettled'];
        $sendData['success_pay_status'] = '';
        $sendData['fail_pay_status'] =
            $const['gmo_payment_gateway.pay_status.fail'];

        $r = $this->sendOrderRequest($Order, $url, $paramNames, $sendData);
        if (!$r) {
            return $r;
        }

        $url = $this->GmoConfig->getServerUrl() . 'ExecTranRakutenId.idPass';
        $paramNames = [
            'Version',
            'ShopID',
            'ShopPass',
            'AccessID',
            'AccessPass',
            'OrderID',
            'ClientField1',
            'ClientField2',
            'ClientField3',
            'ItemId',
            'ItemSubId',
            'ItemName',
            'RetURL',
            'ErrorRcvURL',
            'PaymentTermSec',

            'HttpAccept',
            'HttpUserAgent',
            'DeviceCategory',
        ];

        // 商品ID
        $sendData['ItemId'] = $this->getItemId($Order);
        // 商品名
        $sendData['ItemName'] = $this->getItemName($Order);
        // 決済結果戻しURL(正常時)
        $sendData['RetURL'] = $this->container->get('router')
            ->generate('gmo_payment_gateway_rakuten_pay_result',
                       ['result' => 1], UrlGeneratorInterface::ABSOLUTE_URL);
        // 決済結果戻しURL(異常時)
        $sendData['ErrorRcvURL'] = $this->container->get('router')
            ->generate('gmo_payment_gateway_rakuten_pay_result',
                       ['result' => 0], UrlGeneratorInterface::ABSOLUTE_URL);

        $sendData['action_status'] =
            $const['gmo_payment_gateway.action_status.exec_request'];
        $sendData['pay_status'] = '';
        $sendData['success_pay_status'] =
            $const['gmo_payment_gateway.pay_status.auth'];
        if (!is_null($this->gmoPaymentMethodConfig['JobCd'])) {
            $status = 'gmo_payment_gateway.pay_status.' .
                strtolower($this->gmoPaymentMethodConfig['JobCd']);
            $sendData['success_pay_status'] = $const[$status];
        }
        $sendData['fail_pay_status'] =
            $const['gmo_payment_gateway.pay_status.fail'];

        $r = $this->sendOrderRequest($Order, $url, $paramNames, $sendData);

        PaymentUtil::logInfo('PaymentHelperRakutenPay::doRequest end.');
        
        return $r;
    }

    /**
     * 楽天ペイ側の決済画面へリダイレクトする情報を返す
     *
     * @return PaymentResult
     */
    public function redirectToRakutenPayPage()
    {
        PaymentUtil::logInfo
            ('PaymentHelperCarrier::redirectToRakutenPayPage start.');

        $sendData = [];
        $results = $this->getResults();

        $sendData['StartURL'] = $results['StartURL'];
        $sendData['AccessID'] = $results['AccessID'];
        $sendData['Token'] = $results['Token'];

        $template = '@GmoPaymentGateway4/payments/rakuten_pay_redirect.twig';
        $contents = $this->twig->render($template, ['sendData' => $sendData]);

        $result = new PaymentResult();
        $result->setSuccess(true);
        $result->setResponse(Response::create($contents));

        PaymentUtil::logInfo
            ('PaymentHelperCredit::redirectToRakutenPayPage end.');

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
     * 商品IDを取得する
     *
     * @param Order $Order 注文
     * @return string 商品ID
     */
    private function getItemId(Order $Order)
    {
        $value = '';

        $OrderItems = $Order->getOrderItems();
        foreach ($OrderItems as $OrderItem) {
            $value .= $OrderItem->getId();
            break;
        }

        return $value;
    }

    /**
     * 商品名を全角文字に変換して返す
     *
     * @param Order $Order 注文
     * @return string 商品名
     */
    private function getItemName(Order $Order)
    {
        $value = '';

        $OrderItems = $Order->getOrderItems();
        foreach ($OrderItems as $OrderItem) {
            $value = $OrderItem->getProductName();
            break;
        }

        $value = PaymentUtil::convertProhibitedKigo($value);
        $value = PaymentUtil::convertProhibitedChar($value);
        $value = mb_convert_kana($value, 'KVSA', 'UTF-8');

        return PaymentUtil::subString($value, 255);
    }
}
