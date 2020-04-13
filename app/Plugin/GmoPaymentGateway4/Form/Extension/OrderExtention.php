<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Form\Extension;

use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Repository\PaymentRepository;
use Plugin\GmoPaymentGateway4\Entity\GmoConfig;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentInput;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperMember;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperCvs;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperPayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperPayEasyNet;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperAu;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperDocomo;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperSoftbank;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperRakutenPay;
use Plugin\GmoPaymentGateway4\Service\Method\CreditCard;
use Plugin\GmoPaymentGateway4\Service\Method\Cvs;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyAtm;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyNet;
use Plugin\GmoPaymentGateway4\Service\Method\CarAu;
use Plugin\GmoPaymentGateway4\Service\Method\CarDocomo;
use Plugin\GmoPaymentGateway4\Service\Method\CarSoftbank;
use Plugin\GmoPaymentGateway4\Service\Method\RakutenPay;
use Plugin\GmoPaymentGateway4\Repository\GmoConfigRepository;
use Plugin\GmoPaymentGateway4\Repository\GmoPaymentMethodRepository;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * 注文手続き画面のFormを拡張し、カード入力フォームを追加する.
 * 支払い方法に応じてエクステンションを作成する.
 */
class OrderExtention extends AbstractTypeExtension
{
    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var GmoConfigRepository
     */
    protected $gmoConfigRepository;

    /**
     * @var GmoPaymentMethodRepository
     */
    protected $gmoPaymentMethodRepository;

    /**
     * @var PaymentHelperMember
     */
    protected $PaymentHelperMember;

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
     * @var PaymentHelperAu
     */
    protected $PaymentHelperAu;

    /**
     * @var PaymentHelperDocomo
     */
    protected $PaymentHelperDocomo;

    /**
     * @var PaymentHelperSoftbank
     */
    protected $PaymentHelperSoftbank;

    /**
     * @var PaymentHelperRakutenPay
     */
    protected $PaymentHelperRakutenPay;

    public function __construct
        (PaymentRepository $paymentRepository,
         GmoConfigRepository $gmoConfigRepository,
         GmoPaymentMethodRepository $gmoPaymentMethodRepository,
         PaymentHelperMember $PaymentHelperMember,
         PaymentHelperCvs $PaymentHelperCvs,
         PaymentHelperPayEasyAtm $PaymentHelperPayEasyAtm,
         PaymentHelperPayEasyNet $PaymentHelperPayEasyNet,
         PaymentHelperAu $PaymentHelperAu,
         PaymentHelperDocomo $PaymentHelperDocomo,
         PaymentHelperSoftbank $PaymentHelperSoftbank,
         PaymentHelperRakutenPay $PaymentHelperRakutenPay)
    {
        $this->paymentRepository = $paymentRepository;
        $this->gmoConfigRepository = $gmoConfigRepository;
        $this->gmoPaymentMethodRepository = $gmoPaymentMethodRepository;
        $this->PaymentHelperMember = $PaymentHelperMember;
        $this->PaymentHelperCvs = $PaymentHelperCvs;
        $this->PaymentHelperPayEasyAtm = $PaymentHelperPayEasyAtm;
        $this->PaymentHelperPayEasyNet = $PaymentHelperPayEasyNet;
        $this->PaymentHelperAu = $PaymentHelperAu;
        $this->PaymentHelperDocomo = $PaymentHelperDocomo;
        $this->PaymentHelperSoftbank = $PaymentHelperSoftbank;
        $this->PaymentHelperRakutenPay = $PaymentHelperRakutenPay;
    }

