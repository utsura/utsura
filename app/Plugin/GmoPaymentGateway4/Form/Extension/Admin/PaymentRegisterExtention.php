<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Form\Extension\Admin;

use Eccube\Form\Type\Admin\PaymentRegisterType;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperCvs;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperPayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperPayEasyNet;
use Plugin\GmoPaymentGateway4\Service\Method\CreditCard;
use Plugin\GmoPaymentGateway4\Service\Method\Cvs;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyNet;
use Plugin\GmoPaymentGateway4\Service\Method\CarAu;
use Plugin\GmoPaymentGateway4\Service\Method\CarDocomo;
use Plugin\GmoPaymentGateway4\Service\Method\CarSoftbank;
use Plugin\GmoPaymentGateway4\Service\Method\RakutenPay;
use Plugin\GmoPaymentGateway4\Repository\GmoPaymentMethodRepository;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

/**
 * 支払方法設定(編集)画面のFormを拡張しGMOPG入力フォームを追加する.
 */
class PaymentRegisterExtention extends AbstractTypeExtension
{
    /**
     * @var PaymentMethodRepository
     */
    protected $gmoPaymentMethodRepository;

    /**
     * @var PaymentHelperCvs
     */
    protected $PaymentHelperCvs;

    /**
     * @var PaymentHelperPayEasyAtm
     */
    protected $PaymentHelperPayEasyAtm;

    /**
     * @var PaymentHelperPayEasyNet
     */
    protected $PaymentHelperPayEasyNet;

    /**
     * コンストラクタ
     *
     * @param GmoPaymentMethodRepository $gmoPaymentMethodRepository
     * @param PaymentHelperCvs $PaymentHelperCvs
     * @param PaymentHelperPayEasyAtm $PaymentHelperPayEasyAtm
     * @param PaymentHelperPayEasyNet $PaymentHelperPayEasyNet
     */
    public function __construct
        (GmoPaymentMethodRepository $gmoPaymentMethodRepository,
         PaymentHelperCvs $PaymentHelperCvs,
         PaymentHelperPayEasyAtm $PaymentHelperPayEasyAtm,
         PaymentHelperPayEasyNet $PaymentHelperPayEasyNet)
    {
        $this->gmoPaymentMethodRepository = $gmoPaymentMethodRepository;
        $this->PaymentHelperCvs = $PaymentHelperCvs;
        $this->PaymentHelperPayEasyAtm = $PaymentHelperPayEasyAtm;
        $this->PaymentHelperPayEasyNet = $PaymentHelperPayEasyNet;
    }

