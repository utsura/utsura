<?php

namespace Plugin\PayPalCheckout\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\PayPalCheckout\Entity\Config;
use Plugin\PayPalCheckout\Entity\PluginSetting;
use Plugin\PayPalCheckout\Exception\PayPalCheckoutException;
use Plugin\PayPalCheckout\Form\Type\Admin\ConfigType;
use Plugin\PayPalCheckout\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ConfigController
 * @package Plugin\PayPalCheckout\Controller\Admin
 */
class ConfigController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * ConfigController constructor.
     * @param ConfigRepository $configRepository
     */
    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * @Method("GET")
     * @Route("/%eccube_admin_route%/paypal/config", name="pay_pal_checkout_admin_config")
     * @Template("@PayPalCheckout/admin/config.twig")
     *
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        /** @var Config $config */
        $config = $this->configRepository->get();

        /** @var FormBuilder $builder */
        $builder = $this->formFactory->createBuilder(ConfigType::class, $config->createPluginSettingForm());

        /* @var Form $form */
        $form = $builder->getForm();

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * @Method("POST")
     * @Route("/%eccube_admin_route%/paypal/config", name="pay_pal_checkout_admin_config_submit")
     *
     * @param Request $request
     * @return array|RedirectResponse
     * @throws PayPalCheckoutException
     */
    public function post(Request $request)
    {
        /* @var Form $form */
        $form = $this->createForm(ConfigType::class);
        $form->handleRequest($request);

        switch (true) {
            case $form->isSubmitted() && $form->isValid():
                /** @var PluginSetting $PluginSetting */
                $PluginSetting = $form->getData();
                /** @var Config $Config */
                $Config = $this->configRepository->get();
                $Config->savePluginSetting($PluginSetting);
                $this->entityManager->persist($Config);
                $this->entityManager->flush();
                $this->addSuccess('登録しました。', 'admin');
                break;
            case !$form->isValid():
                $this->addError('失敗しました。', 'admin');
                return [
                    'form' => $form->createView(),
                ];
            default:
                throw new PayPalCheckoutException();
        }

        return $this->redirectToRoute('pay_pal_checkout_admin_config');
    }
}
