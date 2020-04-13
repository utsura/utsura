<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\GmoPaymentGateway4\Form\Type\Admin\ConfigType;
use Plugin\GmoPaymentGateway4\Repository\GmoConfigRepository;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ConfigController extends AbstractController
{
    /**
     * @var GmoConfigRepository
     */
    protected $gmoConfigRepository;

    /**
     * GmoConfigController constructor.
     *
     * @param GmoConfigRepository $gmoConfigRepository
     */
    public function __construct(
        GmoConfigRepository $gmoConfigRepository
    ) {
        $this->gmoConfigRepository = $gmoConfigRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/gmo_payment_gateway/config", name="gmo_payment_gateway4_admin_config")
     * @Template("@GmoPaymentGateway4/admin/config.twig")
     */
    public function index(Request $request)
    {
        $GmoConfig = $this->gmoConfigRepository->get();
        $form = $this->createForm(ConfigType::class, $GmoConfig);
        $form->handleRequest($request);

        // 保存処理
        if ($form->isSubmitted() && $form->isValid()) {
            // 決済プラグイン設定を保存する
            $GmoConfig = $form->getData();
            $this->entityManager->persist($GmoConfig);

            $this->entityManager->flush();

            $this->addSuccess
                ('gmo_payment_gateway.admin.save.success', 'admin');

            return $this->redirectToRoute('gmo_payment_gateway4_admin_config');
        }

        // PGマルチペイメントサービスについて
        $note_link1 = $this->eccubeConfig['gmo_payment_gateway.' .
                                          'admin.config.note_link1'];
        // テストアカウントについて
        $note_link2 = $this->eccubeConfig['gmo_payment_gateway.' .
                                          'admin.config.note_link2'];
        // 結果通知プログラムURL
        $recv_url = $this->generateUrl
            ('gmo_payment_gateway_receive',
             [], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            'form' => $form->createView(),
            'note_link1' => $note_link1,
            'note_link2' => $note_link2,
            'recv_url' => $recv_url,
        ];
    }
}
