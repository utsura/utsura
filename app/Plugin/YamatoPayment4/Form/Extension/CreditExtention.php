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

namespace Plugin\YamatoPayment4\Form\Extension;

use Eccube\Common\EccubeConfig;
use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Repository\PaymentRepository;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\HttpFoundation\RequestStack;

use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Util\PluginUtil;

/**
 * 注文手続き画面のFormを拡張し、カード入力フォームを追加する.
 * 支払い方法に応じてエクステンションを作成する.
 */
class CreditExtention extends AbstractTypeExtension
{
    protected $paymentRepository;
    protected $eccubeConfig;
    protected $yamatoConfigRepository;
    protected $yamatoPaymentMethodRepository;

    public function __construct(
        EccubeConfig $eccubeConfig,
        PaymentRepository $paymentRepository,
        ConfigRepository $yamatoConfigRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        RequestStack $requestStack
    )
    {
        $this->paymentRepository = $paymentRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->requestStack = $requestStack;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $self = $this;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($self) {
            $form = $event->getForm();

            $request = $this->requestStack->getCurrentRequest();
            $dataBag = $request->request->get('_shopping_order');

            /**
             * FIXME: 購入フローからの遷移時に 選択された決済方法 を$eventからうまく受け取れないため、
             *        requestStackを混ぜた無理矢理な記述になっている。
             * 　　　　購入フローからの遷移時(confirm, redirect_to)にelseに入り、requestStackから決済方法を受け取る。
             */
            if($dataBag === null || !array_key_exists('Payment', $dataBag)) {
                $paymentId = $event->getData()->getPayment()->getId();
            } else {
                $paymentId = $dataBag['Payment'];
            }

            // 支払い方法が一致する場合
            $Credit = $this->paymentRepository->findOneBy(['method_class' => Credit::class]);
            if($Credit === null) {
                return;
            } elseif ($paymentId == $Credit->getId()) {

                $pluginUtil = new PluginUtil($self->eccubeConfig);

                $month = $pluginUtil->getZeroMonth();
                $year  = $pluginUtil->getZeroYear(null, date('Y')+15);
                $payWayArray = $pluginUtil->getCreditPayMethod();

                $paymentInfo = $self->yamatoPaymentMethodRepository->findOneBy(['Payment' => $paymentId])->getMemo05();

                $payWay = [];
                if (isset($paymentInfo['pay_way'])) {
                    foreach ((array)$paymentInfo['pay_way'] as $pay_way) {
                        if (!is_null($payWayArray[$pay_way])) {
                            $payWay[$pay_way] = $payWayArray[$pay_way];
                        }
                    }
                }

                $form
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
                ->add(
                    'pay_way',
                    ChoiceType::class,
                    [
                        'label' => '支払い方法',
                        'choices' => array_flip($payWay),
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
                    'register_card',
                    CheckboxType::class,
                    [
                        'label' => ' このカードを登録する',
                        'required' => false,
                        'mapped' => false,
                        'attr' => [
                            'autocomplete' => 'off',
                        ]
                    ]
                )
                ->add(
                    'use_registed_card',
                    CheckboxType::class,
                    [
                        'label' => ' 登録カードを使用する',
                        'required' => false,
                        'mapped' => false,
                        'attr' => [
                            'class' => 'form-control',
                            'autocomplete' => 'off',
                        ]
                    ]
                )

                ->add(
                    'card_key',
                    HiddenType::class,
                    [
                        'required' => false,
                        'mapped' => false,
                    ]
                )
                ->add(
                    'webcollectToken',
                    HiddenType::class,
                    [
                        'required' => false,
                        'mapped' => false,
                    ]
                )->add(
                    'mode',
                    HiddenType::class,
                    [
                        'required' => false,
                        'mapped' => false,
                    ]
                );
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) {
            $options = $event->getForm()->getConfig()->getOptions();

            // 注文確認->注文処理時はフォームは定義されない.
            if ($options['skip_add_form']) {
                return;
            }

            $Payment = $this->paymentRepository->findOneBy(['method_class' => Credit::class]);
            if($Payment === null) {
                return;
            }

            $data = $event->getData();
            $form = $event->getForm();

            // 支払い方法が一致しなければremove
            if (!isset($data['Payment']) || $Payment->getId() != $data['Payment']) {
                $form
                ->remove('card_no')
                ->remove('card_exp_month')
                ->remove('card_exp_year')
                ->remove('card_owner')
                ->remove('security_code')
                ->remove('CardSeq')
                ->remove('pay_way')
                ->remove('register_card')
                ->remove('use_registed_card')
                ->remove('card_key')
                ->remove('webcollectToken');
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return OrderType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'allow_extra_fields' => true,
            ]
        );
    }
}
