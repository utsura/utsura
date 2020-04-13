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

namespace Plugin\YamatoPayment4\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Repository\PaymentRepository;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Plugin\YamatoPayment4\Form\Type\Admin\ConfigType;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Service\YamatoConfigService;

class ConfigController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * ConfigController constructor.
     *
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        ConfigRepository $configRepository,
        PaymentRepository $paymentRepository
    )
    {
        $this->configRepository = $configRepository;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/yamato_payment/config", name="yamato_payment4_admin_config")
     * @Template("@YamatoPayment4/admin/config.twig")
     */
    public function index(Request $request, YamatoConfigService $yamatoConfigService)
    {
        $Config = $this->configRepository->get();
        $form = $this->createForm(ConfigType::class, $Config);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $Config = $form->getData();
            $this->entityManager->persist($Config);
            $this->entityManager->flush();

            $sub_data = $Config->getSubData();
            // SMSを利用する設定なら対象支払方法にSMSを追加する。
            if(in_array($this->eccubeConfig['YAMATO_PAYID']['DEFERRED'], $sub_data['enable_payment_type']) && $sub_data['ycf_sms_flg'] == 1) {
                $sub_data['enable_payment_type'][] = $this->eccubeConfig['YAMATO_PAYID']['SMS_DEFERRED'];
            }
            $enablePayments = $yamatoConfigService->savePayment($sub_data['enable_payment_type']);
            $yamatoConfigService->sevePaymentMethod($enablePayments);

            $yamatoConfigService->undispPayment($sub_data['enable_payment_type']);

            $this->addSuccess('yamato_payment.admin.save.success', 'admin');

            return $this->redirectToRoute('yamato_payment4_admin_config');
        }

        return [
            'form' => $form->createView(),
            'result_notification_url' => $this->generateUrl('yamato_sms_receive', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
