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
use Eccube\Repository\Master\OrderStatusRepository;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Util as YamatoUtil;

class SearchPaymentType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    protected $configRepository;

    protected $orderStatusRepository;

    /**
     * RepeatedPasswordType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(
            EccubeConfig $eccubeConfig,
            ConfigRepository $configRepository,
            OrderStatusRepository $orderStatusRepository
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->configRepository = $configRepository;
        $this->orderStatusRepository = $orderStatusRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $pluginUtil = new YamatoUtil\PluginUtil($this->eccubeConfig);
        $paymentUtil = new YamatoUtil\PaymentUtil($this->eccubeConfig, $this->configRepository);
        $adjusts = $paymentUtil::adjustOrderStatus($this->orderStatusRepository, [$this->eccubeConfig['ORDER_SHIPPING_REGISTERED']]);
        $listOrderStatus = [];
        if($adjusts) {
            foreach($adjusts as $adjust) {
                $listOrderStatus[$adjust['id']] = $adjust['name'];
            }
        }

        $builder
            ->add('order_date_starts', DateType::class, [
                'label' => 'admin.order.order_date__starts',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_order_date_starts',
                    'data-toggle' => 'datetimepicker',
                ],
            ])
            ->add('order_date_ends', DateType::class, [
                'label' => 'admin.order.order_date__ends',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                'attr' => [
                    'class' => 'datetimepicker-input',
                    'data-target' => '#'.$this->getBlockPrefix().'_order_date_ends',
                    'data-toggle' => 'datetimepicker',
                ],
            ])
            ->add('OrderStatuses', ChoiceType::class, [
                'choices' => array_flip($listOrderStatus),
                    'expanded' => true,
                    'multiple' => true
            ])
            ->add('payment_status', ChoiceType::class, [
                'choices' => array_flip($pluginUtil->getYamatoBulkPaymentStatus()),
                'placeholder' => '-',
                'required' => false,
                'expanded' => false,
                'multiple' => false
            ])
            ->add('deferred_status', ChoiceType::class, [
                'choices' => array_flip($pluginUtil->getYamatoBulkDeferredStatus()),
                'placeholder' => '-',
                'required' => false,
                'expanded' => false,
                'multiple' => false
            ])
            ->add('status', ChoiceType::class, [
                    'choices' => array_flip($listOrderStatus),
                    'placeholder' => '-',
                    'required' => false,
                    'expanded' => false,
                    'multiple' => false
            ])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'search_payment';
    }
}
