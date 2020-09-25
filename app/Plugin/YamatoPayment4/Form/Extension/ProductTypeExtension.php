<?php
/*
 * Copyright(c)2016, Yamato Financial Co.,Ltd. All rights reserved.
 * Copyright(c)2016, Yamato Credit finance Co.,Ltd. All rights reserved.
 */


namespace Plugin\YamatoPayment4\Form\Extension;

use Eccube\Form\Type\Admin\ProductType;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Application;
use Eccube\Common\EccubeConfig;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Validator\Constraints as Assert;

use Plugin\YamatoPayment4\Repository\ConfigRepository;

/**
 * 商品タイプエクステンション
 */
class ProductTypeExtension extends AbstractTypeExtension
{
    protected $eccubeConfig;
    protected $yamatoConfigRepository;
    protected $entityManager;

    public function __construct(
        EccubeConfig $eccubeConfig,
        ConfigRepository $yamatoConfigRepository,
        EntityManagerInterface $entityManager
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * 設定画面の構築
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $self = $this;

        $builder
            ->add('reserve_date', DateType::class, [
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#admin_product_reserve_date',
                    'data-toggle' => 'datetimepicker',
                ],
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($self) {
                $form = $event->getForm();

                // 規格無し商品の場合
                if ($form->has('class')) {
                    $Product = $event->getData();
                    $ProductClass = $form['class']->getData();
                    $reserveDate = $form['reserve_date']->getData();

                    $userSettings = $self->yamatoConfigRepository->get()->getSubData();

                    //【契約】
                    //(1)オプションサービス契約なし
                    //(2)予約販売利用なし
                    if ($ProductClass['SaleType']['id'] == $self->eccubeConfig['SALE_TYPE_ID_RESERVE']
                        &&( $userSettings['use_option'] == 1
                        || $userSettings['advance_sale'] == 0 )
                    ) {
                        $form['class']->addError(new FormError('※ 予約商品は登録できません。販売種別を別の設定にするか、プラグイン設定を見直してください。'));
                    }

                    // 予約商品で予約商品出荷予定日が未入力の場合、エラー
                    if ($ProductClass['SaleType']['id'] == $self->eccubeConfig['SALE_TYPE_ID_RESERVE']
                        && empty($reserveDate)
                    ) {
                        $form['reserve_date']->addError(new FormError('※ 予約商品出荷予定日を選択してください。'));
                    }

                    if ($form->isValid()) {
                        $Product->setReserveDate($reserveDate);
                    }
                }
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return ProductType::class;
    }

}
