<?php

namespace Plugin\YamatoPayment4;

use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Entity\Delivery;
use Eccube\Entity\DeliveryFee;
use Eccube\Entity\PaymentOption;
use Eccube\Entity\Csv;
use Eccube\Entity\Master\CsvType;
use Eccube\Entity\Master\SaleType;
use Eccube\Entity\Master\Pref;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\PluginRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\LayoutRepository;
use Eccube\Repository\PageRepository;
use Eccube\Repository\PageLayoutRepository;
use Eccube\Repository\CsvRepository;
use Eccube\Service\PluginService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Plugin\YamatoPayment4\Entity\Config;
use Plugin\YamatoPayment4\Entity\YamatoPaymentMethod;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Service\Method\Deferred;
use Plugin\YamatoPayment4\Service\Method\DeferredSms;

class PluginManager extends AbstractPluginManager
{
    private $config;
    private $entityManager;

    /**
     * PluginManager constructor.
     */
    public function __construct()
    {
    }

    public function enable(array $config, ContainerInterface $container)
    {
        $this->config = $config;

        $this->createConfig($container);
        $this->addMasterData($container);
        $this->addPage($container);

        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        // プラグイン設定項目を初期値
        $this->initModuleSettings();
        // 販売種別追加
        $this->addMtbSaleType();

        // CSVデータの登録
        $this->addProductCsv();
        $this->addOrderCsv();

        // 配送方法登録（予約商品用）
        $this->addReserveDeliveryData();
    }

    public function disable(array $config, ContainerInterface $container)
    {
        // プラグインで管理している決済方法を非表示にする。
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $paymentRepository = $container->get(PaymentRepository::class);

        $methods = [
            'クレジットカード' => Credit::class,
            'クロネコ代金後払い(請求書郵送)' => Deferred::class,
            'クロネコ代金後払い(請求書SMS送付)' => DeferredSms::class,
        ];

        // 本プラグインによる決済方法を非表示にする。
        foreach ($methods as $method_class) {
            $Payment = $paymentRepository->findOneBy(['method_class' => $method_class]);
            if ($Payment) {
                $Payment->setVisible(false);
                $entityManager->persist($Payment);
            }
        }
        $entityManager->flush();

        // CSVレコードの削除
        $csvRepository = $container->get(CsvRepository::class);

        $Csv = $csvRepository->findOneBy(['CsvType' => CsvType::CSV_TYPE_PRODUCT, 'field_name' => 'reserve_date']);
        if ($Csv instanceof Csv) {
            $csvRepository->delete($Csv);
        }
        $Csv = $csvRepository->findOneBy(['CsvType' => CsvType::CSV_TYPE_ORDER, 'field_name' => 'scheduled_shipping_date']);
        if ($Csv instanceof Csv) {
            $csvRepository->delete($Csv);
        }
        $entityManager->flush();
    }

    public function install(array $config, ContainerInterface $container)
    {
        // 現在は処理なし
    }

    public function uninstall(array $config, ContainerInterface $container)
    {
        // 現在は処理なし
    }

    public function update(array $config, ContainerInterface $container)
    {
        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        // スキーマ更新
        $this->updateSchema($container);

        // プラグイン設定項目を追加
        $this->initModuleSettings();
        // 支払い方法のプロパティを初期化
        $this->initPaymentMethodProperty();
        // 販売種別追加
        $this->addMtbSaleType();

        // CSVデータの登録
        $this->addProductCsv();
        $this->addOrderCsv();

        // 配送方法登録（予約商品用）
        $this->addReserveDeliveryData();
    }

    /**
     * スキーマ更新.
     */
    private function updateSchema(ContainerInterface $container)
    {
        $Plugin = $container->get(PluginRepository::class)->findByCode('YamatoPayment4');
        $PluginService = $container->get(PluginService::class);

        if (!$Plugin) {
            throw new NotFoundHttpException();
        }

        $config = $PluginService->readConfig($PluginService->calcPluginDir($Plugin->getCode()));

        $PluginService->generateProxyAndUpdateSchema($Plugin, $config);
    }

