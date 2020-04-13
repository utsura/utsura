<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Entity\Customer;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Service\MailService;
use Eccube\Service\PluginService;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\GmoPaymentGateway4\Entity\GmoConfig;
use Plugin\GmoPaymentGateway4\Entity\GmoOrderPayment;
use Plugin\GmoPaymentGateway4\Repository\GmoConfigRepository;
use Plugin\GmoPaymentGateway4\Repository\GmoOrderPaymentRepository;
use Plugin\GmoPaymentGateway4\Repository\GmoPaymentMethodRepository;
use Plugin\GmoPaymentGateway4\Repository\GmoMemberRepository;
use Plugin\GmoPaymentGateway4\Service\Method\Cvs;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyNet;
use Plugin\GmoPaymentGateway4\Util\ErrorUtil;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 決済共通処理を行うクラス
 */
abstract class PaymentHelper
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var Plugin\GmoPaymentGateway4\Repository\GmoConfigRepository
     */
    protected $gmoConfigRepository;

    /**
     * @var Plugin\GmoPaymentGateway4\Repository\GmoOrderPaymentRepository
     */
    protected $gmoOrderPaymentRepository;

    /**
     * @var Plugin\GmoPaymentGateway4\Repository\GmoPaymentMethodRepository
     */
    protected $gmoPaymentMethodRepository;

    /**
     * @var Plugin\GmoPaymentGateway4\Repository\GmoMemberRepository
     */
    protected $gmoMemberRepository;

    /**
     * プラグインコンフィグ(composer.json)
     * @var array
     */
    protected $pluginConfig;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var Plugin\GmoPaymentGateway4\Entity\GmoConfig
     */
    protected $GmoConfig;

    /**
     * @var array Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod#Memo05
     */
    protected $gmoPaymentMethodConfig;

    /**
     * @var Plugin\GmoPaymentGateway4\Util\ErrorUtil
     */
    protected $errorUtil;

    /**
     * @var array エラー配列
     */
    protected $error = [];

    /**
     * @var array GMO-PG インタフェース送信結果配列
     */
    protected $results = null;

    /**
     * PaymentHelper constructor.
     *
     * @param ContainerInterface $container
     * @param \Swift_Mailer $mailer
     * @param \Twig_Environment $twig
     * @param EccubeConfig $eccubeConfig
     * @param EntityManagerInterface $entityManager
     * @param PluginService $pluginService
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param BaseInfoRepository $baseInfoRepository
     * @param CustomerRepository $customerRepository
     * @param PaymentRepository $paymentRepository
     * @param MailService $mailService
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param GmoConfigRepository $gmoConfigRepository
     * @param GmoMemberRepository $gmoMemberRepository
     * @param ErrorUtil $errorUtil
     */
    public function __construct(
        ContainerInterface $container,
        \Swift_Mailer $mailer,
        \Twig_Environment $twig,
        EccubeConfig $eccubeConfig,
        EntityManagerInterface $entityManager,
        PluginService $pluginService,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        BaseInfoRepository $baseInfoRepository,
        CustomerRepository $customerRepository,
        PaymentRepository $paymentRepository,
        MailService $mailService,
        PurchaseFlow $shoppingPurchaseFlow,
        GmoConfigRepository $gmoConfigRepository,
        GmoOrderPaymentRepository $gmoOrderPaymentRepository,
        GmoPaymentMethodRepository $gmoPaymentMethodRepository,
        GmoMemberRepository $gmoMemberRepository,
        ErrorUtil $errorUtil
    ) {
        $this->container = $container;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->eccubeConfig = $eccubeConfig;
        $this->entityManager = $entityManager;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->customerRepository = $customerRepository;
        $this->paymentRepository = $paymentRepository;
        $this->mailService = $mailService;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->gmoConfigRepository = $gmoConfigRepository;
        $this->gmoOrderPaymentRepository = $gmoOrderPaymentRepository;
        $this->gmoPaymentMethodRepository = $gmoPaymentMethodRepository;
        $this->gmoMemberRepository = $gmoMemberRepository;
        $this->errorUtil = $errorUtil;

        // プラグインコンフィグ(composer.json)を取得
        $dir = $pluginService->calcPluginDir(PaymentUtil::PLUGIN_CODE);
        $this->pluginConfig = $pluginService->readConfig($dir);
        // 店舗設定を取得
        $this->BaseInfo = $baseInfoRepository->get();
        // GMO-PG プラグイン設定を取得
        $this->GmoConfig = $this->gmoConfigRepository->get();
        // GMO-PG 支払方法別の設定を取得
        $this->gmoPaymentMethodConfig = $this->getGmoPaymentMethodConfig();
    }

    /**
     * GMO-PG 支払方法別のクラス名称を取得する（継承先の支払別Helperで実装）
     *
     * @return string 支払方法別のクラス名称
     */
    abstract protected function getGmoPaymentMethodClass();

    /**
     * GMO-PG 支払方法別の設定を取得する
     *
     * @return array 支払方法別の設定配列
     */
    protected function getGmoPaymentMethodConfig()
    {
        $className = $this->getGmoPaymentMethodClass();
        if (is_null($className)) {
            return [];
        }

        return $this->gmoPaymentMethodRepository
            ->getGmoPaymentMethodConfig($className);
    }

    /**
     * 顧客に GMO-PG 情報を付加する
     *
     * @param Eccube\Entity\Customer $Customer
     * @return Eccube\Entity\Customer
     */
    public function prepareGmoInfoForCustomer(Customer $Customer = null)
    {
        if (is_null($Customer)) {
            return $Customer;
        }

        PaymentUtil::logInfo
            ('prepareGmoInfoForCustomer customer_id = ' . $Customer->getId());

        $GmoMember = $this->gmoMemberRepository
            ->findOneBy(['customer_id' => $Customer->getId()]);
        $Customer->setGmoMember($GmoMember);

        return $Customer;
    }

    /**
     * 注文に GMO-PG 情報を付加する
     *
     * @param Eccube\Entity\Order $Order
     * @return Eccube\Entity\Order
     */
    public function prepareGmoInfoForOrder(Order $Order)
    {
        if (is_null($Order)) {
            return $Order;
        }

        PaymentUtil::logInfo
            ('prepareGmoInfoForOrder order_id = ' . $Order->getId());

        // 注文の追加情報
        $GmoOrderPayment = $this->gmoOrderPaymentRepository
            ->findOneBy(['order_id' => $Order->getId()]);
        if (is_null($GmoOrderPayment)) {
            // なければ生成する
            $order_id = $Order->getId();
            $GmoOrderPayment = new GmoOrderPayment();
            $GmoOrderPayment->setOrderId($order_id);
            $this->entityManager->persist($GmoOrderPayment);
            $this->entityManager->flush($GmoOrderPayment);
            PaymentUtil::logInfo
                ("Create GmoOrderPayment order_id = " . $order_id);
        }
        $Order->setGmoOrderPayment($GmoOrderPayment);

        // 決済画面の入力値をセット
        $GmoPaymentInput = $GmoOrderPayment->getGmoPaymentInput();
        $Order->setGmoPaymentInput($GmoPaymentInput);
        PaymentUtil::logInfo($GmoPaymentInput->getArrayData(), [
            'Pass',
            'Token',
            'card_name'
        ]);

        // 支払方法の追加情報
        $Payment = $Order->getPayment();
        if (!is_null($Payment)) {
            $GmoPaymentMethod = $this->gmoPaymentMethodRepository
                ->findOneBy(['payment_id' => $Payment->getId()]);
            $Order->setGmoPaymentMethod($GmoPaymentMethod);
            $GmoOrderPayment->setMemo03($Payment->getMethodClass());
            $this->entityManager->persist($GmoOrderPayment);
            $this->entityManager->flush($GmoOrderPayment);
        }

        // 顧客の追加情報
        $this->prepareGmoInfoForCustomer($Order->getCustomer());

        return $Order;
    }

    /**
     * 支払方法の一致確認を行う
     *
     * @param Order $Order
     * @return boolean true: 一致、false: 不一致
     */
    public function isMatchPayment(Order $Order)
    {
        $methodClass = $this->getGmoPaymentMethodClass();

        $order_id = $Order->getId();
        $Payment = $Order->getPayment();
        if (!$Payment) {
            PaymentUtil::logError("order_id: " . $order_id . ", " .
                                  "Payment is null, " .
                                  "method_class: " . $methodClass);
            return false;
        }

        $payment_id = $Payment->getId();
        $method_class = $Payment->getMethodClass();

        PaymentUtil::logInfo
            ("order_id: " . $order_id . ", " .
             "payment_id: " . $payment_id . ", " .
             "method_class: " . $method_class . " | " . $methodClass);

        if ($method_class !== $methodClass) {
            PaymentUtil::logError(trans('gmo_payment_gateway.' .
                                        'shopping.com.mismatch.payment'));
            return false;
        }

        return true;
    }

    /**
     * GMO-PG インタフェース送信データを生成し取得する
     *
     * @param array $paramNames パラメータ名配列
     * @param array $sourceData ソースデータ配列
     * @param Order $Order 受注Entity
     * @param Customer $Customer 顧客Entity
     * @return array 送信データ配列
     */
    protected function getIfSendData
        (array $paramNames, array $sourceData,
         Order $Order = null, Customer $Customer = null)
    {
        $results = [];
        $paymentLogData = [];

        if (!is_null($Order)) {
            $GmoOrderPayment = $Order->getGmoOrderPayment();
            $paymentLogData = $GmoOrderPayment->getPaymentLogData();
        }

        foreach ($paramNames as $paramName) {
            PaymentUtil::logInfo('getIfSendData::$paramName ' . $paramName);

            $function = "getIf" . $paramName;
            if (is_callable([$this, $function])) {
                $r = $this->$function($sourceData, $Order, $Customer);
                if ($r !== null) {
                    $results[$paramName] = $r;
                }
            } else {
                if (isset($sourceData[$paramName])) {
                    $results[$paramName] = $sourceData[$paramName];
                } elseif (isset($paymentLogData[$paramName])) {
                    $results[$paramName] = $paymentLogData[$paramName];
                } elseif (isset($this->gmoPaymentMethodConfig[$paramName])) {
                    $results[$paramName] =
                        $this->gmoPaymentMethodConfig[$paramName];
                } elseif (method_exists($this->GmoConfig,
                                        "get" . $paramName)) {
                    $method = "get" . $paramName;
                    $results[$paramName] = $this->GmoConfig->$method();
                } elseif (isset($this->results[0][$paramName])) {
                    $results[$paramName] = $this->results[0][$paramName];
                }
            }
        }

        return $results;
    }
        
    /**
     * ショップID
     */
    private function getIfShopID
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $this->GmoConfig->getShopId();
    }

    /**
     * ショップパスワード
     */
    private function getIfShopPass
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $this->GmoConfig->getShopPass();
    }

    /**
     * サイトID
     */
    private function getIfSiteID
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $this->GmoConfig->getSiteId();
    }

    /**
     * サイトパスワード
     */
    private function getIfSitePass
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $this->GmoConfig->getSitePass();
    }

    /**
     * 加盟店自由項目３
     *
     * ※この部分の表記などについて修正・削除等、一切の変更は絶対に
     * 行わないで下さい。問題発生時の調査や解決などに支障が出るため、
     * 変更された場合はサポート等が出来ない場合がございます。
     */
    private function getIfClientField3
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        // Get composer.json plugin version
        return 'EC-CUBE4(' . $this->pluginConfig['version'] . ')';
    }

    /**
     * キャンセル金額
     */
    private function getIfCancelAmount
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $Order->getDecPaymentTotal();
    }

    /**
     * 利用金額
     */
    private function getIfAmount
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $Order->getDecPaymentTotal();
    }

    /**
     * 初回課金利用金額
     */
    private function getIfFirstAmount
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $Order->getDecPaymentTotal();
    }

    /**
     * 3Dセキュア表示店舗名
     */
    private function getIfTdTenantName
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        $tenantName = $this->gmoPaymentMethodConfig['TdTenantName'];
        return PaymentUtil::convTdTenantName($tenantName);
    }

    /**
     * 有効期限
     */
    private function getIfExpire
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $sourceData['expire_year'] . $sourceData['expire_month'];
    }

    /**
     * 支払方法
     */
    private function getIfMethod
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        if (strpos($sourceData['Method'], '-') !== false) {
            list($id, $num) = explode('-', $sourceData['Method']);
            return $id;
        }

        return $sourceData['Method'];
    }

    /**
     * 支払回数
     */
    private function getIfPayTimes
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        if (isset($sourceData['PayTimes']) && $sourceData['PayTimes'] > 0) {
            return $sourceData['PayTimes'];
        }

        if (strpos($sourceData['Method'], '-') === false) {
            return null;
        }

        list($id, $num) = explode('-', $sourceData['Method']);
        if ($num <= 0) {
            return null;
        }

        return $num;
    }

    /**
     * セキュリティコード
     */
    private function getIfSecurityCode
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        if (isset($sourceData['security_code']) &&
            !empty($sourceData['security_code'])) {
            return $sourceData['security_code'];
        }

        return null;
    }

    /**
     * 加盟店自由項目返却フラグ
     */
    private function getIfClientFieldFlag
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return '1';
    }

    /**
     * 本人認証サービス利用フラグ
     */
    private function getIfTdFlag
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $this->gmoPaymentMethodConfig['TdFlag'];
    }

    /**
     * HTTP_ACCEPT
     */
    private function getIfHttpAccept
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            return null;
        }

        return $_SERVER['HTTP_ACCEPT'];
    }

    /**
     * HTTP_USER_AGENT
     */
    private function getIfHttpUserAgent
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return null;
        }

        return $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * 使用端末情報
     */
    private function getIfDeviceCategory
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return '0';
    }

    /**
     * 会員名
     */
    private function getIfMemberName
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        if (is_null($Order) && is_null($Customer)) {
            return null;
        }
        if (!is_null($Order) && is_null($Order->getCustomer())) {
            return null;
        }

        if (is_null($Order)) {
            if (!is_null($Customer->getSecretKey())) {
                return $Customer->getSecretKey();
            } else if (!is_null($Customer->getId()) &&
                       $Customer->getId() !== '0') {
                $readCustomer = $this->customerRepository->findOneBy([
                    'id' => $Customer->getId(),
                    'del_flg' => 0,
                ]);
                if (!is_null($readCustomer)) {
                    return $readCustomer->getSecretKey();
                }
            }

            return null;
        }

        if (!is_null($Order->getCustomer()->getSecretKey())) {
            return $Order->getCustomer()->getSecretKey();
        } else if (!is_null($Order->getCustomer()->getId()) &&
                   $Order->getCustomer()->getId() !== '0') {
            $readCustomer = $this->customerRepository->findOneBy([
                'id' => $Order->getCustomer()->getId(),
                'del_flg' => 0,
            ]);
            if (!is_null($readCustomer)) {
                return $readCustomer->getSecretKey();
            }
        }

        return null;
    }

    /**
     * 氏名
     */
    private function getIfCustomerName
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return PaymentUtil::convCVSText($Order->getName01() .
                                        $Order->getName02());
    }

    /**
     * フリガナ
     */
    private function getIfCustomerKana
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return PaymentUtil::convCVSText($Order->getKana01() .
                                        $Order->getKana02());
    }

    /**
     * 電話番号
     */
    private function getIfTelNo
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $Order->getPhoneNumber();
    }

    /**
     * 結果通知先メールアドレス
     */
    private function getIfMailAddress
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        $mail = null;
        $config = $this->gmoPaymentMethodConfig;

        $Payment = null;
        if (is_null($Order) || is_null($Payment = $Order->getPayment())) {
            return $mail;
        }

        switch ($Payment->getMethodClass()) {
        case Cvs::class:
            // コンビニ
            $code = $sourceData['Convenience'];
            if ($config['enable_mail'] == "1" &&
                in_array($code, $config['enable_cvs_mails'])) {
                $mail = $Order->getEmail();
            }
            break;

        case PayEasyAtm::class:
        case PayEasyNet::class:
            // ペイジー
            if ($config['enable_mail'] == "1") {
                $mail = $Order->getEmail();
                if (!empty($sourceData['MailAddress'])) {
                    $mail = $sourceData['MailAddress'];
                }
            }
            break;

        default:
            break;
        }

        return $mail;
    }

    /**
     * 加盟店メールアドレス
     */
    private function getIfShopMailAddress
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $this->BaseInfo->getEmail01();
    }

    /**
     * 予約番号
     */
    private function getIfReserveNo
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $Order->getId();
    }

    /**
     * 会員番号
     */
    private function getIfMemberNo
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        $customerId = null;

        if (!is_null($Order) && !is_null($Order->getCustomer())) {
            $customerId = $Order->getCustomer()->getId();
        } else if (!is_null($Customer)) {
            $customerId = $Customer->getId();
        }

        return $customerId;
    }

    /**
     * 会員ID
     */
    private function getIfMemberID
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        $customerId = $this->getIfMemberNo($sourceData, $Order, $Customer);
        if (empty($customerId)) {
            return null;
        }

        /* Create Gmo memeber id from customer id
         * Only apply for payment method RegistCredit and CVS
         */
        $gmoMemberId = $this->gmoMemberRepository->getGmoMemberId($customerId);
        if (is_null($gmoMemberId)) {
            // Create new member id
            $gmoMemberId =
                $this->gmoMemberRepository->createGmoMemberId($customerId);
            if (is_null($gmoMemberId)) {
                return null;
            }
            // Save member id into plg_gmo_payment_gateway_member
            $this->gmoMemberRepository
                ->updateOrCreate($customerId, $gmoMemberId);
            $this->entityManager->flush();
        }

        return $gmoMemberId;
    }

    /**
     * 表示電話番号
     */
    private function getIfServiceTel
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $this->gmoPaymentMethodConfig['ServiceTel_1'] . '' .
               $this->gmoPaymentMethodConfig['ServiceTel_2'] . '' .
               $this->gmoPaymentMethodConfig['ServiceTel_3'];
    }

    /**
     * お問合せ先電話番号
     */
    private function getIfReceiptsDisp12
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $this->gmoPaymentMethodConfig['ReceiptsDisp12_1'] . '' .
               $this->gmoPaymentMethodConfig['ReceiptsDisp12_2'] . '' .
               $this->gmoPaymentMethodConfig['ReceiptsDisp12_3'];
    }

    /**
     * お問合せ先受付時間
     */
    private function getIfReceiptsDisp13
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return
            sprintf('%02d',
                    $this->gmoPaymentMethodConfig['ReceiptsDisp13_1']) . ':' .
            sprintf('%02d',
                    $this->gmoPaymentMethodConfig['ReceiptsDisp13_2']) . '-' .
            sprintf('%02d',
                    $this->gmoPaymentMethodConfig['ReceiptsDisp13_3']) . ':' .
            sprintf('%02d',
                    $this->gmoPaymentMethodConfig['ReceiptsDisp13_4']);
    }

    /**
     * 会員作成フラグ
     */
    private function getIfCreateMember
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return '1';
    }

    /**
     * 名義人
     */
    private function getIfHolderName
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        if (!isset($sourceData['card_name1']) &&
            isset($sourceData['HolderName'])) {
            return $sourceData['HolderName'];
        }

        return $sourceData['card_name1'] . ' ' . $sourceData['card_name2'];
    }

    /**
     * オーダーID
     */
    private function getIfOrderID
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $sourceData['OrderID'];
    }

    /**
     * 税送料
     */
    private function getIfTax
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return '';
    }

    /**
     * パラメータバージョン
     */
    private function getIfVerSion
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return '';
    }

    /**
     * クレジットトークン
     */
    private function getIfToken
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return $sourceData['token'];
    }

    /**
     * カード登録連番モード
     */
    private function getIfSeqMode
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return '1';
    }

    /**
     * カード登録連番
     */
    private function getIfCardSeq
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        if (!isset($sourceData['CardSeq'])) {
            return null;
        }

        return $sourceData['CardSeq'];
    }

    /**
     * 決済タイプ
     */
    private function getIfPaymentType
        (array $sourceData, Order $Order = null, Customer $Customer = null)
    {
        return 'E';
    }

    /**
     * GMO-PG 注文に関するインタフェース送受信を行う
     *
     * @param Order $Order 注文
     * @param string $url リクエスト先
     * @param array $paramNames 送信するパラメータ名配列
     * @param array $sendData 送信データ配列
     * @return boolean
     */
    protected function sendOrderRequest
        (Order $Order, $url, array $paramNames, array $sendData)
    {
        PaymentUtil::logInfo('PaymentHelper::sendOrderRequest start.');

        $bkData = $sendData;

        // OrderID を取得してセット
        $OrderID = $Order->getGmoOrderPayment()->getGmoOrderID();
        $sendData['OrderID'] = $OrderID;

        // 送信データを取得
        $data = $this->getIfSendData
            ($paramNames, $sendData, $Order, $Order->getCustomer());

        // 送信受信
        $ret = $this->sendRequest($url, $data);
        if ($ret) {
            $sendData = $this->getResults();
        } else {
            $sendData = [];
            $sendData[0]['request_error'] = $this->getError();
        }

        $sendData[0]['OrderID'] = $OrderID;
        $sendData[0]['Amount'] = $Order->getDecPaymentTotal();

        if (isset($this->gmoPaymentMethodConfig['JobCd']) &&
            !is_null($this->gmoPaymentMethodConfig['JobCd'])) {
            $sendData[0]['JobCd'] = $this->gmoPaymentMethodConfig['JobCd'];
        }

        if (isset($bkData['CardSeq']) && !is_null($bkData['CardSeq'])) {
            $sendData[0]['CardSeq'] = $bkData['CardSeq'];
        }

        if (!is_null($bkData['action_status'])) {
            $sendData[0]['action_status'] = $bkData['action_status'];
        }

        if (isset($bkData['pay_status']) && !is_null($bkData['pay_status'])) {
            $sendData[0]['pay_status'] = $bkData['pay_status'];
        }

        $error = $this->getError();
        if (!is_null($bkData['success_pay_status']) && empty($error)) {
            $sendData[0]['pay_status'] = $bkData['success_pay_status'];
        } else if (!is_null($bkData['fail_pay_status']) && !empty($error)) {
            $sendData[0]['pay_status'] = $bkData['fail_pay_status'];
        }

        // 送受信ログデータを保存
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        $GmoOrderPayment->setPaymentLogData($sendData, false, $Order);

        $this->entityManager->persist($Order);
        $this->entityManager->persist($GmoOrderPayment);
        $this->entityManager->flush();

        if (!empty($this->error)) {
            return false;
        }

        // 成功時のみ表示用データの構築(購入完了画面／メール向け)
        $this->setOrderCompleteMessages($Order);

        PaymentUtil::logInfo('PaymentHelper::sendOrderRequest end.');

        return true;
    }

    /**
     * GMO-PG インタフェースの送受信を行う
     *
     * @param string $url リクエスト先
     * @param array $sendData 送信データ配列
     * @return boolean 処理結果
     */
    protected function sendRequest($url, $sendData)
    {
        $this->resetError();

        PaymentUtil::logInfo($sendData);

        $data = [];
        foreach ($sendData as $key => $value) {
            $data[$key] = mb_convert_encoding($value, 'SJIS-win', 'UTF-8');
        }

        $client = new Client(['curl.options' => ['CURLOPT_SSLVERSION' => 6]]);
        $response = $client->post($url, ['form_params' => $data]);

        $r_code = $response->getStatusCode();
        switch ($r_code) {
        case 200:
            break;
        case 404:
            $msg = trans('gmo_payment_gateway.' .
                         'payment_helper.error3') . $r_code;
            $this->setError($msg);
            return false;
            break;
        case 500:
        default:
            $msg = trans('gmo_payment_gateway.' .
                         'payment_helper.error4') . $r_code;
            $this->setError($msg);
            return false;
            break;
        }

        $response_body = $response->getBody()->getContents();
        if (is_null($response_body)) {
            $msg = trans('gmo_payment_gateway.payment_helper.error5');
            $this->setError($msg);
            return false;
        }

        $arrRet = $this->parseResponse($response_body);
        $this->setResults($arrRet);
        if (!empty($this->error)) {
            return false;
        }

        return true;
    }

    public function setError($msg)
    {
        $this->error[] = $msg;
        PaymentUtil::logError($msg);
    }

    public function getError()
    {
        return $this->error;
    }

    public function resetError()
    {
        $this->error = [];
        PaymentUtil::logInfo("PaymentHelper reset error.");
    }

    /**
     * レスポンスを解析する
     *
     * @param string $string レスポンス
     * @return array 解析結果
     */
    protected function parseResponse($string)
    {
        $arrRet = array();
        $string = trim($string);

        if (strpos($string, 'ACS=1') === 0) {
            $regex = '|^ACS=1&ACSUrl\=(.+?)&PaReq\=(.+?)&MD\=(.+?)$|';
            $ret = preg_match_all($regex, $string, $matches);
            if ($ret !== false && $ret > 0) {
                $arrRet[0]['ACS'] = '1';
                $arrRet[0]['ACSUrl'] = $matches[1][0];
                $arrRet[0]['PaReq'] = $matches[2][0];
                $arrRet[0]['MD'] = $matches[3][0];
            } else {
                $this->setError(trans('gmo_payment_gateway.' .
                                      'payment_helper.error1'));
                $msg = '-> 3D response failed: ' . $string;
            }
        } else {
            $arrTmpAnd = explode('&', $string);
            foreach ($arrTmpAnd as $eqString) {
                // $eqString -> CardSeq=2|0|1, DefaultFlag=0|0|0...
                $pos = strpos($eqString, '=');
                $key = substr($eqString, 0, $pos);
                $val = substr($eqString, $pos + 1);
                if (strpos($key, '<') !== FALSE ||
                    strpos($key, '>') !== FALSE) {
                    $this->setError(trans('gmo_payment_gateway.' .
                                          'payment_helper.error2'));
                    continue;
                }

                // $val -> 2|0|1, 0|0|0, ...
                if (preg_match('/|/', $val)) {
                    $arrTmpl = explode('|', $val);
                    $max = count($arrTmpl);
                    for ($i = 0; $i < $max; $i++) {
                        $arrRet[$i][$key] = trim($arrTmpl[$i]);
                    }
                    // $val -> 2, 0, 1...
                } else {
                    $arrRet[0][$key] = trim($val);
                }
            }
        }

        if (isset($arrRet[0]['ErrCode'])) {
            $this->setError($this->createErrCode($arrRet));
        }

        return $arrRet;
    }

    /**
     * エラーコード文字列を構築する
     *
     * @param array $arrRet
     * @return string
     */
    protected function createErrCode($arrRet)
    {
        $msg = '';

        foreach ($arrRet as $key => $ret) {
            if (is_array($ret)) {
                $errorMsg =
                    $this->errorUtil->lfGetErrorInformation($ret['ErrInfo']);
                $error_text =
                    empty($errorMsg['message']) ?
                    $errorMsg['context'] : $errorMsg['message'];
                $msg .= $error_text . '(' .
                    sprintf('%s-%s', $ret['ErrCode'], $ret['ErrInfo']) .
                    '),';
            } else if ($key == 'ErrInfo') {
                if (preg_match('/|/', $ret)) {
                    $arrTmplInfo = explode('|', $ret);
                    $arrTmplCode = explode('|', $arrRet['ErrCode']);
                } else {
                    $arrTmplInfo = array($ret);
                    $arrTmplCode = array($ret['ErrCode']);
                }
                foreach ($arrTmplInfo as $key2 => $err) {
                    $errorMsg = $this->errorUtil->lfGetErrorInformation($err);
                    $error_text = empty($errorMsg['message']) ?
                        $errorMsg['context'] : $errorMsg['message'];
                    $msg .= $error_text . '(' .
                        sprintf('%s-%s',
                                $arrTmplCode[$key2],
                                $arrTmplInfo[$key2]) .
                        '),';
                }
            }
        }

        $msg = substr($msg, 0, strlen($msg) - 1); // 最後の,をカット

        return $msg;
    }

    /**
     * GMO-PG インタフェース送信結果をセット
     */
    protected function setResults($results)
    {
        $this->results = $results;
        PaymentUtil::logInfo($results);
    }

    /**
     * GMO-PG インタフェース送信結果を取得
     */
    protected function getResults()
    {
        if (is_null($this->results[0]) && !is_null($this->results)) {
            return $this->results;
        }

        return $this->results[0];
    }

    /**
     * 決済毎に購入完了画面およびメールに表示する内容を生成する
     * 決済毎のヘルパーでオーバーライドして実装すること
     *
     * @param Order $Order
     * @return array 表示データ配列
     */
    protected function makeOrderCompleteMessages(Order $Order)
    {
        return [];
    }

    /**
     * 購入完了画面およびメールに表示する内容を保存する
     *
     * @param Order $Order
     */
    protected function setOrderCompleteMessages(Order $Order)
    {
        $data = $this->makeOrderCompleteMessages($Order);
        if (!empty($data)) {
            $GmoPaymentMethod = $Order->getGmoPaymentMethod();

            $data['title']['value'] = '1';
            $data['title']['name'] = $GmoPaymentMethod->getPaymentMethod();

            // 改行を追加
            $data['lf']['name'] = '';
            $data['lf']['value'] = '';

            $GmoOrderPayment = $Order->getGmoOrderPayment();
            $GmoOrderPayment->setOrderCompleteMessages($data);

            $this->entityManager->persist($GmoOrderPayment);
            $this->entityManager->flush($GmoOrderPayment);
        }
    }

    /**
     * 決済ステータス配列を取得する
     */
    public function getPaymentStatuses()
    {
        $const = $this->eccubeConfig;
        $prefix = "gmo_payment_gateway.pay_status.";

        return [
            $const[$prefix . 'unsettled'] => trans($prefix . 'unsettled'),
            $const[$prefix . 'request_success'] =>
                trans($prefix . 'request_success'),
            $const[$prefix . 'reqsales'] => trans($prefix . 'reqsales'),
            $const[$prefix . 'reqcancel'] => trans($prefix . 'reqcancel'),
            $const[$prefix . 'reqchange'] => trans($prefix . 'reqchange'),
            $const[$prefix . 'pay_success'] => trans($prefix . 'pay_success'),
            $const[$prefix . 'paystart'] => trans($prefix . 'paystart'),
            $const[$prefix . 'expire'] => trans($prefix . 'expire'),
            $const[$prefix . 'cancel'] => trans($prefix . 'cancel'),
            $const[$prefix . 'fail'] => trans($prefix . 'fail'),
            $const[$prefix . 'auth'] => trans($prefix . 'auth'),
            $const[$prefix . 'commit'] => trans($prefix . 'commit'),
            $const[$prefix . 'sales'] => trans($prefix . 'sales'),
            $const[$prefix . 'capture'] => trans($prefix . 'capture'),
            $const[$prefix . 'void'] => trans($prefix . 'void'),
            $const[$prefix . 'return'] => trans($prefix . 'return'),
            $const[$prefix . 'returnx'] => trans($prefix . 'returnx'),
            $const[$prefix . 'sauth'] => trans($prefix . 'sauth'),
            $const[$prefix . 'check'] => trans($prefix . 'check'),
            $const[$prefix . 'except'] => trans($prefix . 'except'),
        ];
    }

    /**
     * 決済ステータス名称を取得する
     *
     * @param int $pay_status 取引状態値
     * @return string 取引状態
     */
    public function getPaymentStatusName($pay_status)
    {
        $statuses = $this->getPaymentStatuses();
        return isset($statuses[$pay_status]) ? $statuses[$pay_status] : '';
    }

    /**
     * 対応状況（注文ステータス）から 7:決済処理中、8:購入処理中
     * を除外したステータス配列を取得する
     *
     * @return array 対応状況配列
     */
    public function getOrderStatuses()
    {
        $results = [];

        $OrderStatuses = $this->orderStatusRepository
            ->findNotContainsBy(['id' => [OrderStatus::PENDING,
                                          OrderStatus::PROCESSING]]);
        foreach ($OrderStatuses as $OrderStatus) {
            $results[$OrderStatus->getId()] = $OrderStatus->getName();
        }

        return $results;
    }

    /**
     * GMO-PGの決済一覧を返す
     *
     * @return array 決済配列
     */
    public function getGmoPayments()
    {
        $results = [];

        $GmoPaymentMethods = $this->gmoPaymentMethodRepository
            ->findBy([], ['payment_id' => 'ASC']);
        foreach ($GmoPaymentMethods as $GmoPaymentMethod) {
            $results[$GmoPaymentMethod->getPaymentId()]
                = $GmoPaymentMethod->getPaymentMethod();
        }

        return $results;
    }
}
