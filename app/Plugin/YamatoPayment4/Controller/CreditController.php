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

namespace Plugin\YamatoPayment4\Controller;

use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Service\MailService;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Plugin\YamatoPayment4\Form\Type\CreditChangeType;
use Plugin\YamatoPayment4\Service\Client\CreditClientService;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Util\PaymentUtil;

class CreditController extends AbstractController
{
    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var CustomerStatusRepository
     */
    protected $customerStatusRepository;

    /**
     * @var TokenStorage
     */
    protected $tokenStorage;

    public function __construct(
        EccubeConfig $eccubeConfig,
        ConfigRepository $yamatoConfigRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        TokenStorageInterface $tokenStorage,
        CreditClientService $creditClientService
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->tokenStorage = $tokenStorage;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->creditClientService = $creditClientService;
    }

    /**
     * 変更画面.
     *
     * @Route("/mypage/kuronekocredit", name="mypage_kuronekocredit")
     * @Template("@YamatoPayment4/mypage/credit.twig")
     */
    public function index(Request $request)
    {
        $form = $this->createForm(CreditChangeType::class);

        $Customer = $this->getUser();

        // 決済エラー状態の定期があればメッセージを出す
        $has_paymenterrored_periodicpurchase = $this->hasPaymentErroredPeriodicPurchase($Customer);

        $this->paymentUtil = new PaymentUtil($this->eccubeConfig, $this->yamatoConfigRepository);
        $CreditPaymentMethod = $this->yamatoPaymentMethodRepository->findOneBy(['memo03' => $this->eccubeConfig['YAMATO_PAYID']['CREDIT']]);
        $paymentInfo = $CreditPaymentMethod->getMemo05();

        list($moduleSettings, $creditCardList) = $this->getViewData($Customer, $paymentInfo);

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'has_paymenterrored_periodicpurchase' => $has_paymenterrored_periodicpurchase,
            'moduleSettings' => $moduleSettings,
            'creditCardList' => $creditCardList,
        ];
    }

    /**
     * @Route("/mypage/kuronekocredit/delete", name="mypage_kuronekocredit_delete")
     * @Template("@YamatoPayment4/mypage/credit.twig")
     */
    public function delete(Request $request)
    {
        log_info('カード削除処理開始');

        $form = $this->createForm(CreditChangeType::class);

        $Customer = $this->getUser();

        $form->handleRequest($request);
        $errMessage = '';

        // 必要な入力値をセット
        $arrParam['card_key'] = $request->get('card_key');
        $arrParam['lastCreditDate'] = $request->get('last_credit_date');


        $this->creditClientService->setUserSettings($this->yamatoConfigRepository->get()->getSubData());
        $this->paymentUtil = new PaymentUtil($this->eccubeConfig, $this->yamatoConfigRepository);
        $CreditPaymentMethod = $this->yamatoPaymentMethodRepository->findOneBy(['memo03' => $this->eccubeConfig['YAMATO_PAYID']['CREDIT']]);
        $paymentInfo = $CreditPaymentMethod->getMemo05();
        $this->creditClientService->setPaymentInfo($paymentInfo);

        list($moduleSettings, $creditCardList) = $this->getViewData($Customer, $paymentInfo);

        //削除対象のカード情報セット
        $deleteCardData = array();
        foreach ($creditCardList['cardData'] as $cardData) {
            if ($cardData['cardKey'] == $arrParam['card_key']) {
                $deleteCardData = $cardData;
                break;
            }
        }

        //削除対象が予約販売利用有りの場合はエラーで返す
        if (isset($deleteCardData['subscriptionFlg']) && $deleteCardData['subscriptionFlg'] == '1') {
            $errMessage = '※ 予約販売利用有りのカード情報は削除できません。';
        } else {
            $result = $this->creditClientService->doDeleteCard($Customer->getId(), $arrParam);
            if($result == false) {
                $errMessage = implode("\n", $this->creditClientService->getError());
            }
        }

        if($errMessage) {
            $this->addError($errMessage);
        } else {
            $this->addSuccess('yamato_payment.mypage.delete.success');

            $this->eventDispatcher->dispatch($this->eccubeConfig['YAMATO_MYPAGE_CARD_DELETE'], null);
        }

        // 決済エラー状態の定期があればメッセージを出す
        $has_paymenterrored_periodicpurchase = $this->hasPaymentErroredPeriodicPurchase($Customer);

        list($moduleSettings, $creditCardList) = $this->getViewData($Customer, $paymentInfo);

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'has_paymenterrored_periodicpurchase' => $has_paymenterrored_periodicpurchase,
            'moduleSettings' => $moduleSettings,
            'creditCardList' => $creditCardList,
        ];
    }

    /**
     * @Route("/mypage/kuronekocredit/register", name="mypage_kuronekocredit_register")
     * @Template("@YamatoPayment4/mypage/credit.twig")
     */
    public function register(Request $request)
    {
        log_info('カード登録処理開始');

        $form = $this->createForm(CreditChangeType::class);

        $Customer = $this->getUser();

        $form->handleRequest($request);
        $errMessage = '';

        $this->creditClientService->setUserSettings($this->yamatoConfigRepository->get()->getSubData());
        $this->paymentUtil = new PaymentUtil($this->eccubeConfig, $this->yamatoConfigRepository);
        $CreditPaymentMethod = $this->yamatoPaymentMethodRepository->findOneBy(['memo03' => $this->eccubeConfig['YAMATO_PAYID']['CREDIT']]);
        $paymentInfo = $CreditPaymentMethod->getMemo05();
        $this->creditClientService->setPaymentInfo($paymentInfo);

        // 必要な入力値をセット
        $arrParam['token'] = $request->get('webcollectToken');

        // カード登録のためにダミー受注を作成
        $dummyOrder = $this->creditClientService->getDummyOrder($Customer);

        // IDを吐かせるためにflushまで
        $this->entityManager->persist($dummyOrder);
        $this->entityManager->flush();

        $result = $this->creditClientService->doRegistCard($Customer, $dummyOrder, $arrParam);
        if ($result === false) {
            $errMessage = implode("\n", $this->creditClientService->getError());
            // エラー情報を初期化
            $this->creditClientService->error = [];
        } else {
            $this->addSuccess('yamato_payment.mypage.register.success');

            // 登録のために行った1円決済をキャンセル
            $result = $this->creditClientService->doCancelDummyOrder($dummyOrder);
            if ($result === false) {
                $errMessage = implode("\n", $this->creditClientService->getError());
            } else {
                // 定期プラグインでこのイベントをsubscribeしている
                $this->eventDispatcher->dispatch($this->eccubeConfig['YAMATO_MYPAGE_CARD_REGISTER'], null);
            }
        }

        if ($errMessage) {
            $this->addError($errMessage);
        }

        // 決済エラー状態の定期があればメッセージを出す
        $has_paymenterrored_periodicpurchase = $this->hasPaymentErroredPeriodicPurchase($Customer);

        list($moduleSettings, $creditCardList) = $this->getViewData($Customer, $paymentInfo);

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'has_paymenterrored_periodicpurchase' => $has_paymenterrored_periodicpurchase,
            'moduleSettings' => $moduleSettings,
            'creditCardList' => $creditCardList,
        ];
    }

    private function hasPaymentErroredPeriodicPurchase($Customer)
    {
        if (!empty($this->eccubeConfig['SALE_TYPE_ID_PERIODIC'])) {
            $periodicPurchaseRepository =$this->entityManager->getRepository(\Plugin\IplPeriodicPurchase\Entity\PeriodicPurchase::class);
            if ($periodicPurchaseRepository->getPaymentErroredPeriodicPurchaseWithYamatoCredit($Customer)) {
                return true;
            }
        }

        return false;
    }

    private function getViewData($Customer, $paymentInfo)
    {
        $moduleSettings = $this->paymentUtil->getModuleSettingsNoOrder($Customer, $paymentInfo);

        $this->creditClientService->setModuleSetting($moduleSettings);

        if($moduleSettings['use_option'] == '0') {
            $result = $this->creditClientService->doGetCard($Customer->getId());
            if($result) {
                $creditCardList = $this->creditClientService::getArrCardInfo($this->creditClientService->results);
            }
        }

        return [$moduleSettings, $creditCardList];
    }
}
