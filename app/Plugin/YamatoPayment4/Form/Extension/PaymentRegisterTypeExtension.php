<?php
/*
 * Copyright(c)2016, Yamato Financial Co.,Ltd. All rights reserved.
 * Copyright(c)2016, Yamato Credit finance Co.,Ltd. All rights reserved.
 */

namespace Plugin\YamatoPayment4\Form\Extension;

use Eccube\Common\EccubeConfig;
use Eccube\Form\Type\PriceType;
use Eccube\Form\Type\Admin\PaymentRegisterType;
use Eccube\Repository\PaymentRepository;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints as Asset;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository;
use Plugin\YamatoPayment4\Service\Method\Cvs;
use Plugin\YamatoPayment4\Util\PluginUtil;
use Symfony\Component\Form\FormError;

/**
 * 支払方法タイプエクステンション.
 */
class PaymentRegisterTypeExtension extends AbstractTypeExtension
{
    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;
    protected $yamatoPaymentStatus;
    protected $yamatoPaymentMethodRepository;
    /** @var PluginUtil */
    protected $pluginUtil;

    public function __construct(
        PaymentRepository $paymentRepository,
        YamatoPaymentStatusRepository $yamatoPaymentStatus,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        EccubeConfig $eccubeConfig
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->yamatoPaymentStatus = $yamatoPaymentStatus;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * 設定画面の構築.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->pluginUtil = new PluginUtil($this->eccubeConfig);

        $self = $this;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($self) {
            /** @var Order $data */
            $data = $event->getData();
            $form = $event->getForm();

            // 支払い方法が一致する場合
            if ($data->getMethodClass() === Credit::class) {
                $form->remove('charge');
                $form->add(
                    'charge',
                    PriceType::class,
                    [
                        'attr' => [
                            'readonly' => 'on',
                        ],
                    ]
                );

                $form->add(
                    'pay_way',
                    FormType\ChoiceType::class,
                    [
                        'mapped' => false,
                        'choices' => array_flip($this->pluginUtil->getCreditPayMethod()),
                        'expanded' => true,
                        'multiple' => true,
                        'constraints' => [
                            new Asset\NotBlank(['message' => '※ 支払回数が選択されていません。']),
                        ],
                    ]
                );

                $form->add(
                    'autoRegist',
                    FormType\ChoiceType::class,
                    [
                        'mapped' => false,
                        'choices' => array_flip($this->pluginUtil->getUtilization()),
                        'expanded' => true,
                    ]
                );

                $form->add(
                    'TdFlag',
                    FormType\ChoiceType::class,
                    [
                        'mapped' => false,
                        'choices' => array_flip($this->pluginUtil->getUtilization()),
                        'expanded' => true,
                        'constraints' => [
                            new Asset\NotBlank(['message' => '※ 本人認証サービス(3Dセキュア)が選択されていません。']),
                        ],
                    ]
                );

                // 値をセット
                $yamatoPaymentMethod = $self->yamatoPaymentMethodRepository->findOneBy(['Payment' => $data]);
                $methodParam = $yamatoPaymentMethod->getMemo05();

                $form['pay_way']->setData($methodParam['pay_way']);
                $form['autoRegist']->setData($methodParam['autoRegist']);
                $form['TdFlag']->setData($methodParam['TdFlag']);
            }

            if ($data->getMethodClass() === Cvs::class) {
                $form
                    ->remove('charge')
                    ->add('charge', PriceType::class, [
                        'attr' => [
                            'readonly' => 'on',
                        ],
                    ])
                    ->add('conveni', FormType\ChoiceType::class, [
                        'label' => 'コンビニ選択',
                        'expanded' => true,
                        'multiple' => true,
                        'choices' => array_flip($this->pluginUtil->getAllCvs()),
                        'required' => true,
                        'mapped' => false,
                    ]);

                $yamatoPaymentMethod = $self->yamatoPaymentMethodRepository->findOneBy(['Payment' => $data]);
                $methodParam = $yamatoPaymentMethod->getMemo05();

                $this->setMailPartsForCvs($form, $methodParam);

                // 値をセット
                if (isset($methodParam['conveni'])) {
                    $form['conveni']->setData($methodParam['conveni']);
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($self) {
            /** @var Order $data */
            $data = $event->getData();
            $form = $event->getForm();

            // 支払い方法が一致する場合
            if ($data->getMethodClass() === Credit::class) {
                // クレジットカード
                // 値をセット
                $yamatoPaymentMethod = $self->yamatoPaymentMethodRepository->findOneBy(['Payment' => $data]);
                $memo05 = $yamatoPaymentMethod->getMemo05();

                foreach ($form as $key => $fm) {
                    switch ($key) {
                        case 'pay_way':
                        case 'TdFlag':
                            $memo05[$key] = $fm->getData();
                            break;
                        case 'autoRegist':
                            $autoRegist = $fm->getData();
                            // オプション未契約の場合：利用しない
                            if (is_null($autoRegist)) {
                                $autoRegist = 0;
                            }
                            $memo05[$key] = $autoRegist;
                            break;
                        default:
                            break;
                    }
                }
                $yamatoPaymentMethod->setMemo05($memo05);
            //                $self->yamatoPaymentMethodRepository->_em->
            } elseif ($data->getMethodClass() === Cvs::class) {
                // コンビニ決済
                // 値をセット
                /** @var \Plugin\YamatoPayment4\Entity\YamatoPaymentMethod */
                $yamatoPaymentMethod = $self->yamatoPaymentMethodRepository->findOneBy(['Payment' => $data]);
                $memo05 = $yamatoPaymentMethod->getMemo05();

                $memo05['conveni'] = $form['conveni']->getData();
                if (count($memo05['conveni']) == 0) {
                    $form['cvs']->addError(
                        new FormError('※ コンビニが選択されていません。')
                    );
                }

                foreach (array_keys($this->pluginUtil->getAllCvs()) as $cvsId) {
                    $memo05["order_mail_title_{$cvsId}"] = $form["order_mail_title_{$cvsId}"]->getData();
                    $memo05["order_mail_body_{$cvsId}"] = $form["order_mail_body_{$cvsId}"]->getData();
                }

                $yamatoPaymentMethod->setMemo05($memo05);
            }
        });
    }

    public function setMailPartsForCvs($form, $methodParam)
    {
        foreach ($this->pluginUtil->getAllCvs() as $cvsId => $cvsName) {
            $form
                ->add("order_mail_title_{$cvsId}", FormType\TextType::class, [
                    'data' => $methodParam["order_mail_title_{$cvsId}"] ?? "{$cvsName}でのお支払い",
                    'required' => false,
                    'attr' => [
                        'maxlength' => 50,
                    ],
                    'constraints' => [
                        new Asset\Length([
                            'max' => 50,
                            'maxMessage' => "※ {$cvsName}決済完了案内タイトルは50字以下で入力してください。",
                        ]),
                    ],
                    'mapped' => false,
                ])
                ->add("order_mail_body_{$cvsId}", FormType\TextareaType::class, [
                    'data' => isset($methodParam["order_mail_body_{$cvsId}"]) ? $methodParam["order_mail_body_{$cvsId}"] : null,
                    'required' => false,
                    'attr' => [
                        'maxlength' => 1000,
                    ],
                    'constraints' => [
                        new Asset\Length([
                            'max' => 1000,
                            'maxMessage' => "※ {$cvsName}決済完了案内本文は1000字以下で入力してください。",
                        ]),
                    ],
                    'mapped' => false,
                ]);
        }
    }

    public function getExtendedType()
    {
        return PaymentRegisterType::class;
    }
}
