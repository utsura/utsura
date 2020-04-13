<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Form\Type;

use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class MypageCardType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $PaymentUtil =& PaymentUtil::getInstance();

        // 有効期限を生成
        $listYear = $PaymentUtil->getZeroYear(date('Y'), date('Y') + 15);
        $listMonth = $PaymentUtil->getZeroMonth();

        $builder
            // クレジットトークン
            ->add('token', HiddenType::class, [
                'required' => false,
                'mapped' => false,
            ])
            // カード番号
            ->add('card_no', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'minlength' => '12',
                    'maxlength' => '16',
                    'autocomplete' => 'off',
                    'pattern' => '\d*',
                ],
            ])
            // 有効期限（月）
            ->add('expire_month', ChoiceType::class, [
                'choices' => $listMonth,
                'mapped' => false,
            ])
            // 有効期限（年）
            ->add('expire_year', ChoiceType::class, [
                'choices' => $listYear,
                'mapped' => false,
            ])
            // カード名義人名
            ->add('card_name1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'maxlength' => '50',
                ],
            ])
            // カード連番
            ->add('CardSeq', TextType::class, [
                'required' => false,
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}
