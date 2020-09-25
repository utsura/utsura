<?php

namespace Plugin\YamatoPayment4\Form\Extension;

use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Repository\PaymentRepository;

use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Service\Method\Cvs;
use Plugin\YamatoPayment4\Util\PluginUtil;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 注文手続き画面のFormを拡張し、コンビニ選択フォームを追加する.
 */
class CvsExtension extends AbstractTypeExtension
{
    protected $paymentRepository;

    /** @var PluginUtil */
    protected $pluginUtil;

    /** @var YamatoPaymentMethodRepository */
    protected $yamatoPaymentMethodRepository;

    public function __construct(
        PaymentRepository $paymentRepository,
        PluginUtil $pluginUtil,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        RequestStack $requestStack
    )
    {
        $this->paymentRepository = $paymentRepository;
        $this->pluginUtil = $pluginUtil;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
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
            if ($dataBag === null || !array_key_exists('Payment', $dataBag)) {
                $paymentId = $event->getData()->getPayment()->getId();
            } else {
                $paymentId = $dataBag['Payment'];
            }

            $Cvs = $this->paymentRepository->findOneBy(['method_class' => Cvs::class]);
            if ($Cvs === null || $paymentId != $Cvs->getId()) {
                return;
            }

            $availableCvs = [];
            $paymentInfo = $this->yamatoPaymentMethodRepository->findOneBy(['Payment' => $paymentId])->getMemo05();
            $allCvs = $this->pluginUtil->getAllCvs();
            foreach ($paymentInfo['conveni'] as $cvsId) {
                if (isset($allCvs[$cvsId])) {
                    $availableCvs[$allCvs[$cvsId]] = $cvsId;
                }
            }
            asort($availableCvs);

            // 支払い方法が一致する場合
            $form->add('cvs', ChoiceType::class, [
                'choices' => $availableCvs,
                'expanded' => true,
                'placeholder' => false,
                'required' => false,
                'mapped' => false,
            ]);

        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $options = $event->getForm()->getConfig()->getOptions();

            // 注文確認->注文処理時はフォームは定義されない.
            if ($options['skip_add_form']) {
                return;
            } else {
                $Payment = $this->paymentRepository->findOneBy(['method_class' => Cvs::class]);
                if ($Payment === null) {
                    return;
                }

                $data = $event->getData();
                $form = $event->getForm();

                // 支払い方法が一致しなければremove
                if (!isset($data['Payment']) || $Payment->getId() != $data['Payment']) {
                    $form->remove('cvs');
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
