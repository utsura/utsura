<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * https://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\YamatoPayment4\Service\Method;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Common\EccubeConfig;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;

use Plugin\YamatoPayment4\Entity\YamatoOrder;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository;
use Plugin\YamatoPayment4\Service\Client\CvsClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;

/**
 * コンビニ払いの決済処理を行う
 */
class Cvs implements PaymentMethodInterface
{
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
     * Credit
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
            EccubeConfig $eccubeConfig,
            PurchaseFlow $shoppingPurchaseFlow,
            OrderStatusRepository $orderStatusRepository,
            EntityManagerInterface $entityManager,
            RouterInterface $router,
            ConfigRepository $yamatoConfigRepository,
            YamatoPaymentMethodRepository $yamatoPaymentStatusRepository,
            YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
            YamatoOrderRepository $yamatoOrderRepository
    ) {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->router = $router;

        $this->eccubeConfig = $eccubeConfig;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->yamatoPaymentStatusRepository = $yamatoPaymentStatusRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;

        $this->client = new CvsClientService(
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
     * コンビニ決済は使用しない.
     *
     * @return PaymentResult|void
     */
    public function verify()
    {
        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * 注文時に呼び出される.
     *
     * 受注ステータス, 決済ステータスを更新する.
     * ここでは決済サーバとの通信は行わない.
     *
     * @return PaymentDispatcher
     */
    public function apply()
    {
        CommonUtil::printLog("Csv::apply start");
        // 受注ステータスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);
        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        $this->yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);

        // 決済ステータスを未決済へ変更
        $pluginData = [
            'order_id' => $this->Order->getId(),
            'status' => YamatoPaymentStatus::YAMATO_ACTION_STATUS_WAIT
        ];

        $this->updatePluginPurchaseLog($pluginData);
        CommonUtil::printLog("Csv::apply end");
    }

    /**
     * 注文時に呼び出される.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        // 決済サーバとの通信処理(コンビニ払い込み情報等の取得)
        CommonUtil::printLog("Cvs::checkout start");

        $form = $this->form;
        $this->yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);

        $formName = $form->getName();
        $listParam = $_POST[$formName];

        CommonUtil::printLog("Cvs::checkout doPaymentRequest:start");
        $PluginResult = $this->client->doPaymentRequest($this->Order, $listParam);

        $pluginData = $this->client->getSendResults();
        $pluginData['order_id'] = $this->Order->getId();

        if ($PluginResult) {
            CommonUtil::printLog("Cvs::checkout doPaymentRequest:success");
            // 受注ステータスを新規受付へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $this->Order->setOrderStatus($OrderStatus);

            // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
            $this->purchaseFlow->commit($this->Order, new PurchaseContext());
            $result = new PaymentResult();
            $result->setSuccess(true);

            // 決済ステータスを決済依頼済みへ変更
            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_SEND_REQUEST;
            $pluginData['settle_price'] = $this->Order->getPaymentTotal();
            $this->updatePluginPurchaseLog($pluginData);
        } else {
            CommonUtil::printLog("Cvs::checkout doPaymentRequest:error");
            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $this->Order->setOrderStatus($OrderStatus);

            // 失敗時はpurchaseFlow::rollbackを呼び出す.
            $this->purchaseFlow->rollback($this->Order, new PurchaseContext());

            $result = new PaymentResult();
            $result->setSuccess(false);
            $messages = array(trans('yamato_payment.shopping.checkout.error'));
            $messages = array_merge($messages, $this->client->getError());
            $result->setErrors($messages);

            // 決済ステータスを決済中断へ変更
            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_NG_TRANSACTION;
            $this->updatePluginPurchaseLog($pluginData);
        }

        CommonUtil::printLog("Cvs::checkout end");

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
        if (!$yamatoOrder) {
            $yamatoOrder = new YamatoOrder();
            $yamatoOrder->setOrder($this->Order);
        }
        $this->client->setSetting($this->Order);
        $payData = [];

        if (isset($data['status'])) {
            $payData['action_status'] = $data['status'];
            unset($data['status']);
        }

        $paymentCode = $this->client->getPaymentCode($this->Order);
        $payData['payment_code'] = $paymentCode;

        if (isset($data['settle_price'])) {
            $payData['settle_price'] = intval($data['settle_price']);
        }

        if (isset($data['function_div'])) {
            $payData['function_div'] = $data['function_div'];
        }

        if (isset($data['cvs'])) {
            $payData['cvs'] = $data['cvs'];
        }

        $yamatoOrder = $this->client->setOrderPayData($yamatoOrder, $payData);
        $this->entityManager->persist($yamatoOrder);
        $this->entityManager->flush();

        $yamatoOrder = $this->client->setOrderPaymentViewData($yamatoOrder, $data);
        $this->entityManager->persist($yamatoOrder);
        $this->entityManager->flush();
    }
}