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
use Plugin\YamatoPayment4\Service\Method AS YamatoMethod;

class YamatoPaymentEvent implements EventSubscriberInterface
{
    protected $eccubeConfig;
    protected $paymentRepository;
    protected $yamatoPluginRepository;
    protected $yamatoPaymentMethodRepository;
    protected $yamatoOrderRepository;
    protected $customerRepository;
    protected $router;

    public function __construct(
        EccubeConfig $eccubeConfig,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        ConfigRepository $yamatoPluginRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        RouterInterface $router,
        RequestStack $requestStack
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->yamatoPluginRepository = $yamatoPluginRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;
        $this->router = $router;
        $this->requestStack = $requestStack;
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
            $client = new CreditClientService(
                    $this->eccubeConfig,
                    $this->yamatoPluginRepository,
                    $this->yamatoPaymentMethodRepository,
                    $this->yamatoOrderRepository,
                    $this->router
            );
            $client->setSetting($Order);
            $moduleSettings = $client->getModuleSetting();
            if($Order->getCustomer()) {
                $result = $client->doGetCard($Order->getCustomer()->getId());
                if($result) {
                    $creditCardList = $client::getArrCardInfo($client->results);
                }
            }
            if($creditCardList == "") {
                $creditCardList = ['cardData' => [], 'cardUnit' => '0'];
            }
            $event->addSnippet('@YamatoPayment4/credit.twig');
            $event->setParameter('creditCardList', $creditCardList);
            $event->setParameter('moduleSettings', $moduleSettings);
            $event->addSnippet('@YamatoPayment4/kuronekoToken.twig');
        }

        // クロネコ代金後払い(請求書SMS送付)
        $DeferredSms = $this->paymentRepository->findOneBy(['method_class' => YamatoMethod\DeferredSms::class]);
        if(($DeferredSms !== null) && ($paymentId == $DeferredSms->getId())) {
            $event->addSnippet('@YamatoPayment4/shopping/deferred/sms/deferred_sms.twig');
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
    }

    public function onMypageNaviTwig(TemplateEvent $event)
    {
        $client = new CreditClientService(
                $this->eccubeConfig,
                $this->yamatoPluginRepository,
                $this->yamatoPaymentMethodRepository,
                $this->yamatoOrderRepository,
                $this->router
        );
        $setting = $client->getUserSettings();
        if($setting['use_option'] == '0') {
            // オプションサービス契約時のみ
            $event->addSnippet('@YamatoPayment4/mypage/add_navi.twig');
        }
    }
}