    const arrFunction = [
        // クレジットカード
        CreditCard::class => [
            'appendForm' => 'appendFormCredit',
            'helper' => 'PaymentHelperMember',
            'setData' => 'setDataCredit',
        ],
        // コンビニ
        Cvs::class => [
            'appendForm' => 'appendFormCvs',
            'helper' => 'PaymentHelperCvs',
            'setData' => 'setDataCvs',
        ],
        // ペイジー（銀行ATM）
        PayEasyAtm::class => [
            'appendForm' => 'appendFormPayEasyAtm',
            'helper' => 'PaymentHelperPayEasyAtm',
            'setData' => 'setDataPayEasyAtm',
        ],
        // ペイジー（ネットバンク）
        PayEasyNet::class => [
            'appendForm' => 'appendFormPayEasyNet',
            'helper' => 'PaymentHelperPayEasyNet',
            'setData' => 'setDataPayEasyNet',
        ],
        // auかんたん決済
        CarAu::class => [
            'appendForm' => 'appendFormAu',
            'helper' => 'PaymentHelperAu',
            'setData' => 'setDataAu',
        ],
        // ドコモケータイ払い
        CarDocomo::class => [
            'appendForm' => 'appendFormDocomo',
            'helper' => 'PaymentHelperDocomo',
            'setData' => 'setDataDocomo',
        ],
        // ソフトバンクまとめて支払い
        CarSoftbank::class => [
            'appendForm' => 'appendFormSoftbank',
            'helper' => 'PaymentHelperSoftbank',
            'setData' => 'setDataSoftbank',
        ],
        // 楽天ペイ
        RakutenPay::class => [
            'appendForm' => 'appendFormRakutenPay',
            'helper' => 'PaymentHelperRakutenPay',
            'setData' => 'setDataRakutenPay',
        ],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA,
                                   function (FormEvent $event) {
            /** @var Order $data */
            $Order = $event->getData();
            $form = $event->getForm();

            // プラグイン設定をセット
            $config = $this->gmoConfigRepository->get();
            $Order->setGmoConfig($config);

            $methodClass = $Order->getPayment()->getMethodClass();
            $method_config = $this->gmoPaymentMethodRepository
                ->getGmoPaymentMethodConfig($methodClass);
            $Order->setGmoMethodConfig($method_config);

            // 支払方法毎に処理する
            if (isset(self::arrFunction[$methodClass])) {
                $func = self::arrFunction[$methodClass];

                // フォームに入力項目を追加
                $funcName = $func['appendForm'];
                $this->$funcName($event, $config, $method_config);

                // GMO-PG 情報を付加する
                $helperName = $func['helper'];
                $Order = $this->$helperName->prepareGmoInfoForOrder($Order);

                // 決済入力値をセットする
                $funcName = $func['setData'];
                $this->$funcName($event, $Order->getGmoPaymentInput());
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT,
                                   function (FormEvent $event) {
            $options = $event->getForm()->getConfig()->getOptions();

            // 注文確認->注文処理時はフォームは定義されない.
            if ($options['skip_add_form']) {
                /** @var Order $Order */
                $Order = $event->getForm()->getData();
                $Order->getPayment()->getId();

                return;
            } else {
                $data = $event->getData();
                $form = $event->getForm();

                /** @var Order $Order */
                $Order = $event->getForm()->getData();
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

    /**
     * クレジットカード決済向けのフォームを追加
     *
     * @param FormEvent $event フォームイベント
     * @param GmoConfig $config プラグイン設定
     * @param array $method_config 支払方法設定
     */
    private function appendFormCredit
        (FormEvent $event, GmoConfig $config, array $method_config)
    {
        $PaymentUtil =& PaymentUtil::getInstance();

        // 有効期限を生成
        $listYear = $PaymentUtil->getZeroYear(date('Y'), date('Y') + 15);
        $listMonth = $PaymentUtil->getZeroMonth();

        // 支払方法を生成
        $methods = [];
        if (!empty($method_config['credit_pay_methods'])) {
            $methods = $method_config['credit_pay_methods'];
        }
        $listPayMethod = $PaymentUtil->getFilterCreditPayMethod($methods);

        $form = $event->getForm();

        $form
            // 決済手段（通常入力 or 登録済み）
            ->add('payment_type', ChoiceType::class, [
                'choices' => [
                    trans('gmo_payment_gateway.com.payname.credit') => '0',
                    trans('gmo_payment_gateway.shopping.' .
                          'credit.title.register_card') => '1',
                ],
                'expanded' => true,
                'required' => true,
                'mapped' => false,
            ])
            // クレジットトークン
            ->add('token', HiddenType::class, [
                'required' => false,
                'mapped' => false,
            ])
            // マスクしたカード番号
            ->add('mask_card_no', HiddenType::class, [
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
            // カード名義人名（名）
            ->add('card_name1', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'maxlength' => '25',
                ],
            ])
            // カード名義人名（姓）
            ->add('card_name2', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'maxlength' => '24',
                ],
            ])
            // セキュリティコード
            ->add('security_code', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'maxlength' => '24',
                ],
            ])
            // 支払方法
            ->add('credit_pay_methods', ChoiceType::class, [
                'choices' => $listPayMethod,
                'mapped' => false,
            ])
            // カード情報登録
            ->add('register_card', CheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'label' => trans('gmo_payment_gateway.' .
                                 'shopping.credit.col6.label'),
            ])
            // 選択した登録済みカードのカード連番
            ->add('CardSeq', HiddenType::class, [
                'required' => false,
                'mapped' => false,
            ])
            // 登録済みカードの支払方法
            ->add('credit_pay_methods2', ChoiceType::class, [
                'choices' => $listPayMethod,
                'mapped' => false,
            ]);
    }

