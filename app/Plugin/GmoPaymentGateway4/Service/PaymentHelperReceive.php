<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod;
use Plugin\GmoPaymentGateway4\Entity\GmoOrderPayment;
use Plugin\GmoPaymentGateway4\Service\Method\CreditCard;
use Plugin\GmoPaymentGateway4\Service\Method\Cvs;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyNet;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\Method\CarAu;
use Plugin\GmoPaymentGateway4\Service\Method\CarDocomo;
use Plugin\GmoPaymentGateway4\Service\Method\CarSoftbank;
use Plugin\GmoPaymentGateway4\Service\Method\RakutenPay;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * 結果通知／その他受信向け決済処理を行うクラス
 */
class PaymentHelperReceive extends PaymentHelper
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        // 特定の支払方法を持たないので null を返却
        // 支払方法別の設定情報は使えないので注意
        return null;
    }

    /**
     * 受信データの有効性を確認
     *
     * @param array $receiveData 受信データ
     * @return mixed OK: Order, NG: boolean false
     */
    public function validate(array $receiveData)
    {
        PaymentUtil::logInfo('validate start.');

        // 参照する配列キーが存在するかまとめて検査
        $checkKeys = [
            'ShopID',
            'AccessID',
            'OrderID',
            'Status',
            'Amount',
            'Tax',
            'PayType',
        ];
        foreach ($checkKeys as $key) {
            if (!array_key_exists($key, $receiveData)) {
                PaymentUtil::logError
                    ('validate error. ' . $key . ' not found.');
                return false;
            }
        }

        // ShopID の一致確認
        if (empty($receiveData['ShopID']) ||
            $this->GmoConfig->getShopId() !== $receiveData['ShopID']) {
            $shopId = $receiveData['ShopID'];
            PaymentUtil::logError('!!!!!!! Request contain a ShopID = ' .
                                  $shopId .
                                  ' that does not match in Eccube. !!!!!!!');
            $this->doNoOrder($receiveData);

            return false;
        }
                
        PaymentUtil::logInfo('Receive OrderID = ' . $receiveData['OrderID']);

        // GMO-PG OrderID から EC-CUBE order_id を取得する
        $order_id = $this->convertOrderID($receiveData['OrderID']);

        // 注文番号の確認
        if (empty($order_id)) {
            PaymentUtil::logError
                ('!!!!!!! Request does not contain an OrderID. !!!!!!!');
            $this->doNoOrder($receiveData);

            return false;
        }
                
        // 注文番号の一致確認
        $Order = $this->orderRepository->findOneBy(['id' => $order_id]);
        if (is_null($Order)) {
            PaymentUtil::logError
                ('!!!!!!! Request contain an OrderID = ' .
                 $receiveData['OrderID'] .
                 ' that does not exist in Eccube. !!!!!!!');
            $this->doNoOrder($receiveData);

            return false;
        }

        // GMO-PG 情報の存在確認
        $Order = $this->prepareGmoInfoForOrder($Order);
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        if (is_null($GmoOrderPayment)) {
            PaymentUtil::logError
                ('!!!!!!! Request contain an OrderID = ' .
                 $receiveData['OrderID'] .
                 ' that does not exist in Eccube. !!!!!!!');
            $this->doNoOrder($receiveData);

            return false;
        }

        PaymentUtil::logInfo
            ('validate ok OrderID = ' . $receiveData['OrderID']);
        PaymentUtil::logInfo('validate end.');

        return $Order;
    }

    /**
     * 処理の一時停止が必要かどうかを返す
     *
     * @param array $receiveData 受信データ
     * @return boolean true: 必要, false: 不要
     */
    public function isNeededSleep(array $receiveData)
    {
        $status = $receiveData['Status'];

        if ($status === 'AUTH'  ||
            $status === 'CHECK' ||
            $status === 'CAPTURE') {
            return true;
        }

        return false;
    }

    /**
     * 処理を一時停止する時間(秒)を返す
     *
     * @return integer 待ち時間（秒）
     */
    public function getSleepTime()
    {
        // デフォルトは2秒
        $sleep = 2;

        $time = $this->eccubeConfig['gmo_payment_gateway.receive.wait_time'];
        if (!empty($time)) {
            $sleep = $time;
        }

        return $sleep;
    }

    /**
     * 受信準備が整っているかどうかを返す
     *
     * @param integer $orderId 注文ID
     * @param array $receiveData 受信データ
     * @return mixed OK1: Order, OK2: boolean true, NG: boolean false
     */
    public function isReady($orderId, array $receiveData)
    {
        // REQSUCCESS, AUTHPROCESS を受信した場合は、ログを残し
        // 処理を行わず正常応答を返す。
        if ($receiveData['Status'] == 'REQSUCCESS') {
            PaymentUtil::logInfo('Receive REQSUCCESS normal exit.');
            return true;
        } else if ($receiveData['Status'] == 'AUTHPROCESS') {
            PaymentUtil::logInfo('Receive AUTHPROCESS normal exit.');
            return true;
        }

        $this->entityManager->clear();
        $Order = $this->orderRepository->findOneBy(['id' => $orderId]);
        $Order = $this->prepareGmoInfoForOrder($Order);
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        if (empty($paymentLogData['AccessID'])) {
            PaymentUtil::logInfo('GmoOrderPayment AccessID not found.');
            if (!empty($receiveData['ErrCode'])) {
                // AccessID がない場合でも決済エラーが発生している場合は
                // リトライせずに正常応答を返したい。
                PaymentUtil::logInfo('isReady ErrCode[' .
                                     $receiveData['ErrCode'] .
                                     '] found. Normal exit.');
                return true;
            }
            return false;
        }

        PaymentUtil::logInfo('GmoOrderPayment AccessID found.');

        return $Order;
    }

    /**
     * 支払方法の関数テーブルを返す
     *
     * @param integer $payType GMO-PG支払方法
     * @return array 関数テーブル
     */
    protected function getPaymentFunction($payType)
    {
        $prefix = 'gmo_payment_gateway.';
        $arrFunction = [
            // クレジットカード
            $this->eccubeConfig[$prefix . 'pay_type.credit'] => [
                'class' => [
                    CreditCard::class,
                ],
                'doReceive' => 'doReceiveCredit',
                'payname' => trans($prefix . 'com.payname.credit'),
            ],
            // コンビニ
            $this->eccubeConfig[$prefix . 'pay_type.cvs'] => [
                'class' => [
                    Cvs::class,
                ],
                'doReceive' => 'doReceiveCvs',
                'payname' => trans($prefix . 'com.payname.cvs'),
            ],
            // ペイジー
            $this->eccubeConfig[$prefix . 'pay_type.payeasy'] => [
                'class' => [
                    PayEasyAtm::class,
                    PayEasyNet::class,
                ],
                'doReceive' => 'doReceivePayEasy',
                'payname' => trans($prefix . 'com.payname.payeasy'),
            ],
            // auかんたん決済
            $this->eccubeConfig[$prefix . 'pay_type.au'] => [
                'class' => [
                    CarAu::class,
                ],
                'doReceive' => 'doReceiveAu',
                'payname' => trans($prefix . 'com.payname.carrier.au'),
            ],
            // ドコモケータイ払い
            $this->eccubeConfig[$prefix . 'pay_type.docomo'] => [
                'class' => [
                    CarDocomo::class,
                ],
                'doReceive' => 'doReceiveDocomo',
                'payname' => trans($prefix . 'com.payname.carrier.docomo'),
            ],
            // ソフトバンクまとめて支払い
            $this->eccubeConfig[$prefix . 'pay_type.softbank'] => [
                'class' => [
                    CarSoftbank::class,
                ],
                'doReceive' => 'doReceiveSoftbank',
                'payname' => trans($prefix . 'com.payname.carrier.softbank'),
            ],
            // 楽天ペイ
            $this->eccubeConfig[$prefix . 'pay_type.rakuten_pay'] => [
                'class' => [
                    RakutenPay::class,
                ],
                'doReceive' => 'doReceiveRakutenPay',
                'payname' => trans($prefix . 'com.payname.rakuten_pay'),
            ],
        ];

        if (empty($arrFunction[$payType])) {
            return [];
        }

        return $arrFunction[$payType];
    }

    /**
     * 受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    public function doReceive(Order $Order, array $receiveData)
    {
        $result = false;

        PaymentUtil::logInfo('PaymentHelperReceive::doReceive start.');

        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        // AccessID の検証
        if ($receiveData['AccessID'] !== $paymentLogData['AccessID']) {
            $this->doUnmatchAccessID($Order, $receiveData);

            // ログのみ記録
            $GmoOrderPayment->setPaymentLogData($receiveData, true);
            $this->entityManager->persist($GmoOrderPayment);
            $this->entityManager->flush($GmoOrderPayment);

            return $result;
        }

        // PayType の検証と支払方法毎の受信処理
        $func = $this->getPaymentFunction($receiveData['PayType']);
        if (empty($func)) {
            $this->doUnmatchPayType($Order, $receiveData);
        } else {
            $GmoPaymentMethod = $Order->getGmoPaymentMethod();
            if (!in_array($GmoPaymentMethod->getMemo03(), $func['class'])) {
                $this->doUnmatchPayType($Order, $receiveData);
            } else {
                $funcName = $func['doReceive'];
                $result = $this->$funcName($Order, $receiveData);
            }
        }

        if ($result) {
            unset($receiveData['ShopPass']);
            unset($receiveData['AccessPass']);
            $GmoOrderPayment->setPaymentLogData($receiveData, false, $Order);
            $this->entityManager->persist($Order);
            $this->entityManager->persist($GmoOrderPayment);
        } else {
            // ログのみ
            $GmoOrderPayment->setPaymentLogData($receiveData, true);
            $this->entityManager->persist($GmoOrderPayment);
        }

        $this->entityManager->flush();

        PaymentUtil::logInfo('PaymentHelperReceive::doReceive end.');

        return $result;
    }

    /**
     * クレジットカード受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    protected function doReceiveCredit(Order $Order, array &$receiveData)
    {
        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveCredit start.');

        $orderStatus = 0;

        $const = $this->eccubeConfig;
        $prefix = 'gmo_payment_gateway.pay_status.';

        PaymentUtil::logInfo('Status is ' . $receiveData['Status']);

        switch ($receiveData['Status']) {
        case 'UNPROCESSED':
            $receiveData['pay_status'] = $const[$prefix . 'unsettled'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'AUTHENTICATED':
            $receiveData['pay_status'] = $const[$prefix . 'unsettled'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CHECK':
            $orderStatus = OrderStatus::NEW;
            $receiveData['pay_status'] = $const[$prefix . 'check'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CAPTURE':
            $orderStatus = OrderStatus::NEW;
            $receiveData['pay_status'] = $const[$prefix . 'capture'];
            $Order->setPaymentDate(new \DateTime());
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            PaymentUtil::logInfo('Set payment date now.');
            break;

        case 'AUTH':
        case 'SAUTH':
            $orderStatus = OrderStatus::NEW;
            $receiveData['pay_status'] =
                $const[$prefix . strtolower($receiveData['Status'])];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'SALES':
        case 'VOID':
        case 'RETURN':
        case 'RETURNX':
            $receiveData['pay_status'] =
                $const[$prefix . strtolower($receiveData['Status'])];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        default:
            PaymentUtil::logError
                ('Sorry unknown Status = ' . $receiveData['Status']);
            return false;
        }

        if (empty($receiveData['ErrCode']) &&
            isset($receiveData['Amount']) &&
            (int)trim($receiveData['Amount']) != 0) {
            $paymentTotal = (int)trim($receiveData['Amount']);

            if (isset($receiveData['Tax']) &&
                (int)trim($receiveData['Tax']) != 0) {
                $paymentTotal += (int)trim($receiveData['Tax']);
            }

            $Order->setPaymentTotal($paymentTotal);
            PaymentUtil::logInfo('Set payment total = ' . $paymentTotal);
        }

        if (!empty($orderStatus)) {
            $GmoOrderPayment = $Order->getGmoOrderPayment();
            $paymentLogData = $GmoOrderPayment->getPaymentLogData();
            if (array_key_exists('no_update_status_flg', $paymentLogData)) {
                PaymentUtil::logInfo('no_update_status_flg found.');
                if ($paymentLogData['no_update_status_flg'] != 1) {
                    $Order->setOrderStatus
                        ($this->orderStatusRepository->find($orderStatus));
                    PaymentUtil::logInfo('Set order status = ' . $orderStatus);
                }
            } else {
                PaymentUtil::logInfo('no_update_status_flg not found.');
                $receiveData['no_update_status_flg'] = '0';
            }
        }

        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveCredit end.');

        return true;
    }

    /**
     * コンビニ受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    protected function doReceiveCvs(Order $Order, array &$receiveData)
    {
        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveCvs start.');

        $r = $this->doReceiveDefault($Order, $receiveData);

        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveCvs end.');

        return $r;
    }

    /**
     * ペイジー受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    protected function doReceivePayEasy(Order $Order, array &$receiveData)
    {
        PaymentUtil::logInfo('PaymentHelperReceive::doReceivePayEasy start.');

        $r = $this->doReceiveDefault($Order, $receiveData);

        PaymentUtil::logInfo('PaymentHelperReceive::doReceivePayEasy end.');

        return $r;
    }

    /**
     * auかんたん決済受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    protected function doReceiveAu(Order $Order, array &$receiveData)
    {
        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveAu start.');

        $orderStatus = 0;

        $const = $this->eccubeConfig;
        $prefix = 'gmo_payment_gateway.pay_status.';

        PaymentUtil::logInfo('Status is ' . $receiveData['Status']);

        switch ($receiveData['Status']) {
        case 'UNPROCESSED':
            if ($receiveData['JobCd'] == 'CANCEL') {
                $orderStatus = OrderStatus::CANCEL;
            }
            $receiveData['pay_status'] = $const[$prefix . 'unsettled'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'AUTH':
            $orderStatus = OrderStatus::NEW;
            $receiveData['pay_status'] = $const[$prefix . 'auth'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CAPTURE':
            $orderStatus = OrderStatus::PAID;
            $receiveData['pay_status'] = $const[$prefix . 'capture'];
            $Order->setPaymentDate(new \DateTime());
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            PaymentUtil::logInfo('Set payment date now.');
            break;

        case 'SALES':
        case 'RETURN':
            $receiveData['pay_status'] =
                $const[$prefix . strtolower($receiveData['Status'])];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'PAYFAIL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'fail'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CANCEL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'cancel'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        default:
            PaymentUtil::logError
                ('Sorry unknown Status = ' . $receiveData['Status']);
            return false;
        }

        // 注文確定処理
        $this->fixedOrder($Order, $receiveData, $orderStatus);

        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveAu end.');

        return true;
    }

    /**
     * ドコモケータイ払い受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    protected function doReceiveDocomo(Order $Order, array &$receiveData)
    {
        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveDocomo start.');

        $orderStatus = 0;

        $const = $this->eccubeConfig;
        $prefix = 'gmo_payment_gateway.pay_status.';

        PaymentUtil::logInfo('Status is ' . $receiveData['Status']);

        switch ($receiveData['Status']) {
        case 'UNPROCESSED':
            if ($receiveData['JobCd'] == 'CANCEL') {
                $orderStatus = OrderStatus::CANCEL;
            }
            $receiveData['pay_status'] = $const[$prefix . 'unsettled'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'AUTH':
            $orderStatus = OrderStatus::NEW;
            $receiveData['pay_status'] = $const[$prefix . 'auth'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CAPTURE':
            $orderStatus = OrderStatus::PAID;
            $receiveData['pay_status'] = $const[$prefix . 'capture'];
            $Order->setPaymentDate(new \DateTime());
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            PaymentUtil::logInfo('Set payment date now.');
            break;

        case 'SALES':
            $receiveData['pay_status'] = $const[$prefix . 'sales'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'PAYFAIL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'fail'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'EXPIRED':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'expire'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CANCEL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'cancel'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        default:
            PaymentUtil::logError
                ('Sorry unknown Status = ' . $receiveData['Status']);
            return false;
        }

        // 注文確定処理
        $this->fixedOrder($Order, $receiveData, $orderStatus);

        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveDocomo end.');

        return true;
    }

    /**
     * ソフトバンクまとめて支払い受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    protected function doReceiveSoftbank(Order $Order, array &$receiveData)
    {
        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveSoftbank start.');

        $orderStatus = 0;

        $const = $this->eccubeConfig;
        $prefix = 'gmo_payment_gateway.pay_status.';

        PaymentUtil::logInfo('Status is ' . $receiveData['Status']);

        switch ($receiveData['Status']) {
        case 'UNPROCESSED':
            if ($receiveData['JobCd'] == 'CANCEL') {
                $orderStatus = OrderStatus::CANCEL;
            } else if ($receiveData['JobCd'] == 'AUTH' ||
                       $receiveData['JobCd'] == 'CAPTURE' ||
                       $receiveData['JobCd'] == 'SALES') {
                $orderStatus = OrderStatus::PENDING;
            }
            $receiveData['pay_status'] = $const[$prefix . 'unsettled'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'AUTH':
            $orderStatus = OrderStatus::NEW;
            $receiveData['pay_status'] = $const[$prefix . 'auth'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CAPTURE':
            $orderStatus = OrderStatus::NEW;
            $receiveData['pay_status'] = $const[$prefix . 'capture'];
            $Order->setPaymentDate(new \DateTime());
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            PaymentUtil::logInfo('Set payment date now.');
            break;

        case 'SALES':
            $receiveData['pay_status'] = $const[$prefix . 'sales'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'PAYFAIL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'fail'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'EXPIRED':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'expire'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CANCEL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'cancel'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        default:
            PaymentUtil::logError
                ('Sorry unknown Status = ' . $receiveData['Status']);
            return false;
        }

        // 注文確定処理
        $this->fixedOrder($Order, $receiveData, $orderStatus);

        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveSoftbank end.');

        return true;
    }

    /**
     * 楽天ペイ受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    protected function doReceiveRakutenPay(Order $Order, array &$receiveData)
    {
        PaymentUtil::logInfo
            ('PaymentHelperReceive::doReceiveRakutenPay start.');

        $orderStatus = 0;

        $const = $this->eccubeConfig;
        $prefix = 'gmo_payment_gateway.pay_status.';

        PaymentUtil::logInfo('Status is ' . $receiveData['Status']);

        switch ($receiveData['Status']) {
        case 'UNPROCESSED':
            $receiveData['pay_status'] = $const[$prefix . 'unsettled'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'PAYFAIL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'fail'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'EXPIRED':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'expire'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'AUTH':
        case 'CAPTURE':
            if (empty($receiveData['CompletionDate'])) {
                $orderStatus = OrderStatus::NEW;
            }
            $receiveData['pay_status'] =
                $const[$prefix . strtolower($receiveData['Status'])];
            $Order->setPaymentDate(new \DateTime());
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            PaymentUtil::logInfo('Set payment date now.');
            break;

        case 'PAYSTART':
        case 'REQSALES':
        case 'REQCANCEL':
        case 'REQCHANGE':
        case 'SALES':
            $receiveData['pay_status'] =
                $const[$prefix . strtolower($receiveData['Status'])];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'CANCEL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'cancel'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        default:
            PaymentUtil::logError
                ('Sorry unknown Status = ' . $receiveData['Status']);
            return false;
        }

        // 注文確定処理
        $this->fixedOrder($Order, $receiveData, $orderStatus);

        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveRakutenPay end.');

        return true;
    }

    /**
     * 共通受信処理
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @return boolean true: OK, false: NG
     */
    protected function doReceiveDefault(Order $Order, array &$receiveData)
    {
        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveDefault start.');

        $orderStatus = 0;

        $const = $this->eccubeConfig;
        $prefix = 'gmo_payment_gateway.pay_status.';

        PaymentUtil::logInfo('Status is ' . $receiveData['Status']);

        switch ($receiveData['Status']) {
        case 'UNPROCESSED':
            $receiveData['pay_status'] = $const[$prefix . 'unsettled'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'PAYSUCCESS':
            $orderStatus = OrderStatus::PAID;
            $receiveData['pay_status'] = $const[$prefix . 'pay_success'];
            $Order->setPaymentDate(new \DateTime());
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            PaymentUtil::logInfo('Set payment date now.');
            break;

        case 'PAYFAIL':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'fail'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        case 'EXPIRED':
            $orderStatus = OrderStatus::CANCEL;
            $receiveData['pay_status'] = $const[$prefix . 'expire'];
            PaymentUtil::logInfo('pay_status = ' . $receiveData['pay_status']);
            break;

        default:
            PaymentUtil::logError
                ('Sorry unknown Status = ' . $receiveData['Status']);
            return false;
        }

        if (!empty($orderStatus)) {
            $Order->setOrderStatus
                ($this->orderStatusRepository->find($orderStatus));
            PaymentUtil::logInfo('Set order status = ' . $orderStatus);
        }

        PaymentUtil::logInfo('PaymentHelperReceive::doReceiveDefault end.');

        return true;
    }

    /**
     * GMO-PG OrderID から EC-CUBE order_id を取り出す
     *
     * @param string $OrderID GMO-PG オーダーID
     */
    protected function convertOrderID($OrderID)
    {
        if (empty($OrderID)) {
            return "";
        }

        if (strstr($OrderID, '-') === false) {
            return "";
        }

        list($order_id, $dummy) = explode('-', $OrderID);
        if (empty($order_id) || !is_numeric($order_id)) {
            return "";
        }

        return $order_id;
    }

    /**
     * Send mail in case not found orderId in PaymentRecv or DB or
     * PaymentRecv have error.
     *
     * @param array $receiveData 受信データ
     */
    protected function doNoOrder(array $receiveData)
    {   
        PaymentUtil::logInfo('doNoOrder start.');

        $prefix = 'gmo_payment_gateway.';
        $tplpath = '@GmoPaymentGateway4/admin/mail/recv_no_order.twig';
        $subject = trans($prefix . 'admin.config.title') . ' ' .
            trans($prefix . 'payment_helper.do_no_order.title');

        $this->sendMail($tplpath, $subject, $receiveData);

        PaymentUtil::logInfo('doNoOrder end.');
    }

    /**
     * Send mail in case AccessID in PaymentRecv and Order is not match.
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     */
    protected function doUnmatchAccessID(Order $Order, array $receiveData)
    {
        PaymentUtil::logInfo('doUnmatchAccessID start.');

        PaymentUtil::logError('!!!!!!! Request contain an AccessID = '.
                              $receiveData['AccessID'] .
                              ' that does not match with AccessID' .
                              ' of order in Eccube. !!!!!!!');

        $prefix = 'gmo_payment_gateway.';
        $tplpath = '@GmoPaymentGateway4/admin/mail/recv_unmatch_accessid.twig';
        $subject = trans($prefix . 'admin.config.title') . ' ' .
            trans($prefix . 'payment_helper.do_unmatch_accessid.title');

        $this->sendMail($tplpath, $subject, $receiveData, $Order);

        PaymentUtil::logInfo('doUnmatchAccessID end.');
    }

    /**
     * Send mail in case PayType in PaymentRecv and Order is not match.
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     */
    protected function doUnmatchPayType(Order $Order, array $receiveData)
    {
        PaymentUtil::logInfo('doUnmatchPayType start.');

        PaymentUtil::logError('!!!!!!! Request contain an PayType = ' .
                              $receiveData['PayType'] .
                              ' that does not match with PayType' .
                              ' of order in Eccube. !!!!!!!');

        $prefix = 'gmo_payment_gateway.';
        $tplpath = '@GmoPaymentGateway4/admin/mail/recv_unmatch_paytype.twig';
        $subject = trans($prefix . 'admin.config.title') . ' ' .
            trans($prefix . 'payment_helper.do_unmatch_paytype.title');

        $this->sendMail($tplpath, $subject, $receiveData, $Order);

        PaymentUtil::logInfo('doUnmatchPayType end.');
    }

    /**
     * Send mail when call recv result
     *
     * @param string $templatePath
     * @param string $subject
     * @param array $receiveData 受信データ
     * @param Order $Order 注文
     * @return type
     */
    protected function sendMail
        ($templatePath, $subject, array $receiveData, Order $Order = null)
    {
        PaymentUtil::logInfo('sendMail start.');

        if (!empty($receiveData['ErrCode']) &&
            !empty($receiveData['ErrInfo'])) {
            PaymentUtil::logError('ErrCode and ErrInfo found.');
            return;
        }
        if ($receiveData['Status'] == 'PAYFAIL') {
            PaymentUtil::logError('Status is PAYFAIL.');
            return;
        }

        $receiveData['pay_type'] =
            trans('gmo_payment_gateway.com.payname.unknown') . '(PayType)';
        $func = $this->getPaymentFunction($receiveData['PayType']);
        if (!empty($func)) {
            $receiveData['pay_type'] = $func['payname'];
        }

        // GMO-PG OrderID から EC-CUBE order_id を取得する
        $receiveData['order_id'] =
            $this->convertOrderID($receiveData['OrderID']);

        $payment_method = "";
        $paymentLogData = [];
        if (!is_null($Order)) {
            $payment_method = $Order->getPaymentMethod();
            $GmoOrderPayment = $Order->getGmoOrderPayment();
            $paymentLogData = $GmoOrderPayment->getPaymentLogData();
        }

        $body = $this->twig->render($templatePath, [
            'data' => $receiveData,
            'orderData' => $paymentLogData,
            'payment_method' => $payment_method,
        ]);

        $message = (new \Swift_Message())
            ->setSubject($subject)
            ->setFrom([$this->BaseInfo->getEmail01() =>
                       $this->BaseInfo->getShopName()])
            ->setTo($this->BaseInfo->getEmail02())
            ->setBcc($this->BaseInfo->getEmail01())
            ->setReplyTo($this->BaseInfo->getEmail03())
            ->setReturnPath($this->BaseInfo->getEmail04())
            ->setBody($body);
        $this->mailer->send($message);

        PaymentUtil::logInfo('sendMail end.');
    }

    /**
     * 注文を確定して注文完了メールを送信する
     *
     * @param Order $Order 注文
     * @param array $receiveData 受信データ
     * @param int $orderStatus 注文ステータス
     */
    private function fixedOrder(Order $Order, array $receiveData, $orderStatus)
    {
        PaymentUtil::logInfo('PaymentHelperReceive::fixedOrder start.');

        // エラーがセットされている場合は何もしない
        if (!empty($receiveData['ErrCode'])) {
            PaymentUtil::logInfo('Error found exit. [' .
                                 $receiveData['ErrCode'] . '-' .
                                 $receiveData['ErrInfo'] . ']');
            return;
        }

        if (isset($receiveData['Amount']) &&
            (int)trim($receiveData['Amount']) != 0) {
            $paymentTotal = (int)trim($receiveData['Amount']);

            if (isset($receiveData['Tax']) &&
                (int)trim($receiveData['Tax']) != 0) {
                $paymentTotal += (int)trim($receiveData['Tax']);
            }

            $Order->setPaymentTotal($paymentTotal);
            PaymentUtil::logInfo('Set payment total = ' . $paymentTotal);
        }

        if (!empty($orderStatus)) {
            if ($orderStatus == OrderStatus::NEW ||
                $orderStatus == OrderStatus::PAID) {
                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($Order, new PurchaseContext());
            }

            $Order->setOrderStatus
                ($this->orderStatusRepository->find($orderStatus));
            PaymentUtil::logInfo('Set order status = ' . $orderStatus);

            if ($orderStatus == OrderStatus::NEW ||
                $orderStatus == OrderStatus::PAID) {
                // メール送信
                PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                           'com.done.info2',
                                           ['%order_id%' => $Order->getId()]));
                $this->mailService->sendOrderMail($Order);
            }
        }

        PaymentUtil::logInfo('PaymentHelperReceive::fixedOrder end.');
    }
}
