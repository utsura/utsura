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
use Plugin\GmoPaymentGateway4\Service\PaymentHelperRakutenPay;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * 楽天ペイの決済処理を行う.
 */
class RakutenPay extends GmoMethod
{
    /**
     * RakutenPay constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param PaymentHelperRakutenPay $paymentHelperRakutenPay
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        PaymentHelperRakutenPay $paymentHelperRakutenPay
    ) {
        $this->entityManager = $entityManager;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->paymentHelper = $paymentHelperRakutenPay;
    }

    /**
     * 決済画面の入力値をフォームから取得して返す
     *
     * @return GmoPaymentInput
     */
    protected function getGmoPaymentInputFromForm()
    {
        // 入力項目なし
        return null;
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
        // 楽天ペイ側の決済画面へリダイレクトする
        return $this->paymentHelper->redirectToRakutenPayPage();
    }
}
