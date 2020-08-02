<?php

namespace Plugin\PayPalCheckout;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Cart;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Entity\Shipping;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Eccube\Service\CartService;
use Eccube\Repository\PaymentRepository;
// use Eccube\Service\OrderHelper;
use Plugin\PayPalCheckout\Entity\Config;
use Plugin\PayPalCheckout\Exception\OtherPaymentMethodException;
use Plugin\PayPalCheckout\Repository\ConfigRepository;
use Plugin\PayPalCheckout\Service\LoggerService;
use Plugin\PayPalCheckout\Service\Method\BankTransfer;
use Plugin\PayPalCheckout\Service\Method\CreditCard;
use Plugin\PayPalCheckout\Service\PayPalOrderService;
use Plugin\PayPalCheckout\Service\PayPalService;
use Plugin\PayPalCheckout\PluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class Event
 * @package Plugin\PayPalCheckout
 */
class Event implements EventSubscriberInterface
{
    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var PayPalService
     */
    protected $paypalService;

    /**
     * @var PayPalOrderService
     */
    protected $paypalOrderService;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var Config
     */
    protected $Config;

    /**
     * @var LoggerService
     */
    protected $logger;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * PayPalCheckoutEvent constructor.
     *
     * @param CartService $cartService
     * @param PayPalOrderService $paypalOrderService
     * @param EccubeConfig $eccubeConfig
     * @param ConfigRepository $configRepository
     * @param ContainerInterface $container
     * @param LoggerService $loggerService
     * @param SessionInterface $session
     */
    public function __construct(
        CartService $cartService,
        PayPalService $payPalService,
        PayPalOrderService $paypalOrderService,
        EccubeConfig $eccubeConfig,
        ConfigRepository $configRepository,
        ContainerInterface $container,
        LoggerService $loggerService,
        SessionInterface $session)
    {
        $this->cartService = $cartService;
        $this->paypalService = $payPalService;
        $this->paypalOrderService = $paypalOrderService;
        $this->container = $container;
        $this->eccubeConfig = $eccubeConfig;
        $this->Config = $configRepository->get();
        $this->logger = $loggerService;
        $this->session = $session;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Shopping/login.twig' => 'onDefaultShoppingLoginTwig',
            'Shopping/index.twig' => 'onDefaultShoppingIndexTwig',
            'Shopping/confirm.twig' => 'onDefaultShoppingConfirmTwig',
            'Cart/index.twig' => 'onDefaultCartIndexTwig',
            'Block/paypal_logo.twig' => 'onDefaultPayPalLogoTwig',
            EccubeEvents::FRONT_SHOPPING_SHIPPING_COMPLETE => 'onChangedShippingAddress',
        ];
    }

    /**
     * @param TemplateEvent $event
     * @throws Exception\PayPalCheckoutException
     */
    public function onDefaultShoppingLoginTwig(TemplateEvent $event): void
    {
        $Carts = $this->cartService->getCart();
        if (is_null($Carts)) {
            return;
        }

        if ($this->paypalService::existsSubscriptionProductInCart($Carts)) {
            return;
        }
        if (!$this->paypalService->useExpressBtn()) {
            return;
        }

        // カート金額が決済可能金額でない場合、PayPalボタンを表示しない
        $amount = $this->paypalService::getCartAmount($Carts);
        if(!$this->isPayableAmount($amount)) {
            return;
        }

        list($snippet, $parameters) = $this->paypalOrderService->generateFrontEndParametersOnShoppingLoginPage($Carts);
        $parameters['PayPalCheckout']['Config'] = $this->Config;

        $this->logger->debug('Generate PayPal frontend parameters on /shipping/login page: ', [
            'snippet' => $snippet,
            'parameters' => $parameters,
        ]);

        $event->addSnippet($snippet);
        $event->addAsset('@PayPalCheckout/default/meta.twig');
        $event->setParameters(array_merge($event->getParameters(), $parameters));
    }

    /**
     * @param TemplateEvent $event
     * @throws Exception\PayPalCheckoutException
     */
    public function onDefaultCartIndexTwig(TemplateEvent $event): void
    {
//        $this->session->remove(OrderHelper::SESSION_NON_MEMBER);
//        $this->session->remove(OrderHelper::SESSION_NON_MEMBER_ADDRESSES);

        /** @var array $parameters */
        $parameters = $event->getParameters();
        if (empty($parameters['Carts'])) {
            return;
        }
        if (!$this->paypalService->useExpressBtn()) {
            return;
        }

        /** @var Cart $cart */
        $cart = $parameters['Carts'][0];

        // カート金額が決済可能金額でない場合、PayPalボタンを表示しない
        $amount = $this->paypalService::getCartAmount($cart);
        if(!$this->isPayableAmount($amount)) {
            return;
        }

        list($snippet, $parameters) = $this->paypalOrderService->generateFrontEndParametersOnCartIndexPage($cart);
        $parameters['PayPalCheckout']['Config'] = $this->Config;

        $this->logger->debug('Generate PayPal frontend parameters on /cart page: ', [
            'snippet' => $snippet,
            'parameters' => $parameters,
        ]);

        $event->addSnippet($snippet);
        $event->addAsset('@PayPalCheckout/default/meta.twig');
        $event->setParameters(array_merge($event->getParameters(), $parameters));
    }

    /**
     * @param TemplateEvent $event
     */
    public function onDefaultShoppingIndexTwig(TemplateEvent $event): void
    {
        /** @var array $parameters */
        $parameters = $event->getParameters();
        if (empty($parameters['Order'])) {
            return;
        }

        /** @var Payment $payment */
        $payment = $parameters['Order']->getPayment();

        // 支払い方法が何も選択されてない場合は、処理しない
        if(!isset($payment)) return;

        switch ($payment->getMethodClass()) {
            case BankTransfer::class || CreditCard::class:
                list($snippet, $parameters) = $this->paypalOrderService->generateFrontEndParametersOnShoppingIndexPage();
                $parameters['PayPalCheckout']['Config'] = $this->Config;

                $this->logger->debug('Generate PayPal frontend parameters on /shopping page: ', [
                    'snippet' => $snippet,
                    'parameters' => $parameters,
                ]);

                $event->addSnippet($snippet);
		        $event->addAsset('@PayPalCheckout/default/meta.twig');
                $event->setParameters(array_merge($event->getParameters(), $parameters));
                break;
            default:
                // do nothing
        }
    }

    /**
     * @param TemplateEvent $event
     */
    public function onDefaultShoppingConfirmTwig(TemplateEvent $event): void
    {
        /** @var Order $order */
        $order = $event->getParameter('Order');
        $isProcessingShortcutPayment = $this->paypalOrderService->isProcessingShortcutPayment();
        try {
            if ($isProcessingShortcutPayment) {
                $this->session->remove(PayPalOrderService::SESSION_SHORTCUT);
                $snippet = '@PayPalCheckout/default/Shopping/confirm/shortcut.twig';
            } else {
                list($snippet, $parameters) = $this->paypalOrderService->generateFrontEndParametersOnShoppingConfirmPage($order);
            }
            $parameters['PayPalCheckout']['Config'] = $this->Config;
        } catch (OtherPaymentMethodException $e) {
            // PayPal決済以外は処理終了
            return;
        }

        // 「注文する」ボタンが一瞬表示されてしまうので、あらかじめ非表示にしておく。
        // 逆にショートカット決済の場合は「注文する」ボタンを使うので処理しない。
        if (!$isProcessingShortcutPayment) {
            $source = $event->getSource();
            $pattern = '#<button[^>]*class="ec-blockBtn--action"[^>]*>[^<]+</button>#';
            $replacedSource = preg_replace($pattern, '<button class="ec-blockBtn--action" style="opacity: 0" disabled></button>', $source);
            if (!is_null($replacedSource)) {
                $event->setSource($replacedSource);
            }
        }

        $this->logger->debug('Generate PayPal frontend parameters on /shopping/confirm page: ', [
            'custom_data' => [
                'snippet' => $snippet,
                'parameters' => $parameters,
            ],
        ]);

        $event->addSnippet($snippet);
        $event->addAsset('@PayPalCheckout/default/meta.twig');
        $event->setParameters(array_merge($event->getParameters(), $parameters));
    }

    /**
     * @param EventArgs $event
     */
    public function onChangedShippingAddress(EventArgs $event): void
    {
        /** @var Shipping $shipping */
        $shipping = $event->getArgument('Shipping');
        $this->paypalOrderService->setShippingAddress($shipping);
    }

    /**
     * @param TemplateEvent $event
     */
    public function onDefaultPayPalLogoTwig(TemplateEvent $event): void
    {
        /** @var string $selectedNumber */
        $selectedNumber = $this->Config->getPaypalLogo();
        $parameters['PayPalCheckout']['paypal_logo'] = $this->eccubeConfig->get("paypal.paypal_express_paypal_logo_${selectedNumber}");
        $event->setParameters(array_merge($event->getParameters(), $parameters));
    }

    private function isPayableAmount($amount): bool
    {
        $paymentRepository = $this->container->get(PaymentRepository::class);
        $payment = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);
        if (!empty($payment)) {
            $minRule = $payment->getRuleMin();
            $maxRule = $payment->getRuleMax();
        }
        if(!isset($minRule) || $minRule < PluginManager::MIN_AMOUNT)
            $minRule = PluginManager::MIN_AMOUNT;
        if(!isset($maxRule) || $maxRule > PluginManager::MAX_AMOUNT)
            $maxRule = PluginManager::MAX_AMOUNT;

        if($amount < $minRule || $amount > $maxRule) {
            return false;
        }

        return true;

    }
}
