<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentResult;
use Plugin\GmoPaymentGateway4\Service\Method\CreditCard;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * クレジット決済処理を行うクラス
 */
class PaymentHelperCredit extends PaymentHelper
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        return CreditCard::class;
    }

    /**
     * クレジットカード決済を行う
     *
     * @param Order $Order 注文
     * @param array $sendData 送信データ
     * @return boolean
     */
    public function doRequest(Order $Order, array $sendData)
    {
        PaymentUtil::logInfo('PaymentHelperCredit::doRequest start.');

        $const = $this->eccubeConfig;

        $url = $this->GmoConfig->getServerUrl() . 'EntryTran.idPass';
        $paramNames = [
            'ShopID',
            'ShopPass',
            'OrderID',
            'JobCd',
            'Amount',
            'TdFlag',
            'TdTenantName',
        ];

        // データ補正
        if ($sendData['payment_type'] === "0") {
            // クレジットカード入力決済
            if (isset($sendData['CardSeq'])) {
                unset($sendData['CardSeq']);
            }
        }

        $sendData['action_status'] =
            $const['gmo_payment_gateway.action_status.entry_request'];
        $sendData['pay_status'] =
            $const['gmo_payment_gateway.pay_status.unsettled'];
        $sendData['success_pay_status'] = '';
        $sendData['fail_pay_status'] =
            $const['gmo_payment_gateway.pay_status.fail'];

        $GmoOrderPayment = $Order->getGmoOrderPayment();
        if (!$GmoOrderPayment->isExistsAccessIDAndPass()) {
            $r = $this->sendOrderRequest($Order, $url, $paramNames, $sendData);
            if (!$r) {
                return $r;
            }
        }

        $url = $this->GmoConfig->getServerUrl() . 'ExecTran.idPass';
        $paramNames = [
            'AccessID',
            'AccessPass',
            'OrderID',
            'Method',
            'PayTimes',
            'ClientField1',
            'ClientField2',
            'ClientField3',
        ];

        $paramNames[] = 'HttpAccept';
        $paramNames[] = 'HttpUserAgent';
        $paramNames[] = 'DeviceCategory';

        // パラメータ補正
        if ($sendData['payment_type'] === "0") {
            // クレジットカード入力決済
            $paramNames[] = 'Token';
            // データ補正
            $sendData['Method'] = $sendData['credit_pay_methods'];
        } else {
            // 登録済みクレジットカード決済
            $paramNames = array_merge($paramNames, [
                'SiteID',
                'SitePass',
                'MemberID',
                'CardSeq',
                'ClientFieldFlag',
                'SeqMode',
            ]);
            // データ補正
            $sendData['Method'] = $sendData['credit_pay_methods2'];
        }

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
        if ($this->gmoPaymentMethodConfig['TdFlag'] === '1') {
            $sendData['success_pay_status'] =
                $const['gmo_payment_gateway.pay_status.unsettled'];
        }

        $r = $this->sendOrderRequest($Order, $url, $paramNames, $sendData);

        PaymentUtil::logInfo('PaymentHelperCredit::doRequest end.');
        
        return $r;
    }

    /**
     * 決済実行の結果3Dセキュアを実行するためのレスポンスが返ってきたか確認する
     *
     * @return boolean true: 3Dセキュア, false: 非3Dセキュア
     */
    public function is3dSecureResponse()
    {
        $results = $this->getResults();
        if (!empty($results['ACS']) && $results['ACS'] === "1" &&
            !empty($results['ACSUrl']) &&
            !empty($results['PaReq']) &&
            !empty($results['MD'])) {
            return true;
        }

        return false;
    }

    /**
     * 3Dセキュアパスワード入力画面へリダイレクトする情報を返す
     *
     * @return PaymentResult
     */
    public function redirectTo3dSecurePage()
    {
        PaymentUtil::logInfo
            ('PaymentHelperCredit::redirectTo3dSecurePage start.');

        $sendData = [];
        $results = $this->getResults();

        $sendData['ACSUrl'] = $results['ACSUrl'];
        $sendData['PaReq'] = $results['PaReq'];
        $sendData['TermUrl'] = $this->container->get('router')
            ->generate('gmo_payment_gateway_3dsecure', [],
                       UrlGeneratorInterface::ABSOLUTE_URL);
        $sendData['MD'] = $results['MD'];

        $template = '@GmoPaymentGateway4/payments/credit_3dsecure.twig';
        $contents = $this->twig->render($template, ['sendData' => $sendData]);

        $result = new PaymentResult();
        $result->setSuccess(true);
        $result->setResponse(Response::create($contents));

        PaymentUtil::logInfo
            ('PaymentHelperCredit::redirectTo3dSecurePage end.');

        return $result;
    }

    /**
     * 本人認証サービス（3Dセキュア）パスワード入力画面後の処理
     * ReceiveController で利用する
     *
     * @param Order $Order 受注
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    public function do3dSecureContinuation(Order $Order, array $receiveData)
    {
        PaymentUtil::logInfo
            ('PaymentHelperCredit::do3dSecureContinuation start.');

        $const = $this->eccubeConfig;

        // 取引ID(MD)の検証
        $paymentLogData = $Order->getGmoOrderPayment()->getPaymentLogData();
        if (isset($paymentLogData['MD']) &&
            $paymentLogData['MD'] !== $receiveData['MD']) {
            $msg = 'gmo_payment_gateway.shopping.credit.3dsecure.error1';
            $this->setError(trans($msg, [
                '%MD1%' => $receiveData['MD'],
                '%MD2%' => $paymentLogData['MD'],
            ]));
            return false;
        }

        // 結果電文の検証
        if (empty($receiveData['PaRes'])) {
            $msg = 'gmo_payment_gateway.shopping.credit.3dsecure.error2';
            $this->setError(trans($msg));
            return false;
        }

        $url = $this->GmoConfig->getServerUrl() . 'SecureTran.idPass';

        $paramNames = [
            'PaRes',
            'MD',
        ];

        $receiveData['action_status'] =
            $const['gmo_payment_gateway.action_status.recv_notice'];
        $receiveData['success_pay_status'] =
            $const['gmo_payment_gateway.pay_status.auth'];
        if (!is_null($this->gmoPaymentMethodConfig['JobCd'])) {
            $status = 'gmo_payment_gateway.pay_status.' .
                strtolower($this->gmoPaymentMethodConfig['JobCd']);
            $receiveData['success_pay_status'] = $const[$status];
        }
        $receiveData['fail_pay_status'] =
            $const['gmo_payment_gateway.pay_status.fail'];

        $r = $this->sendOrderRequest($Order, $url, $paramNames, $receiveData);

        PaymentUtil::logInfo
            ('PaymentHelperCredit::do3dSecureContinuation end.');

        return $r;
    }

    /**
     * 注文後に OrderID を利用してカード登録を行う
     *
     * @param Order $Order 注文
     * @param array $sendData 送信データ
     */
    public function doRegistCard(Order $Order, array $sendData)
    {
        PaymentUtil::logInfo('PaymentHelperCredit::doRegistCard start.');

        $url = $this->GmoConfig->getServerUrl() . 'TradedCard.idPass';

        $paramNames = [
            'ShopID',
            'ShopPass',
            'OrderID',
            'SiteID',
            'SitePass',
            'MemberID',
            'SeqMode',
            'DefaultFlag',
            'HolderName',
        ];

        // データ補正
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $sendData['OrderID'] = $GmoOrderPayment->getGmoOrderID();
        if (isset($sendData['CardSeq'])) {
            unset($sendData['CardSeq']);
        }

        $data = $this->getIfSendData($paramNames, $sendData, $Order);

        $r = $this->sendRequest($url, $data);

        PaymentUtil::logInfo('PaymentHelperCredit::doRegistCard end.');

        return $r;
    }

    /**
     * クレジットカード編集機能が利用可能かどうかを返す
     *
     * @return boolean true: 可, false: 不可
     */
    public function isAvailableCardEdit()
    {
        // カード登録機能の有効化有無を確認
        if (!$this->GmoConfig->getCardRegistFlg()) {
            return false;
        }

        // 支払方法を確認する
        $Payment = $this->paymentRepository
            ->findOneBy(['method_class' => CreditCard::class]);
        if (is_null($Payment)) {
            return false;
        }

        return true;
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

        // 承認番号
        if (isset($results['Approve']) && !is_null($results['Approve'])) {
            $data['Approve']['name'] = trans($prefix . 'approve');
            $data['Approve']['value'] = $results['Approve'];
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