    private function setDataCredit
        (FormEvent $event, GmoPaymentInput $GmoPaymentInput)
    {
        $form = $event->getForm();

        if ($GmoPaymentInput->payment_type == "") {
            // 決済種別
            $GmoPaymentInput->payment_type = "0";
            $form['payment_type']->setData($GmoPaymentInput->payment_type);
            return;
        }

        // 決済種別
        $form['payment_type']->setData($GmoPaymentInput->payment_type);
        // カード有効期限
        $form['expire_month']->setData($GmoPaymentInput->expire_month);
        $form['expire_year']->setData($GmoPaymentInput->expire_year);
        // カード名義人名
        $form['card_name1']->setData($GmoPaymentInput->card_name1);
        $form['card_name2']->setData($GmoPaymentInput->card_name2);
        // 支払い方法
        $form['credit_pay_methods']
            ->setData($GmoPaymentInput->credit_pay_methods);
        // カード情報登録
        $form['register_card']->setData($GmoPaymentInput->register_card);

        // 登録済み情報
        // カード連番
        $form['CardSeq']->setData($GmoPaymentInput->CardSeq);
        // 支払い方法
        $form['credit_pay_methods2']
            ->setData($GmoPaymentInput->credit_pay_methods2);
    }

    /**
     * コンビニ決済向けのフォームを追加
     *
     * @param FormEvent $event フォームイベント
     * @param GmoConfig $config プラグイン設定
     * @param array $method_config 支払方法設定
     */
    private function appendFormCvs
        (FormEvent $event, GmoConfig $config, array $method_config)
    {
        $PaymentUtil =& PaymentUtil::getInstance();

        $form = $event->getForm();
        $conveni = $this->PaymentHelperCvs->getConveni(true);

        $form
            // コンビニ選択
            ->add('Convenience', ChoiceType::class, [
                'choices' => array_flip($conveni),
                'required' => true,
                'expanded' => true,
                'mapped' => false,
            ]);
    }

    private function setDataCvs
        (FormEvent $event, GmoPaymentInput $GmoPaymentInput)
    {
        $form = $event->getForm();

        // コンビニ選択
        $form['Convenience']->setData($GmoPaymentInput->Convenience);
    }

    /**
     * Pay-easy(銀行ATM)決済向けのフォームを追加
     *
     * @param FormEvent $event フォームイベント
     * @param GmoConfig $config プラグイン設定
     * @param array $method_config 支払方法設定
     */
    private function appendFormPayEasyAtm
        (FormEvent $event, GmoConfig $config, array $method_config)
    {
        ;
    }

    private function setDataPayEasyAtm
        (FormEvent $event, GmoPaymentInput $GmoPaymentInput)
    {
        ;
    }

    /**
     * Pay-easy(ネットバンク)決済向けのフォームを追加
     *
     * @param FormEvent $event フォームイベント
     * @param GmoConfig $config プラグイン設定
     * @param array $method_config 支払方法設定
     */
    private function appendFormPayEasyNet
        (FormEvent $event, GmoConfig $config, array $method_config)
    {
        ;
    }

    private function setDataPayEasyNet
        (FormEvent $event, GmoPaymentInput $GmoPaymentInput)
    {
        ;
    }

    /**
     * auかんたん決済向けのフォームを追加
     *
     * @param FormEvent $event フォームイベント
     * @param GmoConfig $config プラグイン設定
     * @param array $method_config 支払方法設定
     */
    private function appendFormAu
        (FormEvent $event, GmoConfig $config, array $method_config)
    {
        ;
    }

    private function setDataAu
        (FormEvent $event, GmoPaymentInput $GmoPaymentInput)
    {
        ;
    }

    /**
     * ドコモケータイ払い向けのフォームを追加
     *
     * @param FormEvent $event フォームイベント
     * @param GmoConfig $config プラグイン設定
     * @param array $method_config 支払方法設定
     */
    private function appendFormDocomo
        (FormEvent $event, GmoConfig $config, array $method_config)
    {
        ;
    }

    private function setDataDocomo
        (FormEvent $event, GmoPaymentInput $GmoPaymentInput)
    {
        ;
    }

    /**
     * ソフトバンクまとめて支払い向けのフォームを追加
     *
     * @param FormEvent $event フォームイベント
     * @param GmoConfig $config プラグイン設定
     * @param array $method_config 支払方法設定
     */
    private function appendFormSoftbank
        (FormEvent $event, GmoConfig $config, array $method_config)
    {
        ;
    }

    private function setDataSoftbank
        (FormEvent $event, GmoPaymentInput $GmoPaymentInput)
    {
        ;
    }

    /**
     * 楽天ペイ向けのフォームを追加
     *
     * @param FormEvent $event フォームイベント
     * @param GmoConfig $config プラグイン設定
     * @param array $method_config 支払方法設定
     */
    private function appendFormRakutenPay
        (FormEvent $event, GmoConfig $config, array $method_config)
    {
        ;
    }

    private function setDataRakutenPay
        (FormEvent $event, GmoPaymentInput $GmoPaymentInput)
    {
        ;
    }
}