    /**
     * プラグインデータ登録.
     */
    private function createConfig(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $Plugin = $entityManager->find(Config::class, 1);

        $PluginRepository = $entityManager->getRepository(Config::class);
        $Plugin = $PluginRepository->findOneBy([]);
        if ($Plugin) {
            $Plugin->setDelFlg('0');
        } else {
            $Plugin = new Config();
            $Plugin->setCode($this->config['code']);
            $Plugin->setName($this->config['name']);
            $Plugin->setSubData();
            $Plugin->setB2Data();
            $Plugin->setAutoUpdateFlg('0');
            $Plugin->setDelFlg('0');
        }

        $entityManager->persist($Plugin);
        $entityManager->flush($Plugin);
    }

    /**
     * マスター登録.
     */
    private function addMasterData(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $creditStatuses = [
            0 => '決済依頼済み',
            1 => '決済申込完了',
            2 => '入金完了（速報）',
            3 => '入金完了（確報）',
            4 => '与信完了',
            5 => '予約受付完了',
            11 => '購入者都合エラー',
            12 => '加盟店都合エラー',
            13 => '決済機関都合エラー',
            14 => 'その他システムエラー',
            15 => '予約販売与信エラー',
            16 => '決済依頼取消エラー',
            17 => '金額変更NG',
            20 => '決済中断',
            21 => '決済手続き中',
            30 => '精算確定待ち',
            31 => '精算確定',
            40 => '取消',
            50 => '3Dセキュア認証中',
        ];

        $i = 0;
        foreach ($creditStatuses as $id => $name) {
            $YamatoPaymentStatus = $entityManager->find(YamatoPaymentStatus::class, $id);
            if ($YamatoPaymentStatus) {
                continue;
            }

            $YamatoPaymentStatus = new YamatoPaymentStatus();

            $YamatoPaymentStatus->setId($id);
            $YamatoPaymentStatus->setName($name);
            $YamatoPaymentStatus->setSortNo($i++);

            $entityManager->persist($YamatoPaymentStatus);
            $entityManager->flush($YamatoPaymentStatus);
        }
    }

    private function addPage(ContainerInterface $container)
    {
        $entityManage = $container->get('doctrine.orm.entity_manager');

        // ページ追加
        $pageRepository = $container->get(PageRepository::class);

        $layoutRepository = $container->get(LayoutRepository::class);
        $Layout = $layoutRepository->find(2);

        $pageLayoutRepository = $container->get(PageLayoutRepository::class);
        $LastPageLayout = $pageLayoutRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $LastPageLayout->getSortNo();

        $arrPage = [
            [
                'page_name' => 'MYページ/クレジットカード編集',
                'url' => 'mypage_kuronekocredit',
                'file_name' => '@YamatoPayment4/mypage/credit',
            ],
            [
                'page_name' => 'MYページ/クレジットカード編集',
                'url' => 'mypage_kuronekocredit_delete',
                'file_name' => '@YamatoPayment4/mypage/credit',
            ],
            [
                'page_name' => 'MYページ/クレジットカード編集',
                'url' => 'mypage_kuronekocredit_register',
                'file_name' => '@YamatoPayment4/mypage/credit',
            ],
        ];

        foreach ($arrPage as $p) {
            $Page = $pageRepository->findOneBy(['url' => $p['url']]);
            if ($Page) {
                continue;
            }

            $Page = new Page();
            $Page->setName($p['page_name']);
            $Page->setUrl($p['url']);
            $Page->setFileName($p['file_name']);
            $Page->setEditType(Page::EDIT_TYPE_DEFAULT);
            $Page->setCreateDate(new \DateTime());
            $Page->setUpdateDate(new \DateTime());
            $Page->setMetaRobots('noindex');

            $entityManage->persist($Page);
            $entityManage->flush($Page);

            $PageLayout = new PageLayout();
            $PageLayout->setPage($Page);
            $PageLayout->setPageId($Page->getId());
            $PageLayout->setLayout($Layout);
            $PageLayout->setLayoutId($Layout->getId());
            $PageLayout->setSortNo($sortNo++);

            $entityManage->persist($PageLayout);
            $entityManage->flush($PageLayout);
        }
    }

