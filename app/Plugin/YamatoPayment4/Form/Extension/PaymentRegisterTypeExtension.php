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
use Symfony\Component\Form\Extension\Core\Type AS FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints AS Asset;

use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository;
use Plugin\YamatoPayment4\Util\PluginUtil;

/**
 * 支払方法タイプエクステンション
 */
class PaymentRegisterTypeExtension extends AbstractTypeExtension
{
    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;
    protected $yamatoPaymentStatus;
    protected $yamatoPaymentMethodRepository;
    protected $pluginUtil;

    public function __construct(
            PaymentRepository $paymentRepository,
            YamatoPaymentStatusRepository $yamatoPaymentStatus,
            YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
            EccubeConfig $eccubeConfig
    )
    {
        $this->paymentRepository = $paymentRepository;
        $this->yamatoPaymentStatus = $yamatoPaymentStatus;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * 設定画面の構築
     * @param FormBuilderInterface $builder
     * @param array $options
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
                                'readonly' => 'on'
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

                // 値をセット
                $yamatoPaymentMethod = $self->yamatoPaymentMethodRepository->findOneBy(['Payment' => $data]);
                $methodParam = $yamatoPaymentMethod->getMemo05();

                $form['pay_way']->setData($methodParam['pay_way']);
            }
        });



        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($self) {
            /** @var Order $data */
            $data = $event->getData();
            $form = $event->getForm();

            // 支払い方法が一致する場合
            if ($data->getMethodClass() === Credit::class) {
                // 値をセット
                $yamatoPaymentMethod = $self->yamatoPaymentMethodRepository->findOneBy(['Payment' => $data]);
                $memo05 = $yamatoPaymentMethod->getMemo05();

                $paramName = ['pay_way'];
                foreach($form as $key => $fm) {
                    if(in_array($key, $paramName)) {
                        $memo05[$key] = $fm->getData();
                    }
                }
                $yamatoPaymentMethod->setMemo05($memo05);
//                $self->yamatoPaymentMethodRepository->_em->
            }
        });
    }

    public function getExtendedType()
    {
        return PaymentRegisterType::class;
    }

}
