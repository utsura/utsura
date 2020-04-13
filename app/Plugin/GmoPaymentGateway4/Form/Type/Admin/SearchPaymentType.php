<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Form\Type\Admin;

use Doctrine\ORM\EntityRepository;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperAdmin;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class SearchPaymentType extends AbstractType
{
    /**
     * 7:決済処理中、8:購入処理中を除外したステータス配列
     * @var array
     */
    protected $orderStatuses = [];

    /**
     * @var array
     */
    protected $gmoPayments = [];

    /**
     * @var array
     */
    protected $gmoPaymentStatuses = [];

    /**
     * コンストラクタ
     */
    public function __construct(PaymentHelperAdmin $PaymentHelperAdmin) {
        $orderStatuses = $PaymentHelperAdmin->getOrderStatuses();
        $this->orderStatuses = array_flip($orderStatuses);
        $payments = $PaymentHelperAdmin->getGmoPayments();
        $this->gmoPayments = array_flip($payments);
        $paymentStatuses = $PaymentHelperAdmin->getPaymentStatuses();
        $this->gmoPaymentStatuses = array_flip($paymentStatuses);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('Payments', ChoiceType::class, [
                'choices' => $this->gmoPayments,
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('OrderStatuses', ChoiceType::class, [
                'choices' => $this->orderStatuses,
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('PaymentStatuses', ChoiceType::class, [
                'choices' => $this->gmoPaymentStatuses,
                'multiple' => true,
                'expanded' => true,
            ]);
    }
}
