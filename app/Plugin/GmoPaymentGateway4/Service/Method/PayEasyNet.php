<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service\Method;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Symfony\Component\HttpFoundation\Session\Session;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperPayEasyNet;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * Pay-easy(ネットバンク)の決済処理を行う.
 */
class PayEasyNet extends PayEasy
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * PayEasyNet constructor.
     *
     * @param Session $session
     * @param EntityManagerInterface $entityManager
     * @param CartService $cartService
     * @param MailService $mailService
     * @param OrderStatusRepository $orderStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param PaymentHelperPayEasyNet $paymentHelperPayEasyNet
     */
    public function __construct(
        Session $session,
        EntityManagerInterface $entityManager,
        CartService $cartService,
        MailService $mailService,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        PaymentHelperPayEasyNet $paymentHelperPayEasyNet
    ) {
        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->cartService = $cartService;
        $this->mailService = $mailService;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->paymentHelper = $paymentHelperPayEasyNet;
    }

    /**
     * [オーバーライド] 注文時に呼び出される.
     *
     * Pay-easy(ネットバンク)の決済処理を行う.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        $result = parent::checkout();
        if (!$result->isSuccess()) {
            return $result;
        }

        $this->entityManager->flush();

        // カート削除
        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info1',
                                   ['%order_id%' => $this->Order->getId()]));
        $this->cartService->clear();

        // 受注IDをセッションにセット
        $this->session->set
            (OrderHelper::SESSION_ORDER_ID, $this->Order->getId());

        // メール送信
        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info2',
                                   ['%order_id%' => $this->Order->getId()]));
        $this->mailService->sendOrderMail($this->Order);
        $this->entityManager->flush();

        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'payeasy.netbank.info1',
                                   ['%order_id%' => $this->Order->getId()]));

        return $this->paymentHelper->redirectToSelectBankPage();
    }
}
