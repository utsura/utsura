<?php

namespace Plugin\YamatoPayment4\Form\Extension;

use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Form\Type\PhoneNumberType;
use Eccube\Repository\PaymentRepository;

use Plugin\YamatoPayment4\Service\Method\DeferredSms;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 注文手続き画面のFormを拡張し、携帯番号入力フォームを追加する.
 */
class DeferredSmsExtention extends AbstractTypeExtension
{
    protected $paymentRepository;

    public function __construct(
        PaymentRepository $paymentRepository,
        RequestStack $requestStack
    )
    {
        $this->paymentRepository = $paymentRepository;
        $this->requestStack = $requestStack;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            /**
             * 購入フローからの遷移時に 選択された決済方法 を$eventからうまく受け取れないため、
             * requestStackを混ぜた無理矢理な記述になっている。
             * 購入フローからの遷移時(confirm, redirect_to)にelseに入り、requestStackから決済方法を受け取る。
             */
            $request = $this->requestStack->getCurrentRequest();
            $dataBag = $request->request->get('_shopping_order');
            if($dataBag === null || !array_key_exists('Payment', $dataBag)) {
                $paymentId = $event->getData()->getPayment()->getId();
            } else {
                $paymentId = $dataBag['Payment'];
            }

            // 支払い方法が一致する場合
            $DeferredSms = $this->paymentRepository->findOneBy(['method_class' => DeferredSms::class]);
            if($DeferredSms === null) {
                return;
            } elseif ($paymentId == $DeferredSms->getId()) {
                $form->add(
                    'phone_number',
                    PhoneNumberType::class,
                    [
                        'label' => '携帯電話番号',
                        'attr' => [
                        ],
                        'required' => false,
                        'mapped' => false,
                    ]
                );
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $options = $event->getForm()->getConfig()->getOptions();

            // 注文確認->注文処理時はフォームは定義されない.
            if ($options['skip_add_form']) {
                return;
            } else {
                $Payment = $this->paymentRepository->findOneBy(['method_class' => DeferredSms::class]);
                if($Payment === null) {
                    return;
                }

                $data = $event->getData();
                $form = $event->getForm();

                // 支払い方法が一致しなければremove
                if (!isset($data['Payment']) || $Payment->getId() != $data['Payment']) {
                    $form->remove('phone_number');
                }
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
}
