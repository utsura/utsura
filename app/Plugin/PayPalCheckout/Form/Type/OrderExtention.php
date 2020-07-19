<?php

namespace Plugin\PayPalCheckout\Form\Type;

use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Repository\PaymentRepository;
use Symfony\Component\Form\AbstractTypeExtension;

/**
 * Class OrderExtention
 */
class OrderExtention extends AbstractTypeExtension
{
    protected $paymentRepository;

    public function __construct(
        PaymentRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return OrderType::class;
    }
}
