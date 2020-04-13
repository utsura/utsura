<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\YamatoPayment4\Form\Type;

use Eccube\Common\EccubeConfig;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Plugin\YamatoPayment4\Util as YamatoUtil;

class CreditChangeType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    protected $configRepository;

    protected $orderStatusRepository;

    /**
     * RepeatedPasswordType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
            EccubeConfig $eccubeConfig
    )
    {
        $this->eccubeConfig = $eccubeConfig;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $pluginUtil = new YamatoUtil\PluginUtil($this->eccubeConfig);
        $month = $pluginUtil->getZeroMonth();
        $year  = $pluginUtil->getZeroYear(null, date('Y')+15);

        $builder
            ->add('card_key', RadioType::class, [])
            ->add('last_credit_date', HiddenType::class, [])
            ->add(
                'card_no',
                TextType::class,
                [
                    'label' => 'カード番号',
                    'attr' => [
                        'class' => 'form-control',
                        'minlength' => '12',
                        'maxlength' => '16',
                        'autocomplete' => 'off',
                    ],
                    'required' => false,
                    'mapped' => false,
                ]
            )
            ->add(
                'card_exp_month',
                ChoiceType::class,
                [
                    'label' => '有効期限(月)',
                    'required' => false,
                    'choices' => array_flip($month),
                    'mapped' => false,
                    'expanded' => false,
                    'multiple' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'off',
                    ]
                ]
            )
            ->add(
                'card_exp_year',
                ChoiceType::class,
                [
                    'label' => '有効期限(年)',
                    'required' => false,
                    'choices' => array_flip($year),
                    'mapped' => false,
                    'expanded' => false,
                    'multiple' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'off',
                    ]
                ]
            )
            ->add(
                'card_owner',
                TextType::class,
                [
                    'label' => 'カード名義',
                    'attr' => [
                        'maxlength' => '25',
                    ],
                    'required' => false,
                    'mapped' => false,
                ]
            )
            ->add(
                'security_code',
                TextType::class,
                [
                    'label' => 'セキュリティコード',
                    'attr' => [
                        'class' => 'form-control',
                        'maxlength' => '4',
                        'autocomplete' => 'off',
                    ],
                    'required' => false,
                    'mapped' => false,
                ]
            )
        ;
    }
}
