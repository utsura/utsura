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

namespace Plugin\YamatoPayment4\Form\Type\Admin;

use Eccube\Common\EccubeConfig;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type AS FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints AS Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use Plugin\YamatoPayment4\Entity\Config;
use Plugin\YamatoPayment4\Util\PluginUtil;

class ConfigType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * RepeatedPasswordType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $self = $this;

        $pluginUtil = new PluginUtil($this->eccubeConfig);

        $builder
            ->add('exec_mode', FormType\ChoiceType::class, [
                'label' => '動作モード',
                'mapped' => false,
                'choices' => array_flip($pluginUtil->getExecMode()),
                'expanded' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => '※ 動作モードが入力されていません。']),
                ],
            ])
            ->add('enable_payment_type', FormType\ChoiceType::class, [
                'label' => '有効にする決済方法',
                'mapped' => false,
                'choices' => array_flip($pluginUtil->getPaymentTypeNames()),
                'expanded' => true,
                'multiple' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => '※ 決済方法が選択されていません。']),
                ],
            ])

            ->add('shop_id', FormType\TextType::class, [
                'label' => 'クロネコｗｅｂコレクト加盟店コード',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => '9',
                ],
                'constraints' => [
                    new Assert\Callback(function (
                        $objcet,
                        ExecutionContextInterface $context
                    ) {
                        $enable_payment_type = $context->getRoot()->get('enable_payment_type')->getData();
                        if ((in_array($this->eccubeConfig['YAMATO_PAYID_CREDIT'], $enable_payment_type, true)
                            || in_array($this->eccubeConfig['YAMATO_PAYID_CVS'], $enable_payment_type, true))
                            && !$objcet)
                        {
                            $context->buildViolation('※ クロネコｗｅｂコレクト加盟店コードが入力されていません。')
                                ->atPath('shop_id')
                                ->addViolation()
                            ;
                        }
                    }),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ クロネコｗｅｂコレクト加盟店コードは数字で入力してください。'
                    ]),
                    new Assert\Length([
                        'max' => 9,
                        'maxMessage' => "※ クロネコｗｅｂコレクト加盟店コードは9字以下で入力してください。"
                    ]),
                ],
            ])
            ->add('access_key', FormType\TextType::class, [
                'label' => 'アクセスキー',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => '7',
                ],
                'constraints' => [
                    new Assert\Callback(function (
                        $objcet,
                        ExecutionContextInterface $context
                    ) {
                        $enable_payment_type = $context->getRoot()->get('enable_payment_type')->getData();
                        if ((in_array($this->eccubeConfig['YAMATO_PAYID_CREDIT'], $enable_payment_type, true)
                            || in_array($this->eccubeConfig['YAMATO_PAYID_CVS'], $enable_payment_type, true))
                            && !$objcet)
                        {
                            $context->buildViolation('※ アクセスキーが入力されていません。')
                                ->atPath('access_key')
                                ->addViolation()
                            ;
                        }
                    }),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ アクセスキーは数字で入力してください。'
                    ]),
                    new Assert\Length([
                        'max' => 7,
                        'maxMessage' => "※ アクセスキーは7字以下で入力してください。"
                    ]),
                ],
            ])
            ->add('use_option', FormType\ChoiceType::class, [
                'label' => 'オプションサービス',
                'mapped' => false,
                'choices' => array_flip($pluginUtil->getUseOption()),
                'expanded' => true,
            ])
            ->add('advance_sale', FormType\ChoiceType::class, [
                'label' => '予約販売機能',
                'mapped' => false,
                'choices' => array_flip($pluginUtil->getUtilization()),
                'expanded' => true,
            ])

            ->add('ycf_str_code', FormType\TextType::class, [
                'label' => 'クロネコ代金後払い加盟店コード',
                'mapped' => false, 
                'required' => false,
                'attr' => [
                    'maxlength' => '11',
                ],
                'constraints' => [
                    new Assert\Callback(function (
                        $objcet,
                        ExecutionContextInterface $context
                    ) {
                        $enable_payment_type = $context->getRoot()->get('enable_payment_type')->getData();
                        if (in_array(60, $enable_payment_type, true) && !$objcet) {
                            $context->buildViolation('※ クロネコ代金後払い加盟店コードが入力されていません。')
                                ->atPath('ycf_str_code')
                                ->addViolation()
                            ;
                        }
                    }),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ クロネコ代金後払い加盟店コードは数字で入力してください。'
                    ]),
                    new Assert\Length([
                        'max' => 11,
                        'maxMessage' => "※ クロネコ代金後払い加盟店コードは11字で入力してください。"
                    ]),
                    new Assert\Length([
                        'min' => 11,
                        'minMessage' => "※ クロネコ代金後払い加盟店コードは11字で入力してください。"
                    ]),
                ],
            ])
            ->add('ycf_str_password', FormType\TextType::class, [
                'label' => 'パスワード',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => '8',
                ],
                'constraints' => [
                    new Assert\Callback(function (
                        $objcet,
                        ExecutionContextInterface $context
                    ) {
                        $enable_payment_type = $context->getRoot()->get('enable_payment_type')->getData();
                        if (in_array(60, $enable_payment_type, true) && !$objcet) {
                            $context->buildViolation('※ パスワードが入力されていません。')
                                ->atPath('ycf_str_password')
                                ->addViolation()
                            ;
                        }
                    }),
                    new Assert\Regex([
                        'pattern' => "/^[a-zA-Z0-9]+$/",
                        'match' => true,
                        'message' => '※ パスワードは半角英数字で入力してください。'
                    ]),
                    new Assert\Length([
                        'max' => 8,
                        'maxMessage' => "※ パスワードは8字で入力してください。"
                    ]),
                    new Assert\Length([
                        'min' => 8,
                        'minMessage' => "※ パスワードは8字で入力してください。"
                    ]),
                ],
            ])
            ->add('ycf_send_div', FormType\ChoiceType::class, [
                'label' => '請求書の同梱',
                'mapped' => false,
                'choices' => array_flip($pluginUtil->getSendDivision()),
                'expanded' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => '※ 請求書の同梱が入力されていません。']),
                ],
            ])
            ->add('ycf_sms_flg', FormType\ChoiceType::class, [
                'label' => 'スマホタイプ利用有無',
                'mapped' => false,
                'choices' => array_flip($pluginUtil->getUtilization()),
                'expanded' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => '※ スマホタイプ利用有無が入力されていません。']),
                ],
            ])
            ->add('ycf_ship_ymd', FormType\TextType::class, [
                'label' => '出荷予定日',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => '2',
                ],
                'constraints' => [
                    new Assert\Callback(function (
                        $objcet,
                        ExecutionContextInterface $context
                    ) {
                        $enable_payment_type = $context->getRoot()->get('enable_payment_type')->getData();
                        if (in_array(60, $enable_payment_type, true) && !$objcet) {
                            $context->buildViolation('※ 出荷予定日が入力されていません。')
                                ->atPath('ycf_ship_ymd')
                                ->addViolation()
                            ;
                        }
                    }),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ 出荷予定日は数字で入力してください。'
                    ]),
                    new Assert\Length([
                        'max' => 2,
                        'maxMessage' => "※ 出荷予定日は2桁以下で入力してください。"
                    ]),
                ],
            ])
            ->add('ycf_deliv_disp', FormType\ChoiceType::class, [
                'label' => 'メールの追跡情報表示機能',
                'mapped' => false,
                'choices' => array_flip($pluginUtil->getUtilization()),
                'expanded' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => '※ メールの追跡情報表示機能が入力されていません。']),
                ],
            ])
            ->add('ycf_invoice_reissue_mail_address', FormType\TextType::class, [
                'label' => '請求書再発行通知メール：受取メールアドレス',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Callback(function (
                        $objcet,
                        ExecutionContextInterface $context
                    ) {
                        $enable_payment_type = $context->getRoot()->get('enable_payment_type')->getData();
                        if (in_array(60, $enable_payment_type, true) && !$objcet) {
                            $context->buildViolation('※ 請求書再発行通知メール：受取メールアドレスが入力されていません。')
                                ->atPath('ycf_invoice_reissue_mail_address')
                                ->addViolation()
                            ;
                        }
                    }),
                    new Assert\Email(['message' => '※ メールアドレスの形式が不正です。']),
                ],
            ])
            ->add('ycf_invoice_reissue_mail_header', FormType\TextareaType::class, [
                'label' => '請求書再発行通知メール：メールヘッダー',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Callback(function (
                        $objcet,
                        ExecutionContextInterface $context
                    ) {
                        $enable_payment_type = $context->getRoot()->get('enable_payment_type')->getData();
                        if (in_array(60, $enable_payment_type, true) && !$objcet) {
                            $context->buildViolation('※ 請求書再発行通知メール：メールヘッダーが入力されていません。')
                                ->atPath('ycf_invoice_reissue_mail_header')
                                ->addViolation()
                            ;
                        }
                    }),
                ],
            ])
            ->add('ycf_invoice_reissue_mail_footer', FormType\TextareaType::class, [
                'label' => '請求書再発行通知メール：メールフッター',
                'mapped' => false,
                'required' => false,
            ])
            
            ->add('delivery_service_code', FormType\ChoiceType::class, [
                'label' => '他社配送設定',
                'mapped' => false,
                'choices' => array_flip($pluginUtil->getDeliveryServiceCode()),
                'expanded' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => '※ 他社配送設定が入力されていません。']),
                ],
            ])
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($self) {

                $data = $event->getData();

                $subdata = $data->getSubData();
                // 初期値設定
                $self->setDefaultData($subdata);

                $form = $event->getForm();
                foreach($subdata as $key => $val) {
                    $form[$key]->setData($val);
                }
            })
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                $form = $event->getForm();
                $sub_data = [];
                foreach($form as $key => $f) {
                    if($key != 'sub_data') {
                        $sub_data[$key] = $f->getData();
                    }
                }

                $Config = $event->getData();
                $Config->setSubData($sub_data);
                $Config->setAutoUpdateFlg(0);
                $Config->setDelFlg(0);
            });
