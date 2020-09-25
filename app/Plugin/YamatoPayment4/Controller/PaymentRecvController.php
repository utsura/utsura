<?php

namespace Plugin\YamatoPayment4\Controller;

use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Order;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CartRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Plugin\YamatoPayment4\Entity\YamatoPaymentMethod;
use Plugin\YamatoPayment4\Entity\YamatoPaymentStatus;
use Plugin\YamatoPayment4\Form\Type\PaymentRecvType;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Service\Client\DeferredSmsClientService;
use Plugin\YamatoPayment4\Service\Client\UtilClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;
use Plugin\YamatoPayment4\Util\PluginUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaymentRecvController extends AbstractController
{
    /** @var \Swift_Mailer */
    protected $mailer;

    /** @var EccubeConfig */
    protected $eccubeConfig;

    /** @var BaseInfo */
    protected $BaseInfo;

    /** @var PurchaseFlow */
    protected $purchaseFlow;

    /** @var CartRepository */
    protected $cartRepository;

    /** @var OrderStatusRepository */
    protected $orderStatusRepository;

    /** @var OrderRepository */
    protected $orderRepository;

    /** @var ConfigRepository */
    protected $yamatoConfigRepository;

    /** @var YamatoOrderRepository */
    protected $yamatoOrderRepository;

    /** @var YamatoPaymentMethodRepository */
    protected $yamatoPaymentMethodRepository;

    /** @var PluginUtil */
    protected $pluginUtil;

    /** @var RouterInterface */
    protected $router;

    public function __construct(
        \Swift_Mailer $mailer,
        EccubeConfig $eccubeConfig,
        PurchaseFlow $purchaseFlow,
        BaseInfoRepository $baseInfoRepository,
        CartRepository $cartRepository,
        OrderStatusRepository $orderStatusRepository,
        OrderRepository $orderRepository,
        ConfigRepository $yamatoConfigRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        PluginUtil $pluginUtil,
        RouterInterface $router
    )
    {
        $this->mailer = $mailer;
        $this->eccubeConfig = $eccubeConfig;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->purchaseFlow = $purchaseFlow;
        $this->cartRepository = $cartRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->orderRepository = $orderRepository;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->pluginUtil = $pluginUtil;
        $this->router = $router;
    }

    /**
     * @Route("shopping/yamato_payment_recv", name="yamato_shopping_payment_recv")
     */
    public function index(Request $request)
    {
        // IP制限チェック
        $allowHost = $this->eccubeConfig['RECV_ALLOW_HOST'];
        if (!in_array($request->getClientIp(), $allowHost)) {
            throw new AccessDeniedHttpException();
        }

        // POST値ログ出力
        CommonUtil::printLog('******* receiver data start *******');
        CommonUtil::printLog(print_r($request->request->all(), true));
        CommonUtil::printLog('******* receiver data end *******');

        $result = false;
        if ($request->getMethod() !== 'POST') {
            CommonUtil::printLog('error GETアクセスは許可されていません。');
            return $this->sendResponse($result);
        }

        return $this->receiveSettlementResultData($request);
    }

    /**
     * 決済結果データレシーブ
     *
     * @param Request $request
     * @return Response
     */
    protected function receiveSettlementResultData($request)
    {
        $form = $this->createForm(PaymentRecvType::class);
        $form->submit($request->request->all());

        $data = $form->getData();

        $result = false;
        if ($form->isValid()) {
            // レシーブ処理
            $result = $this->doReceive($data);
            return $this->sendResponse($result);
        }

        // エラーメッセージ取得
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        CommonUtil::printLog('param_error_all: ' . print_r($errors, true));

        // エラーメール送信(受注データ不在)
        if (!$form->get('order_no')->isValid()) {
            $this->doNoOrder($data);
            return $this->sendResponse($result);
        }

        // エラーメール送信（決済未使用）
        if (!$form->get('function_div')->isValid()) {
            $this->doNoFunctionDiv($data);
        }

        // エラーメール送信 (支払方法不一致)
        if (!$form->get('settle_method')->isValid()) {
            $this->doUnmatchPayMethod($data);
        }

        // エラーメール送信（決済金額不一致）
        if (!$form->get('settle_price')->isValid()) {
            $this->doUnMatchSettlePrice($data);
        }

        return $this->sendResponse($result);
    }

    /**
     * レシーブ処理
     *
     * @param array $formData POST値
     * @return void
     */
    protected function doReceive(&$formData)
    {
        /** @var Order */
        $Order = $this->orderRepository->find($formData['order_no']);
        /** @var YamatoPaymentMethod */
        $YamatoPaymentMethod = $this->yamatoPaymentMethodRepository->findOneBy(['Payment' => $Order->getPayment()]);
        $memo03 = $YamatoPaymentMethod->getMemo03();

        switch ($memo03) {
            //コンビニ決済
            case $this->eccubeConfig['YAMATO_PAYID_CVS']:
                $result = $this->doReceiveCvs($formData, $Order);
                break;
            //クレジットカード決済
            case $this->eccubeConfig['YAMATO_PAYID_CREDIT']:
                $result = $this->doReceiveCredit($formData, $Order);
                break;
            default:
                $result = false;
        }

        if ($result) {
            $client = new UtilClientService(
                $this->eccubeConfig,
                $this->yamatoConfigRepository,
                $this->yamatoPaymentMethodRepository,
                $this->yamatoOrderRepository,
                $this->router
            );
            $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
            $YamatoOrder = $client->setOrderPayData($YamatoOrder, $formData);
            $this->entityManager->persist($YamatoOrder);
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * レシーブ処理（コンビニ決済）
     *
     * 正常・異常ともに想定内のPOST値であればtrueを返す.
     * 想定しないPOST値の場合はfalseを返す.
     *
     * @param array $formData
     * @param Order $Order
     * @return bool
     */
    protected function doReceiveCvs(&$formData, $Order)
    {
        $dataTime = new \DateTime();
        $orderStatus = null;

        switch ($formData['settle_detail']) {
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_PROMPT_REPORT']:
                // 入金完了（速報）
                if ($Order->getOrderStatus()->getId() == OrderStatus::NEW) {
                    $orderStatus = OrderStatus::PAID;
                }
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_PROMPT_REPORT'];
                break;
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_DIFINIT_REPORT']:
                // 入金完了（確報）
                // セブンイレブンとファミリーマートの場合はスキップ
                if (in_array($formData['settle_method'], [
                    $this->eccubeConfig['CONVENI_ID_SEVENELEVEN'],
                    $this->eccubeConfig['CONVENI_ID_FAMILYMART'],
                ])) {
                    return true;
                }

                if ($Order->getOrderStatus()->getId() == OrderStatus::NEW) {
                    $orderStatus = OrderStatus::PAID;
                }
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_PROMPT_REPORT'];
                break;
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_CUSTOMER']:
                // 購入者都合エラー（支払期限切れ、コンビニエンスストアから入金取消の通知が発生した場合等）
                $orderStatus = OrderStatus::CANCEL;
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_CUSTOMER'];
                break;
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_PAYMENT']:
                // 決済機関都合エラー（コンビニエンスストアより応答がない場合、異常の応答を受けた場合等）
                // OrderStatusは更新しない
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_PAYMENT'];
                break;
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_SYSTEM']:
                // その他のシステムエラー
                // OrderStatusは更新しない
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_SYSTEM'];
                break;
            default:
                return false;
        }

        // 対応状況更新
        if ($orderStatus) {
            $OrderStatus = $this->orderStatusRepository->find($orderStatus);
            $Order->setOrderStatus($OrderStatus);

            // 対応状況が入金済みで入金日がnullの場合、入金日をセット
            if ($orderStatus === OrderStatus::PAID && is_null($Order->getPaymentDate())) {
                $Order->setPaymentDate($dataTime);
            }
            $Order->setUpdateDate($dataTime);
            $this->entityManager->persist($Order);
            $this->entityManager->flush();
        }

        return true;
    }

    /**
     * レシーブ処理（予約販売用：クレジットカード決済）
     *
     * 正常・異常ともに想定内のPOST値であればtrueを返す.
     * 想定しないPOST値の場合、または対象でない取引状況の場合はfalseを返す.
     *
     * @param array $formData POST値
     * @param Order $Order
     * @return bool
     */
    public function doReceiveCredit(&$formData, Order $Order)
    {
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);

        //取引状況「予約受付完了」の場合のみ処理する.
        if ($YamatoOrder->getMemo04() != $this->eccubeConfig['YAMATO_ACTION_STATUS_COMP_RESERVE']) {
            CommonUtil::printLog('error 取引状況が「予約受付完了」ではありません。(' . $YamatoOrder->getMemo04() . ')');
            return false;
        }

        switch ($formData['settle_detail']) {
            //与信完了
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_COMP_AUTH']:
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_COMP_AUTH'];
                break;
            //購入者都合エラー（カード情報に誤りがある場合等）
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_CUSTOMER']:
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_CUSTOMER'];
                break;
            //加盟店都合エラー（決済取消等）
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_SHOP']:
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_SHOP'];
                break;
            //決済機関都合エラー（決済機関から応答が無い場合、異常の応答を受けた場合等）
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_PAYMENT']:
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_PAYMENT'];
                break;
            //その他システムエラー
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_SYSTEM']:
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_SYSTEM'];
                break;
            //予約販売与信エラー
            case $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_RESERVE']:
                $formData['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_NG_RESERVE'];
                break;
            default:
                return false;
        }
        return true;
    }

    /**
     * エラーメール送信（受注データ不在）
     *
     * 受注データが存在しない
     *
     * @param array $formData
     * @return void
     */
    protected function doNoOrder(&$formData)
    {
        $templatePath = 'YamatoPayment4/Resource/template/mail_template/recv_no_order.twig';
        $subject = $this->getPluginName() . ' 不一致データ検出';
        $this->sendMail($templatePath, $subject, $formData);
    }

    /**
     * エラーメール送信（決済未使用）
     *
     * 受注データが存在するが決済を利用していない
     *
     * @param array $formData
     * @return void
     */
    protected function doNoFunctionDiv(&$formData)
    {
        $templatePath = 'YamatoPayment4/Resource/template/mail_template/recv_no_function_div.twig';
        $subject = $this->getPluginName() . ' 決済未使用データ検出';
        $this->sendMail($templatePath, $subject, $formData);
    }

    /**
     * エラーメール送信（支払方法不一致）
     *
     * 受注データとECサイトの受注の不一致
     * 決済の種類が異なる.コンビニの種類も含める.
     *
     * @param array $formData
     * @return void
     */
    protected function doUnmatchPayMethod(&$formData)
    {
        $templatePath = 'YamatoPayment4/Resource/template/mail_template/recv_unmatch_pay_method.twig';
        $subject = $this->getPluginName() . ' 支払い方法不一致データ検出';
        $this->sendMail($templatePath, $subject, $formData);
    }

    /**
     * エラーメール送信（決済金額不一致）
     *
     * 受注データとECサイトの受注の不一致
     * お支払い合計と決済金額が異なる.
     *
     * @param array $formData
     * @return void
     */
    protected function doUnMatchSettlePrice(&$formData)
    {
        $templatePath = 'YamatoPayment4/Resource/template/mail_template/recv_unmatch_settle_price.twig';
        $subject = $this->getPluginName() . ' 決済金額不一致データ検出';
        $this->sendMail($templatePath, $subject, $formData);
    }

    /**
     * メール送信.
     *
     * @param string $templatePath
     * @param string $subject
     * @param array $formData
     * @return void
     */
    protected function sendMail($templatePath, $subject, $formData)
    {
        CommonUtil::printLog('param_error: ' . $subject . ' Param: ' . print_r($formData, true));

        // 支払方法(名前)セット
        $formData['settle_method'] = $this->getPayNameFromSettleMethod($formData['settle_method']);

        $Order = new Order();
        if (isset($formData['order_no'])) {
            $Order = $this->orderRepository->find($formData['order_no']);
        }

        $body = $this->renderView($templatePath, [
            'data' => $formData,
            'order' => $Order,
        ]);

        /** @var \Swift_Message $message */
        $message = (new \Swift_Message())
            ->setSubject($subject)
            ->setFrom($this->BaseInfo->getEmail03())
            ->setTo($this->BaseInfo->getEmail02())
            ->setReturnPath($this->BaseInfo->getEmail04())
            ->setBody($body);
        $this->mailer->send($message);
    }

    /**
     * 支払方法名取得
     *
     * 決済手段(settle_method)から支払方法名を取得する.
     * コンビニの場合はコンビニの種類も後ろに結合して取得する.
     *
     * @param  string $settle_method 決済手段ID
     * @return string 支払方法名
     */
    protected function getPayNameFromSettleMethod($settle_method)
    {
        if ($settle_method >= $this->eccubeConfig['CREDIT_METHOD_UC']
            && $settle_method <= $this->eccubeConfig['CREDIT_METHOD_RAKUTEN']
        ) {
            $payment_name = 'クレジットカード決済';

        } elseif ($settle_method >= $this->eccubeConfig['CONVENI_ID_SEVENELEVEN']
            && $settle_method <= $this->eccubeConfig['CONVENI_ID_MINISTOP']
        ) {
            $listCvs = $this->pluginUtil->getAllCvs();
            $payment_name = 'コンビニ決済 ' . $listCvs[$settle_method];
        } else {
            $payment_name = '不明な支払方法';
        }

        return $payment_name;
    }

    /**
     * レスポンスを返す
     *
     * @param boolean $result
     * @return Response
     */
    protected function sendResponse($result)
    {
        $response = new Response();
        if ($result) {
            CommonUtil::printLog('response: true');
            $response->setContent('0');
        } else {
            CommonUtil::printLog('response: false');
            $response->setContent('1');
        }

        return $response;
    }

    protected function getPluginName()
    {
        $Config = $this->yamatoConfigRepository->find(1);
        return $Config->getName();
    }
}