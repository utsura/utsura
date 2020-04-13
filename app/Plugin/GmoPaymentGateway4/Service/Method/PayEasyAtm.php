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
use Plugin\GmoPaymentGateway4\Service\PaymentHelperPayEasyAtm;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * Pay-easy(銀行ATM)の決済処理を行う.
 */
class PayEasyAtm extends PayEasy
{
    /**
     * PayEasyAtm constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param PaymentHelperPayEasyAtm $paymentHelperPayEasyAtm
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        PaymentHelperPayEasyAtm $paymentHelperPayEasyAtm
    ) {
        $this->entityManager = $entityManager;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->paymentHelper = $paymentHelperPayEasyAtm;
    }
}
