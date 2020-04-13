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
 * Pay-easy決済処理を行う基底クラス
 */
abstract class PaymentHelperPayEasy extends PaymentHelper
{
    /**
     * 決済完了案内本文を返す
     *
     * @return string 案内本文
     */
    abstract public function getMailBody();

    /**
     * Pay-easy決済を行う
     *
     * @param Order $Order 注文
     * @param array $sendData 送信データ
     * @return boolean
     */
    public function doRequest(Order $Order, array $sendData)
    {
        PaymentUtil::logInfo('PaymentHelperPayEasy::doRequest start.');

        $const = $this->eccubeConfig;

        $url = $this->GmoConfig->getServerUrl() . 'EntryTranPayEasy.idPass';
        $paramNames = [
            'ShopID',
            'ShopPass',
            'OrderID',
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

        $url = $this->GmoConfig->getServerUrl() . 'ExecTranPayEasy.idPass';
        $paramNames = [
            'AccessID',
            'AccessPass',
            'OrderID',
            'CustomerName',
            'CustomerKana',
            'TelNo',
            'PaymentTermDay',
            'MailAddress',
            'ShopMailAddress',
            'RegisterDisp1',
            'RegisterDisp2',
            'RegisterDisp3',
            'RegisterDisp4',
            'RegisterDisp5',
            'RegisterDisp6',
            'RegisterDisp7',
            'RegisterDisp8',
            'ReceiptsDisp1',
            'ReceiptsDisp2',
            'ReceiptsDisp3',
            'ReceiptsDisp4',
            'ReceiptsDisp5',
            'ReceiptsDisp6',
            'ReceiptsDisp7',
            'ReceiptsDisp8',
            'ReceiptsDisp9',
            'ReceiptsDisp10',
            'ReceiptsDisp11',
            'ReceiptsDisp12',
            'ReceiptsDisp13',
            'ClientField1',
            'ClientField2',
            'ClientField3',
            'ClientFieldFlag',
            'PaymentType'
        ];

        $sendData['action_status'] =
            $const['gmo_payment_gateway.action_status.exec_request'];
        $sendData['pay_status'] = '';
        $sendData['success_pay_status'] =
            $const['gmo_payment_gateway.pay_status.request_success'];
        $sendData['fail_pay_status'] =
            $const['gmo_payment_gateway.pay_status.fail'];

        $r = $this->sendOrderRequest($Order, $url, $paramNames, $sendData);

        PaymentUtil::logInfo('PaymentHelperPayEasy::doRequest end.');
        
        return $r;
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
        $results = $this->getResults();
        $prefix = "gmo_payment_gateway.payment_helper.";

        // お客様番号
        if (isset($results['CustID']) && !is_null($results['CustID'])) {
            $data['CustID']['name'] = trans($prefix . 'custid');
            $data['CustID']['value'] = $results['CustID'];
        }

        // 収納機関番号
        if (isset($results['BkCode']) && !is_null($results['BkCode'])) {
            $data['BkCode']['name'] = trans($prefix . 'bkcode');
            $data['BkCode']['value'] = $results['BkCode'];
        }

        // 確認番号
        if (isset($results['ConfNo']) && !is_null($results['ConfNo'])) {
            $data['ConfNo']['name'] = trans($prefix . 'confno1');
            $data['ConfNo']['value'] = $results['ConfNo'];
        }

        // お支払い期限
        if (isset($results['PaymentTerm']) &&
            !is_null($results['PaymentTerm'])) {
            $data['PaymentTerm']['name'] = trans($prefix . 'paymentterm');
            sscanf($results['PaymentTerm'], "%04d%02d%02d%02d%02d%02d",
                   $year, $month, $day, $hour, $min, $sec);
            $data['PaymentTerm']['value'] =
                sprintf(trans($prefix . 'paymentterm.fmt'),
                        $year, $month, $day, $hour, $min);
        }

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
}
