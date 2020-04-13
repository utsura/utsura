<?php

namespace Plugin\YamatoPayment4\Service;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Payment;
use Eccube\Repository\PaymentRepository;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Plugin\YamatoPayment4\Entity\YamatoPaymentMethod;
use Plugin\YamatoPayment4\Util\PaymentUtil;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Service\Method\Deferred;
use Plugin\YamatoPayment4\Service\Method\DeferredSms;

class YamatoConfigService
{
    public function __construct(
        ContainerInterface $container,
        EccubeConfig $eccubeConfig,
        PaymentRepository $paymentRepository,
        PaymentUtil $paymentUtil
    ){
        $this->container = $container;
        $this->eccubeConfig = $eccubeConfig;
        $this->paymentRepository = $paymentRepository;
        $this->paymentUtil = $paymentUtil;
    }

    /**
     * 対象支払方法を登録、及び 表示にする
     * 
     * @param $PaymentIds 有効にする支払方法のID配列
     * @return array $Payments 有効または表示状態にした支払方法
     */
    public function savePayment($PaymentTypeIds) 
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        $Payment = $this->paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() : 1;

        foreach ($PaymentTypeIds as $PaymentTypeId) {
            $arrPaymentConfig = $this->getPaymentConfig($PaymentTypeId);

            if($Payment = $this->paymentRepository->findOneBy(['method_class' => $arrPaymentConfig['method_class']])) {
                $Payment->setVisible(true);
            } else {
                $sortNo ++;
                $Payment = new Payment();

                $Payment->setMethod($arrPaymentConfig['method']);
                $Payment->setRuleMin(1);
                $Payment->setRuleMax($arrPaymentConfig['rule_max']);
                $Payment->setCharge($arrPaymentConfig['charge']);
                $Payment->setSortNo($sortNo);
                $Payment->setVisible(true);
                $Payment->setMethodClass($arrPaymentConfig['method_class']);
            }

            $Payments[] = $Payment;

            $entityManager->persist($Payment);
        }
        $entityManager->flush();

        return $Payments;
    }

    /**
     * 対象決済方法とその設定をプラグイン独自で管理しているテーブルに登録する
     * 
     * @param array $Payments 対象決済方法配列
     */
    public function sevePaymentMethod($Payments) {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $PaymentMethodRepository = $entityManager->getRepository(YamatoPaymentMethod::class);

        foreach ($Payments as $Payment) {
            $PaymentMethod = $PaymentMethodRepository->findOneBy(['Payment' => $Payment]);
            if(!$PaymentMethod) {
                $PaymentMethod = new YamatoPaymentMethod();
                $this->setPaymentMethodProperty($PaymentMethod, $Payment);
                $entityManager->persist($PaymentMethod);
            }
        }
        $entityManager->flush();
    }

    /**
     * 有効にしない決済方法を非表示にする
     * 
     * @param $PaymentIds 有効にする支払方法のID配列
     */
    public function undispPayment($PaymentIds) {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        $arrPaymentMaster = $this->eccubeConfig['YAMATO_PAYID'];
        // 連想配列→配列に置換
        $arrPaymentMaster = array_values($arrPaymentMaster);
        $arrDiff = array_diff($arrPaymentMaster, $PaymentIds);
        foreach ($arrDiff as $PaymentTypeId) {
            if ($PaymentTypeId == $this->eccubeConfig['YAMATO_PAYID']['CREDIT']) {
                $method_class = Credit::class;
            } elseif ($PaymentTypeId == $this->eccubeConfig['YAMATO_PAYID']['DEFERRED']) {
                $method_class = Deferred::class;
            } elseif ($PaymentTypeId == $this->eccubeConfig['YAMATO_PAYID']['SMS_DEFERRED']) {
                $method_class = DeferredSms::class;
            }
            $Payment = $this->paymentRepository->findOneBy(['method_class' => $method_class]);
            if ($Payment !== null) {
                $Payment->setVisible(false);
                $entityManager->persist($Payment);
                $entityManager->flush($Payment);
            }
        }
    }

    /**
     * @param $PaymentTypeId 支払方法のID
     * @param $arrRet 支払方法毎に仕様として決まっている設定
     */
    protected function getPaymentConfig($PaymentTypeId) {
        $arrRet = [];
        if($PaymentTypeId == $this->eccubeConfig['YAMATO_PAYID']['CREDIT']) {
            $arrRet['method'] = 'クレジットカード';
            $arrRet['method_class'] = Credit::class;
            $arrRet['charge']   = 0;
            $arrRet['rule_max'] = 300000;
        }

        if($PaymentTypeId == $this->eccubeConfig['YAMATO_PAYID']['DEFERRED']) {
            $arrRet['method'] = 'クロネコ代金後払い(請求書郵送)';
            $arrRet['method_class'] = Deferred::class;
            $arrRet['charge']   = 190;
            $arrRet['rule_max'] = 50000;
        }

        if($PaymentTypeId == $this->eccubeConfig['YAMATO_PAYID']['SMS_DEFERRED']) {
            $arrRet['method'] = 'クロネコ代金後払い(請求書SMS送付)';
            $arrRet['method_class'] = DeferredSms::class;
            $arrRet['charge']   = 160;
            $arrRet['rule_max'] = 50000;
        }

        return $arrRet;
    }

    /**
     * 支払方法のプロパティをセットする
     * 
     * @param YamatoPaymentMethod $PaymentMethod プラグインの支払方法
     * @param Payment $Payment 支払方法
     * @return void
     */
    protected function setPaymentMethodProperty($PaymentMethod, $Payment) {
        switch ($Payment->getMethodClass()) {
            case Credit::class:
                $PaymentMethod->setPayment($Payment);
                $PaymentMethod->setPaymentMethod($Payment->getMethod());
                $PaymentMethod->setMemo03($this->eccubeConfig['YAMATO_PAYID']['CREDIT']);
                $memo05 = [
                    "pay_way" => [1],
                    "TdFlag" => 0,
                    "order_mail_title" => "お支払いについて",
                    "order_mail_body" => "クレジット決済完了案内本文",
                    "autoRegist" => 1
                ];
                $PaymentMethod->setMemo05($memo05);    
                break;
            
            case Deferred::class:
                $PaymentMethod->setPayment($Payment);
                $PaymentMethod->setPaymentMethod($Payment->getMethod());
                $PaymentMethod->setMemo03($this->eccubeConfig['YAMATO_PAYID']['DEFERRED']);
                break;
            
            case DeferredSms::class:
                $PaymentMethod->setPayment($Payment);
                $PaymentMethod->setPaymentMethod($Payment->getMethod());
                $PaymentMethod->setMemo03($this->eccubeConfig['YAMATO_PAYID']['SMS_DEFERRED']);
                break;
        
            default:
                break;
        }
    }
}