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

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Common\EccubeConfig;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Response;
use Plugin\YamatoPayment4\Entity\YamatoOrder;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository;
use Plugin\YamatoPayment4\Service\Client\CreditClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;

/**
 * クレジットカード(トークン決済)の決済処理を行う.
 */
class Credit implements PaymentMethodInterface
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
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

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
     * Credit.
     *
     * @param EccubeConfig                  $eccubeConfig
     * @param ProductRepository             $productRepository
     * @param PurchaseFlow                  $shoppingPurchaseFlow
     * @param OrderRepository               $orderRepository
     * @param OrderStatusRepository         $orderStatusRepository
     * @param RouterInterface               $router
     * @param ConfigRepository              $yamatoConfigRepository
     * @param YamatoPaymentStatusRepository $yamatoPaymentStatusRepository
     * @param YamatoPaymentMethodRepository $yamatoPaymentMethodRepository
     * @param YamatoOrderRepository         $yamatoOrderRepository
     */
    public function __construct(
            EccubeConfig $eccubeConfig,
            ProductRepository $productRepository,
            PurchaseFlow $shoppingPurchaseFlow,
            OrderRepository $orderRepository,
            OrderStatusRepository $orderStatusRepository,
            EntityManagerInterface $entityManager,
            RouterInterface $router,
            ConfigRepository $yamatoConfigRepository,
            YamatoPaymentStatusRepository $yamatoPaymentStatusRepository,
            YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
            YamatoOrderRepository $yamatoOrderRepository
    ) {
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->router = $router;

        $this->eccubeConfig = $eccubeConfig;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->yamatoPaymentStatusRepository = $yamatoPaymentStatusRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;

        $this->client = new CreditClientService(
            $this->eccubeConfig,
            $this->productRepository,
            $this->orderRepository,
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
     * クレジットカードの有効性チェックを行う.
     *
     * @return PaymentResult
     *
     * @throws \Eccube\Service\PurchaseFlow\PurchaseException
     */
    public function verify()
    {
        // 決済サーバとの通信処理(有効性チェックやカード番号の下4桁取得)
        // ...
        //
        if (true) {
            $result = new PaymentResult();
            $result->setSuccess(true);
        } else {
            $result = new PaymentResult();
            $result->setSuccess(false);
            $result->setErrors([trans('yamato_payment.shopping.verify.error')]);
        }

        return $result;
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
        CommonUtil::printLog('Credit::apply start');
        // 受注ステータスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);
        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        $this->yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);

        // 決済ステータスを未決済へ変更
        $pluginData = [
            'order_id' => $this->Order->getId(),
            'status' => YamatoPaymentStatus::YAMATO_ACTION_STATUS_WAIT,
        ];

        $this->updatePluginPurchaseLog($pluginData);
        CommonUtil::printLog('Credit::apply end');
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
        CommonUtil::printLog('Credit::checkout start');

        $form = $this->form;

        $this->yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $this->Order]);

        // 決済サーバに仮売上のリクエスト送る(設定等によって送るリクエストは異なる)
        $formName = $form->getName();
        $listParam = $_POST[$formName];
        // 予約商品有無
        $tpl_is_reserve = $this->client->isReservedOrder($this->Order);
        $listParam['tpl_is_reserve'] = $tpl_is_reserve;
        if (empty($listParam['register_card'])) {
            $listParam['register_card'] = '0';
        }
        if (empty($listParam['use_registed_card'])) {
            $listParam['use_registed_card'] = '0';
        }
        if ($listParam['tpl_is_reserve'] == true && !empty($listParam['card_no'])) {
            // 予約商品有の場合、カードお預かりは必須
            $listParam['register_card'] = '1';
        }

        CommonUtil::printLog('Credit::checkout doPaymentRequest:start');
        $PluginResult = $this->client->doPaymentRequest($this->Order, $listParam);

        $pluginData = $this->client->getSendResults();
        $pluginData['order_id'] = $this->Order->getId();

        if ($PluginResult) {
            //3Dセキュア有りの場合
            if (isset($pluginData['threeDAuthHtml'], $pluginData['threeDToken'])) {
                //ACSリダイレクト設定
                // CDATAで画面表示できなくなるバグが存在するためCDATAを除去する
                $threeDAuthHtml = preg_replace('/\]\]>$/', '', preg_replace('/^<!\[CDATA\[/', '', $pluginData['threeDAuthHtml']));

                $result = new PaymentResult();
                $result->setSuccess(true);
                $result->setResponse(new Response($threeDAuthHtml));

                //リクエスト結果保存
                unset($pluginData['threeDAuthHtml']);
                $YamatoOrder = $this->client->setOrderPayData($this->yamatoOrder, $pluginData);
                $this->entityManager->persist($YamatoOrder);
                $this->entityManager->flush();
            } else {
                CommonUtil::printLog('Credit::checkout doPaymentRequest:success');
                // 受注ステータスを新規受付へ変更
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $this->Order->setOrderStatus($OrderStatus);

                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($this->Order, new PurchaseContext());
                $result = new PaymentResult();
                $result->setSuccess(true);

                //「予約販売」の場合「予約受付完了」に変更する
                if ($this->client->isReserve($tpl_is_reserve, $this->Order)) {
                    $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_RESERVE;
                } else {
                    // 決済ステータスを与信完了へ変更
                    $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH;
                }
                $pluginData['settle_price'] = $this->Order->getPaymentTotal();
                $this->updatePluginPurchaseLog($pluginData);
                // 予約商品購入の場合は出荷予定日をセット
                if ($tpl_is_reserve) {
                    //出荷予定日取得
                    $scheduled_shipping_date = $this->client->getMaxScheduledShippingDate($this->Order->getId());
                    $this->Order->setScheduledShippingDate($scheduled_shipping_date);
                }
            }
        } else {
            CommonUtil::printLog('Credit::checkout doPaymentRequest:error');
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

            // 決済ステータスを与信中断へ変更
            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_NG_TRANSACTION;
            $this->updatePluginPurchaseLog($pluginData);
        }

        CommonUtil::printLog('Credit::checkout end');

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

        if (empty($data['status']) == false) {
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

        $yamatoOrder = $this->client->setOrderPayData($yamatoOrder, $payData);
        $this->entityManager->persist($yamatoOrder);
        $this->entityManager->flush();

        $yamatoOrder = $this->client->setOrderPaymentViewData($yamatoOrder, $data);
        $this->entityManager->persist($yamatoOrder);
        $this->entityManager->flush();
    }
}
