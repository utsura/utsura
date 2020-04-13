<?php

namespace Plugin\YamatoPayment4;

use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\LayoutRepository;
use Eccube\Repository\PageRepository;
use Eccube\Repository\PageLayoutRepository;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Plugin\YamatoPayment4\Entity\Config;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Service\Method\Deferred;
use Plugin\YamatoPayment4\Service\Method\DeferredSms;

class PluginManager extends AbstractPluginManager
{
    private $config;
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
    }

    public function install(array $config, ContainerInterface $container)
    {
        // 現在は処理なし
    }

    public function uninstall(array $config, ContainerInterface $container)
    {
        // 現在は処理なし
    }

    /**
     * プラグインデータ登録
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
     * マスター登録
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
        foreach($creditStatuses as $id => $name) {
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
                'file_name' => '@YamatoPayment4/mypage/credit'
            ],
            [
                'page_name' => 'MYページ/クレジットカード編集',
                'url' => 'mypage_kuronekocredit_delete',
                'file_name' => '@YamatoPayment4/mypage/credit'
            ],
            [
                'page_name' => 'MYページ/クレジットカード編集',
                'url' => 'mypage_kuronekocredit_register',
                'file_name' => '@YamatoPayment4/mypage/credit'
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
     * コピーしたファイルを削除
     */
    private function removeAssets()
    {
        // 現在は処理なし
    }
}
