<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service\Method;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentInput;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\Form\FormInterface;

/**
 * [基底クラス] 決済処理を行う.
 */
abstract class GmoMethod implements PaymentMethodInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

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
    protected $orderStatusRepository;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelper
     */
    protected $paymentHelper;

    /**
     * 決済画面の入力値をフォームから取得して返す
     *
     * @return GmoPaymentInput
     */
    abstract protected function getGmoPaymentInputFromForm();

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * 有効性チェックを行う.
     *
     * @return PaymentResult
     *
     * @throws \Eccube\Service\PurchaseFlow\PurchaseException
     */
    public function verify()
    {
        PaymentUtil::logInfo("verify start [" . get_class($this) . "]");

        // GMO-PG 情報を付加する
        $this->Order =
            $this->paymentHelper->prepareGmoInfoForOrder($this->Order);

        // 支払方法の一致確認
        if ($result = $this->checkPayment()) {
            return $result;
        }

        // 決済画面の入力値をフォームから取得して注文にセット
        $GmoPaymentInput = $this->getGmoPaymentInputFromForm();
        if (!is_null($GmoPaymentInput)) {
            $this->Order->setGmoPaymentInput($GmoPaymentInput);

            // 決済画面の入力値を保存する
            $GmoOrderPayment = $this->Order->getGmoOrderPayment();
            $GmoOrderPayment->setGmoPaymentInput($GmoPaymentInput);

            $this->entityManager->persist($GmoOrderPayment);
            $this->entityManager->flush($GmoOrderPayment);
        }

        $result = new PaymentResult();
        $result->setSuccess(true);

        PaymentUtil::logInfo("verify ok [" . get_class($this) . "]");

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
        PaymentUtil::logInfo("apply start [" . get_class($this) . "]");

        // 受注ステータスを決済処理中へ変更
        $OrderStatus =
            $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);

        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        PaymentUtil::logInfo("apply ok [" . get_class($this) . "]");
    }

    /**
     * 決済処理の後に行う処理を実装する
     *   リダイレクトする場合は PaymentResult を返却
     *   リダイレクトしない場合は null を返却
     *
     * @param GmoPaymentInput $GmoPaymentInput
     * @return PaymentResult|null
     */
    abstract protected function postRequest(GmoPaymentInput $GmoPaymentInput);

    /**
     * 注文時に呼び出される.
     *
     * 決済処理を行う.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        PaymentUtil::logInfo("checkout start [" . get_class($this) . "]");

        // GMO-PG 情報を付加する
        $this->Order =
            $this->paymentHelper->prepareGmoInfoForOrder($this->Order);

        // 支払方法の一致確認
        if ($result = $this->checkPayment()) {
            $this->rollbackOrder();
            return $result;
        }

        $GmoOrderPayment = $this->Order->getGmoOrderPayment();
        $GmoPaymentInput = $GmoOrderPayment->getGmoPaymentInput();

        // 決済処理
        $r = $this->paymentHelper
            ->doRequest($this->Order, $GmoPaymentInput->getArrayData());
        if (!$r) {
            $this->rollbackOrder();

            $result = new PaymentResult();
            $result->setSuccess(false);
            $result->setErrors($this->paymentHelper->getError());

            return $result;
        }

        // 決済後処理
        $result = $this->postRequest($GmoPaymentInput);
        if (!is_null($result)) {
            // リダイレクト
            return $result;
        }

        // 受注ステータスを新規受付へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
        $this->Order->setOrderStatus($OrderStatus);

        // 注文完了画面/注文完了メールにメッセージを追加
        $messages = $GmoOrderPayment->getOrderCompleteMessages();
        foreach ($messages as $message) {
            $this->Order->appendCompleteMessage(nl2br($message));
            $this->Order->appendCompleteMailMessage($message);
        }

        // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
        $this->purchaseFlow->commit($this->Order, new PurchaseContext());

        $result = new PaymentResult();
        $result->setSuccess(true);

        PaymentUtil::logInfo("checkout ok [" . get_class($this) . "]");

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

    /**
     * 支払方法をチェックします
     *
     * @return PaymentResult|null
     */
    protected function checkPayment()
    {
        $result = null;

        // 支払い方法の一致確認
        if (!$this->paymentHelper->isMatchPayment($this->Order)) {
            $result = new PaymentResult();
            $result->setSuccess(false);
            $result->setErrors([
                trans('gmo_payment_gateway.shopping.com.mismatch.payment'),
            ]);
        }

        return $result;
    }

    /**
     * 受注ステータスと購入フローをロールバックする
     */
    protected function rollbackOrder()
    {
        // 受注ステータスを決済処理中 -> 購入処理中へ変更
        $OrderStatus =
            $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $this->Order->setOrderStatus($OrderStatus);

        // 失敗時はpurchaseFlow::rollbackを呼び出す.
        $this->purchaseFlow->rollback($this->Order, new PurchaseContext());
    }
}
