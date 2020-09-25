<?php

namespace Plugin\YamatoPayment4\Controller;

use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\CartRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Plugin\YamatoPayment4\Entity\YamatoOrder;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentStatusRepository;
use Plugin\YamatoPayment4\Service\Client\DeferredSmsClientService;
use Plugin\YamatoPayment4\Service\Client\CreditClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;

class PaymentController extends AbstractController
{
    private $mailService;

    private $productRepository;

    private $orderRepository;

    private $orderStatusRepository;

    private $yamatoPaymentStatusRepository;

    private $purchaseFlow;

    private $yamatoConfigRepository;

    private $yamatoPaymentMethodRepository;

    private $yamatoOrderRepository;

    private $client;

    private $localEccubeConfig;

    private $router;

    public function __construct(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        CartRepository $cartRepository,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        yamatoPaymentStatusRepository $yamatoPaymentStatusRepository,
        CartService $cartService,
        MailService $mailService,
        EccubeConfig $localEccubeConfig,
        ConfigRepository $yamatoConfigRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        RouterInterface $router
    ) {
        $this->productRepository = $productRepository;
        $this->cartRepository = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;

        $this->yamatoPaymentStatusRepository = $yamatoPaymentStatusRepository;

        $this->cartService = $cartService;
        $this->mailService = $mailService;

        $this->localEccubeConfig = $localEccubeConfig;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;
        $this->router = $router;

        $this->client = new DeferredSmsClientService(
            $this->localEccubeConfig,
            $this->yamatoConfigRepository,
            $this->yamatoPaymentMethodRepository,
            $this->yamatoOrderRepository,
            $this->router
        );
    }

    /**
     * @Route("shopping/yamato/deferred/sms/auth", name="deferred_sms_auth")
     * @Template("@YamatoPayment4/shopping/deferred/sms/input_auth.twig")
     */
    public function requestSmsAuth()
    {
        $form_option = [
            'attr' => [
                'maxlength' => 1,
                'required' => 'required',
            ],
            'constraints' => [
                new Assert\NotBlank(['message' => '※ 4桁の認証コードを入力してください。']),
            ],
        ];

        $form = $this->createFormBuilder()
            ->add('auth_param01', FormType\IntegerType::class, $form_option)
            ->add('auth_param02', FormType\IntegerType::class, $form_option)
            ->add('auth_param03', FormType\IntegerType::class, $form_option)
            ->add('auth_param04', FormType\IntegerType::class, $form_option)
            ->getForm();

        return [
            'form' => $form->createView(),
            'phone_number' => CommonUtil::parsePhoneNumber($this->session->get('authPhoneNumber')),
        ];
    }

    /**
     * @Route("shopping/yamato/deferred/sms/complete", name="yamato_sms_complete")
     */
    public function completeSmsPayment(Request $request)
    {
        // リクエストパラメータ生成
        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->orderRepository->findOneBy(['pre_order_id' => $preOrderId]);
        $params = $this->client->createSmsRequestData($Order, $request->get('form'));

        $status = $this->client->requestSmsAuth($params);

        $pluginData = $this->client->getSendResults();
        $pluginData['order_id'] = $Order->getId();

        if ($status === true) {
            CommonUtil::printLog('SMS Auth::OK');

            // 受注ステータスを新規受付へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $Order->setOrderStatus($OrderStatus);

            // 購入処理を完了させる.
            $this->purchaseFlow->commit($Order, new PurchaseContext());
            $result = new PaymentResult();
            $result->setSuccess(true);

            // 決済ステータスを与信完了へ変更
            $pluginData['status'] = YamatoPaymentStatus::DEFERRED_STATUS_AUTH_OK;
            $pluginData['settle_price'] = $Order->getPaymentTotal();
            $this->client->updatePluginPurchaseLog($pluginData);

            // カート削除
            log_info('[注文処理] カートをクリアします.', [$Order->getId()]);
            $this->cartService->clear();

            // 受注IDをセッションにセット
            $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

            // メール送信
            log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
            $this->mailService->sendOrderMail($Order);
            $this->entityManager->flush();

            log_info('[注文処理] 注文処理が完了しました. 購入完了画面へ遷移します.', [$Order->getId()]);

            return $this->redirectToRoute('shopping_complete');
        } else {
            CommonUtil::printLog('SMS Auth::NG');
            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $Order->setOrderStatus($OrderStatus);

            $this->purchaseFlow->rollback($Order, new PurchaseContext());

            $messages = array(trans('yamato_payment.shopping.checkout.error'));
            $messages = array_merge($messages, $this->client->getError());
            foreach ($messages as $message) {
                $this->addError($message);
            }

            // 決済ステータスを与信中断へ変更
            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_NG_TRANSACTION;
            $this->client->updatePluginPurchaseLog($pluginData);

            log_info('[注文処理] フォームエラーのため, 購入エラー画面へ遷移します.', [$Order->getId()]);

            return $this->redirectToRoute('shopping_error');
        }
    }

