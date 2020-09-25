<?php

namespace Plugin\YamatoPayment4\Service\Method;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;

use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;

use Plugin\YamatoPayment4\Entity\YamatoOrder;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository;
use Plugin\YamatoPayment4\Service\Client\DeferredSmsClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;


/**
 * クロネコ代金後払いの決済処理を行う.
 */
class DeferredSms implements PaymentMethodInterface
{
    use ControllerTrait;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var YamatoPaymentStatusRepository
     */
    private $yamatoPaymentStatusRepository;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    private $eccubeConfig;

    private $yamatoConfigRepository;

    private $yamatoPaymentMethodRepository;

    private $yamatoOrderRepository;

    private $client;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * DeferredSms
     * @param EccubeConfig $eccubeConfig
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param OrderStatusRepository $orderStatusRepository
     * @param RouterInterface $router
     * @param ConfigRepository $yamatoConfigRepository
     * @param YamatoPaymentMethodRepository $yamatoPaymentStatusRepository
     * @param YamatoPaymentMethodRepository $yamatoPaymentMethodRepository
     * @param YamatoOrderRepository $yamatoOrderRepository
     */
    public function __construct(
        ContainerInterface $container,
        EccubeConfig $eccubeConfig,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderStatusRepository $orderStatusRepository,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        ConfigRepository $yamatoConfigRepository,
        YamatoPaymentMethodRepository $yamatoPaymentStatusRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        \Twig_Environment $twig,
        SessionInterface $session
    ) {
        $this->container = $container;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->router = $router;

        $this->eccubeConfig = $eccubeConfig;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->yamatoPaymentStatusRepository = $yamatoPaymentStatusRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;
        $this->twig = $twig;
        $this->session = $session;

        $this->client = new DeferredSmsClientService(
            $this->eccubeConfig,
            $this->yamatoConfigRepository,
            $this->yamatoPaymentMethodRepository,
            $this->yamatoOrderRepository,
            $this->router
        );

        $this->entityManager = $entityManager;
    }

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * @return PaymentResult
     *
     * @throws \Eccube\Service\PurchaseFlow\PurchaseException
     */
    public function verify()
    {
        return false;
    }

    /**
     * 注文時に呼び出される.
     *
     * 受注ステータス, 決済ステータスを更新する.
     * ここでは決済サーバとの通信は行わない.
     *
     * @return PaymentDispatcher|null
     */
    public function apply()
    {
        CommonUtil::printLog("DeferredSms::apply start");
        // 受注ステータスを決済処理中へ変更
        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        $this->yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);

        // プラグイン側の受注管理テーブルに受注を作成する。
        $pluginData = ['order_id' => $this->Order->getId()];
        $this->updatePluginPurchaseLog($pluginData);

        CommonUtil::printLog("DeferredSms::apply end");
    }

    /**
     * 注文時に呼び出される.
     *
     * SMS後払いの決済処理を行う.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        CommonUtil::printLog("DeferredSms::checkout start");

        $authPhoneNumber = $this->form->get('phone_number')->getData();
        $this->yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);
        $listParam = $this->client->createPaymentRequestData($this->yamatoOrder, $authPhoneNumber);

        CommonUtil::printLog("DeferredSms::checkout :start");
        $PluginResult = $this->client->doPaymentRequest($this->Order, $listParam);

        $pluginData = $this->client->getSendResults();
        $pluginData['order_id'] = $this->Order->getId();

        if ($PluginResult == $this->eccubeConfig['DEFERRED_UNDER_EXAM']) {
            CommonUtil::printLog("DeferredSms::doPaymentRequest RESULT:OK. Trade is waiting Authorization Request");
            $result = new PaymentResult();
            $this->session->set('authPhoneNumber', $authPhoneNumber);
            // SMS認証画面へ遷移
            $result->setResponse($this->redirectToRoute('deferred_sms_auth'));
        } else {
            CommonUtil::printLog("DeferredSms::doPaymentRequest RESULT:error");

            // 受注ステータス変更
            $this->Order->setOrderStatus($this->orderStatusRepository->find(OrderStatus::PROCESSING));
            $this->purchaseFlow->rollback($this->Order, new PurchaseContext());

            // 決済ステータス変更(プラグイン独自定義のステータス)
            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_NG_TRANSACTION;
            $this->updatePluginPurchaseLog($pluginData);

            $result = new PaymentResult();
            $result->setSuccess(false);
            $messages = array(trans('yamato_payment.shopping.checkout.error'));
            $messages = array_merge($messages, $this->client->getError());
            $result->setErrors($messages);
        }

        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);
        $this->entityManager->flush();

        CommonUtil::printLog("DeferredSms::checkout end");
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormType(FormInterface $form)
    {
        $this->form = $form;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder(Order $Order)
    {
        $this->Order = $Order;
    }

    public function updatePluginPurchaseLog($data)
    {
        $yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);
        if(!$yamatoOrder) {
            $yamatoOrder = new YamatoOrder();
            $yamatoOrder->setOrder($this->Order);
        }
        $this->client->setSetting($this->Order);
        $payData = [];

        if(empty($data['status']) == false) {
            $payData['action_status'] = $data['status'];
            unset($data['status']);
        }

        $paymentCode = $this->client->getPaymentCode($this->Order);
        $payData['payment_code'] = $paymentCode;

        if(isset($data['settle_price'])) {
            $payData['settle_price'] = intval($data['settle_price']);
        }

        $yamatoOrder = $this->client->setOrderPayData($yamatoOrder, $payData);
        $yamatoOrder = $this->client->setOrderPaymentViewData($yamatoOrder, $data);
        $this->entityManager->persist($yamatoOrder);
        $this->entityManager->flush();
    }
}
