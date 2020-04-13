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

namespace Plugin\YamatoPayment4\Service\Method;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;

use Plugin\YamatoPayment4\Entity\YamatoOrder;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository;
use Plugin\YamatoPayment4\Service\Client\DeferredClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;

/**
 * クロネコ代金後払いの決済処理を行う.
 */
class Deferred implements PaymentMethodInterface
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
     * Deferred
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

        $this->client = new DeferredClientService(
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
        CommonUtil::printLog("Deferred::apply start");
        // 受注ステータスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);
        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        $this->yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);

        // プラグイン側の受注管理テーブルに受注を作成する。
        $pluginData = ['order_id' => $this->Order->getId()];

        $this->updatePluginPurchaseLog($pluginData);
        CommonUtil::printLog("Deferred::apply end");
    }

    /**
     * 注文時に呼び出される.
     *
     * クレジットカードの決済処理を行う.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        CommonUtil::printLog("Deferred::checkout start");

        $form = $this->form;

        $this->yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);
        // 決済サーバリクエスト送信(設定等によって送るリクエストは異なる)
        $listParam = $this->client->createRequestData($this->yamatoOrder);

        CommonUtil::printLog("Deferred::checkout doPaymentRequest:start");
        $PluginResult = $this->client->doPaymentRequest($this->Order, $listParam);

        $pluginData = $this->client->getSendResults();
        $pluginData['order_id'] = $this->Order->getId();

        if ($PluginResult) {
            CommonUtil::printLog("Deferred::checkout doPaymentRequest:success");
            // 受注ステータスを新規受付へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $this->Order->setOrderStatus($OrderStatus);

            // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
            $this->purchaseFlow->commit($this->Order, new PurchaseContext());
            $result = new PaymentResult();
            $result->setSuccess(true);

            // 決済ステータスを与信完了へ変更
            $pluginData['status'] = YamatoPaymentStatus::DEFERRED_STATUS_AUTH_OK;
            $pluginData['settle_price'] = $this->Order->getPaymentTotal();
            $this->updatePluginPurchaseLog($pluginData);

        } else {
            CommonUtil::printLog("Deferred::checkout doPaymentRequest:error");
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
        }

        CommonUtil::printLog("Deferred::checkout end");
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

    public function updatePluginPurchaseLog($data) {
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
