<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\YamatoPayment4;

use Eccube\Common\EccubeConfig;
use Eccube\Event\TemplateEvent;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Service\Client\CreditClientService;
use Plugin\YamatoPayment4\Service\Client\CvsClientService;
use Plugin\YamatoPayment4\Service\Method AS YamatoMethod;

class YamatoPaymentEvent implements EventSubscriberInterface
{
    protected $eccubeConfig;
    protected $paymentRepository;
    protected $yamatoPluginRepository;
    protected $yamatoPaymentMethodRepository;
    protected $yamatoOrderRepository;
    protected $productRepository;
    protected $customerRepository;
    protected $router;
    protected $client;

    public function __construct(
        EccubeConfig $eccubeConfig,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        ConfigRepository $yamatoPluginRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        RouterInterface $router,
        RequestStack $requestStack
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->yamatoPluginRepository = $yamatoPluginRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;
        $this->router = $router;
        $this->requestStack = $requestStack;

        $this->client = new CreditClientService(
            $this->eccubeConfig,
            $this->productRepository,
            $this->orderRepository,
            $this->yamatoPluginRepository,
            $this->yamatoPaymentMethodRepository,
            $this->yamatoOrderRepository,
            $this->router
        );
    }


    /**
     * リッスンしたいサブスクライバのイベント名の配列を返します。
     * 配列のキーはイベント名、値は以下のどれかをしてします。
     * - 呼び出すメソッド名
     * - 呼び出すメソッド名と優先度の配列
     * - 呼び出すメソッド名と優先度の配列の配列
     * 優先度を省略した場合は0
     *
     * 例：
     * - ['eventName' => 'methodName']
     * - ['eventName' => ['methodName', $priority]]
     * - ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Setting/Shop/payment_edit.twig' => 'onAdminSettingShopPaymentEditTwig',
            '@admin/Product/product.twig' => 'onAdminProductProductTwig',
            'Shopping/index.twig' => 'onShoppingIndexTwig',
            'Shopping/confirm.twig' => 'onShoppingConfirmTwig',
            'Mypage/index.twig' => 'onMypageNaviTwig',
            'Mypage/favorite.twig' => 'onMypageNaviTwig',
            'Mypage/change.twig' => 'onMypageNaviTwig',
            'Mypage/withdraw.twig' => 'onMypageNaviTwig',
            'Mypage/delivery.twig' => 'onMypageNaviTwig',
            'Mypage/delivery_edit.twig' => 'onMypageNaviTwig',
            '@YamatoPayment4/mypage/credit.twig' => 'onMypageNaviTwig',

            '@IplPeriodicPurchase/mypage/index.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/history.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/cycle.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/next_shipping.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/shipping.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/products.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/skip.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/suspend.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/resume.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/cancel.twig' => 'onMypageNaviTwig',
            '@IplPeriodicPurchase/mypage/complete.twig' => 'onMypageNaviTwig',
        ];
    }

    public function onAdminSettingShopPaymentEditTwig(TemplateEvent $event)
    {
        $event->addSnippet('@YamatoPayment4/admin/payment_register.twig');

        $moduleSettings = $this->client->getUserSettings();
        $event->setParameter('moduleSettings', $moduleSettings);
    }

    public function onAdminProductProductTwig(TemplateEvent $event)
    {

        /* 予約商品出荷予定日項目追加 */
        $snipet = file_get_contents(__DIR__ . '/Resource/template/admin/Product/product_edit.twig');

        // HTML の書き換え
        $search = '{# エンティティ拡張の自動出力 #}';
        $replace = $snipet . $search;
        $source = $event->getSource();
        $source = str_replace($search, $replace, $source);

        $event->setSource($source);

        /* パラメータの追加 */
        $parameters = $event->getParameters();

        // 追加パラメータを取得
        $moduleSettings = $this->client->getUserSettings();

        $addParams = [
            'use_option' => $moduleSettings['use_option'],
            'advance_sale' => $moduleSettings['advance_sale']
        ];

