<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Form\Extension\Admin;

use Eccube\Entity\Customer;
use Eccube\Form\Type\Admin\CustomerType;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperMember;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * 会員修正画面のFormを拡張しPGマルチペイメントサービス決済情報を追加する.
 */
class CustomerExtention extends AbstractTypeExtension
{
    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperMember
     */
    protected $PaymentHelperMember;

    /**
     * コンストラクタ
     *
     * @param EccubeConfig $eccubeConfig
     * @param PaymentHelperMember $PaymentHelperMember
     */
    public function __construct(
        PaymentHelperMember $PaymentHelperMember
    ) {
        $this->PaymentHelperMember = $PaymentHelperMember;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA,
                                   function (FormEvent $event) {
            /** @var Customer $Customer */
            $Customer = $event->getData();
            $form = $event->getForm();

            if (!is_null($Customer) && $Customer->getId() > 0) {
                // GMO-PG 情報を付加する
                $Customer = $this->PaymentHelperMember
                    ->prepareGmoInfoForCustomer($Customer);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return CustomerType::class;
    }
}
