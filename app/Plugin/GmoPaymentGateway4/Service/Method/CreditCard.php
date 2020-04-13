<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service\Method;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentInput;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperCredit;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperMember;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * クレジットカード(トークン決済)の決済処理を行う.
 */
class CreditCard extends GmoMethod
{
    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperMember
     */
    protected $paymentHelperMember;

    /**
     * CreditCard constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param PaymentHelperCredit $paymentHelperCredit
     * @param PaymentHelperMember $paymentHelperMember
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        PaymentHelperCredit $paymentHelperCredit,
        PaymentHelperMember $paymentHelperMember
    ) {
        $this->entityManager = $entityManager;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->paymentHelper = $paymentHelperCredit;
        $this->paymentHelperMember = $paymentHelperMember;
    }

    /**
     * クレジットカード決済画面の入力値をフォームから取得して返す
     *
     * @return GmoPaymentInput
     */
    protected function getGmoPaymentInputFromForm()
    {
        $GmoPaymentInput = new GmoPaymentInput();
        $GmoPaymentInput->setFormData($this->form);
        $GmoPaymentInput = $this->setRegisterCardInfo($GmoPaymentInput);

        return $GmoPaymentInput;
    }

    /**
     * 決済処理の後に行う処理を実装する
     *   リダイレクトする場合は PaymentResult を返却
     *   リダイレクトしない場合は null を返却
     *
     * @param GmoPaymentInput $GmoPaymentInput
     * @return PaymentResult|null
     */
    protected function postRequest(GmoPaymentInput $GmoPaymentInput)
    {
        // 3Dセキュアの必要性を確認
        if ($this->paymentHelper->is3dSecureResponse()) {
            // リダイレクトレスポンスを返却
            return $this->paymentHelper->redirectTo3dSecurePage();
        }

        // カード登録処理
        if ($GmoPaymentInput->isRegisterCard()) {
            $Customer = $this->Order->getCustomer();
            // GMO-PG 会員登録状況を確認
            if (!$this->paymentHelperMember->isExistGmoMember($Customer)) {
                // GMO-PG 会員登録を行う
                $this->paymentHelperMember->saveGmoMember($Customer);
            }
            // カード登録
            $this->paymentHelper
                ->doRegistCard($this->Order, $GmoPaymentInput->getArrayData());
        }

        // リダイレクトなし
        return null;
    }

    /**
     * 登録済みカード情報を取得して情報を補完する
     *
     * @param GmoPaymentInput $GmoPaymentInput
     * @return GmoPaymentInput
     */
    private function setRegisterCardInfo(GmoPaymentInput $GmoPaymentInput)
    {
        PaymentUtil::logInfo('CreditCard::setRegisterCardInfo start.');

        // 登録済みクレジットカード決済を選択している場合のみ処理続行
        if ($GmoPaymentInput->payment_type !== "1") {
            return $GmoPaymentInput;
        }

        $sendData = [];
        $sendData['CardSeq'] = $GmoPaymentInput->CardSeq;
        $Customer = $this->Order->getCustomer();

        $card = $this->paymentHelperMember
            ->searchCard($Customer, $sendData, true);
        if (empty($card)) {
            return $GmoPaymentInput;
        }

        $GmoPaymentInput->mask_card_no = $card[0]['CardNo'];
        $GmoPaymentInput->expire_month = $card[0]['expire_month'];
        $GmoPaymentInput->expire_year = $card[0]['expire_year'];
        $GmoPaymentInput->card_name1 = $card[0]['HolderName'];

        PaymentUtil::logInfo('CreditCard::setRegisterCardInfo end.');

        return $GmoPaymentInput;
    }
}