;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }

    /**
     * 初期値設定
     *
     * @param array $subdata
     */
    public function setDefaultData(&$subdata)
    {
        // 動作モード
        if (!isset($subdata['exec_mode'])) {
            $subdata['exec_mode'] = '0';
        }

        // オプションサービス
        if (!isset($subdata['use_option'])) {
            $subdata['use_option'] = '1';
        }

        // 予約販売機能
        if (!isset($subdata['advance_sale'])) {
            $subdata['advance_sale'] = '0';
        }

        // 請求書の同梱
        if (!isset($subdata['ycf_send_div'])) {
            $subdata['ycf_send_div'] = '0';
        }

        // スマホタイプ利用有無
        if (!isset($subdata['ycf_sms_flg'])) {
            $subdata['ycf_sms_flg'] = '0';
        }

        // 出荷予定日
        if (!isset($subdata['ycf_ship_ymd'])) {
            $subdata['ycf_ship_ymd'] = '3';
        }

        // メールの追跡情報表示機能
        if (!isset($subdata['ycf_deliv_disp'])) {
            $subdata['ycf_deliv_disp'] = '0';
        }

        // 他社配送設定
        if (!isset($subdata['delivery_service_code'])) {
            $subdata['delivery_service_code'] = '00';
        }
    }
}
