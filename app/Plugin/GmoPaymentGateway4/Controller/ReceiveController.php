<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Controller;

use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\GmoPaymentGateway4\Entity\GmoConfig;
use Plugin\GmoPaymentGateway4\Repository\GmoConfigRepository;
use Plugin\GmoPaymentGateway4\Service\PaymentHelper;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperCredit;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperMember;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperAu;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperDocomo;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperSoftbank;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperRakutenPay;
use Plugin\GmoPaymentGateway4\Service\PaymentHelperReceive;
use Plugin\GmoPaymentGateway4\Util\ErrorUtil;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceiveController extends AbstractController
{
    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var Plugin\GmoPaymentGateway4\Repository\GmoConfigRepository
     */
    protected $gmoConfigRepository;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperCredit
     */
    protected $PaymentHelperCredit;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperMember
     */
    protected $PaymentHelperMember;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperAu
     */
    protected $PaymentHelperAu;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperDocomo
     */
    protected $PaymentHelperDocomo;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperSoftbank
     */
    protected $PaymentHelperSoftbank;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperRakutenPay
     */
    protected $PaymentHelperRakutenPay;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperReceive
     */
    protected $PaymentHelperReceive;

    /**
     * @var Plugin\GmoPaymentGateway4\Util\ErrorUtil
     */
    protected $errorUtil;

    /**
     * ReceiveController constructor.
     *
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param CartService $cartService
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param PaymentHelperCredit $PaymentHelperCredit
     * @param PaymentHelperMember $PaymentHelperMember
     * @param PaymentHelperAu $PaymentHelperAu
     * @param PaymentHelperDocomo $PaymentHelperDocomo
     * @param PaymentHelperSoftbank $PaymentHelperSoftbank
     * @param PaymentHelperRakutenPay $PaymentHelperRakutenPay
     * @param PaymentHelperReceive $PaymentHelperReceive
     * @param ErrorUtil $errorUtil
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        CartService $cartService,
        MailService $mailService,
        PurchaseFlow $shoppingPurchaseFlow,
        PaymentHelperCredit $PaymentHelperCredit,
        PaymentHelperMember $PaymentHelperMember,
        PaymentHelperAu $PaymentHelperAu,
        PaymentHelperDocomo $PaymentHelperDocomo,
        PaymentHelperSoftbank $PaymentHelperSoftbank,
        PaymentHelperRakutenPay $PaymentHelperRakutenPay,
        PaymentHelperReceive $PaymentHelperReceive,
        ErrorUtil $errorUtil
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->cartService = $cartService;
        $this->mailService = $mailService;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->PaymentHelperCredit = $PaymentHelperCredit;
        $this->PaymentHelperMember = $PaymentHelperMember;
        $this->PaymentHelperAu = $PaymentHelperAu;
        $this->PaymentHelperDocomo = $PaymentHelperDocomo;
        $this->PaymentHelperSoftbank = $PaymentHelperSoftbank;
        $this->PaymentHelperRakutenPay = $PaymentHelperRakutenPay;
        $this->PaymentHelperReceive = $PaymentHelperReceive;
        $this->errorUtil = $errorUtil;
    }

    /**
     * 結果通知を受け取る
     *
     * @Method("POST")
     * @Route("/gmo_payment_gateway/receive", name="gmo_payment_gateway_receive")
     */
    public function receive(Request $request)
    {
        $responseCode = 0;

        PaymentUtil::logInfo('ReceiveController::receive start');

        // 受信データ配列を取得
        $receiveData = $request->request->all();
        $this->logReceiveData($receiveData);

        $Order = $this->PaymentHelperReceive->validate($receiveData);
        if ($Order === false) {
            $responseCode = $this->sendResponse($Order);
        } else {
            if ($this->PaymentHelperReceive->isNeededSleep($receiveData)) {
                sleep($this->PaymentHelperReceive->getSleepTime());
            }

            $i = 0;
            $orderId = $Order->getId();
            do {
                if ($i > 0) sleep(1);
                $Order = $this->PaymentHelperReceive
                    ->isReady($orderId, $receiveData);
            } while (++$i < 10 && $Order === false);

            if ($Order === false || $Order === true) {
                // 異常応答 または 受信処理は行わず正常応答
                $r = $Order;
            } else {
                // 受信処理
                $r = $this->PaymentHelperReceive
                    ->doReceive($Order, $receiveData);
            }
            $responseCode = $this->sendResponse($r);
        }

        PaymentUtil::logInfo
            ('ReceiveController::receive end(' . $responseCode . ')');

        return new Response(
            $responseCode,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * 本人認証サービス（3Dセキュア）からの戻りを受け取る
     *
     * @Method("POST")
     * @Route("/gmo_payment_gateway/3dsecure", name="gmo_payment_gateway_3dsecure")
     */
    public function card3dsecure(Request $request)
    {
        PaymentUtil::logInfo('ReceiveController::card3dsecure start.');

        // 受信データ配列を取得
        $receiveData = $request->request->all();
        $this->logReceiveData($receiveData);

        // 受注を取得
        $Order = $this->getPendingOrder($this->cartService->getPreOrderId());
        if (is_null($Order)) {
            goto fail;
        }

        // GMO-PG 付加情報を取得
        $Order = $this->PaymentHelperCredit->prepareGmoInfoForOrder($Order);

        // 受注をチェック
        if (!$this->validateOrder($this->PaymentHelperCredit, $Order)) {
            goto fail;
        }

        // 3Dセキュアパスワード入力画面からデータを受け取り処理続行
        $r = $this->PaymentHelperCredit
            ->do3dSecureContinuation($Order, $receiveData);
        if (!$r) {
            $errors = $this->PaymentHelperCredit->getError();
            foreach ($errors as $error) {
                $this->addError($error);
            }
            goto fail;
        }

        // GMO-PG 受注付加情報を取得
        $GmoOrderPayment = $Order->getGmoOrderPayment();

        // カード登録処理
        $GmoPaymentInput = $GmoOrderPayment->getGmoPaymentInput();
        if ($GmoPaymentInput->isRegisterCard()) {
            $Customer = $Order->getCustomer();
            // GMO-PG 会員登録状況を確認
            if (!$this->PaymentHelperMember->isExistGmoMember($Customer)) {
                // GMO-PG 会員登録を行う
                $this->PaymentHelperMember->saveGmoMember($Customer);
            }
            // カード登録
            $this->PaymentHelperCredit
                ->doRegistCard($Order, $GmoPaymentInput->getArrayData());
        }

        // 受注ステータスを新規受付へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
        $Order->setOrderStatus($OrderStatus);

        // 注文完了画面/注文完了メールにメッセージを追加
        $messages = $GmoOrderPayment->getOrderCompleteMessages();
        foreach ($messages as $message) {
            $Order->appendCompleteMessage(nl2br($message));
            $Order->appendCompleteMailMessage($message);
        }

        // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
        $this->purchaseFlow->commit($Order, new PurchaseContext());

        $this->entityManager->flush();

        // カート削除
        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info1',
                                   ['%order_id%' => $Order->getId()]));
        $this->cartService->clear();

        // 受注IDをセッションにセット
        $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

        // メール送信
        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info2',
                                   ['%order_id%' => $Order->getId()]));
        $this->mailService->sendOrderMail($Order);
        $this->entityManager->flush();

        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info3',
                                   ['%order_id%' => $Order->getId()]));

        PaymentUtil::logInfo('ReceiveController::card3dsecure end.');

        return $this->redirectToRoute('shopping_complete');

    fail:

        $this->entityManager->rollback();

        if (!is_null($Order)) {
            // 受注ステータスを決済処理中 -> 購入処理中へ変更
            $OrderStatus =
                $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $Order->setOrderStatus($OrderStatus);

            // 失敗時はpurchaseFlow::rollbackを呼び出す.
            $this->purchaseFlow->rollback($Order, new PurchaseContext());

            $this->entityManager->flush();
        }

        return $this->redirectToRoute('shopping_error');
    }

    /**
     * auかんたん決済のセンター側からの戻りを受け取る
     *
     * @Method("POST")
     * @Route("/gmo_payment_gateway/au_result", name="gmo_payment_gateway_au_result")
     */
    public function auResult(Request $request)
    {
        PaymentUtil::logInfo('ReceiveController::auResult start.');

        // 受信データ配列を取得
        $receiveData = $request->request->all();
        $this->logReceiveData($receiveData);

        // 受注を取得
        $Order = $this->getPendingOrder($this->cartService->getPreOrderId());
        if (is_null($Order)) {
            goto fail;
        }

        // エラーチェック
        if (!$this->checkErrorInfo($receiveData)) {
            goto fail;
        }

        // キャンセルチェック
        $msg = $this->PaymentHelperAu->checkCancelStatus($receiveData);
        if (!empty($msg)) {
            $this->addError($msg);
            goto fail;
        }

        // GMO-PG 付加情報を取得
        $Order = $this->PaymentHelperAu->prepareGmoInfoForOrder($Order);

        // 受注をチェック
        if (!$this->validateOrder($this->PaymentHelperAu, $Order)) {
            goto fail;
        }

        // GMO-PG 受注付加情報を取得
        $GmoOrderPayment = $Order->getGmoOrderPayment();

        // メッセージを追加
        $prefix = "gmo_payment_gateway.payment_helper.";
        $mergeData['PayInfoNo']['name'] = trans($prefix . 'payinfono');
        $mergeData['PayInfoNo']['value'] = $_REQUEST['PayInfoNo'];
        $GmoOrderPayment->mergeHeadOrderCompleteMessages($mergeData);

        // 注文完了画面/注文完了メール用のメッセージを追加
        $messages = $GmoOrderPayment->getOrderCompleteMessages();
        foreach ($messages as $message) {
            $Order->appendCompleteMessage(nl2br($message));
            $Order->appendCompleteMailMessage($message);
        }

        $this->entityManager->flush();

        // カート削除
        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info1',
                                   ['%order_id%' => $Order->getId()]));
        $this->cartService->clear();

        // 受注IDをセッションにセット
        $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info3',
                                   ['%order_id%' => $Order->getId()]));

        PaymentUtil::logInfo('ReceiveController::auResult end.');

        return $this->redirectToRoute('shopping_complete');

    fail:

        $this->entityManager->rollback();

        if (!is_null($Order)) {
            // 受注ステータスを決済処理中 -> 購入処理中へ変更
            $OrderStatus =
                $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $Order->setOrderStatus($OrderStatus);

            // 失敗時はpurchaseFlow::rollbackを呼び出す.
            $this->purchaseFlow->rollback($Order, new PurchaseContext());

            $this->entityManager->flush();
        }

        return $this->redirectToRoute('shopping_error');
    }

    /**
     * ドコモケータイ払いのセンター側からの戻りを受け取る
     *
     * @Method("POST")
     * @Route("/gmo_payment_gateway/docomo_result", name="gmo_payment_gateway_docomo_result")
     */
    public function docomoResult(Request $request)
    {
        PaymentUtil::logInfo('ReceiveController::docomoResult start.');

        // 受信データ配列を取得
        $receiveData = $request->request->all();
        $this->logReceiveData($receiveData);

        // 受注を取得
        $Order = $this->getPendingOrder($this->cartService->getPreOrderId());
        if (is_null($Order)) {
            goto fail;
        }

        // エラーチェック
        if (!$this->checkErrorInfo($receiveData)) {
            goto fail;
        }

        // キャンセルチェック
        $msg = $this->PaymentHelperDocomo->checkCancelStatus($receiveData);
        if (!empty($msg)) {
            $this->addError($msg);
            goto fail;
        }

        // GMO-PG 付加情報を取得
        $Order = $this->PaymentHelperDocomo->prepareGmoInfoForOrder($Order);

        // 受注をチェック
        if (!$this->validateOrder($this->PaymentHelperDocomo, $Order)) {
            goto fail;
        }

        // GMO-PG 受注付加情報を取得
        $GmoOrderPayment = $Order->getGmoOrderPayment();

        // メッセージを追加
        $key = 'DocomoSettlementCode';
        $prefix = "gmo_payment_gateway.payment_helper.";
        $mergeData[$key]['name'] = trans($prefix . 'docomocode');
        $mergeData[$key]['value'] = $_REQUEST[$key];
        $GmoOrderPayment->mergeHeadOrderCompleteMessages($mergeData);

        // 注文完了画面/注文完了メール用のメッセージを追加
        $messages = $GmoOrderPayment->getOrderCompleteMessages();
        foreach ($messages as $message) {
            $Order->appendCompleteMessage(nl2br($message));
            $Order->appendCompleteMailMessage($message);
        }

        $this->entityManager->flush();

        // カート削除
        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info1',
                                   ['%order_id%' => $Order->getId()]));
        $this->cartService->clear();

        // 受注IDをセッションにセット
        $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info3',
                                   ['%order_id%' => $Order->getId()]));

        PaymentUtil::logInfo('ReceiveController::docomoResult end.');

        return $this->redirectToRoute('shopping_complete');

    fail:

        $this->entityManager->rollback();

        if (!is_null($Order)) {
            // 受注ステータスを決済処理中 -> 購入処理中へ変更
            $OrderStatus =
                $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $Order->setOrderStatus($OrderStatus);

            // 失敗時はpurchaseFlow::rollbackを呼び出す.
            $this->purchaseFlow->rollback($Order, new PurchaseContext());

            $this->entityManager->flush();
        }

        return $this->redirectToRoute('shopping_error');
    }

    /**
     * ソフトバンクまとめて支払いのセンター側からの戻りを受け取る
     *
     * @Method("POST")
     * @Route("/gmo_payment_gateway/softbank_result", name="gmo_payment_gateway_softbank_result")
     */
    public function softbankResult(Request $request)
    {
        PaymentUtil::logInfo('ReceiveController::softbankResult start.');

        // 受信データ配列を取得
        $receiveData = $request->request->all();
        $this->logReceiveData($receiveData);

        // 受注を取得
        $Order = $this->getPendingOrder($this->cartService->getPreOrderId());
        if (is_null($Order)) {
            goto fail;
        }

        // エラーチェック
        if (!$this->checkErrorInfo($receiveData)) {
            goto fail;
        }

        // GMO-PG 付加情報を取得
        $Order = $this->PaymentHelperSoftbank->prepareGmoInfoForOrder($Order);

        // 受注をチェック
        if (!$this->validateOrder($this->PaymentHelperSoftbank, $Order)) {
            goto fail;
        }

        // GMO-PG 受注付加情報を取得
        $GmoOrderPayment = $Order->getGmoOrderPayment();

        // メッセージを追加
        $key = 'SbTrackingId';
        $prefix = "gmo_payment_gateway.payment_helper.";
        $mergeData[$key]['name'] = trans($prefix . 'softbanktrackid');
        $mergeData[$key]['value'] = $_REQUEST[$key];
        $GmoOrderPayment->mergeHeadOrderCompleteMessages($mergeData);

        // 注文完了画面/注文完了メール用のメッセージを追加
        $messages = $GmoOrderPayment->getOrderCompleteMessages();
        foreach ($messages as $message) {
            $Order->appendCompleteMessage(nl2br($message));
            $Order->appendCompleteMailMessage($message);
        }

        $this->entityManager->flush();

        // カート削除
        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info1',
                                   ['%order_id%' => $Order->getId()]));
        $this->cartService->clear();

        // 受注IDをセッションにセット
        $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info3',
                                   ['%order_id%' => $Order->getId()]));

        PaymentUtil::logInfo('ReceiveController::softbankResult end.');

        return $this->redirectToRoute('shopping_complete');

    fail:

        $this->entityManager->rollback();

        if (!is_null($Order)) {
            // 受注ステータスを決済処理中 -> 購入処理中へ変更
            $OrderStatus =
                $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $Order->setOrderStatus($OrderStatus);

            // 失敗時はpurchaseFlow::rollbackを呼び出す.
            $this->purchaseFlow->rollback($Order, new PurchaseContext());

            $this->entityManager->flush();
        }

        return $this->redirectToRoute('shopping_error');
    }

    /**
     * 楽天ペイのセンター側からの戻りを受け取る
     *
     * @Method("POST")
     * @Route("/gmo_payment_gateway/rakuten_pay_result/{result}", requirements={"result" = "\d+"}, name="gmo_payment_gateway_rakuten_pay_result")
     */
    public function rakutenPayResult(Request $request, $result)
    {
        PaymentUtil::logInfo('ReceiveController::rakutenPayResult start.');

        // 受信データ配列を取得
        $receiveData = $request->request->all();
        $this->logReceiveData($receiveData);

        // 受注を取得
        $Order = $this->getPendingOrder($this->cartService->getPreOrderId());
        if (is_null($Order)) {
            goto fail;
        }

        // エラーチェック
        if ($result != 1) {
            $this->checkErrorInfo($receiveData);
            goto fail;
        }

        // GMO-PG 付加情報を取得
        $Order =
            $this->PaymentHelperRakutenPay->prepareGmoInfoForOrder($Order);

        // 受注をチェック
        if (!$this->validateOrder($this->PaymentHelperRakutenPay, $Order)) {
            goto fail;
        }

        // GMO-PG 受注付加情報を取得
        $GmoOrderPayment = $Order->getGmoOrderPayment();

        // 注文完了画面/注文完了メール用のメッセージを追加
        $messages = $GmoOrderPayment->getOrderCompleteMessages();
        foreach ($messages as $message) {
            $Order->appendCompleteMessage(nl2br($message));
            $Order->appendCompleteMailMessage($message);
        }

        $this->entityManager->flush();

        // カート削除
        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info1',
                                   ['%order_id%' => $Order->getId()]));
        $this->cartService->clear();

        // 受注IDをセッションにセット
        $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

        PaymentUtil::logInfo(trans('gmo_payment_gateway.shopping.' .
                                   'com.done.info3',
                                   ['%order_id%' => $Order->getId()]));

        PaymentUtil::logInfo('ReceiveController::softbankResult end.');

        return $this->redirectToRoute('shopping_complete');

    fail:

        $this->entityManager->rollback();

        if (!is_null($Order)) {
            // 受注ステータスを決済処理中 -> 購入処理中へ変更
            $OrderStatus =
                $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $Order->setOrderStatus($OrderStatus);

            // 失敗時はpurchaseFlow::rollbackを呼び出す.
            $this->purchaseFlow->rollback($Order, new PurchaseContext());

            $this->entityManager->flush();
        }

        return $this->redirectToRoute('shopping_error');
    }

    /**
     * レスポンスを返す。
     *
     * @param boolean 処理結果
     * @return integer レスポンスコード
     */
    protected function sendResponse($result) {        
        if (!$result) {
            return 1;
        }

        return 0;
    }

    /**
     * 受信データをログ出力する
     *
     * @param array $receiveData 受信データ
     */
    protected function logReceiveData($receiveData)
    {
        PaymentUtil::logInfo('******* Receive Data Dump start *******');
        PaymentUtil::logInfo($receiveData);
        PaymentUtil::logInfo('******* Receive Data Dump   end *******');
    }

    /**
     * 受信データのエラー状況をチェックする
     * エラーがセットされている場合はエラーメッセージ化して登録する
     *
     * @param array $receiveData 受信データ配列
     * @return boolean true: OK, false: NG
     */
    protected function checkErrorInfo(array $receiveData)
    {
        if (empty($receiveData['ErrCode'])) {
            PaymentUtil::logInfo('ReceiveController::checkErrorInfo OK');
            return true;
        }

        $code = $receiveData['ErrCode'];
        $info = $receiveData['ErrInfo'];

        $errorInfo = $this->errorUtil->lfGetErrorInformation($info);

        $msg = empty($errorInfo['message']) ?
            $errorInfo['context'] : $errorInfo['message'];
        $msg .= '(' . sprintf('%s-%s', $code, $info) . ')';

        $this->addError($msg);
        PaymentUtil::logError('ReceiveController::checkErrorInfo ' . $msg);

        return false;
    }

    /**
     * 決済処理中の受注を取得する
     *
     * @param string $preOrderId
     * @return Order|null
     */
    protected function getPendingOrder($preOrderId)
    {
        PaymentUtil::logInfo('ReceiveController::getPendingOrder start.');

        PaymentUtil::logInfo('pre_order_id is ' . $preOrderId);

        $Order = $this->orderRepository->findOneBy([
            'pre_order_id' => $preOrderId,
            'OrderStatus' => OrderStatus::PENDING,
        ]);

        if (is_null($Order)) {
            $msg = 'gmo_payment_gateway.shopping.com.unusable.order';
            $this->addError(trans($msg));
            PaymentUtil::logError(trans($msg));
        }

        PaymentUtil::logInfo('ReceiveController::getPendingOrder end.');

        return $Order;
    }

    /**
     * 受注をチェック
     *
     * @param PaymentHelper $PaymentHelper 決済ヘルパー
     * @param Order $Order 受注
     * @return boolean true: OK, false: NG
     */
    protected function validateOrder
        (PaymentHelper $PaymentHelper, Order $Order)
    {
        PaymentUtil::logInfo('ReceiveController::validateOrder start.');

        // 支払方法をチェック
        if (!$PaymentHelper->isMatchPayment($Order)) {
            $msg = trans('gmo_payment_gateway.shopping.com.mismatch.payment');
            $this->addError($msg);
            return false;
        }

        // GMO-PG 受注付加情報を確認
        $GmoOrderPayment = $Order->getGmoOrderPayment();
        if (is_null($GmoOrderPayment)) {
            $msg = 'gmo_payment_gateway.shopping.com.unusable.paymentinfo';
            $this->addError(trans($msg));
            PaymentUtil::logError(trans($msg));
            return false;
        }

        // GMO-PG 決済ログを取得
        $paymentLogData = $GmoOrderPayment->getPaymentLogData();
        if (empty($paymentLogData)) {
            $msg = 'gmo_payment_gateway.shopping.com.unusable.paymentinfo';
            $this->addError(trans($msg));
            PaymentUtil::logError(trans($msg));
            return false;
        }

        PaymentUtil::logInfo('validateOrder OK.');

        PaymentUtil::logInfo('ReceiveController::validateOrder end.');

        return true;
    }
}
