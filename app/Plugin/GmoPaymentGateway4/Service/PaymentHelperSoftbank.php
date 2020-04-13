<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentResult;
use Plugin\GmoPaymentGateway4\Service\Method\CarSoftbank;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * キャリア ソフトバンク決済処理を行うクラス
 */
class PaymentHelperSoftbank extends PaymentHelperCarrier
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        return CarSoftbank::class;
    }

    /**
     * ソフトバンクまとめて支払いを行う
     *
     * @param Order $Order 注文
     * @param array $sendData 送信データ
     * @return boolean
     */
    public function doRequest(Order $Order, array $sendData)
    {
        PaymentUtil::logInfo('PaymentHelperSoftbank::doRequest start.');

        $const = $this->eccubeConfig;

        $url = $this->GmoConfig->getServerUrl() . 'EntryTranSb.idPass';
        $paramNames = [
            'ShopID',
            'ShopPass',
            'OrderID',
            'JobCd',
            'Amount',
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

        $url = $this->GmoConfig->getServerUrl() . 'ExecTranSb.idPass';
        $paramNames = [
            'ShopID',
            'ShopPass',
            'AccessID',
            'AccessPass',
            'OrderID',
            'ClientField1',
            'ClientField2',
            'ClientField3',
            'RetURL',
            'PaymentTermSec',
        ];

        // 決済結果戻しURL
        $sendData['RetURL'] = $this->container->get('router')
            ->generate('gmo_payment_gateway_softbank_result', [],
                       UrlGeneratorInterface::ABSOLUTE_URL);

        $sendData['action_status'] =
            $const['gmo_payment_gateway.action_status.exec_request'];
        $sendData['pay_status'] = '';
        $sendData['success_pay_status'] = '';
        $sendData['fail_pay_status'] =
            $const['gmo_payment_gateway.pay_status.fail'];

        $r = $this->sendOrderRequest($Order, $url, $paramNames, $sendData);

        PaymentUtil::logInfo('PaymentHelperSoftbank::doRequest end.');
        
        return $r;
    }
}
