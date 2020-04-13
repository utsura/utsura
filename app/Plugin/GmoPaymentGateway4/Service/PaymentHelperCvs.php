<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentResult;
use Plugin\GmoPaymentGateway4\Service\Method\Cvs;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * コンビニ決済処理を行うクラス
 */
class PaymentHelperCvs extends PaymentHelper
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        return Cvs::class;
    }

    /**
     * コンビニ名、コード配列を返す
     *
     * @param boolean $filter false: すべて返す、true: 有効設定のみ返す
     * @return array
     */
    public function getConveni($filter = false)
    {
        $const = $this->eccubeConfig;
        $cd_pre = 'gmo_payment_gateway_cvs_';
        $nm_pre = 'gmo_payment_gateway.com.payname.cvs.';

        $conveni_all = [
            $const[$cd_pre . 'lawson'] => trans($nm_pre . 'lawson'),
            $const[$cd_pre . 'familymart'] => trans($nm_pre . 'familymart'),
            $const[$cd_pre . 'ministop'] => trans($nm_pre . 'ministop'),
            $const[$cd_pre . 'daily'] => trans($nm_pre . 'daily'),
            $const[$cd_pre . 'seveneleven'] => trans($nm_pre . 'seveneleven'),
            $const[$cd_pre . 'seicomart'] => trans($nm_pre . 'seicomart'),
        ];

        if (!$filter) {
            // すべてを返す
            return $conveni_all;
        }

        $result = [];

        // 有効化されたコンビニのみにフィルターする
        $config = $this->getGmoPaymentMethodConfig();
        if (empty($config['conveni'])) {
            return $result;
        }
        foreach ($conveni_all as $key => $value) {
            if (in_array($key, $config['conveni'])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * コンビニ名称を返す
     *
     * @param string $conveniCode コンビニコード
     * @return string コンビニ名称
     */
    public function getConveniName($conveniCode)
    {
        $conveniNames = $this->getConveni();

        if (empty($conveniNames[$conveniCode])) {
            return "";
        }

        return $conveniNames[$conveniCode];
    }

    /**
     * コンビニ決済を行う
     *
     * @param Order $Order 注文
     * @param array $sendData 送信データ
     * @return boolean
     */
    public function doRequest(Order $Order, array $sendData)
    {
        PaymentUtil::logInfo('PaymentHelperCvs::doRequest start.');

        $const = $this->eccubeConfig;

        $url = $this->GmoConfig->getServerUrl() . 'EntryTranCvs.idPass';
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

        $url = $this->GmoConfig->getServerUrl() . 'ExecTranCvs.idPass';
        $paramNames = [
            'AccessID',
            'AccessPass',
            'OrderID',
            'Convenience',
            'CustomerName',
            'CustomerKana',
            'TelNo',
            'PaymentTermDay',
            'MailAddress',
            'ShopMailAddress',
            'ReserveNo',
            'MemberNo',
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
        ];

        $sendData['action_status'] =
            $const['gmo_payment_gateway.action_status.exec_request'];
        $sendData['pay_status'] = '';
        $sendData['success_pay_status'] =
            $const['gmo_payment_gateway.pay_status.request_success'];
        $sendData['fail_pay_status'] =
            $const['gmo_payment_gateway.pay_status.fail'];

        $r = $this->sendOrderRequest($Order, $url, $paramNames, $sendData);

        PaymentUtil::logInfo('PaymentHelperCvs::doRequest end.');
        
        return $r;
    }

    /**
     * コンビニ案内文のタイトルを返す
     *
     * @param string $cvscode コンビニコード
     * @return string タイトル
     */
    protected function getCvsGuidanceTitle($cvscode)
    {
        // （コンビニ名）でのお支払い
        $prefix = "gmo_payment_gateway.payment_helper.";
        return $this->getConveniName($cvscode) . trans($prefix . 'cvstitle');
    }

    /**
     * コンビニ案内文の本文を返す
     *
     * @param string $cvscode コンビニコード
     * @return string 本文
     */
    protected function getCvsGuidanceBody($cvscode)
    {
        $path = '@GmoPaymentGateway4/mail/cvs_' . $cvscode . '.twig';
        return $this->twig->render($path, []);
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
        $const = $this->eccubeConfig;
        $rprefix = "gmo_payment_gateway.payment_helper.";
        $dprefix = "gmo_payment_gateway_cvs_";

        if (empty($results['Convenience'])) {
            return $data;
        }

        $cvscode = $results['Convenience'];

        if ($cvscode == $const[$dprefix . 'lawson'] ||
            $cvscode == $const[$dprefix . 'ministop']) {
            // ローソン、ミニストップ（お客様番号）
            if (isset($results['ReceiptNo']) &&
                !is_null($results['ReceiptNo'])) {
                $data['ReceiptNo']['name'] = trans($rprefix . 'receiptno1');
                $data['ReceiptNo']['value'] = $results['ReceiptNo'];
            }
        } else if ($cvscode == $const[$dprefix . 'seicomart']) {
            // セイコーマート（受付番号）
            if (isset($results['ReceiptNo']) &&
                !is_null($results['ReceiptNo'])) {
                $data['ReceiptNo']['name'] = trans($rprefix . 'receiptno2');
                $data['ReceiptNo']['value'] = $results['ReceiptNo'];
            }
        }

        if (isset($results['ConfNo']) && !is_null($results['ConfNo'])) {
            if ($cvscode == $const[$dprefix . 'familymart']) {
                // ファミリーマート（企業コード）
                $data['ConfNo']['name'] = trans($rprefix . 'confno2');
            } else if ($cvscode == $const[$dprefix . 'seicomart']) {
                // セイコーマート（申込番号）
                $data['ConfNo']['name'] = trans($rprefix . 'confno3');
            } else {
                // その他コンビニ（確認番号）
                $data['ConfNo']['name'] = trans($rprefix . 'confno1');
            }
            $data['ConfNo']['value'] = $results['ConfNo'];
        }

        if ($cvscode == $const[$dprefix . 'familymart']) {
            // ファミリーマート（注文番号）
            if (isset($results['ReceiptNo']) &&
                !is_null($results['ReceiptNo'])) {
                $data['ReceiptNo']['name'] = trans($rprefix . 'receiptno3');
                $data['ReceiptNo']['value'] = $results['ReceiptNo'];
            }
        } else if ($cvscode == $const[$dprefix . 'daily'] ||
                   $cvscode == $const[$dprefix . 'seveneleven']) {
            // デイリーヤマザキ、セブンイレブン（受付番号）
            if (isset($results['ReceiptNo']) &&
                !is_null($results['ReceiptNo'])) {
                $data['ReceiptNo']['name'] = trans($rprefix . 'receiptno2');
                $data['ReceiptNo']['value'] = $results['ReceiptNo'];
            }
        }

        // 払込票URL
        if (isset($results['ReceiptUrl']) &&
            !is_null($results['ReceiptUrl']))  {
            $data['ReceiptUrl']['name'] = trans($rprefix . 'receipturl');
            $data['ReceiptUrl']['value'] = $results['ReceiptUrl'];
        }

        // お支払い期限
        if (isset($results['PaymentTerm']) &&
            !is_null($results['PaymentTerm'])) {
            $data['PaymentTerm']['name'] = trans($rprefix . 'paymentterm');
            sscanf($results['PaymentTerm'], "%04d%02d%02d%02d%02d%02d",
                   $year, $month, $day, $hour, $min, $sec);
            $data['PaymentTerm']['value'] =
                sprintf(trans($rprefix . 'paymentterm.fmt'),
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

        $title_key = 'order_mail_title_' . $cvscode;
        $data[$title_key]['name'] = $this->getCvsGuidanceTitle($cvscode);
        $data[$title_key]['value']  = "\n\n";
        $data[$title_key]['value'] .= $this->getCvsGuidanceBody($cvscode);

        return $data;
    }
}