    const arrFunction = [
        // クレジットカード
        CreditCard::class => [
            'appendForm' => 'appendFormCredit',
            'setData' => 'setDataCredit',
            'setDefaultData' => 'setDefaultDataCredit',
            'appendValidation' => 'appendValidationCredit',
            'getData' => 'getDataCredit',
        ],
        // コンビニ
        Cvs::class => [
            'appendForm' => 'appendFormCvs',
            'setData' => 'setDataCvs',
            'setDefaultData' => 'setDefaultDataCvs',
            'appendValidation' => 'appendValidationCvs',
            'getData' => 'getDataCvs',
        ],
        // ペイジー（銀行ATM）
        PayEasyAtm::class => [
            'appendForm' => 'appendFormPayEasyAtm',
            'setData' => 'setDataPayEasyAtm',
            'setDefaultData' => 'setDefaultDataPayEasyAtm',
            'appendValidation' => 'appendValidationPayEasyAtm',
            'getData' => 'getDataPayEasyAtm',
        ],
        // ペイジー（ネットバンク）
        PayEasyNet::class => [
            'appendForm' => 'appendFormPayEasyNet',
            'setData' => 'setDataPayEasyNet',
            'setDefaultData' => 'setDefaultDataPayEasyNet',
            'appendValidation' => 'appendValidationPayEasyNet',
            'getData' => 'getDataPayEasyNet',
        ],
        // auかんたん決済
        CarAu::class => [
            'appendForm' => 'appendFormAu',
            'setData' => 'setDataAu',
            'setDefaultData' => 'setDefaultDataAu',
            'appendValidation' => 'appendValidationAu',
            'getData' => 'getDataAu',
        ],
        // ドコモケータイ払い
        CarDocomo::class => [
            'appendForm' => 'appendFormDocomo',
            'setData' => 'setDataDocomo',
            'setDefaultData' => 'setDefaultDataDocomo',
            'appendValidation' => 'appendValidationDocomo',
            'getData' => 'getDataDocomo',
        ],
        // ソフトバンクまとめて支払い
        CarSoftbank::class => [
            'appendForm' => 'appendFormSoftbank',
            'setData' => 'setDataSoftbank',
            'setDefaultData' => 'setDefaultDataSoftbank',
            'appendValidation' => 'appendValidationSoftbank',
            'getData' => 'getDataSoftbank',
        ],
        // 楽天ペイ
        RakutenPay::class => [
            'appendForm' => 'appendFormRakutenPay',
            'setData' => 'setDataRakutenPay',
            'setDefaultData' => 'setDefaultDataRakutenPay',
            'appendValidation' => 'appendValidationRakutenPay',
            'getData' => 'getDataRakutenPay',
        ],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA,
                                   function (FormEvent $event) {
            $form = $event->getForm();
            $Payment = $form->getData();
            $methodClass = $Payment->getMethodClass();
            $repo =& $this->gmoPaymentMethodRepository;

            // 支払方法毎に取得処理を行う
            if (isset(self::arrFunction[$methodClass])) {
                $func = self::arrFunction[$methodClass];

                // フォームを追加
                $funcName = $func['appendForm'];
                $this->$funcName($event);

                // 設定データをセット
                $config = $repo->getGmoPaymentMethodConfig($methodClass);
                if (!empty($config)) {
                    $funcName = $func['setData'];
                    $this->$funcName($event, $config);
                } else {
                    $funcName = $func['setDefaultData'];
                    $this->$funcName($event);
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT,
                                   function (FormEvent $event) {
            $form = $event->getForm();
            $Payment = $form->getData();
            $methodClass = $Payment->getMethodClass();

            // 支払方法毎に取得処理を行う
            if (isset(self::arrFunction[$methodClass])) {
                $func = self::arrFunction[$methodClass];

                // フォームの追加検証
                $funcName = $func['appendValidation'];
                $this->$funcName($event);
            }

            if ($form->isValid()) {
                $repo =& $this->gmoPaymentMethodRepository;

                // 支払方法毎に保存処理を行う
                if (isset(self::arrFunction[$methodClass])) {
                    $func = self::arrFunction[$methodClass];

                    // フォームからデータ取得
                    $funcName = $func['getData'];
                    $data = $this->$funcName($event);
                    $repo->saveGmoPaymentMethod($methodClass, $data);
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return PaymentRegisterType::class;
    }

    /**
     * クレジットカード決済向けのフォームを追加
     */
    private function appendFormCredit(FormEvent $event)
    {
        $PaymentUtil = PaymentUtil::getInstance();
        $form = $event->getForm();

        $form
            // 処理区分
            ->add('JobCd', ChoiceType::class, [
                'expanded' => true,
                'choices' => $PaymentUtil->getJobCds(CreditCard::class),
                'label' => trans('gmo_payment_gateway.admin.' .
                                 'payment_edit.credit.col1'),
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            // 支払い種別
            ->add('credit_pay_methods', ChoiceType::class, [
                'choices' => $PaymentUtil->getCreditPayMethod(),
                'required' => true,
                'expanded' => true,
                'multiple' => true,
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // セキュリティコード入力必須化
            ->add('use_securitycd', ChoiceType::class, [
                'expanded' => true,
                'mapped' => false,
                'choices' => [
                    trans('gmo_payment_gateway.admin.use') => 1,
                    trans('gmo_payment_gateway.admin.notuse') => 0,
                ],
            ])
            // セキュリティコードの空欄を許可する
            ->add('use_securitycd_option', ChoiceType::class, [
                'expanded' => true,
                'mapped' => false,
                'choices' => [
                    trans('gmo_payment_gateway.admin.permit') => 1,
                    trans('gmo_payment_gateway.admin.notpermit') => 0,
                ],
            ])
            // 本人認証サービス(3Dセキュア)
            ->add('TdFlag', ChoiceType::class, [
                'expanded' => true,
                'mapped' => false,
                'choices' => [
                    trans('gmo_payment_gateway.admin.use') => 1,
                    trans('gmo_payment_gateway.admin.notuse') => 0,
                ],
            ])
            // 3Dセキュア表示店舗名
            ->add('TdTenantName', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '18',
                ],
            ])
            // 決済完了案内タイトル
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '50',
                ],
            ])
            // 決済完了案内本文
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'area',
                    'maxlength' => '1000',
                ],
            ])
            // 自由項目1
            ->add('ClientField1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ])
            // 自由項目2
            ->add('ClientField2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ]);
    }

    private function setDataCredit(FormEvent $event, $data)
    {
        $form = $event->getForm();

        $form['JobCd']->setData($data['JobCd']);
        $form['credit_pay_methods']->setData($data['credit_pay_methods']);
        $form['use_securitycd']->setData($data['use_securitycd']);
        $form['use_securitycd_option']
            ->setData($data['use_securitycd_option']);
        $form['TdFlag']->setData($data['TdFlag']);
        $form['TdTenantName']->setData($data['TdTenantName']);
        $form['order_mail_title1']->setData($data['order_mail_title1']);
        $form['order_mail_body1']->setData($data['order_mail_body1']);
        $form['ClientField1']->setData($data['ClientField1']);
        $form['ClientField2']->setData($data['ClientField2']);
    }

    private function setDefaultDataCredit(FormEvent $event)
    {
        $form = $event->getForm();

        $form['order_mail_title1']
            ->setData(trans('gmo_payment_gateway.admin.' .
                            'payment_edit.order_mail_title1'));
        $form['use_securitycd']->setData(0);
        $form['use_securitycd_option']->setData(1);
        $form['TdFlag']->setData(0);
    }

    /**
     * クレジットカード決済向けのフォームの追加検証
     */
    private function appendValidationCredit(FormEvent $event)
    {
        // 処理なし
        ;
    }

    private function getDataCredit(FormEvent $event)
    {
        $form = $event->getForm();

        $data = [];

        $data['JobCd'] = $form['JobCd']->getData();
        $data['credit_pay_methods'] = $form['credit_pay_methods']->getData();
        $data['use_securitycd'] = $form['use_securitycd']->getData();
        $data['use_securitycd_option'] =
            $form['use_securitycd_option']->getData();
        $data['TdFlag'] = $form['TdFlag']->getData();
        $data['TdTenantName'] = $form['TdTenantName']->getData();
        $data['order_mail_title1'] = $form['order_mail_title1']->getData();
        $data['order_mail_body1'] = $form['order_mail_body1']->getData();
        $data['ClientField1'] = $form['ClientField1']->getData();
        $data['ClientField2'] = $form['ClientField2']->getData();

        return $data;
    }

    /**
     * コンビニ決済向けのフォームを追加
     */
    private function appendFormCvs(FormEvent $event)
    {
        $PaymentUtil = PaymentUtil::getInstance();
        $form = $event->getForm();

        $form
            // コンビニ選択
            ->add('conveni', ChoiceType::class, [
                'choices' => array_flip($this->PaymentHelperCvs->getConveni()),
                'required' => true,
                'expanded' => true,
                'multiple' => true,
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // 支払期限
            ->add('PaymentTermDay', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'maxlength' => '2',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new LessThanOrEqual([
                        'value' => 30,
                        'message' => trans(
                            'gmo_payment_gateway.admin.' .
                            'payment_edit.cvs.payment_term_day.validate1'
                        ),
                    ]),
                ],
            ])
            // PGメール送信
            ->add('enable_mail', ChoiceType::class, [
                'expanded' => true,
                'mapped' => false,
                'choices' => [
                    trans('gmo_payment_gateway.admin.use') => 1,
                    trans('gmo_payment_gateway.admin.notuse') => 0,
                ],
            ])
            // コンビニ単位PGメール送信有無
            ->add('enable_cvs_mails', ChoiceType::class, [
                'choices' => array_flip($this->PaymentHelperCvs->getConveni()),
                'expanded' => true,
                'multiple' => true,
                'mapped' => false,
            ])
            // POSレジ表示欄1（店名）
            ->add('RegisterDisp1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // POSレジ表示欄2
            ->add('RegisterDisp2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // POSレジ表示欄3
            ->add('RegisterDisp3', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // POSレジ表示欄4
            ->add('RegisterDisp4', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // POSレジ表示欄5
            ->add('RegisterDisp5', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // POSレジ表示欄6
            ->add('RegisterDisp6', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // POSレジ表示欄7
            ->add('RegisterDisp7', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // POSレジ表示欄8
            ->add('RegisterDisp8', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄1
            ->add('ReceiptsDisp1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄2
            ->add('ReceiptsDisp2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄3
            ->add('ReceiptsDisp3', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄4
            ->add('ReceiptsDisp4', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄5
            ->add('ReceiptsDisp5', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄6
            ->add('ReceiptsDisp6', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄7
            ->add('ReceiptsDisp7', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄8
            ->add('ReceiptsDisp8', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄9
            ->add('ReceiptsDisp9', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // レシート表示欄10
            ->add('ReceiptsDisp10', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // お問合せ先
            ->add('ReceiptsDisp11', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '42',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号1
            ->add('ReceiptsDisp12_1', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号2
            ->add('ReceiptsDisp12_2', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号3
            ->add('ReceiptsDisp12_3', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間1
            ->add('ReceiptsDisp13_1', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getHours(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間2
            ->add('ReceiptsDisp13_2', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getMinutes(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間3
            ->add('ReceiptsDisp13_3', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getHours(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間4
            ->add('ReceiptsDisp13_4', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getMinutes(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // 決済完了案内タイトル
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '50',
                ],
            ])
            // 決済完了案内本文
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'area',
                    'maxlength' => '1000',
                ],
            ])
            // 自由項目1
            ->add('ClientField1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ])
            // 自由項目2
            ->add('ClientField2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ]);
    }

    private function setDataCvs(FormEvent $event, $data)
    {
        $form = $event->getForm();

        $form['conveni']->setData($data['conveni']);
        $form['PaymentTermDay']->setData($data['PaymentTermDay']);
        $form['enable_mail']->setData($data['enable_mail']);
        $form['enable_cvs_mails']->setData($data['enable_cvs_mails']);
        $form['RegisterDisp1']->setData($data['RegisterDisp1']);
        $form['RegisterDisp2']->setData($data['RegisterDisp2']);
        $form['RegisterDisp3']->setData($data['RegisterDisp3']);
        $form['RegisterDisp4']->setData($data['RegisterDisp4']);
        $form['RegisterDisp5']->setData($data['RegisterDisp5']);
        $form['RegisterDisp6']->setData($data['RegisterDisp6']);
        $form['RegisterDisp7']->setData($data['RegisterDisp7']);
        $form['RegisterDisp8']->setData($data['RegisterDisp8']);
        $form['ReceiptsDisp1']->setData($data['ReceiptsDisp1']);
        $form['ReceiptsDisp2']->setData($data['ReceiptsDisp2']);
        $form['ReceiptsDisp3']->setData($data['ReceiptsDisp3']);
        $form['ReceiptsDisp4']->setData($data['ReceiptsDisp4']);
        $form['ReceiptsDisp5']->setData($data['ReceiptsDisp5']);
        $form['ReceiptsDisp6']->setData($data['ReceiptsDisp6']);
        $form['ReceiptsDisp7']->setData($data['ReceiptsDisp7']);
        $form['ReceiptsDisp8']->setData($data['ReceiptsDisp8']);
        $form['ReceiptsDisp9']->setData($data['ReceiptsDisp9']);
        $form['ReceiptsDisp10']->setData($data['ReceiptsDisp10']);
        $form['ReceiptsDisp11']->setData($data['ReceiptsDisp11']);
        $form['ReceiptsDisp12_1']->setData($data['ReceiptsDisp12_1']);
        $form['ReceiptsDisp12_2']->setData($data['ReceiptsDisp12_2']);
        $form['ReceiptsDisp12_3']->setData($data['ReceiptsDisp12_3']);
        $form['ReceiptsDisp13_1']->setData($data['ReceiptsDisp13_1']);
        $form['ReceiptsDisp13_2']->setData($data['ReceiptsDisp13_2']);
        $form['ReceiptsDisp13_3']->setData($data['ReceiptsDisp13_3']);
        $form['ReceiptsDisp13_4']->setData($data['ReceiptsDisp13_4']);
        $form['order_mail_title1']->setData($data['order_mail_title1']);
        $form['order_mail_body1']->setData($data['order_mail_body1']);
        $form['ClientField1']->setData($data['ClientField1']);
        $form['ClientField2']->setData($data['ClientField2']);
    }

    private function setDefaultDataCvs(FormEvent $event)
    {
        $form = $event->getForm();

        $form['enable_mail']->setData(0);
        $form['order_mail_title1']
            ->setData(trans('gmo_payment_gateway.admin.' .
                            'payment_edit.order_mail_title1'));
    }

    /**
     * コンビニ決済向けのフォームの追加検証
     */
    private function appendValidationCvs(FormEvent $event)
    {
        // 処理なし
        ;
    }

    private function getDataCvs(FormEvent $event)
    {
        $form = $event->getForm();

        $data = [];

        $data['conveni'] = $form['conveni']->getData();
        $data['PaymentTermDay'] = $form['PaymentTermDay']->getData();
        $data['enable_mail'] = $form['enable_mail']->getData();
        $data['enable_cvs_mails'] = $form['enable_cvs_mails']->getData();
        $data['RegisterDisp1'] = mb_convert_kana
            ($form['RegisterDisp1']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp2'] = mb_convert_kana
            ($form['RegisterDisp2']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp3'] = mb_convert_kana
            ($form['RegisterDisp3']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp4'] = mb_convert_kana
            ($form['RegisterDisp4']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp5'] = mb_convert_kana
            ($form['RegisterDisp5']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp6'] = mb_convert_kana
            ($form['RegisterDisp6']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp7'] = mb_convert_kana
            ($form['RegisterDisp7']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp8'] = mb_convert_kana
            ($form['RegisterDisp8']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp1'] = mb_convert_kana
            ($form['ReceiptsDisp1']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp2'] = mb_convert_kana
            ($form['ReceiptsDisp2']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp3'] = mb_convert_kana
            ($form['ReceiptsDisp3']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp4'] = mb_convert_kana
            ($form['ReceiptsDisp4']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp5'] = mb_convert_kana
            ($form['ReceiptsDisp5']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp6'] = mb_convert_kana
            ($form['ReceiptsDisp6']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp7'] = mb_convert_kana
            ($form['ReceiptsDisp7']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp8'] = mb_convert_kana
            ($form['ReceiptsDisp8']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp9'] = mb_convert_kana
            ($form['ReceiptsDisp9']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp10'] = mb_convert_kana
            ($form['ReceiptsDisp10']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp11'] = mb_convert_kana
            ($form['ReceiptsDisp11']->getData(), 'KV', 'UTF-8');
        $data['ReceiptsDisp12_1'] = $form['ReceiptsDisp12_1']->getData();
        $data['ReceiptsDisp12_2'] = $form['ReceiptsDisp12_2']->getData();
        $data['ReceiptsDisp12_3'] = $form['ReceiptsDisp12_3']->getData();
        $data['ReceiptsDisp13_1'] = $form['ReceiptsDisp13_1']->getData();
        $data['ReceiptsDisp13_2'] = $form['ReceiptsDisp13_2']->getData();
        $data['ReceiptsDisp13_3'] = $form['ReceiptsDisp13_3']->getData();
        $data['ReceiptsDisp13_4'] = $form['ReceiptsDisp13_4']->getData();
        $data['order_mail_title1'] = $form['order_mail_title1']->getData();
        $data['order_mail_body1'] = $form['order_mail_body1']->getData();
        $data['ClientField1'] = $form['ClientField1']->getData();
        $data['ClientField2'] = $form['ClientField2']->getData();

        return $data;
    }

    /**
     * Pay-easy(銀行ATM)決済向けのフォームを追加
     */
    private function appendFormPayEasyAtm(FormEvent $event)
    {
        $PaymentUtil = PaymentUtil::getInstance();
        $form = $event->getForm();

        $form
            // 支払期限
            ->add('PaymentTermDay', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'maxlength' => '2',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new LessThanOrEqual([
                        'value' => 30,
                        'message' => trans(
                            'gmo_payment_gateway.admin.' .
                            'payment_edit.payeasy_atm.' .
                            'payment_term_day.validate1'
                        ),
                    ]),
                ],
            ])
            // PGメール送信
            ->add('enable_mail', ChoiceType::class, [
                'expanded' => true,
                'mapped' => false,
                'choices' => [
                    trans('gmo_payment_gateway.admin.use') => 1,
                    trans('gmo_payment_gateway.admin.notuse') => 0,
                ],
            ])
            // ATM表示欄1（店名）
            ->add('RegisterDisp1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // ATM表示欄2
            ->add('RegisterDisp2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // ATM表示欄3
            ->add('RegisterDisp3', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // ATM表示欄4
            ->add('RegisterDisp4', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // ATM表示欄5
            ->add('RegisterDisp5', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // ATM表示欄6
            ->add('RegisterDisp6', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // ATM表示欄7
            ->add('RegisterDisp7', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // ATM表示欄8
            ->add('RegisterDisp8', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '16',
                ],
            ])
            // 利用明細表示欄1
            ->add('ReceiptsDisp1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄2
            ->add('ReceiptsDisp2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄3
            ->add('ReceiptsDisp3', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄4
            ->add('ReceiptsDisp4', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄5
            ->add('ReceiptsDisp5', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄6
            ->add('ReceiptsDisp6', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄7
            ->add('ReceiptsDisp7', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄8
            ->add('ReceiptsDisp8', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄9
            ->add('ReceiptsDisp9', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄10
            ->add('ReceiptsDisp10', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // お問合せ先
            ->add('ReceiptsDisp11', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '42',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号1
            ->add('ReceiptsDisp12_1', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号2
            ->add('ReceiptsDisp12_2', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号3
            ->add('ReceiptsDisp12_3', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間1
            ->add('ReceiptsDisp13_1', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getHours(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間2
            ->add('ReceiptsDisp13_2', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getMinutes(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間3
            ->add('ReceiptsDisp13_3', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getHours(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間4
            ->add('ReceiptsDisp13_4', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getMinutes(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // 決済完了案内タイトル
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '50',
                ],
            ])
            // 決済完了案内本文
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'area',
                    'maxlength' => '1000',
                ],
            ])
            // 自由項目1
            ->add('ClientField1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ])
            // 自由項目2
            ->add('ClientField2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ]);
    }

    private function setDataPayEasyAtm(FormEvent $event, $data)
    {
        $form = $event->getForm();

        $form['PaymentTermDay']->setData($data['PaymentTermDay']);
        $form['enable_mail']->setData($data['enable_mail']);
        $form['RegisterDisp1']->setData($data['RegisterDisp1']);
        $form['RegisterDisp2']->setData($data['RegisterDisp2']);
        $form['RegisterDisp3']->setData($data['RegisterDisp3']);
        $form['RegisterDisp4']->setData($data['RegisterDisp4']);
        $form['RegisterDisp5']->setData($data['RegisterDisp5']);
        $form['RegisterDisp6']->setData($data['RegisterDisp6']);
        $form['RegisterDisp7']->setData($data['RegisterDisp7']);
        $form['RegisterDisp8']->setData($data['RegisterDisp8']);
        $form['ReceiptsDisp1']->setData($data['ReceiptsDisp1']);
        $form['ReceiptsDisp2']->setData($data['ReceiptsDisp2']);
        $form['ReceiptsDisp3']->setData($data['ReceiptsDisp3']);
        $form['ReceiptsDisp4']->setData($data['ReceiptsDisp4']);
        $form['ReceiptsDisp5']->setData($data['ReceiptsDisp5']);
        $form['ReceiptsDisp6']->setData($data['ReceiptsDisp6']);
        $form['ReceiptsDisp7']->setData($data['ReceiptsDisp7']);
        $form['ReceiptsDisp8']->setData($data['ReceiptsDisp8']);
        $form['ReceiptsDisp9']->setData($data['ReceiptsDisp9']);
        $form['ReceiptsDisp10']->setData($data['ReceiptsDisp10']);
        $form['ReceiptsDisp11']->setData($data['ReceiptsDisp11']);
        $form['ReceiptsDisp12_1']->setData($data['ReceiptsDisp12_1']);
        $form['ReceiptsDisp12_2']->setData($data['ReceiptsDisp12_2']);
        $form['ReceiptsDisp12_3']->setData($data['ReceiptsDisp12_3']);
        $form['ReceiptsDisp13_1']->setData($data['ReceiptsDisp13_1']);
        $form['ReceiptsDisp13_2']->setData($data['ReceiptsDisp13_2']);
        $form['ReceiptsDisp13_3']->setData($data['ReceiptsDisp13_3']);
        $form['ReceiptsDisp13_4']->setData($data['ReceiptsDisp13_4']);
        $form['order_mail_title1']->setData($data['order_mail_title1']);
        $form['order_mail_body1']->setData($data['order_mail_body1']);
        $form['ClientField1']->setData($data['ClientField1']);
        $form['ClientField2']->setData($data['ClientField2']);
    }

    private function setDefaultDataPayEasyAtm(FormEvent $event)
    {
        $form = $event->getForm();

        $form['enable_mail']->setData(0);
        $form['order_mail_title1']
            ->setData(trans('gmo_payment_gateway.admin.' .
                            'payment_edit.order_mail_title1'));
        $body = $this->PaymentHelperPayEasyAtm->getMailBody();
        $form['order_mail_body1']->setData($body);
    }

    /**
     * Pay-easy(銀行ATM)決済向けのフォームの追加検証
     */
    private function appendValidationPayEasyAtm(FormEvent $event)
    {
        // 処理なし
        ;
    }

    private function getDataPayEasyAtm(FormEvent $event)
    {
        $form = $event->getForm();

        $data = [];

        $data['PaymentTermDay'] = $form['PaymentTermDay']->getData();
        $data['enable_mail'] = $form['enable_mail']->getData();
        $data['RegisterDisp1'] = mb_convert_kana
            ($form['RegisterDisp1']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp2'] = mb_convert_kana
            ($form['RegisterDisp2']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp3'] = mb_convert_kana
            ($form['RegisterDisp3']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp4'] = mb_convert_kana
            ($form['RegisterDisp4']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp5'] = mb_convert_kana
            ($form['RegisterDisp5']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp6'] = mb_convert_kana
            ($form['RegisterDisp6']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp7'] = mb_convert_kana
            ($form['RegisterDisp7']->getData(), 'KASV', 'UTF-8');
        $data['RegisterDisp8'] = mb_convert_kana
            ($form['RegisterDisp8']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp1'] = mb_convert_kana
            ($form['ReceiptsDisp1']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp2'] = mb_convert_kana
            ($form['ReceiptsDisp2']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp3'] = mb_convert_kana
            ($form['ReceiptsDisp3']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp4'] = mb_convert_kana
            ($form['ReceiptsDisp4']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp5'] = mb_convert_kana
            ($form['ReceiptsDisp5']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp6'] = mb_convert_kana
            ($form['ReceiptsDisp6']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp7'] = mb_convert_kana
            ($form['ReceiptsDisp7']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp8'] = mb_convert_kana
            ($form['ReceiptsDisp8']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp9'] = mb_convert_kana
            ($form['ReceiptsDisp9']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp10'] = mb_convert_kana
            ($form['ReceiptsDisp10']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp11'] = mb_convert_kana
            ($form['ReceiptsDisp11']->getData(), 'KV', 'UTF-8');
        $data['ReceiptsDisp12_1'] = $form['ReceiptsDisp12_1']->getData();
        $data['ReceiptsDisp12_2'] = $form['ReceiptsDisp12_2']->getData();
        $data['ReceiptsDisp12_3'] = $form['ReceiptsDisp12_3']->getData();
        $data['ReceiptsDisp13_1'] = $form['ReceiptsDisp13_1']->getData();
        $data['ReceiptsDisp13_2'] = $form['ReceiptsDisp13_2']->getData();
        $data['ReceiptsDisp13_3'] = $form['ReceiptsDisp13_3']->getData();
        $data['ReceiptsDisp13_4'] = $form['ReceiptsDisp13_4']->getData();
        $data['order_mail_title1'] = $form['order_mail_title1']->getData();
        $data['order_mail_body1'] = $form['order_mail_body1']->getData();
        $data['ClientField1'] = $form['ClientField1']->getData();
        $data['ClientField2'] = $form['ClientField2']->getData();

        return $data;
    }

    /**
     * Pay-easy(ネットバンク)決済向けのフォームを追加
     */
    private function appendFormPayEasyNet(FormEvent $event)
    {
        $PaymentUtil = PaymentUtil::getInstance();
        $form = $event->getForm();

        $form
            // 支払期限
            ->add('PaymentTermDay', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'maxlength' => '2',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new LessThanOrEqual([
                        'value' => 30,
                        'message' => trans(
                            'gmo_payment_gateway.admin.' .
                            'payment_edit.payeasy_net.' .
                            'payment_term_day.validate1'
                        ),
                    ]),
                ],
            ])
            // PGメール送信
            ->add('enable_mail', ChoiceType::class, [
                'expanded' => true,
                'mapped' => false,
                'choices' => [
                    trans('gmo_payment_gateway.admin.use') => 1,
                    trans('gmo_payment_gateway.admin.notuse') => 0,
                ],
            ])
            // 利用明細表示欄1
            ->add('ReceiptsDisp1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄2
            ->add('ReceiptsDisp2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄3
            ->add('ReceiptsDisp3', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄4
            ->add('ReceiptsDisp4', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄5
            ->add('ReceiptsDisp5', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄6
            ->add('ReceiptsDisp6', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄7
            ->add('ReceiptsDisp7', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄8
            ->add('ReceiptsDisp8', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄9
            ->add('ReceiptsDisp9', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // 利用明細表示欄10
            ->add('ReceiptsDisp10', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '30',
                ],
            ])
            // お問合せ先
            ->add('ReceiptsDisp11', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '42',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号1
            ->add('ReceiptsDisp12_1', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号2
            ->add('ReceiptsDisp12_2', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先電話番号3
            ->add('ReceiptsDisp12_3', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間1
            ->add('ReceiptsDisp13_1', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getHours(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間2
            ->add('ReceiptsDisp13_2', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getMinutes(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間3
            ->add('ReceiptsDisp13_3', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getHours(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // お問合せ先受付時間4
            ->add('ReceiptsDisp13_4', ChoiceType::class, [
                'required' => true,
                'choices' => $PaymentUtil->getMinutes(),
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // 決済完了案内タイトル
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '50',
                ],
            ])
            // 決済完了案内本文
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'area',
                    'maxlength' => '1000',
                ],
            ])
            // 自由項目1
            ->add('ClientField1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ])
            // 自由項目2
            ->add('ClientField2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ]);
    }

    private function setDataPayEasyNet(FormEvent $event, $data)
    {
        $form = $event->getForm();

        $form['PaymentTermDay']->setData($data['PaymentTermDay']);
        $form['enable_mail']->setData($data['enable_mail']);
        $form['ReceiptsDisp1']->setData($data['ReceiptsDisp1']);
        $form['ReceiptsDisp2']->setData($data['ReceiptsDisp2']);
        $form['ReceiptsDisp3']->setData($data['ReceiptsDisp3']);
        $form['ReceiptsDisp4']->setData($data['ReceiptsDisp4']);
        $form['ReceiptsDisp5']->setData($data['ReceiptsDisp5']);
        $form['ReceiptsDisp6']->setData($data['ReceiptsDisp6']);
        $form['ReceiptsDisp7']->setData($data['ReceiptsDisp7']);
        $form['ReceiptsDisp8']->setData($data['ReceiptsDisp8']);
        $form['ReceiptsDisp9']->setData($data['ReceiptsDisp9']);
        $form['ReceiptsDisp10']->setData($data['ReceiptsDisp10']);
        $form['ReceiptsDisp11']->setData($data['ReceiptsDisp11']);
        $form['ReceiptsDisp12_1']->setData($data['ReceiptsDisp12_1']);
        $form['ReceiptsDisp12_2']->setData($data['ReceiptsDisp12_2']);
        $form['ReceiptsDisp12_3']->setData($data['ReceiptsDisp12_3']);
        $form['ReceiptsDisp13_1']->setData($data['ReceiptsDisp13_1']);
        $form['ReceiptsDisp13_2']->setData($data['ReceiptsDisp13_2']);
        $form['ReceiptsDisp13_3']->setData($data['ReceiptsDisp13_3']);
        $form['ReceiptsDisp13_4']->setData($data['ReceiptsDisp13_4']);
        $form['order_mail_title1']->setData($data['order_mail_title1']);
        $form['order_mail_body1']->setData($data['order_mail_body1']);
        $form['ClientField1']->setData($data['ClientField1']);
        $form['ClientField2']->setData($data['ClientField2']);
    }

    private function setDefaultDataPayEasyNet(FormEvent $event)
    {
        $form = $event->getForm();

        $form['enable_mail']->setData(0);
        $form['order_mail_title1']
            ->setData(trans('gmo_payment_gateway.admin.' .
                            'payment_edit.order_mail_title1'));
        $body = $this->PaymentHelperPayEasyNet->getMailBody();
        $form['order_mail_body1']->setData($body);
    }

    /**
     * Pay-easy(ネットバンク)決済向けのフォームの追加検証
     */
    private function appendValidationPayEasyNet(FormEvent $event)
    {
        // 処理なし
        ;
    }

    private function getDataPayEasyNet(FormEvent $event)
    {
        $form = $event->getForm();

        $data = [];

        $data['PaymentTermDay'] = $form['PaymentTermDay']->getData();
        $data['enable_mail'] = $form['enable_mail']->getData();
        $data['ReceiptsDisp1'] = mb_convert_kana
            ($form['ReceiptsDisp1']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp2'] = mb_convert_kana
            ($form['ReceiptsDisp2']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp3'] = mb_convert_kana
            ($form['ReceiptsDisp3']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp4'] = mb_convert_kana
            ($form['ReceiptsDisp4']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp5'] = mb_convert_kana
            ($form['ReceiptsDisp5']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp6'] = mb_convert_kana
            ($form['ReceiptsDisp6']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp7'] = mb_convert_kana
            ($form['ReceiptsDisp7']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp8'] = mb_convert_kana
            ($form['ReceiptsDisp8']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp9'] = mb_convert_kana
            ($form['ReceiptsDisp9']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp10'] = mb_convert_kana
            ($form['ReceiptsDisp10']->getData(), 'KASV', 'UTF-8');
        $data['ReceiptsDisp11'] = mb_convert_kana
            ($form['ReceiptsDisp11']->getData(), 'KV', 'UTF-8');
        $data['ReceiptsDisp12_1'] = $form['ReceiptsDisp12_1']->getData();
        $data['ReceiptsDisp12_2'] = $form['ReceiptsDisp12_2']->getData();
        $data['ReceiptsDisp12_3'] = $form['ReceiptsDisp12_3']->getData();
        $data['ReceiptsDisp13_1'] = $form['ReceiptsDisp13_1']->getData();
        $data['ReceiptsDisp13_2'] = $form['ReceiptsDisp13_2']->getData();
        $data['ReceiptsDisp13_3'] = $form['ReceiptsDisp13_3']->getData();
        $data['ReceiptsDisp13_4'] = $form['ReceiptsDisp13_4']->getData();
        $data['order_mail_title1'] = $form['order_mail_title1']->getData();
        $data['order_mail_body1'] = $form['order_mail_body1']->getData();
        $data['ClientField1'] = $form['ClientField1']->getData();
        $data['ClientField2'] = $form['ClientField2']->getData();

        return $data;
    }

    /**
     * auかんたん決済向けのフォームを追加
     */
    private function appendFormAu(FormEvent $event)
    {
        $PaymentUtil = PaymentUtil::getInstance();
        $form = $event->getForm();

        $form
            // 処理区分
            ->add('JobCd', ChoiceType::class, [
                'expanded' => true,
                'choices' => $PaymentUtil->getJobCds(CarAu::class),
                'label' => trans('gmo_payment_gateway.admin.' .
                                 'payment_edit.au.col1'),
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            // 支払期限
            ->add('PaymentTermSec', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'maxlength' => '5',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new LessThanOrEqual([
                        'value' => 86400,
                        'message' => trans(
                            'gmo_payment_gateway.admin.' .
                            'payment_edit.au.payment_term_sec.validate1'
                        ),
                    ]),
                ],
            ])
            // サービス名（店名）
            ->add('ServiceName', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '48',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // 表示電話番号1
            ->add('ServiceTel_1', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // 表示電話番号2
            ->add('ServiceTel_2', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // 表示電話番号3
            ->add('ServiceTel_3', TextType::class, [
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'minlength' => '2',
                    'maxlength' => '4',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            // 決済完了案内タイトル
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '50',
                ],
            ])
            // 決済完了案内本文
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'area',
                    'maxlength' => '1000',
                ],
            ])
            // 自由項目1
            ->add('ClientField1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ])
            // 自由項目2
            ->add('ClientField2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ]);
    }

    private function setDataAu(FormEvent $event, $data)
    {
        $form = $event->getForm();

        $form['JobCd']->setData($data['JobCd']);
        $form['PaymentTermSec']->setData($data['PaymentTermSec']);
        $form['ServiceName']->setData($data['ServiceName']);
        $form['ServiceTel_1']->setData($data['ServiceTel_1']);
        $form['ServiceTel_2']->setData($data['ServiceTel_2']);
        $form['ServiceTel_3']->setData($data['ServiceTel_3']);
        $form['order_mail_title1']->setData($data['order_mail_title1']);
        $form['order_mail_body1']->setData($data['order_mail_body1']);
        $form['ClientField1']->setData($data['ClientField1']);
        $form['ClientField2']->setData($data['ClientField2']);
    }

    private function setDefaultDataAu(FormEvent $event)
    {
        $form = $event->getForm();

        $form['order_mail_title1']
            ->setData(trans('gmo_payment_gateway.admin.' .
                            'payment_edit.order_mail_title1'));
    }

    /**
     * auかんたん決済向けのフォームの追加検証
     */
    private function appendValidationAu(FormEvent $event)
    {
        $form = $event->getForm();
        $PaymentUtil = PaymentUtil::getInstance();

        // サービス名の禁止文字をチェック
        $len = mb_strlen($form['ServiceName']->getData());
        for ($i = 0; $i < $len; ++$i) {
            $tmp = mb_substr($form['ServiceName']->getData(), $i , 1);
            if ($PaymentUtil->isProhibitedChar($tmp)) {
                $form['ServiceName']->addError
                    (new FormError(trans('gmo_payment_gateway.admin.' .
                                         'payment_edit.au.action_error1',
                                         ['%char%' => $tmp])));
                PaymentUtil::logError(trans('gmo_payment_gateway.admin.' .
                                         'payment_edit.au.action_error1',
                                         ['%char%' => $tmp]));
                break;
            }
        }
    }

    private function getDataAu(FormEvent $event)
    {
        $form = $event->getForm();

        $data = [];

        $data['JobCd'] = $form['JobCd']->getData();
        $data['PaymentTermSec'] = $form['PaymentTermSec']->getData();
        $data['ServiceName'] =
            mb_convert_kana($form['ServiceName']->getData(), 'KVSA', 'UTF-8');
        $data['ServiceTel_1'] = $form['ServiceTel_1']->getData();
        $data['ServiceTel_2'] = $form['ServiceTel_2']->getData();
        $data['ServiceTel_3'] = $form['ServiceTel_3']->getData();
        $data['order_mail_title1'] = $form['order_mail_title1']->getData();
        $data['order_mail_body1'] = $form['order_mail_body1']->getData();
        $data['ClientField1'] = $form['ClientField1']->getData();
        $data['ClientField2'] = $form['ClientField2']->getData();

        return $data;
    }

    /**
     * ドコモケータイ払い向けのフォームを追加
     */
    private function appendFormDocomo(FormEvent $event)
    {
        $PaymentUtil = PaymentUtil::getInstance();
        $form = $event->getForm();

        $form
            // 処理区分
            ->add('JobCd', ChoiceType::class, [
                'expanded' => true,
                'choices' => $PaymentUtil->getJobCds(CarDocomo::class),
                'label' => trans('gmo_payment_gateway.admin.' .
                                 'payment_edit.docomo.col1'),
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            // 支払期限
            ->add('PaymentTermSec', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'maxlength' => '5',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new LessThanOrEqual([
                        'value' => 86400,
                        'message' => trans(
                            'gmo_payment_gateway.admin.' .
                            'payment_edit.docomo.payment_term_sec.validate1'
                        ),
                    ]),
                ],
            ])
            // ドコモ表示項目１
            ->add('DocomoDisp1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '40',
                ],
            ])
            // ドコモ表示項目２
            ->add('DocomoDisp2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '40',
                ],
            ])
            // 決済完了案内タイトル
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '50',
                ],
            ])
            // 決済完了案内本文
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'area',
                    'maxlength' => '1000',
                ],
            ])
            // 自由項目1
            ->add('ClientField1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ])
            // 自由項目2
            ->add('ClientField2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ]);
    }

    private function setDataDocomo(FormEvent $event, $data)
    {
        $form = $event->getForm();

        $form['JobCd']->setData($data['JobCd']);
        $form['PaymentTermSec']->setData($data['PaymentTermSec']);
        $form['DocomoDisp1']->setData($data['DocomoDisp1']);
        $form['DocomoDisp2']->setData($data['DocomoDisp2']);
        $form['order_mail_title1']->setData($data['order_mail_title1']);
        $form['order_mail_body1']->setData($data['order_mail_body1']);
        $form['ClientField1']->setData($data['ClientField1']);
        $form['ClientField2']->setData($data['ClientField2']);
    }

    private function setDefaultDataDocomo(FormEvent $event)
    {
        $form = $event->getForm();

        $form['order_mail_title1']
            ->setData(trans('gmo_payment_gateway.admin.' .
                            'payment_edit.order_mail_title1'));
    }

    /**
     * ドコモケータイ払い向けのフォームの追加検証
     */
    private function appendValidationDocomo(FormEvent $event)
    {
        $form = $event->getForm();
        $PaymentUtil = PaymentUtil::getInstance();

        // ドコモ表示項目１の禁止文字をチェック
        $len = mb_strlen($form['DocomoDisp1']->getData());
        for ($i = 0; $i < $len; ++$i) {
            $tmp = mb_substr($form['DocomoDisp1']->getData(), $i , 1);
            if ($PaymentUtil->isProhibitedChar($tmp)) {
                $form['DocomoDisp1']->addError
                    (new FormError(trans('gmo_payment_gateway.admin.' .
                                         'payment_edit.docomo.action_error1',
                                         ['%no%' => '１',
                                          '%char%' => $tmp])));
                PaymentUtil::logError(trans('gmo_payment_gateway.admin.' .
                                         'payment_edit.docomo.action_error1',
                                         ['%no%' => '１',
                                          '%char%' => $tmp]));
                break;
            }
        }

        // ドコモ表示項目２の禁止文字をチェック
        $len = mb_strlen($form['DocomoDisp2']->getData());
        for ($i = 0; $i < $len; ++$i) {
            $tmp = mb_substr($form['DocomoDisp2']->getData(), $i , 1);
            if ($PaymentUtil->isProhibitedChar($tmp)) {
                $form['DocomoDisp2']->addError
                    (new FormError(trans('gmo_payment_gateway.admin.' .
                                         'payment_edit.docomo.action_error1',
                                         ['%no%' => '２',
                                          '%char%' => $tmp])));
                PaymentUtil::logError(trans('gmo_payment_gateway.admin.' .
                                         'payment_edit.docomo.action_error1',
                                         ['%no%' => '２',
                                          '%char%' => $tmp]));
                break;
            }
        }
    }

    private function getDataDocomo(FormEvent $event)
    {
        $form = $event->getForm();

        $data = [];

        $data['JobCd'] = $form['JobCd']->getData();
        $data['PaymentTermSec'] = $form['PaymentTermSec']->getData();
        $data['DocomoDisp1'] =
            mb_convert_kana($form['DocomoDisp1']->getData(), 'KVSA', 'UTF-8');
        $data['DocomoDisp2'] =
            mb_convert_kana($form['DocomoDisp2']->getData(), 'KVSA', 'UTF-8');
        $data['order_mail_title1'] = $form['order_mail_title1']->getData();
        $data['order_mail_body1'] = $form['order_mail_body1']->getData();
        $data['ClientField1'] = $form['ClientField1']->getData();
        $data['ClientField2'] = $form['ClientField2']->getData();

        return $data;
    }

    /**
     * ソフトバンクまとめて支払い向けのフォームを追加
     */
    private function appendFormSoftbank(FormEvent $event)
    {
        $PaymentUtil = PaymentUtil::getInstance();
        $form = $event->getForm();

        $form
            // 処理区分
            ->add('JobCd', ChoiceType::class, [
                'expanded' => true,
                'choices' => $PaymentUtil->getJobCds(CarSoftbank::class),
                'label' => trans('gmo_payment_gateway.admin.' .
                                 'payment_edit.softbank.col1'),
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            // 支払期限
            ->add('PaymentTermSec', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width65',
                    'maxlength' => '5',
                    'pattern' => '\d*',
                ],
                'constraints' => [
                    new LessThanOrEqual([
                        'value' => 86400,
                        'message' => trans(
                            'gmo_payment_gateway.admin.' .
                            'payment_edit.softbank.payment_term_sec.validate1'
                        ),
                    ]),
                ],
            ])
            // 決済完了案内タイトル
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '50',
                ],
            ])
            // 決済完了案内本文
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'area',
                    'maxlength' => '1000',
                ],
            ])
            // 自由項目1
            ->add('ClientField1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ])
            // 自由項目2
            ->add('ClientField2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ]);
    }

    private function setDataSoftbank(FormEvent $event, $data)
    {
        $form = $event->getForm();

        $form['JobCd']->setData($data['JobCd']);
        $form['PaymentTermSec']->setData($data['PaymentTermSec']);
        $form['order_mail_title1']->setData($data['order_mail_title1']);
        $form['order_mail_body1']->setData($data['order_mail_body1']);
        $form['ClientField1']->setData($data['ClientField1']);
        $form['ClientField2']->setData($data['ClientField2']);
    }

    private function setDefaultDataSoftbank(FormEvent $event)
    {
        $form = $event->getForm();

        $form['order_mail_title1']
            ->setData(trans('gmo_payment_gateway.admin.' .
                            'payment_edit.order_mail_title1'));
    }

    /**
     * ソフトバンクまとめて支払い向けのフォームの追加検証
     */
    private function appendValidationSoftbank(FormEvent $event)
    {
        // 処理なし
        ;
    }

    private function getDataSoftbank(FormEvent $event)
    {
        $form = $event->getForm();

        $data = [];

        $data['JobCd'] = $form['JobCd']->getData();
        $data['PaymentTermSec'] = $form['PaymentTermSec']->getData();
        $data['order_mail_title1'] = $form['order_mail_title1']->getData();
        $data['order_mail_body1'] = $form['order_mail_body1']->getData();
        $data['ClientField1'] = $form['ClientField1']->getData();
        $data['ClientField2'] = $form['ClientField2']->getData();

        return $data;
    }

    /**
     * 楽天ペイ向けのフォームを追加
     */
    private function appendFormRakutenPay(FormEvent $event)
    {
        $PaymentUtil = PaymentUtil::getInstance();
        $form = $event->getForm();

        $form
            // 処理区分
            ->add('JobCd', ChoiceType::class, [
                'expanded' => true,
                'choices' => $PaymentUtil->getJobCds(RakutenPay::class),
                'label' => trans('gmo_payment_gateway.admin.' .
                                 'payment_edit.rakuten_pay.col1'),
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            // 決済完了案内タイトル
            ->add('order_mail_title1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '50',
                ],
            ])
            // 決済完了案内本文
            ->add('order_mail_body1', TextareaType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'area',
                    'maxlength' => '1000',
                ],
            ])
            // 自由項目1
            ->add('ClientField1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ])
            // 自由項目2
            ->add('ClientField2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'width222',
                    'maxlength' => '100',
                ],
            ]);
    }

    private function setDataRakutenPay(FormEvent $event, $data)
    {
        $form = $event->getForm();

        $form['JobCd']->setData($data['JobCd']);
        $form['order_mail_title1']->setData($data['order_mail_title1']);
        $form['order_mail_body1']->setData($data['order_mail_body1']);
        $form['ClientField1']->setData($data['ClientField1']);
        $form['ClientField2']->setData($data['ClientField2']);
    }

    private function setDefaultDataRakutenPay(FormEvent $event)
    {
        $form = $event->getForm();

        $form['order_mail_title1']
            ->setData(trans('gmo_payment_gateway.admin.' .
                            'payment_edit.order_mail_title1'));
    }

    /**
     * 楽天ペイ向けのフォームの追加検証
     */
    private function appendValidationRakutenPay(FormEvent $event)
    {
        // 処理なし
        ;
    }

    private function getDataRakutenPay(FormEvent $event)
    {
        $form = $event->getForm();

        $data = [];

        $data['JobCd'] = $form['JobCd']->getData();
        $data['order_mail_title1'] = $form['order_mail_title1']->getData();
        $data['order_mail_body1'] = $form['order_mail_body1']->getData();
        $data['ClientField1'] = $form['ClientField1']->getData();
        $data['ClientField2'] = $form['ClientField2']->getData();

        return $data;
    }
}