    /**
     * @Route("shopping/yamato/threeDSecure/receive", name="yamato_three_d_secure_receive")
     */
    public function receiveThreeDSecurePost(Request $request)
    {
        $client = new CreditClientService(
            $this->eccubeConfig,
            $this->productRepository,
            $this->orderRepository,
            $this->yamatoConfigRepository,
            $this->yamatoPaymentMethodRepository,
            $this->yamatoOrderRepository,
            $this->router
        );

        $orderId = $request->query->get('orderId');
        $Order = $this->orderRepository->findOneBy(['id' => $orderId]);
        $listParam = $request->request->all();

        $pluginData = $client->getSendResults();
        $pluginData['order_id'] = $Order->getId();

        // 決済実行
        //ACSからの戻りは加工せずに戻す
        if (empty($listParam['TOKEN'])) {
            $result = $client->doSecureTran($Order, $listParam);
        } else {
            $result = $client->doSecureTranToken($Order, $listParam);
        }

        if (!$result) {
            CommonUtil::printLog('ThreeDSecure::NG');
            $listErr = $client->getError();
            log_info('[注文処理] 決済でエラーが発生しました。購入エラー画面へ遷移します.', [$Order->getId()]);

            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $Order->setOrderStatus($OrderStatus);

            $this->purchaseFlow->rollback($Order, new PurchaseContext());

            //決済ステータスを「決済中断」に変更する
            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_NG_TRANSACTION;
            $client->updatePluginPurchaseLog($pluginData);

            $this->addError(trans('yamato_payment.shopping.checkout.error'));
            foreach ($listErr as $error) {
                $this->addError($error);
            }

            return $this->redirectToRoute('shopping_error');
        }

        CommonUtil::printLog('ThreeDSecure::OK');
        //注文状況を「新規受付」へ
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
        $Order->setOrderStatus($OrderStatus);

        // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
        $this->purchaseFlow->commit($Order, new PurchaseContext());
        $result = new PaymentResult();
        $result->setSuccess(true);

        $is_reserve = $client->isReservedOrder($Order);
        //「予約販売」の場合「予約受付完了」に変更する
        if ($client->isReserve($is_reserve, $Order)) {
            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_RESERVE;
        } else {
            //「与信完了」に変更する
            $pluginData['status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_COMP_AUTH;
        }
        $pluginData['settle_price'] = $Order->getPaymentTotal();
        $client->updatePluginPurchaseLog($pluginData);

        // 予約商品購入の場合は出荷予定日をセット
        if ($is_reserve) {
            //出荷予定日取得
            $scheduled_shipping_date = $client->getMaxScheduledShippingDate($Order->getId());
            $Order->setScheduledShippingDate($scheduled_shipping_date);
        }

        // カート削除
        log_info('[注文処理] カートをクリアします.', [$Order->getId()]);
        $this->cartService->clear();

        // 受注IDをセッションにセット
        $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

        // メール送信
        log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
        $this->mailService->sendOrderMail($Order);
        $this->entityManager->flush();

        log_info('[注文処理] 注文処理が完了しました. 購入完了画面へ遷移します.', [$Order->getId()]);

        return $this->redirectToRoute('shopping_complete');
    }

    /**
     * @Route("shopping/yamato/deferred/sms/receive", name="yamato_sms_receive"):
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function receiveSmsPost(Request $request)
    {
        $id = $request->get('juchuNo');
        CommonUtil::printLog($id);
        $Order = $this->orderRepository->findOneBy([
            'id' => $id,
        ]);

        if (!$Order) {
            CommonUtil::printLog('The Order is not found. id='.$id);
            throw new NotFoundHttpException();
        }

        $sms_authentication_result = $request->get('smsNinKk');
        if ($sms_authentication_result != 0) {
            // ロールバック
            $Cart = $this->cartRepository->findOneBy(['pre_order_id' => $Order->getPreOrderId()], ['id' => 'DESC']);
            $this->session->set('cart_keys', [$Cart->getCartKey()]);
            $this->purchaseFlow->rollback($Order, new PurchaseContext());
            // 受注ステータス変更
            $Order->setOrderStatus($this->orderStatusRepository->find(OrderStatus::CANCEL));
            $this->entityManager->flush();
            // 決済ステータス変更
            $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
            $payData['action_status'] = YamatoPaymentStatus::YAMATO_ACTION_STATUS_NG_TRANSACTION;
            $this->client->setOrderPayData($YamatoOrder, $payData);

            CommonUtil::printLog('The Order was canceled. id='.$id);
        }

        return $this->json('OK', 200);
    }

    /**
     * @Route("/yamato_payment4_log", name="yamato_payment4_log")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function log(Request $request)
    {
        $data = $request->request->all();
        // ハッシュ存在チェック
        if (empty($data['hash'])) {
            return;
        }

        // ハッシュ一致チェック
        $checkKeys = explode(',', $data['checkKey']);
        $hash = CommonUtil::getArrayToHash($data, $checkKeys);
        if ($data['hash'] != $hash) {
            return;
        }

        if (empty($data['order_id'])) {
            return;
        }

        // 余分なデータを削除
        $removeKeys = ['hash', 'checkKey'];
        foreach ($data as $key => $val) {
            if (in_array($key, $removeKeys)) {
                unset($data[$key]);
            }
        }

        $Order = $this->orderRepository->find($data['order_id']);

        $yamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        if (!$yamatoOrder) {
            $yamatoOrder = new YamatoOrder();
            $yamatoOrder->setOrder($Order);
        }

        $this->client->setSetting($Order);

        $payData = [];

        if (empty($data['status']) == false) {
            $payData['action_status'] = $data['status'];
            unset($data['status']);
        }

        $paymentCode = $this->client->getPaymentCode($Order);
        $payData['payment_code'] = $paymentCode;

        if (isset($data['settle_price'])) {
            $payData['settle_price'] = intval($data['settle_price']);
        }

        $yamatoOrder = $this->client->setOrderPayData($yamatoOrder, $payData);

        CommonUtil::printLog('BeforePersist', $payData);
        $this->entityManager->persist($yamatoOrder);
        $this->entityManager->flush();
        CommonUtil::printLog('AfterPersist');

        CommonUtil::printLog('BeforePersist', $data);
        $yamatoOrder = $this->client->setOrderPaymentViewData($yamatoOrder, $data);
        $this->entityManager->persist($yamatoOrder);
        $this->entityManager->flush();
        CommonUtil::printLog('AfterPersist');

        return $this->json('OK', 200);
    }
}
