<?php

namespace Plugin\YamatoPayment4\Form\Type;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Eccube\Repository\OrderRepository;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;

use Plugin\YamatoPayment4\Entity\YamatoPaymentMethod;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Service\Method\Credit;
use Plugin\YamatoPayment4\Service\Method\Cvs;

class PaymentRecvType extends AbstractType
{
    /** @var EccubeConfig */
    private $eccubeConfig;

    /** @var OrderRepository */
    private $orderRepository;

    /** @var ConfigRepository */
    private $yamatoConfigRepository;

    /** @var YamatoPaymentMethodRepository */
    private $yamatoPaymentMethodRepository;

    public function __construct(
        EccubeConfig $eccubeConfig,
        OrderRepository $orderRepository,
        ConfigRepository $yamatoConfigRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->orderRepository = $orderRepository;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
    }

    /**
     * 決済結果受信パラメータ構築
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $userSettings = $this->yamatoConfigRepository->get()->getSubData();

        $builder
            ->add('trader_code', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => '※ trader_codeが入力されていません。',
                    ]),
                    new Assert\Length([
                        'max' => 20,
                        'maxMessage' => '※ trader_codeは20字以下で入力してください。',
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[[:graph:][:space:]]+$/i",
                        'match' => true,
                        'message' => '※ trader_codeは英数記号で入力してください。',
                    ]),
                ]
            ])
            ->add('order_no', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => '※ order_noが入力されていません。',
                    ]),
                    new Assert\Length([
                        'max' => 23,
                        'maxMessage' => '※ order_noは23字以下で入力してください。',
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ order_noは数字で入力してください。',
                    ]),
                ]
            ])
            ->add('function_div', TextType::class)
            ->add('settle_price', TextType::class, [
                'constraints' => [
                    new Assert\Length([
                        'max' => 7,
                        'maxMessage' => '※ settle_priceは7字以下で入力してください。',
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ settle_priceは数字で入力してください。',
                    ]),
                ]
            ])
            ->add('settle_date', TextType::class, [
                'constraints' => [
                    new Assert\Length([
                        'max' => 14,
                        'maxMessage' => '※ settle_dateは14字以下で入力してください。',
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ settle_dateは数字で入力してください。',
                    ]),
                ]
            ])
            ->add('settle_result', TextType::class, [
                'constraints' => [
                    new Assert\Length([
                        'max' => 1,
                        'maxMessage' => '※ settle_resultは数字で入力してください。',
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ settle_resultは数字で入力してください。',
                    ]),
                ]
            ])
            ->add('settle_detail', TextType::class, [
                'constraints' => [
                    new Assert\Length([
                        'max' => 2,
                        'maxMessage' => '※ settle_detailは2字以下で入力してください。',
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ settle_detailは数字で入力してください。',
                    ]),
                ]
            ])
            ->add('settle_method', TextType::class, [
                'constraints' => [
                    new Assert\Length([
                        'max' => 2,
                        'maxMessage' => '※ settle_methodは2字以下で入力してください。',
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[0-9]+$/",
                        'match' => true,
                        'message' => '※ settle_methodは数字で入力してください。',
                    ]),
                ]
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use($userSettings) {
            $form = $event->getForm();

            // ショップIDチェック
            if ($form['trader_code']->getData() != $userSettings['shop_id']) {
                $form['trader_code']->addError(
                    new FormError('※ shop_idが一致しません。')
                );
            }
            if (!$form->isValid()) {
                return;
            }

            // 注文データ取得
            $order_id = $form['order_no']->getData();
            $Order = $this->orderRepository->find($order_id);
            $Payment = $Order->getPayment();
            $paymentLists = [
                Credit::class,
                Cvs::class,
            ];
            if (!$Order instanceof Order || !in_array($Payment->getMethodClass(), $paymentLists)) {
                $form['order_no']->addError(
                    new FormError('※ order_no ' . $order_id . ' が存在しません。')
                );
                return;
            }

            // 決済データ取得
            $YamatoOrder = $Order->getYamatoOrder()->first();
            $memo05 = $YamatoOrder->getMemo05();

            // チェック処理
            if (!isset($memo05['function_div'])) {
                $form['function_div']->addError(
                    new FormError('※ ECサイトの注文は決済をご利用になっておりません。')
                );
                return;
            }

            // 支払方法チェック
            if ($this->isCheckPaymentMethod($form['settle_method']->getData(), $YamatoOrder->getMemo03())) {
                $form['settle_method']->addError(
                    new FormError('※ 支払方法が一致していません。(' . $YamatoOrder->getMemo03() . ')')
                );
                return;
            }

            // コンビニ種類チェック
            if (isset($memo05['cvs']) && $memo05['cvs'] != $form['settle_method']->getData()) {
                $form['settle_method']->addError(
                    new FormError('※ コンビニエンスストアの種類が異なります。(' . $memo05['cvs'] . ')')
                );
                return;
            }

            // 決済金額チェック
            if ($form['settle_price']->getData() != $Order->getPaymentTotal()) {
                $form['settle_price']->addError(
                    new FormError('※ 決済金額がECサイトのお支払い合計金額と異なります。(' . $Order->getPaymentTotal() . ')')
                );
                return;
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'allow_extra_fields' => true
        ]);
    }

    /**
     * 支払い方法チェック
     *
     * 戻り値
     *  true  支払方法不整合あり
     *  false 支払方法不整合なし
     *
     * POST値とpay_idとの支払方法整合性チェック
     * POST値では支払方法ではなく決済手段として送信されるため、以下のような間接的なチェックとする.
     *
     * (1)クレジットカード決済
     *    POST    settle_method 1～13, 99
     *            pay_id        10
     * (2)コンビニ決済
     *    POST    settle_method 21～25
     *            pay_id        30
     *
     * @param  string $settle_method 決済手段(POST)
     * @param  string $pay_id 決済タイプ（識別ID)
     * @return bool
     */
    private function isCheckPaymentMethod($settle_method, $pay_id)
    {
        $isError = false;

        // (1)クレジットカード決済
        if ($settle_method >= $this->eccubeConfig['CREDIT_METHOD_UC'] && $settle_method <= $this->eccubeConfig['CREDIT_METHOD_TOP']
            && $pay_id != $this->eccubeConfig['YAMATO_PAYID_CREDIT']
        ) {
            $isError = true;
        }
        // (2)コンビニ決済
        if ($settle_method >= $this->eccubeConfig['CONVENI_ID_SEVENELEVEN'] && $settle_method <= $this->eccubeConfig['CONVENI_ID_MINISTOP']
            && $pay_id != $this->eccubeConfig['YAMATO_PAYID_CVS']
        ) {
            $isError = true;
        }

        return $isError;
    }
}