    /**
     * ファイルをコピー
     */
    private function copyAssets()
    {
        // 現在は処理なし
    }

    /**
     * コピーしたファイルを削除.
     */
    private function removeAssets()
    {
        // 現在は処理なし
    }

    /**
     * プラグイン設定項目の初期化.
     */
    protected function initModuleSettings()
    {
        $Config = $this->entityManager->find(Config::class, 1);
        $sub_data = $Config->getSubData();

        // オプションサービス
        if (!isset($sub_data['use_option'])) {
            // 初期値：未契約
            $sub_data['use_option'] = '1';
        }
        // 予約販売機能
        if (!isset($sub_data['advance_sale'])) {
            // 初期値：利用しない
            $sub_data['advance_sale'] = '0';
        }

        $Config->setSubData($sub_data);

        $this->entityManager->persist($Config);
        $this->entityManager->flush();
    }

    /**
     * 支払い方法のプロパティを初期化する.
     */
    protected function initPaymentMethodProperty()
    {
        $yamatoPaymentMethodRepository = $this->entityManager->getRepository(YamatoPaymentMethod::class);
        $YamatoPaymentMethod = $yamatoPaymentMethodRepository->findOneBy(['memo03' => 10]);

        if (!$YamatoPaymentMethod) {
            return;
        }

        $memo05 = $YamatoPaymentMethod->getMemo05();

        // 自動カード登録 初期値：利用しない
        $memo05['autoRegist'] = 0;
        $YamatoPaymentMethod->setMemo05($memo05);

        $this->entityManager->persist($YamatoPaymentMethod);
        $this->entityManager->flush();
    }

    /**
     * 販売種別マスター登録.
     */
    protected function addMtbSaleType()
    {
        $exists = $this->entityManager->find(SaleType::class, 9625) instanceof SaleType;
        if ($exists) {
            return;
        }

        $Master = new \Eccube\Entity\Master\SaleType();
        $Master
            ->setId(9625)
            ->setName('予約商品')
            ->setSortNo(9625);

        $this->entityManager->persist($Master);
        $this->entityManager->flush();
    }

    /**
     * 商品CSVデータ追加.
     */
    protected function addProductCsv()
    {
        $csvRepository = $this->entityManager->getRepository(Csv::class);

        $exists = $csvRepository->findOneBy(['CsvType' => CsvType::CSV_TYPE_PRODUCT, 'field_name' => 'reserve_date']) instanceof Csv;
        if ($exists) {
            return;
        }
        /** @var CsvType $CsvType */
        $CsvType = $this->entityManager->getRepository(CsvType::class)->find(CsvType::CSV_TYPE_PRODUCT);

        /** @var Csv $Csv */
        $Csv = $csvRepository->findOneBy(['CsvType' => $CsvType], ['sort_no' => 'DESC']);
        $sort_no = $Csv->getSortNo() + 1;

        $Csv = new Csv();
        $Csv
            ->setCsvType($CsvType)
            ->setCreator(null)
            ->setEntityName('Eccube\\Entity\\Product')
            ->setFieldName('reserve_date')
            ->setDispName('予約商品出荷予定日')
            ->setSortNo($sort_no)
            ->setEnabled(false);

        $this->entityManager->persist($Csv);
        $this->entityManager->flush();
    }

