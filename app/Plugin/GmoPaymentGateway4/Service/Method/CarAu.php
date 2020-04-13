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
use Plugin\GmoPaymentGateway4\Service\PaymentHelperAu;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * キャリア Au の決済処理を行う.
 */
class CarAu extends Carrier
{
    /**
     * CarAu constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param PaymentHelperAu $paymentHelperAu
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        PaymentHelperAu $paymentHelperAu
    ) {
        $this->entityManager = $entityManager;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->paymentHelper = $paymentHelperAu;
    }
}
