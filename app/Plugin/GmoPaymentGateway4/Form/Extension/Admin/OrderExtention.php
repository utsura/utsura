<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Form\Extension\Admin;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Form\Type\Admin\OrderType;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperAdmin;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperCvs;
use Plugin\GmoPaymentGateway4\Service\Method\CreditCard;
use Plugin\GmoPaymentGateway4\Service\Method\Cvs;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyNet;
use Plugin\GmoPaymentGateway4\Service\Method\CarAu;
use Plugin\GmoPaymentGateway4\Service\Method\CarDocomo;
use Plugin\GmoPaymentGateway4\Service\Method\CarSoftbank;
use Plugin\GmoPaymentGateway4\Service\Method\RakutenPay;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * 受注修正画面のFormを拡張しPGマルチペイメントサービス決済情報を追加する.
 */
class OrderExtention extends AbstractTypeExtension
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperAdmin
     */
    protected $PaymentHelperAdmin;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperCvs
     */
    protected $PaymentHelperCvs;

    /**
     * コンストラクタ
     *
     * @param EccubeConfig $eccubeConfig
     * @param PaymentHelperAdmin $PaymentHelperAdmin
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        PaymentHelperAdmin $PaymentHelperAdmin,
        PaymentHelperCvs $PaymentHelperCvs
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->PaymentHelperAdmin = $PaymentHelperAdmin;
        $this->PaymentHelperCvs = $PaymentHelperCvs;
    }

    const arrFunction = [
        // クレジットカード
        CreditCard::class => [
            'createInfo' => 'createCreditInfo',
        ],
        // コンビニ
        Cvs::class => [
            'createInfo' => 'createCvsInfo',
        ],
        // ペイジー（銀行ATM）
        PayEasyAtm::class => [
            'createInfo' => 'createPayEasyInfo',
        ],
        // ペイジー（ネットバンク）
        PayEasyNet::class => [
            'createInfo' => 'createPayEasyInfo',
        ],
        // auかんたん決済
        CarAu::class => [
            'createInfo' => 'createCarrierInfo',
        ],
        // ドコモケータイ払い
        CarDocomo::class => [
            'createInfo' => 'createCarrierInfo',
        ],
        // ソフトバンクまとめて支払い
        CarSoftbank::class => [
            'createInfo' => 'createCarrierInfo',
        ],
        // 楽天ペイ
        RakutenPay::class => [
            'createInfo' => 'createRakutenPayInfo',
        ],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA,
                                   function (FormEvent $event) {
            /** @var Order $Order */
            $Order = $event->getData();
            $form = $event->getForm();

            // 支払方法からGMO-PG決済を削除するための情報
            $Order->setGmoPaymentIds
                ($this->PaymentHelperAdmin->getGmoPaymentIds());

            $Payment = $Order->getPayment();
            if (is_null($Payment)) {
                // 新規受注の場合は取得できない
                return;
            }

            // GMO-PG 決済以外の場合はここまで
            $pat = '/^Plugin\\\GmoPaymentGateway4\\\.*/';
            $class = $Payment->getMethodClass();
            if (preg_match($pat, $class) !== 1) {
                return;
            }

            // GMO-PG 情報を付加する
            $Order = $this->PaymentHelperAdmin->prepareGmoInfoForOrder($Order);

            // GMO-PG 送受信ログデータを取得
            $GmoOrderPayment = $Order->getGmoOrderPayment();
            $logData = $GmoOrderPayment->getPaymentLogData();

            // 表示用データを作成
            $info = $this->createCommonInfo($Order, $logData);

            // 支払方法毎に表示用データを追加する
            $methodClass = $Payment->getMethodClass();
            if (isset(self::arrFunction[$methodClass])) {
                $func = self::arrFunction[$methodClass];

                // 決済毎の表示用データを作成
                $funcName = $func['createInfo'];
                $info = $this->$funcName($Order, $logData, $info);
            }

            // 表示用データをセット
            $Order->setGmoPaymentInfo($info);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return OrderType::class;
    }

    /**
     * 共通の表示用決済情報を生成する
     *
     * @param Order $Order 注文
     * @param array $logData GMO-PG 送受信ログデータ配列
     * @return array 表示用情報配列
     */
    private function createCommonInfo($Order, $logData)
    {
        $results = [];

        // 取引状態
        if (isset($logData['pay_status'])) {
            $results['pay_status'] = $this->PaymentHelperAdmin
                ->getPaymentStatusName($logData['pay_status']);
        }
        // 決済オーダーID
        if (isset($logData['OrderID']) && !empty($logData['OrderID'])) {
            $results['OrderID'] = $logData['OrderID'];
        }
        // 最終エラーコード
        if (isset($logData['ErrInfo']) && !empty($logData['ErrInfo'])) {
            $results['ErrInfo'] = $logData['ErrInfo'];
        }
        // 最終エラーメッセージ
        if (isset($logData['error_msg']) && !empty($logData['error_msg'])) {
            $results['error_msg'] = $logData['error_msg'];
        }
        // 決済金額
        if (isset($logData['Amount']) && !empty($logData['Amount'])) {
            $results['Amount'] = $logData['Amount'];
            if ($Order->getDecPaymentTotal() != $logData['Amount']) {
                $results['attention'] =
                    trans('gmo_payment_gateway.admin.' .
                          'order_edit.amount.attention');
            }
        }

        // 決済ログ
        $results['payment_log'] = $logData['payment_log'];

        return $results;
    }

    /**
     * クレジットカード向けの決済情報
     *
     * @param Order $Order 注文
     * @param array $logData GMO-PG 送受信ログデータ配列
     * @param array $info 表示用決済情報配列
     * @return array 表示用決済情報配列
     */
    private function createCreditInfo(Order $Order, $logData, array $info)
    {
        $const = $this->eccubeConfig;
        $prefix = "gmo_payment_gateway.pay_status.";

        // 承認番号
        if (isset($logData['pay_status']) &&
            $logData['pay_status'] != $const[$prefix . 'unsettled']) {
            $info['Approve'] = $logData['Approve'];
        }
        // 支払い方法
        if (isset($logData['Method']) && isset($logData['PayTimes'])) {
            $pay_times = $logData['PayTimes'];
            if (empty($pay_times)) {
                $pay_times = "0";
            }
            $code = $logData['Method'] . '-' . $pay_times;
            $info['Method'] = PaymentUtil::getCreditPayMethodName($code);
        }
        // 仕向け先
        if (isset($logData['Forward']) && !empty($logData['Forward'])) {
            $info['Forward'] = $logData['Forward'];
        }
        // トランザクションID
        if (isset($logData['TranID']) && !empty($logData['TranID'])) {
            $info['TranID'] = $logData['TranID'];
        }
        // 与信日時
        if (isset($logData['TranDate']) && !empty($logData['TranDate'])) {
            $info['TranDate'] = $logData['TranDate'];
        }

        // 操作ボタン
        $info['buttons']['status'] = 1;
        if (isset($logData['pay_status'])) {
            $pay_status = $logData['pay_status'];
            if ($pay_status == $const[$prefix . 'auth']) {
                $info['buttons']['commit'] = 1;
            }
            if ($pay_status == $const[$prefix . 'auth'] ||
                $pay_status == $const[$prefix . 'commit'] ||
                $pay_status == $const[$prefix . 'sales'] ||
                $pay_status == $const[$prefix . 'capture']) {
                $info['buttons']['cancel'] = 1;
                $info['buttons']['change'] = 1;
            }
            if ($pay_status == $const[$prefix . 'void'] ||
                $pay_status == $const[$prefix . 'return'] ||
                $pay_status == $const[$prefix . 'returnx']) {
                $info['buttons']['reauth'] = 1;
            }
        }

        return $info;
    }

    /**
     * コンビニ向けの決済情報
     *
     * @param Order $Order 注文
     * @param array $logData GMO-PG 送受信ログデータ配列
     * @param array $info 表示用決済情報配列
     * @return array 表示用決済情報配列
     */
    private function createCvsInfo(Order $Order, $logData, array $info)
    {
        // 支払い先コンビニ
        if (isset($logData['Convenience']) &&
            !empty($logData['Convenience'])) {
            $info['Convenience'] = $this->PaymentHelperCvs
                ->getConveniName($logData['Convenience']);
        }
        // 確認番号
        if (isset($logData['ConfNo']) && !empty($logData['ConfNo'])) {
            $info['ConfNo'] = $logData['ConfNo'];
        }
        // 受付番号
        if (isset($logData['ReceiptNo']) && !empty($logData['ReceiptNo'])) {
            $info['ReceiptNo'] = $logData['ReceiptNo'];
        }
        // 払込期限
        if (isset($logData['PaymentTerm']) &&
            !empty($logData['PaymentTerm'])) {
            $info['PaymentTerm'] = $logData['PaymentTerm'];
        }

        return $info;
    }

    /**
     * ペイジー向けの決済情報
     *
     * @param Order $Order 注文
     * @param array $logData GMO-PG 送受信ログデータ配列
     * @param array $info 表示用決済情報配列
     * @return array 表示用決済情報配列
     */
    private function createPayEasyInfo(Order $Order, $logData, array $info)
    {
        // お客様番号
        if (isset($logData['CustID']) && !empty($logData['CustID'])) {
            $info['CustID'] = $logData['CustID'];
        }
        // 収納機関番号
        if (isset($logData['BkCode']) && !empty($logData['BkCode'])) {
            $info['BkCode'] = $logData['BkCode'];
        }
        // 確認番号
        if (isset($logData['ConfNo']) && !empty($logData['ConfNo'])) {
            $info['ConfNo'] = $logData['ConfNo'];
        }
        // 払込期限
        if (isset($logData['PaymentTerm']) &&
            !empty($logData['PaymentTerm'])) {
            $info['PaymentTerm'] = $logData['PaymentTerm'];
        }

        return $info;
    }

    /**
     * キャリア決済向けの決済情報
     *
     * @param Order $Order 注文
     * @param array $logData GMO-PG 送受信ログデータ配列
     * @param array $info 表示用決済情報配列
     * @return array 表示用決済情報配列
     */
    private function createCarrierInfo(Order $Order, $logData, array $info)
    {
        $const = $this->eccubeConfig;
        $prefix = "gmo_payment_gateway.pay_status.";

        // 操作ボタン
        $info['buttons']['status'] = 1;
        if (isset($logData['pay_status'])) {
            $pay_status = $logData['pay_status'];
            if ($pay_status == $const[$prefix . 'auth']) {
                $info['buttons']['commit'] = 1;
            }
            if ($pay_status == $const[$prefix . 'auth'] ||
                $pay_status == $const[$prefix . 'commit'] ||
                $pay_status == $const[$prefix . 'sales'] ||
                $pay_status == $const[$prefix . 'capture']) {
                $info['buttons']['cancel'] = 1;
            }
        }

        return $info;
    }

    /**
     * 楽天ペイ向けの決済情報
     *
     * @param Order $Order 注文
     * @param array $logData GMO-PG 送受信ログデータ配列
     * @param array $info 表示用決済情報配列
     * @return array 表示用決済情報配列
     */
    private function createRakutenPayInfo(Order $Order, $logData, array $info)
    {
        $const = $this->eccubeConfig;
        $prefix = "gmo_payment_gateway.pay_status.";

        // 操作ボタン
        $info['buttons']['status'] = 1;
        if (isset($logData['pay_status'])) {
            if (isset($logData['JobCd']) && $logData['JobCd'] == 'AUTH') {
                $pay_status = $logData['pay_status'];
                if ($pay_status == $const[$prefix . 'auth']) {
                    $info['buttons']['commit'] = 1;
                }
                if ($pay_status == $const[$prefix . 'auth'] ||
                    $pay_status == $const[$prefix . 'commit'] ||
                    $pay_status == $const[$prefix . 'sales'] ||
                    $pay_status == $const[$prefix . 'capture']) {
                    $info['buttons']['cancel'] = 1;
                    $info['buttons']['change'] = 1;
                }
            }
        }

        return $info;
    }
}
