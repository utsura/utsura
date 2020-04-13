<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Form\Type\Admin;

use Plugin\GmoPaymentGateway4\Entity\GmoConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class ConfigType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('connect_server_type', ChoiceType::class, [
                'label' => trans('gmo_payment_gateway.admin.config.col1'),
                'choices' => [
                    trans('gmo_payment_gateway.admin.config.col1.label1') => 1,
                    trans('gmo_payment_gateway.admin.config.col1.label2') => 2,
                    trans('gmo_payment_gateway.admin.config.col1.label3') => 3,
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check-inline',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('server_url', TextType::class, [
                'label' => trans('gmo_payment_gateway.admin.config.col8'),
                'constraints' => [
                    new NotBlank(),
                    new Url(),
                ],
            ])
            ->add('kanri_server_url', TextType::class, [
                'label' => trans('gmo_payment_gateway.admin.config.col9'),
                'constraints' => [
                    new NotBlank(),
                    new Url(),
                ],
            ])
            ->add('site_id', TextType::class, [
                'label' => trans('gmo_payment_gateway.admin.config.col2'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('site_pass', TextType::class, [
                'label' => trans('gmo_payment_gateway.admin.config.col3'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('shop_id', TextType::class, [
                'label' => trans('gmo_payment_gateway.admin.config.col4'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('shop_pass', TextType::class, [
                'label' => trans('gmo_payment_gateway.admin.config.col5'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('card_regist_flg', ChoiceType::class, [
                'choices' => [
                    trans('gmo_payment_gateway.admin.config.col7.label1') => 0,
                    trans('gmo_payment_gateway.admin.config.col7.label2') => 1,
                ],
                'multiple' => false,
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check-inline',
                ],
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => GmoConfig::class,
        ]);
    }
}
