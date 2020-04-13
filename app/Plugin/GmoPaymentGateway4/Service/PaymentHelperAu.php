<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentResult;
use Plugin\GmoPaymentGateway4\Service\Method\CarAu;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * キャリア Au 決済処理を行うクラス
 */
class PaymentHelperAu extends PaymentHelperCarrier
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        return CarAu::class;
    }

    /**
     * auかんたん決済を行う
     *
     * @param Order $Order 注文
     * @param array $sendData 送信データ
     * @return boolean
     */
    public function doRequest(Order $Order, array $sendData)
    {
        PaymentUtil::logInfo('PaymentHelperAu::doRequest start.');

        $const = $this->eccubeConfig;

        $url = $this->GmoConfig->getServerUrl() . 'EntryTranAu.idPass';
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

        $url = $this->GmoConfig->getServerUrl() . 'ExecTranAu.idPass';
        $paramNames = [
            'ShopID',
            'ShopPass',
            'AccessID',
            'AccessPass',
            'OrderID',
            'SiteID',
            'SitePass',
            'ClientField1',
            'ClientField2',
            'ClientField3',
            'Commodity',
            'RetURL',
            'PaymentTermSec',
            'ServiceName',
            'ServiceTel',
        ];

        if ($Customer = $Order->getCustomer()) {
            $id = $Customer->getId();
            if (!empty($id) && $id > 0) {
                $paramNames[] = 'MemberID';
                $paramNames[] = 'MemberName';
                $paramNames[] = 'CreateMember';
            }
        }

        // 摘要
        $sendData['Commodity'] = $this->getCommodity($Order);
        // 決済結果戻しURL
        $sendData['RetURL'] = $this->container->get('router')
            ->generate('gmo_payment_gateway_au_result', [],
                       UrlGeneratorInterface::ABSOLUTE_URL);

        $sendData['action_status'] =
            $const['gmo_payment_gateway.action_status.exec_request'];
        $sendData['pay_status'] = '';
        $sendData['success_pay_status'] = '';
        $sendData['fail_pay_status'] =
            $const['gmo_payment_gateway.pay_status.fail'];

        $r = $this->sendOrderRequest($Order, $url, $paramNames, $sendData);

        PaymentUtil::logInfo('PaymentHelperAu::doRequest end.');
        
        return $r;
    }

    /**
     * 摘要 商品名を全角文字に変換して返す
     *
     * @param Order $Order 注文
     * @return string 摘要
     */
    private function getCommodity(Order $Order)
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

        return PaymentUtil::subString($value, 48);
    }
}
