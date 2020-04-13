<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4;

use Eccube\Plugin\AbstractPluginManager;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Layout;
use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Entity\Payment;
use Plugin\GmoPaymentGateway4\Entity\GmoConfig;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod;
use Plugin\GmoPaymentGateway4\Service\Method\CreditCard;
use Plugin\GmoPaymentGateway4\Service\Method\Cvs;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyNet;
use Plugin\GmoPaymentGateway4\Service\Method\CarAu;
use Plugin\GmoPaymentGateway4\Service\Method\CarDocomo;
use Plugin\GmoPaymentGateway4\Service\Method\CarSoftbank;
use Plugin\GmoPaymentGateway4\Service\Method\RakutenPay;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    /**
     * Update the plugin.
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function update(array $meta, ContainerInterface $container)
    {
        PaymentUtil::logInfo('PluginManager::update start.');

        try {
            // GMO-PG プラグイン設定用のレコードを生成
            $this->createConfig($container);
            // 支払方法を追加
            $this->createPayments($container);
            // マイページ/カード情報編集向けにページおよび
            // ページレイアウトを生成
            $this->createPageLayout($container);
        } catch (\Exception $e) {
            PaymentUtil::logError($e->getMessage());
            throw $e;
        }

        PaymentUtil::logInfo('PluginManager::update end.');
    }

    /**
     * Enable the plugin.
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function enable(array $meta, ContainerInterface $container)
    {
        PaymentUtil::logInfo('PluginManager::enable start.');

        try {
            // GMO-PG プラグイン設定用のレコードを生成
            $this->createConfig($container);
            // 支払方法を追加
            $this->createPayments($container);
            // マイページ/カード情報編集向けにページおよび
            // ページレイアウトを生成
            $this->createPageLayout($container);
        } catch (\Exception $e) {
            PaymentUtil::logError($e->getMessage());
            throw $e;
        }

        PaymentUtil::logInfo('PluginManager::enable end.');
    }

    /**
     * GMO-PG プラグイン設定用のレコードを生成
     */
    private function createConfig(ContainerInterface $container)
    {
        PaymentUtil::logInfo('PluginManager::createConfig start.');

        $entityManager = $container->get('doctrine')->getManager();
        $GmoConfig = $entityManager->find(GmoConfig::class, 1);
        if ($GmoConfig) {
            PaymentUtil::logInfo('GmoConfig found.');
            return;
        }

        $EccubeConfig = $container->get(EccubeConfig::class);
        $server_url =
            $EccubeConfig['gmo_payment_gateway.' .
                          'admin.config.test.server_url'];
        $kanri_server_url =
            $EccubeConfig['gmo_payment_gateway.' .
                          'admin.config.test.kanri_server_url'];

        $GmoConfig = new GmoConfig();
        $GmoConfig->setConnectServerType(1);
        $GmoConfig->setServerUrl($server_url);
        $GmoConfig->setKanriServerUrl($kanri_server_url);
        $GmoConfig->setSiteId('');
        $GmoConfig->setSitePass('');
        $GmoConfig->setShopId('');
        $GmoConfig->setShopPass('');
        $GmoConfig->setCardRegistFlg(1);

        $entityManager->persist($GmoConfig);
        $entityManager->flush($GmoConfig);

        PaymentUtil::logInfo('PluginManager::createConfig end.');
    }

    /**
     * 支払方法の利用条件、最低金額を返す
     */
    private function getPaymentRuleMin($class)
    {
        $rule_min = 1;

        switch ($class) {
        case RakutenPay::class:
            $rule_min = 100;
            break;

        default:
            break;
        }

        return $rule_min;
    }

    /**
     * 支払方法の利用条件、最大金額を返す
     */
    private function getPaymentRuleMax($class)
    {
        $rule_max = '';

        switch ($class) {
        case Cvs::class:
            $rule_max = 299999;
            break;

        case PayEasyAtm::class:
        case PayEasyNet::class:
            $rule_max = 999999;
            break;

        case CarAu::class:
            $rule_max = 9999999;
            break;

        case CarDocomo::class:
            $rule_max = 30000;
            break;

        case CarSoftbank::class:
            $rule_max = 100000;
            break;

        case RakutenPay::class:
            $rule_max = 99999999;
            break;

        case CreditCard::class:
        default:
            break;
        }

        return $rule_max;
    }

    /**
     * 支払方法を追加
     */
    private function createPayment
        (ContainerInterface $container, array $paymentInfo)
    {
        PaymentUtil::logInfo('PluginManager::createPayment(' .
                             $paymentInfo['class'] . ') start.');

        $entityManager = $container->get('doctrine')->getManager();

        $Payment = $entityManager->getRepository(Payment::class)
            ->findOneBy(['method_class' => $paymentInfo['class']]);
        if (is_null($Payment)) {
            $Payment = $entityManager->getRepository(Payment::class)
                ->findOneBy([], ['sort_no' => 'DESC']);
            $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

            $Payment = new Payment();
            $Payment->setCharge(0);
            $Payment->setSortNo($sortNo);
            $Payment->setVisible(true);
            $Payment->setMethod($paymentInfo['name']);
            $Payment->setMethodClass($paymentInfo['class']);
            $min = $this->getPaymentRuleMin($paymentInfo['class']);
            $Payment->setRuleMin($min);
            $max = $this->getPaymentRuleMax($paymentInfo['class']);
            if (!empty($max)) {
                $Payment->setRuleMax($max);
            }

            $entityManager->persist($Payment);
            $entityManager->flush($Payment);

            PaymentUtil::logInfo('create dtb_payment.');
        }

        $GmoPaymentMethod = $entityManager
            ->getRepository(GmoPaymentMethod::class)
            ->getFromClass($paymentInfo['class']);
        if (is_null($GmoPaymentMethod)) {
            $GmoPaymentMethod = new GmoPaymentMethod();
            $GmoPaymentMethod->setPaymentId($Payment->getId());
            $GmoPaymentMethod->setPaymentMethod($Payment->getMethod());
            $GmoPaymentMethod->setMemo03($Payment->getMethodClass());
            $now = new \DateTime();
            $GmoPaymentMethod->setCreateDate($now);
            $GmoPaymentMethod->setUpdateDate($now);
            $GmoPaymentMethod->setPluginCode(PaymentUtil::PLUGIN_CODE);

            $entityManager->persist($GmoPaymentMethod);

            PaymentUtil::logInfo
                ('create plg_gmo_payment_gateway_payment_method.');
        }

        PaymentUtil::logInfo('PluginManager::createPayment end.');
    }

    /**
     * プラグインがサポートする支払方法を追加
     */
    private function createPayments(ContainerInterface $container)
    {
        PaymentUtil::logInfo('PluginManager::createPayments start.');

        $payments = [
            ['class' => CreditCard::class,
             'name' => trans('クレジット決済')],
            ['class' => Cvs::class,
             'name' => trans('コンビニ決済')],
            ['class' => PayEasyAtm::class,
             'name' => trans('Pay-easy決済(銀行ATM)')],
            ['class' => PayEasyNet::class,
             'name' => trans('Pay-easy決済(ネットバンク)')],
            ['class' => CarAu::class,
             'name' => trans('auかんたん決済')],
            ['class' => CarDocomo::class,
             'name' => trans('ドコモケータイ払い')],
            ['class' => CarSoftbank::class,
             'name' => trans('ソフトバンクまとめて支払い')],
            ['class' => RakutenPay::class,
             'name' => trans('楽天ペイ')],
        ];

        // 支払方法を追加
        foreach ($payments as $payment) {
            $this->createPayment($container, $payment);
        }

        PaymentUtil::logInfo('PluginManager::createPayments end.');
    }

    /**
     * マイページ/カード情報編集向けにページおよびページレイアウトを生成
     */
    private function createPageLayout(ContainerInterface $container)
    {
        PaymentUtil::logInfo('PluginManager::createPageLayout start.');

        $entityManager = $container->get('doctrine')->getManager();

        $url = 'gmo_mypage_card_edit';

        // 存在確認
        $Page = $entityManager
            ->getRepository(Page::class)->findOneBy(['url' => $url]);
        if ($Page) {
            PaymentUtil::logInfo('Page found.');
            return;
        }

        // ページを生成
        $now = new \DateTime();
        $Page = new Page();
        $Page->setName(trans('MYページ/カード情報編集'));
        $Page->setUrl($url);
        $Page->setFileName('@GmoPaymentGateway4/mypage_card');
        $Page->setEditType(Page::EDIT_TYPE_DEFAULT);
        $Page->setCreateDate($now);
        $Page->setUpdateDate($now);
        $Page->setMetaRobots('noindex');

        // 保存
        $entityManager->persist($Page);
        $entityManager->flush();

        $layout_id = 2;

        // レイアウトを取得
        $Layout = $entityManager->find(Layout::class, $layout_id);

        // ページレイアウトのソート番号最大値を取得
        $MaxSortNoPageLayout = $entityManager->getRepository(PageLayout::class)
            ->findOneBy(['layout_id' => $layout_id], ['sort_no' => 'desc']);

        // ページレイアウトを生成
        $PageLayout = new PageLayout();
        $PageLayout->setPageId($Page->getId());
        $PageLayout->setPage($Page);
        $PageLayout->setLayoutId($layout_id);
        $PageLayout->setLayout($Layout);
        $PageLayout->setSortNo($MaxSortNoPageLayout->getSortNo() + 1);

        // 保存
        $entityManager->persist($PageLayout);
        $entityManager->flush();
        
        PaymentUtil::logInfo('PluginManager::createPageLayout end.');
    }
}