    /**
     * 受注CSVデータ追加.
     */
    protected function addOrderCsv()
    {
        $csvRepository = $this->entityManager->getRepository(Csv::class);

        $exists = $csvRepository->findOneBy(['CsvType' => CsvType::CSV_TYPE_ORDER, 'field_name' => 'scheduled_shipping_date']) instanceof Csv;
        if ($exists) {
            return;
        }
        /** @var CsvType $CsvType */
        $CsvType = $this->entityManager->getRepository(CsvType::class)->find(CsvType::CSV_TYPE_ORDER);

        /** @var Csv $Csv */
        $Csv = $csvRepository->findOneBy(['CsvType' => $CsvType], ['sort_no' => 'DESC']);
        $sort_no = $Csv->getSortNo() + 1;

        $Csv = new Csv();
        $Csv
            ->setCsvType($CsvType)
            ->setCreator(null)
            ->setEntityName('Eccube\\Entity\\Order')
            ->setFieldName('scheduled_shipping_date')
            ->setDispName('出荷予定日')
            ->setSortNo($sort_no)
            ->setEnabled(false);

        $this->entityManager->persist($Csv);
        $this->entityManager->flush();
    }

    /**
     * 配送方法登録（予約商品用）.
     *
     * @param array $paymentIdList
     */
    protected function addReserveDeliveryData()
    {
        $deliveryRepository = $this->entityManager->getRepository(Delivery::class);

        $exists = $deliveryRepository->findOneBy(['SaleType' => 9625]) instanceof Delivery;

        // データが存在する場合は、以下を処理しない
        if ($exists) {
            return null;
        }

        // 配送業者登録（予約商品用）
        $Delivery = $this->addReserveDelivery();

        // 配送方法/支払方法紐づけ登録
        $this->addPaymentOption($Delivery);

        // 配送料金マスター登録
        $this->addDeliveryFee($Delivery);
    }

    /**
     * 配送業者登録（予約商品用）.
     *
     * @return Delivery
     */
    protected function addReserveDelivery()
    {
        $saleTypeRepository = $this->entityManager->getRepository(SaleType::class);
        $deliveryRepository = $this->entityManager->getRepository(Delivery::class);

        /** @var SaleType $SaleType */
        $SaleType = $saleTypeRepository->find(9625);
        /** @var Delivery $Delivery */
        $Delivery = $deliveryRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sort_no = $Delivery->getSortNo() + 1;

        $Delivery = new Delivery();
        $Delivery->setName('予約商品配送業者');
        $Delivery->setServiceName('予約商品配送業者');
        $Delivery
            ->setCreator(null)
            ->setSortNo($sort_no)
            ->setSaleType($SaleType)
            ->setVisible(true);

        $this->entityManager->persist($Delivery);
        $this->entityManager->flush();

        return $Delivery;
    }

    /**
     * 配送方法/支払方法紐づけ登録.
     *
     * @param Delivery $Delivery
     */
    protected function addPaymentOption($Delivery)
    {
        $yamatoPaymentMethodRepository = $this->entityManager->getRepository(YamatoPaymentMethod::class);

        // クレジットカード決済
        $YamatoPaymentMethod = $yamatoPaymentMethodRepository->findOneBy(['memo03' => 10]);
        if ($YamatoPaymentMethod instanceof YamatoPaymentMethod) {
            $PaymentOption = new PaymentOption();
            $PaymentOption
                ->setPaymentId($YamatoPaymentMethod->getPayment()->getId())
                ->setPayment($YamatoPaymentMethod->getPayment())
                ->setDeliveryId($Delivery->getId())
                ->setDelivery($Delivery);
            $Delivery->addPaymentOption($PaymentOption);

            $this->entityManager->persist($Delivery);
            $this->entityManager->flush();
        }
    }

    /**
     * 送料登録.
     *
     * @param Delivery $Delivery
     */
    protected function addDeliveryFee($Delivery)
    {
        $prefRepository = $this->entityManager->getRepository(Pref::class);
        $Prefs = $prefRepository->findBy([], ['sort_no' => 'ASC']);

        foreach ($Prefs as $Pref) {
            /** @var Pref $Pref */
            $DeliveryFee = new DeliveryFee();
            $DeliveryFee
                ->setDelivery($Delivery)
                ->setPref($Pref)
                ->setFee(0);
            $this->entityManager->persist($DeliveryFee);
        }

        $this->entityManager->flush();
    }
}