        // パラメータ追加
        $parameters = array_merge($parameters, $addParams);
        $event->setParameters($parameters);
    }

    public function onShoppingIndexTwig(TemplateEvent $event) {

        $moduleSettings = "";
        $creditCardList = "";
        $Order = $event->getParameter('Order');

        if (!empty($this->eccubeConfig['SALE_TYPE_ID_PERIODIC'])) {
            if ($Order->getSaleTypes()[0]->getId() === $this->eccubeConfig['SALE_TYPE_ID_PERIODIC']) {
                $event->setParameter('isPeriodic', true);
            } else {
                $event->setParameter('isPeriodic', false);
            }
        } else {
            $event->setParameter('isPeriodic', false);
        }

        $Payment = $Order->getPayment();
        // 配送方法を変えると支払方法が未設定になるケースがある.
        if($Payment === null) return;

        /**
         * 購入フローからの遷移時に 選択された決済方法 を$eventからうまく受け取れないため、
         * requestStackを混ぜた無理矢理な記述になっている。
         * 購入フローからの遷移時(confirm, redirect_to)にelseに入り、requestStackから決済方法を受け取る。
         */
        $request = $this->requestStack->getCurrentRequest();
        $dataBag = $request->request->get('_shopping_order');
        if($dataBag === null || !array_key_exists('Payment', $dataBag)) {
            $paymentId = $Payment->getId();
        } else {
            $paymentId = $dataBag['Payment'];
        }

        // クレジットカード決済
        $Credit = $this->paymentRepository->findOneBy(['method_class' => YamatoMethod\Credit::class]);
        if (($Credit !== null) && ($paymentId == $Credit->getId())) {
            $this->client->setSetting($Order);
            $moduleSettings = $this->client->getModuleSetting();
            if($Order->getCustomer()) {
                $result = $this->client->doGetCard($Order->getCustomer()->getId());
                if($result) {
                    $creditCardList = $this->client::getArrCardInfo($this->client->results);
                }
            }
            if($creditCardList == "") {
                $creditCardList = ['cardData' => [], 'cardUnit' => '0'];
            }

            /*
             * 予約商品販売機能の使用有無を判定する
             *     設定ファイルの「予約販売」の利用の有無を取得
             *     カート内商品が「予約販売対象商品」か判定する
             */
            //予約商品存在確認
            $tpl_is_reserv_service = $this->client->isReservedOrder($Order);
        
            $yamatoPaymentMethod = $this->yamatoPaymentMethodRepository->findOneBy(['Payment' => $Payment]);
            $methodParam = $yamatoPaymentMethod->getMemo05();
            $event->addSnippet('@YamatoPayment4/credit.twig');
            $event->setParameter('creditCardList', $creditCardList);
            $event->setParameter('moduleSettings', $moduleSettings);
            $event->setParameter('autoRegist', $methodParam['autoRegist']);
            $event->setParameter('reservService', $tpl_is_reserv_service);
            $event->addSnippet('@YamatoPayment4/kuronekoToken.twig');
        }

        // クロネコ代金後払い(請求書SMS送付)
        $DeferredSms = $this->paymentRepository->findOneBy(['method_class' => YamatoMethod\DeferredSms::class]);
        if(($DeferredSms !== null) && ($paymentId == $DeferredSms->getId())) {
            $event->addSnippet('@YamatoPayment4/shopping/deferred/sms/deferred_sms.twig');
        }

        // コンビニ決済
        $Cvs = $this->paymentRepository->findOneBy(['method_class' => YamatoMethod\Cvs::class]);
        if ($Cvs !== null && $paymentId == $Cvs->getId()) {
            $client = new CvsClientService(
                $this->eccubeConfig,
                $this->yamatoPluginRepository,
                $this->yamatoPaymentMethodRepository,
                $this->yamatoOrderRepository,
                $this->router
            );
            $client->setSetting($Order);
            $yamatoPaymentMethod = $this->yamatoPaymentMethodRepository->findOneBy(['Payment' => $Payment]);
            $methodParam = $yamatoPaymentMethod->getMemo05();
            $event->addSnippet('@YamatoPayment4/cvs.twig');
            $event->addSnippet('@YamatoPayment4/kuronekoToken.twig');
        }
    }

    public function onShoppingConfirmTwig(TemplateEvent $event)
    {
        $Order = $event->getParameter('Order');

        if($Order->getPayment()->getMethodClass() === YamatoMethod\Credit::class) {
            $event->addSnippet('@YamatoPayment4/credit_confirm.twig');
        }

        if($Order->getPayment()->getMethodClass() === YamatoMethod\DeferredSms::class) {
            $event->addSnippet('@YamatoPayment4/shopping/deferred/sms/deferred_sms_confirm.twig');
            $event->addSnippet('@YamatoPayment4/shopping/deferred/sms/sms_confirm_button.twig');
        }

        if($Order->getPayment()->getMethodClass() === YamatoMethod\Cvs::class) {
            $event->addSnippet('@YamatoPayment4/cvs_confirm.twig');
        }
    }

    public function onMypageNaviTwig(TemplateEvent $event)
    {
        $setting = $this->client->getUserSettings();
        if($setting['use_option'] == '0') {
            // オプションサービス契約時のみ
            $event->addSnippet('@YamatoPayment4/mypage/add_navi.twig');
        }
    }
}
