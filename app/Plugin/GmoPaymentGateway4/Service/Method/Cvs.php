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
use Plugin\GmoPaymentGateway4\Service\PaymentHelperCvs;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * コンビニの決済処理を行う.
 */
class Cvs extends GmoMethod
{
    /**
     * Cvs constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param PaymentHelperCvs $paymentHelperCvs
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        PaymentHelperCvs $paymentHelperCvs
    ) {
        $this->entityManager = $entityManager;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->paymentHelper = $paymentHelperCvs;
    }

    /**
     * コンビニ決済画面の入力値をフォームから取得して返す
     *
     * @return GmoPaymentInput
     */
    protected function getGmoPaymentInputFromForm()
    {
        $GmoPaymentInput = new GmoPaymentInput($this->paymentHelper);
        $GmoPaymentInput->setFormData($this->form);

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
        // 処理なし、リダイレクトなし
        return null;
    }
}
