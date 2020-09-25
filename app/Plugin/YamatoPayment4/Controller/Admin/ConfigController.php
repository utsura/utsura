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

use Eccube\Entity\Delivery;
use Eccube\Entity\PaymentOption;
use Eccube\Controller\AbstractController;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\DeliveryRepository;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Plugin\YamatoPayment4\Form\Type\Admin\ConfigType;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Service\YamatoConfigService;
use Plugin\YamatoPayment4\Service\Client\UtilClientService;
use Plugin\YamatoPayment4\Service\Method\Credit;

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
     * @var DeliveryRepository
     */
    protected $deliveryRepository;

    /**
     * @var UtilClientService
     */
    protected $utilClientService;

    /**
     * ConfigController constructor.
     *
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        ConfigRepository $configRepository,
        PaymentRepository $paymentRepository,
        DeliveryRepository $deliveryRepository,
        UtilClientService $utilClientService
    )
    {
        $this->configRepository = $configRepository;
        $this->paymentRepository = $paymentRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->utilClientService = $utilClientService;
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

            // クレジットカード有効化時は、予約商品配送業者の支払い方法に追加
            foreach ($enablePayments as $enablePayment) {
                if ($enablePayment->getMethodClass() == Credit::class) {
                    $Delivery = $this->deliveryRepository->findOneBy(['SaleType' => $this->eccubeConfig['SALE_TYPE_ID_RESERVE']]);

                    if (count($Delivery->getPaymentOptions()) > 0) {
                        break;
                    }
                    $PaymentOption = new PaymentOption();
                    $PaymentOption
                        ->setPaymentId($enablePayment->getId())
                        ->setPayment($enablePayment)
                        ->setDeliveryId($Delivery->getId())
                        ->setDelivery($Delivery);
                    $Delivery->addPaymentOption($PaymentOption);

                    $this->entityManager->persist($Delivery);
                    $this->entityManager->flush();
                    break;
                }
            }

            $yamatoConfigService->undispPayment($sub_data['enable_payment_type']);

            $this->addSuccess('yamato_payment.admin.save.success', 'admin');

            return $this->redirectToRoute('yamato_payment4_admin_config');
        }

        return [
            'form' => $form->createView(),
            'sms_result_notification_url' => $this->generateUrl('yamato_sms_receive', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'cvs_result_notification_url' => $this->generateUrl('yamato_shopping_payment_recv', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/yamato_payment/config/ip_address", name="yamato_payment4_admin_config_ip_address")
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getGlobalIpAddress()
    {
        $global_ip_address = $this->utilClientService->doGetGlobalIpAddress();
        if (!$global_ip_address) {
            $errorMsgs = $this->utilClientService->getError();
            $result = $this->utilClientService->getResults();
            if ($result['errorCode'] === 'Z012000007') {
                $errorMsgs = [
                    'テスト環境申込みがされていないため、グローバルIPアドレスの照会は行えません。',
                ];
                return $this->json([
                    'status' => 'NG',
                    'error_messages' => $errorMsgs,
                ], 400);
            }
        }

        return $this->json([
            'status' => 'OK',
            'ip_address' => $global_ip_address,
        ], 200);
    }
}
