<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Plugin\GmoPaymentGateway4\Entity\GmoOrderPayment;
use Plugin\GmoPaymentGateway4\Service\Method\CreditCard;
use Plugin\GmoPaymentGateway4\Service\Method\Cvs;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyNet;
use Plugin\GmoPaymentGateway4\Service\Method\CarAu;
use Plugin\GmoPaymentGateway4\Service\Method\CarDocomo;
use Plugin\GmoPaymentGateway4\Service\Method\CarSoftbank;
use Plugin\GmoPaymentGateway4\Service\Method\RakutenPay;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * 管理画面向け決済処理を行うクラス
 */
class PaymentHelperAdmin extends PaymentHelper
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
     * GMO-PG 決済の payment_id 一覧を返す
     * 管理画面の支払方法からGMO-PG決済を削除するために必要
     *
     * @return array
     */
    public function getGmoPaymentIds()
    {
        $results = [];

        $PaymentMethods = $this->gmoPaymentMethodRepository->findAll();
        foreach ($PaymentMethods as $PaymentMethod) {
            $results[] = $PaymentMethod->getPaymentId();
        }

        return $results;
    }

    const arrFunction = [
        // クレジットカード
        CreditCard::class => [
            'commit' => [
                'url' => 'AlterTran.idPass',
                'addParams' => ['JobCd', 'Amount'],
                'term_days' => '90',
                'check' => null,
                'check_term' => true,
            ],
            'cancel' =>  [
                'url' => 'AlterTran.idPass',
                'addParams' => ['JobCd'],
                'term_days' => '180',
                'check' => null,
                'check_term' => true,
            ],
            'change' =>  [
                'url' => 'ChangeTran.idPass',
                'addParams' => ['JobCd', 'Amount'],
                'term_days' => '180',
                'check' => null,
                'check_term' => true,
            ],
            'status' =>  [
                'url' => 'SearchTrade.idPass',
                'addParams' => [],
                'addData' => null,
            ],
        ],
        // auかんたん決済
        CarAu::class => [
            'commit' => [
                'url' => 'AuSales.idPass',
                'addParams' => ['OrderID', 'Amount'],
                'term_days' => '90',
                'check' => null,
                'check_term' => true,
            ],
            'cancel' =>  [
                'url' => 'AuCancelReturn.idPass',
                'addParams' => ['OrderID', 'CancelAmount'],
                'term_days' => '60',
                'check' => 'checkCancelAu',
                'check_term' => true,
            ],
            'status' =>  [
                'url' => 'SearchTradeMulti.idPass',
                'addParams' => ['PayType'],
                'addData' => 'addStatusData',
            ],
        ],
        // ドコモケータイ払い
        CarDocomo::class => [
            'commit' => [
                'url' => 'DocomoSales.idPass',
                'addParams' => ['OrderID', 'Amount'],
                'term_days' => '90',
                'check' => null,
                'check_term' => true,
            ],
            'cancel' =>  [
                'url' => 'DocomoCancelReturn.idPass',
                'addParams' => ['OrderID', 'CancelAmount'],
                'term_days' => '180',
                'check' => 'checkCancelDocomo',
                'check_term' => true,
            ],
            'status' =>  [
                'url' => 'SearchTradeMulti.idPass',
                'addParams' => ['PayType'],
                'addData' => 'addStatusData',
            ],
        ],
        // ソフトバンクまとめて支払い
        CarSoftbank::class => [
            'commit' => [
                'url' => 'SbSales.idPass',
                'addParams' => ['OrderID', 'Amount'],
                'term_days' => '60',
                'check' => null,
                'check_term' => true,
            ],
            'cancel' =>  [
                'url' => 'SbCancel.idPass',
                'addParams' => ['OrderID', 'CancelAmount'],
                'term_days' => '60',
                'check' => 'checkCancelSoftbank',
                'check_term' => true,
            ],
            'status' =>  [
                'url' => 'SearchTradeMulti.idPass',
                'addParams' => ['PayType'],
                'addData' => 'addStatusData',
            ],
        ],
        // 楽天ペイ
        RakutenPay::class => [
            'commit' => [
                'url' => 'RakutenIdSales.idPass',
                'addParams' => ['OrderID'],
                'term_days' => '',
                'check' => 'checkCommitRakutenPay',
                'check_term' => false,
            ],
            'cancel' =>  [
                'url' => 'RakutenIdCancel.idPass',
                'addParams' => ['OrderID'],
                'term_days' => '',
                'check' => 'checkCancelRakutenPay',
                'check_term' => false,
            ],
            'change' =>  [
                'url' => 'RakutenIdChange.idPass',
                'addParams' => ['OrderID', 'Amount', 'Tax'],
                'term_days' => '',
                'check' => 'checkChangeRakutenPay',
                'check_term' => false,
            ],
            'status' =>  [
                'url' => 'SearchTradeMulti.idPass',
                'addParams' => ['PayType'],
                'addData' => 'addStatusData',
            ],
        ],
    ];

    /**
     * 売上確定(実売上)を実行します
     *
     * @param Order $Order 注文
     * @return boolean 処理結果
     */
    public function doCommitOrder(Order $Order)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::doCommitOrder start.');

        $target_term_days = '';
        $prefix = "gmo_payment_gateway.";
        $action = trans($prefix . 'admin.order_edit.button.commit');
        $url = $this->GmoConfig->getServerUrl();

        $paramNames = [
            'ShopID',
            'ShopPass',
            'AccessID',
            'AccessPass',
        ];

        // 処理方法が定義されているかを確認
        $methodClass = $Order->getPayment()->getMethodClass();
        if (!isset(self::arrFunction[$methodClass]) ||
            !isset(self::arrFunction[$methodClass]['commit'])) {
            $msg = trans($prefix . 'admin.order_edit.action_error1',
                         ['%action%' => $action]);
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        $commit = self::arrFunction[$methodClass]['commit'];

        $url .= $commit['url'];
        $paramNames = array_merge($paramNames, $commit['addParams']);
        $target_term_days = $commit['term_days'];

        // チェック処理が定義されていれば実施
        if (!empty($func = $commit['check'])) {
            if (!$this->$func($Order, $target_term_days)) {
                return false;
            }
        }

        $sendData = [];

        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        // 共通の期限に関するチェック処理
        if ($commit['check_term']) {
            if (empty($paymentLogData['TranDate'])) {
                $msg = trans($prefix . 'admin.order_edit.action_error2');
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }

            sscanf($paymentLogData['TranDate'],
                   '%04d%02d%02d%02d%02d%02d',
                   $year, $month, $day, $hour, $min, $sec);
            $target_time = strtotime
                ('+' . $target_term_days . ' days',
                 mktime($hour, $min, $sec, $month, $day, $year));

            if ($target_time < time()) {
                $msg = trans($prefix . 'admin.order_edit.action_error3',
                             ['%target_term_days%' => $target_term_days]);
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }
        }

        if (array_search('JobCd', $paramNames) !== false) {
            $sendData['JobCd'] = 'SALES';
        }
        if (array_search('OrderID', $paramNames) !== false) {
            $sendData['OrderID'] = $paymentLogData['OrderID'];
        }

        $data = $this->getIfSendData($paramNames, $sendData, $Order);

        $ret = $this->sendRequest($url, $data);

        $error = $this->getError();
        if (!empty($error)) {
            return false;
        }

        $results = $this->getResults();

        $const = $this->eccubeConfig;

        $action_status = $prefix . 'action_status.exec_success';
        $results['action_status'] = $const[$action_status];
        $pay_status = $prefix . 'pay_status.commit';
        $results['pay_status'] = $const[$pay_status];

        $GmoOrderPayment->setPaymentLogData($results, false, $Order);

        $this->entityManager->persist($Order);
        $this->entityManager->persist($GmoOrderPayment);
        $this->entityManager->flush();

        PaymentUtil::logInfo('PaymentHelperAdmin::doCommitOrder end.');

        return true;
    }

    /**
     * 取消(返品)を実行します
     *
     * @param Order $Order 注文
     * @return boolean 処理結果
     */
    public function doCancelOrder(Order $Order)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::doCancelOrder start.');

        $target_term_days = '';
        $prefix = "gmo_payment_gateway.";
        $action = trans($prefix . 'admin.order_edit.button.cancel');
        $url = $this->GmoConfig->getServerUrl();

        $paramNames = [
            'ShopID',
            'ShopPass',
            'AccessID',
            'AccessPass',
        ];

        // 処理方法が定義されているかを確認
        $methodClass = $Order->getPayment()->getMethodClass();
        if (!isset(self::arrFunction[$methodClass]) ||
            !isset(self::arrFunction[$methodClass]['cancel'])) {
            $msg = trans($prefix . 'admin.order_edit.action_error1',
                         ['%action%' => $action]);
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        $cancel = self::arrFunction[$methodClass]['cancel'];

        $url .= $cancel['url'];
        $paramNames = array_merge($paramNames, $cancel['addParams']);
        $target_term_days = $cancel['term_days'];

        // チェック処理が定義されていれば実施
        if (!empty($func = $cancel['check'])) {
            if (!$this->$func($Order, $target_term_days)) {
                return false;
            }
        }

        $sendData = [];

        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        // 共通の期限に関するチェック処理
        if ($cancel['check_term']) {
            if (empty($paymentLogData['TranDate'])) {
                $msg = trans($prefix . 'admin.order_edit.action_error2');
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }

            sscanf($paymentLogData['TranDate'],
                   '%04d%02d%02d%02d%02d%02d',
                   $year, $month, $day, $hour, $min, $sec);

            if (date('Ymd') == sprintf('%04d%02d%02d', $year, $month, $day)) {
                if (array_search('JobCd', $paramNames) !== false) {
                    $sendData['JobCd'] = 'VOID';
                }
            } else if (date('Ym') == sprintf('%04d%02d', $year, $month)) {
                if (array_search('JobCd', $paramNames) !== false) {
                    $sendData['JobCd'] = 'RETURN';
                }
            } else {
                $target_time = strtotime
                    ('+' . $target_term_days . ' days',
                     mktime($hour, $min, $sec, $month, $day, $year));

                if ($target_time < time()) {
                    $msg = trans($prefix . 'admin.order_edit.action_error3',
                                 ['%target_term_days%' => $target_term_days]);
                    PaymentUtil::logError($msg);
                    $this->setError($msg);
                    return false;
                }

                if (array_search('JobCd', $paramNames) !== false) {
                    $sendData['JobCd'] = 'RETURNX';
                    if (isset($paymentLogData['Status']) &&
                        $paymentLogData['Status'] == 'AUTH') {
                        $sendData['JobCd'] = 'RETURN';
                    }
                }
            }
        }

        $const = $this->eccubeConfig;

        if (array_search('JobCd', $paramNames) !== false) {
            $pay_status = $prefix . 'pay_status.auth';
            if ($sendData['JobCd'] == 'RETURNX' &&
                $paymentLogData['pay_status'] == $const[$pay_status]) {
                $sendData['JobCd'] = 'RETURN';
            }
        }
        if (array_search('OrderID', $paramNames) !== false) {
            $sendData['OrderID'] = $paymentLogData['OrderID'];
        }

        $data = $this->getIfSendData($paramNames, $sendData, $Order);

        $ret = $this->sendRequest($url, $data);

        $error = $this->getError();
        if (!empty($error)) {
            return false;
        }

        $results = $this->getResults();

        if (array_search('JobCd', $paramNames) !== false) {
            $results['JobCd'] = $sendData['JobCd'];
        }

        $action_status = $prefix . 'action_status.exec_success';
        $results['action_status'] = $const[$action_status];

        if (array_search('JobCd', $paramNames) === false &&
            empty($sendData['JobCd'])) {
            $sendData['JobCd'] = 'CANCEL';
        }

        $pay_status = $prefix . 'pay_status.' . strtolower($sendData['JobCd']);
        $results['pay_status'] = $const[$pay_status];

        $GmoOrderPayment->setPaymentLogData($results, false, $Order);

        $this->entityManager->persist($Order);
        $this->entityManager->persist($GmoOrderPayment);
        $this->entityManager->flush();

        PaymentUtil::logInfo('PaymentHelperAdmin::doCancelOrder end.');

        return true;
    }

    /**
     * 決済金額変更を実行します
     *
     * @param Order $Order 注文
     * @return boolean 処理結果
     */
    public function doChangeOrder(Order $Order)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::doChangeOrder start.');

        $target_term_days = '';
        $prefix = "gmo_payment_gateway.";
        $action = trans($prefix . 'admin.order_edit.button.change');
        $url = $this->GmoConfig->getServerUrl();

        $paramNames = [
            'ShopID',
            'ShopPass',
            'AccessID',
            'AccessPass',
        ];

        // 処理方法が定義されているかを確認
        $methodClass = $Order->getPayment()->getMethodClass();
        if (!isset(self::arrFunction[$methodClass]) ||
            !isset(self::arrFunction[$methodClass]['change'])) {
            $msg = trans($prefix . 'admin.order_edit.action_error1',
                         ['%action%' => $action]);
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        $change = self::arrFunction[$methodClass]['change'];

        $url .= $change['url'];
        $paramNames = array_merge($paramNames, $change['addParams']);
        $target_term_days = $change['term_days'];

        // チェック処理が定義されていれば実施
        if (!empty($func = $change['check'])) {
            if (!$this->$func($Order, $target_term_days)) {
                return false;
            }
        }

        $sendData = [];

        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        // 共通の期限に関するチェック処理
        if ($change['check_term']) {
            if (empty($paymentLogData['TranDate'])) {
                $msg = trans($prefix . 'admin.order_edit.action_error2');
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }

            sscanf($paymentLogData['TranDate'],
                   '%04d%02d%02d%02d%02d%02d',
                   $year, $month, $day, $hour, $min, $sec);
            $target_time = strtotime
                ('+' . $target_term_days . ' days',
                 mktime($hour, $min, $sec, $month, $day, $year));

            if ($target_time < time()) {
                $msg = trans($prefix . 'admin.order_edit.action_error3',
                             ['%target_term_days%' => $target_term_days]);
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }
        }

        $GmoPaymentMethod = $Order->getGmoPaymentMethod();
        $gmoPaymentMethodConfig = $GmoPaymentMethod->getPaymentMethodConfig();

        if (array_search('JobCd', $paramNames) !== false) {
            $sendData['JobCd'] = $gmoPaymentMethodConfig['JobCd'];
        }
        if (array_search('OrderID', $paramNames) !== false) {
            $sendData['OrderID'] = $paymentLogData['OrderID'];
        }

        $GmoOrderPayment->setPaymentLogData
            (['no_update_status_flg' => '1'], false);

        $data = $this->getIfSendData($paramNames, $sendData, $Order);

        $ret = $this->sendRequest($url, $data);

        $error = $this->getError();
        if (!empty($error)) {
            return false;
        }

        $results = $this->getResults();

        $const = $this->eccubeConfig;

        $action_status = $prefix . 'action_status.exec_success';
        $results['action_status'] = $const[$action_status];
        $pay_status = $prefix . 'pay_status.auth';
        if (array_search('JobCd', $paramNames) !== false) {
            $pay_status  = $prefix . 'pay_status.';
            $pay_status .= strtolower($sendData['JobCd']);
        }
        $results['pay_status'] = $const[$pay_status];

        if (array_search('JobCd', $paramNames) !== false) {
            $results['JobCd'] = $sendData['JobCd'];
        }

        if (empty($results['Amount'])) {
            $results['Amount'] = $Order->getDecPaymentTotal();
        }

        $GmoOrderPayment->setPaymentLogData($results, false, $Order);

        $this->entityManager->persist($Order);
        $this->entityManager->persist($GmoOrderPayment);
        $this->entityManager->flush();

        PaymentUtil::logInfo('PaymentHelperAdmin::doChangeOrder end.');

        return true;
    }

    /**
     * 再オーソリを実行します
     *
     * @param Order $Order 注文
     * @return boolean 処理結果
     */
    public function doReauthOrder(Order $Order)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::doReauthOrder start.');

        $prefix = "gmo_payment_gateway.";
        $action = trans($prefix . 'admin.order_edit.button.reauth');

        $methodClass = $Order->getPayment()->getMethodClass();
        if ($methodClass !== CreditCard::class) {
            $msg = trans($prefix . 'admin.order_edit.action_error4');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        $const = $this->eccubeConfig;

        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        $pay_status_void = $prefix . 'pay_status.void';
        $pay_status_return = $prefix . 'pay_status.return';
        $pay_status_returnx = $prefix . 'pay_status.returnx';
        if ($paymentLogData['pay_status'] != $const[$pay_status_void] &&
            $paymentLogData['pay_status'] != $const[$pay_status_return] &&
            $paymentLogData['pay_status'] != $const[$pay_status_returnx]) {
            $msg = trans($prefix . 'admin.order_edit.action_error5');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        $url = $this->GmoConfig->getServerUrl() . 'AlterTran.idPass';

        $paramNames = [
            'ShopID',
            'ShopPass',
            'AccessID',
            'AccessPass',
            'JobCd',
            'Amount',
            'Method',
            'PayTimes',
        ];

        $sendData = [];

        $GmoPaymentMethod = $Order->getGmoPaymentMethod();
        $gmoPaymentMethodConfig = $GmoPaymentMethod->getPaymentMethodConfig();

        $sendData['JobCd'] = $gmoPaymentMethodConfig['JobCd'];
        $sendData['Method'] = $paymentLogData['Method'];
        $sendData['PayTimes'] = $paymentLogData['PayTimes'];

        $GmoOrderPayment->setPaymentLogData
            (['no_update_status_flg' => '1'], false);

        $data = $this->getIfSendData($paramNames, $sendData, $Order);

        $ret = $this->sendRequest($url, $data);

        $error = $this->getError();
        if (!empty($error)) {
            return false;
        }

        $results = $this->getResults();

        $action_status = $prefix . 'action_status.exec_success';
        $results['action_status'] = $const[$action_status];

        $jobcd = $gmoPaymentMethodConfig['JobCd'];
        $pay_status = $prefix . 'pay_status.' . strtolower($jobcd);
        $results['pay_status'] = $const[$pay_status];
        $results['JobCd'] = $jobcd;

        if (!isset($results['Amount'])) {
            $results['Amount'] = $Order->getDecPaymentTotal();
        }

        $GmoOrderPayment->setPaymentLogData($results, false, $Order);

        $this->entityManager->persist($Order);
        $this->entityManager->persist($GmoOrderPayment);
        $this->entityManager->flush();

        PaymentUtil::logInfo('PaymentHelperAdmin::doReauthOrder end.');

        return true;
    }

    /**
     * 決済状態確認・反映を実行します
     *
     * @param Order $Order 注文
     * @return boolean 処理結果
     */
    public function doStatusOrder(Order $Order)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::doStatusOrder start.');

        $prefix = "gmo_payment_gateway.";
        $action = trans($prefix . 'admin.order_edit.button.status');
        $url = $this->GmoConfig->getServerUrl();

        $paramNames = [
            'ShopID',
            'ShopPass',
            'OrderID',
        ];

        $sendData = [];

        // 処理方法が定義されているかを確認
        $methodClass = $Order->getPayment()->getMethodClass();
        if (!isset(self::arrFunction[$methodClass]) ||
            !isset(self::arrFunction[$methodClass]['status'])) {
            $msg = trans($prefix . 'admin.order_edit.action_error1',
                         ['%action%' => $action]);
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        $status = self::arrFunction[$methodClass]['status'];

        $url .= $status['url'];
        $paramNames = array_merge($paramNames, $status['addParams']);

        // 追加データ処理が定義されていれば実施
        if (!empty($func = $status['addData'])) {
            $sendData = $this->$func($Order, $sendData, $methodClass);
        }

        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        $sendData['OrderID'] = $paymentLogData['OrderID'];

        $data = $this->getIfSendData($paramNames, $sendData, $Order);

        $ret = $this->sendRequest($url, $data);
        
        $error = $this->getError();
        if (!empty($error)) {
            return false;
        }
        
        $results = $this->getResults();

        $const = $this->eccubeConfig;

        $action_status = $prefix . 'action_status.exec_success';
        $results['action_status'] = $const[$action_status];

        $pay_status = $prefix . 'pay_status.' . strtolower($results['Status']);
        if ($const->has($pay_status)) {
            $results['pay_status'] = $const[$pay_status];
        } else if (!is_null($results['Status'])) {
            $pay_status = $prefix . 'pay_status.except';
            $results['pay_status'] = $const[$pay_status];
        }

        $GmoOrderPayment->setPaymentLogData($results, false, $Order);

        $this->entityManager->persist($Order);
        $this->entityManager->persist($GmoOrderPayment);
        $this->entityManager->flush();

        PaymentUtil::logInfo('PaymentHelperAdmin::doStatusOrder end.');

        return true;
    }

    /**
     * 決済方法クラス名を GMO PayType 型に変換して取得する
     *
     * @param string $class 決済方法クラス名
     * @return integer PayType
     */
    protected function getPayType($class)
    {
        $result = -1;
        $prefix = 'gmo_payment_gateway.pay_type.';
        $const = $this->eccubeConfig;

        switch ($class) {
        case CreditCard::class:
            $result = $const[$prefix . 'credit'];
            break;

        case Cvs::class:
            $result = $const[$prefix . 'cvs'];
            break;

        case PayEasyAtm::class:
        case PayEasyNet::class:
            $result = $const[$prefix . 'payeasy'];
            break;

        case CarAu::class:
            $result = $const[$prefix . 'au'];
            break;

        case CarDocomo::class:
            $result = $const[$prefix . 'docomo'];
            break;

        case CarSoftbank::class:
            $result = $const[$prefix . 'softbank'];
            break;

        case RakutenPay::class:
            $result = $const[$prefix . 'rakuten_pay'];
            break;

        default:
            break;
        }

        return $result;
    }

    /**
     * 楽天ペイの注文状況を確認する
     *
     * @param Order $Order 注文
     * @param boolean $pay_status_auth_check 決済状態AUTHをチェックするどうか
     * @return boolean true:続行可、false:続行不可
     */
    protected function checkRakutenPay
        (Order $Order, $pay_status_auth_check = false)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::checkRakutenPay start.');

        $prefix = "gmo_payment_gateway.";
        $const = $this->eccubeConfig;
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();
        $paymentStatus = $GmoOrderPayment->getMemo04();

        // Check various conditions for rakuten pay
        if ($pay_status_auth_check &&
            $paymentStatus != $const[$prefix . 'pay_status.auth']) {
            $msg = trans($prefix . 'admin.order_edit.action_error6');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        // Check if the transaction is registered as temporary sale
        if ($paymentLogData['JobCd'] !== 'AUTH') {
            $msg = trans($prefix . 'admin.order_edit.action_error7');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        // Base on the order's status, validate the datetime appropriately
        if ($paymentStatus == $const[$prefix . 'pay_status.auth']) {
            // The order is not finalized yet

            // Extract the OrderDate
            if (empty($paymentLogData['OrderDate'])) {
                $msg = trans($prefix . 'admin.order_edit.action_error8');
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }

            sscanf($paymentLogData['OrderDate'],
                   '%04d%02d%02d%02d%02d%02d',
                   $year, $month, $day, $hour, $min, $sec);

            if (strtotime('last day of fifth month',
                          mktime(23, 59, 59, $month, $day, $year)) < time()) {
                $msg = trans($prefix . 'admin.order_edit.action_error9');
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }
        } else if ($paymentStatus == $const[$prefix . 'pay_status.commit'] ||
                   $paymentStatus == $const[$prefix . 'pay_status.sales'] ||
                   $paymentStatus == $const[$prefix . 'pay_status.capture']) {
            // The order is finalized

            // Extract the CompletionDate
            if (empty($paymentLogData['CompletionDate'])) {
                $msg = trans($prefix . 'admin.order_edit.action_error10');
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }

            sscanf($paymentLogData['CompletionDate'],
                   '%04d%02d%02d', $year, $month, $day);

            if (strtotime('last day of next month',
                          mktime(23, 59, 59, $month, $day, $year)) < time()) {
                $msg = trans($prefix . 'admin.order_edit.action_error11');
                PaymentUtil::logError($msg);
                $this->setError($msg);
                return false;
            }
        } else {
            $msg = trans($prefix . 'admin.order_edit.action_error6');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        PaymentUtil::logInfo('PaymentHelperAdmin::checkRakutenPay end.');

        return true;
    }

    /**
     * 売上確定(実売上)前のデータ検証
     *
     * @param Order $Order 注文
     * @param string $term_days 有効期日
     * @return boolean true:OK, false:NG
     */
    protected function checkCommitRakutenPay(Order $Order, &$term_days)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::' .
                             'checkCommitRakutenPay start.');

        $r = $this->checkRakutenPay($Order, true);

        PaymentUtil::logInfo('PaymentHelperAdmin::' .
                             'checkCommitRakutenPay end.');

        return $r;
    }

    /**
     * 取消(返品)前のデータ検証
     *
     * @param Order $Order 注文
     * @param string $term_days 有効期日
     * @return boolean true:OK, false:NG
     */
    protected function checkCancelAu(Order $Order, &$term_days)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::checkCancelAu start.');

        $prefix = "gmo_payment_gateway.";
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        // auかんたん決済(WebMoney)の場合はキャンセル不可
        if ($paymentLogData['PayMethod'] == '03') {
            $msg = trans($prefix . 'admin.order_edit.action_error12');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        if ($paymentLogData['Status'] == 'AUTH') {
            // 仮売上後90日以内
            $term_days = '90';
        } else {
            sscanf($paymentLogData['TranDate'],
                   '%04d%02d%02d%02d%02d%02d',
                   $year, $month, $day, $hour, $min, $sec);
            $target = mktime($hour, $min, $sec, $month, $day, $year);
            if (date('Ym') != date('Ym', $target)) {
                // 翌々月末日 (３ヶ月先の１日未満）
                $limit_time = mktime(0, 0, 0, $month + 3, 1, $year);
                if ($limit_time < time()) {
                    $msg = trans($prefix . 'admin.order_edit.action_error13');
                    PaymentUtil::logError($msg);
                    $this->setError($msg);
                    return false;
                }
            }
        }

        PaymentUtil::logInfo('PaymentHelperAdmin::checkCancelAu end.');

        return true;
    }

    /**
     * 取消(返品)前のデータ検証
     *
     * @param Order $Order 注文
     * @param string $term_days 有効期日
     * @return boolean true:OK, false:NG
     */
    protected function checkCancelDocomo(Order $Order, &$term_days)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::checkCancelDocomo start.');

        $prefix = "gmo_payment_gateway.";
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        if (empty($paymentLogData['TranDate'])) {
            $msg = trans($prefix . 'admin.order_edit.action_error2');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        sscanf($paymentLogData['TranDate'],
               '%04d%02d%02d%02d%02d%02d',
               $year, $month, $day, $hour, $min, $sec);
        $cancel_limit = mktime(20, 0, 0, $month + 3, 0, $year);

        if (time() > $cancel_limit) {
            $msg = trans($prefix . 'admin.order_edit.action_error14');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        PaymentUtil::logInfo('PaymentHelperAdmin::checkCancelDocomo end.');

        return true;
    }

    /**
     * 取消(返品)前のデータ検証
     *
     * @param Order $Order 注文
     * @param string $term_days 有効期日
     * @return boolean true:OK, false:NG
     */
    protected function checkCancelSoftbank(Order $Order, &$term_days)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::checkCancelSoftbank start.');

        $prefix = "gmo_payment_gateway.";
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();

        if ($paymentLogData['Status'] != 'SALES') {
            return true;
        }

        sscanf($paymentLogData['TranDate'],
               '%04d%02d%02d%02d%02d%02d',
               $year, $month, $day, $hour, $min, $sec);

        if ($day > 0 && $day <= 10) {
            $target = mktime($hour, $min, $sec, $month, 13, $year);
        } else if ($day > 10 && $day <= 20) {
            $target = mktime($hour, $min, $sec, $month, 23, $year);
        } else {
            $target = mktime($hour, $min, $sec, $month + 1, 2, $year);
        }

        if ($target < time()) {
            $msg = trans($prefix . 'admin.order_edit.action_error15');
            PaymentUtil::logError($msg);
            $this->setError($msg);
            return false;
        }

        PaymentUtil::logInfo('PaymentHelperAdmin::checkCancelSoftbank end.');

        return true;
    }

    /**
     * 取消(返品)前のデータ検証
     *
     * @param Order $Order 注文
     * @param string $term_days 有効期日
     * @return boolean true:OK, false:NG
     */
    protected function checkCancelRakutenPay(Order $Order, &$term_days)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::' .
                             'checkCancelRakutenPay start.');

        $r = $this->checkRakutenPay($Order);

        PaymentUtil::logInfo('PaymentHelperAdmin::' .
                             'checkCancelRakutenPay end.');

        return $r;
    }

    /**
     * 決済金額変更前のデータ検証
     *
     * @param Order $Order 注文
     * @param string $term_days 有効期日
     * @return boolean true:OK, false:NG
     */
    protected function checkChangeRakutenPay(Order $Order, &$term_days)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::' .
                             'checkChangeRakutenPay start.');

        $r = $this->checkRakutenPay($Order);

        PaymentUtil::logInfo('PaymentHelperAdmin::' .
                             'checkChangeRakutenPay end.');

        return $r;
    }

    /**
     * 決済状態確認・反映時の追加データ
     *
     * @param Order $Order 注文
     * @param array $sendData 送信データ配列
     * @param string $class 決済クラス名
     * @return array 送信データ配列
     */
    protected function addStatusData(Order $Order, array $sendData, $class)
    {
        PaymentUtil::logInfo('PaymentHelperAdmin::addStatusData start.');

        $sendData['PayType'] = $this->getPayType($class);

        PaymentUtil::logInfo('PaymentHelperAdmin::addStatusData end.');

        return $sendData;
    }
}
