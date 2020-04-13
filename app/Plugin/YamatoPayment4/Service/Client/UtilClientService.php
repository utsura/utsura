<?php

namespace Plugin\YamatoPayment4\Service\Client;

use Eccube\Entity\Order;

use Plugin\YamatoPayment4\Service\Client\BaseClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;

class UtilClientService extends BaseClientService
{

    /**
     * 出荷情報登録
     *
     * @param $Order 注文情報
     * @param $ShippingRepository 配送情報リポジトリ
     * @return bool
     */
    public function doShipmentEntry($Order, $ShippingRepository)
    {
        $orderId = $Order->getId();

        //決済設定情報取得
        $this->setSetting($Order);

        //API設定
        $function_div = 'E01';
        $server_url = $this->getApiUrl($function_div);
        //送信キー
        $listSendKey = [
            'function_div',
            'trader_code',
            'order_no',
            'delivery_service_code',
            'slip_no',
        ];

        // 個別パラメタ
        $listParam = [];
        $listParam['function_div'] = $function_div;

        $listParam['delivery_service_code'] = $this->moduleSettings["delivery_service_code"];

        // 配送先情報取得
        $Shippings = $ShippingRepository->findBy(['Order' => $Order]);

        if (empty($Shippings)) {
            if($listParam['delivery_service_code'] == '00') {
                return [false, []];
            }
        }

        // 配送先ごとに出荷情報登録処理
        // 処理成功分送り状番号保持用配列
        $listSuccessSlip = [];
        foreach ($Shippings as $Shipping) {
            /** @var YamatoShippingDelivSlip $YamatoShipping */
            $shippingId = $Shipping->getId();

            // 送り状番号設定
            if($listParam['delivery_service_code'] == '00') {
                $listParam['slip_no'] = $Shipping->getTrackingNumber();
            } else {
                $listParam['slip_no'] = $orderId;
            }

            // リクエスト送信
            if (!$this->sendOrderRequest(
                    $server_url,
                    $listSendKey,
                    $orderId,
                    $listParam,
                    $Order)
            ) {
                return [false, $listSuccessSlip];
            }

            $listSuccessSlip[$shippingId] = $listParam['slip_no'];

            if($listParam['delivery_service_code'] != '00') {
                break;
            }
        }

        return [true, $listSuccessSlip];
    }

    /**
     * 出荷情報登録ロールバック
     *
     * 出荷情報登録処理（複数配送）で失敗した際
     * それまでに成功した登録済送り状番号の取消処理をする
     *
     * @param $Order 注文情報
     * @param array $listSuccessSlip
     * @return void
     */
    public function doShipmentRollback($Order, $listSuccessSlip)
    {

        //成功出荷情報が0件の場合は処理しない
        if (count($listSuccessSlip) === 0) {
            return false;
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        //API設定
        $function_div = 'E02';
        $server_url = $this->getApiUrl($function_div);
        //送信キー
        $listSendKey = [
                'function_div',
                'trader_code',
                'order_no',
                'slip_no'
        ];

        //個別パラメタ
        $listParam = [];
        $listParam['function_div'] = $function_div;

        //配送先ごとの処理
        foreach ($listSuccessSlip as $slip) {
            $listParam['slip_no'] = $slip;
            $ret = $this->sendOrderRequest(
                    $server_url,
                    $listSendKey,
                    $orderId,
                    $listParam,
                    $Order);
            if($ret == false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 決済取消(クレジット決済)
     *
     * @param $Order 注文情報
     * @return bool
     */
    public function doCreditCancel($Order)
    {
        //クレジットカード決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();
        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
            $msg = '決済キャンセル・返品エラー：キャンセル・返品処理に対応していない決済です。';
            $this->setError($msg);
            return false;
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        //API設定
        $function_div = 'A06';
        $server_url = $this->getApiUrl($function_div);

        //送信キー
        $listSendKey = [
            'function_div',
            'trader_code',
            'order_no'
        ];

        //個別パラメタ
        $listParam = [];
        $listParam['function_div'] = $function_div;

        // リクエスト送信
        if (!$this->sendOrderRequest(
                    $server_url,
                    $listSendKey,
                    $orderId,
                    $listParam,
                    $Order)
        ) {
            return false;
        }

        return true;
    }

    /**
     * クレカ受注金額変更
     *
     * @param Order $Order 注文情報
     * @return bool
     */
    public function doCreditChangePrice($Order)
    {
        //クレジットカード決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();
        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
            $msg = '金額変更に対応していない決済です。';
            $this->setError($msg);
            return false;
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        //API設定
        $function_div = 'A07';
        $server_url = $this->getApiUrl($function_div);

        //送信キー
        $listSendKey = [
            'function_div',
            'trader_code',
            'order_no',
            'new_price'
        ];

        //個別パラメタ
        $listParam = [];
        $listParam['function_div'] = $function_div;

        // リクエスト送信
        if (!$this->sendOrderRequest(
                    $server_url,
                    $listSendKey,
                    $orderId,
                    $listParam,
                    $Order)
        ) {
            return false;
        }

        return true;
    }

    /**
     * 決済状況取得.
     *
     * 対応状況の更新を実行
     * (1)レスポンスの処理結果が0件ではない
     * (2)レスポンスと注文番号が同じ
     * (3)レスポンス値と注文データの決済手段が同じ
     *
     * @param OrderExtension $OrderExtension 注文情報
     * @return bool
     */
    public function doGetTradeInfo($Order)
    {
        $orderId = $Order->getId();

        // 決済設定情報取得
        $this->setSetting($Order);

        // API設定
        $function_div = 'E04';
        $server_url = $this->getApiUrl($function_div);

        // 送信キー
        $listSendKey = [
            'function_div',
            'trader_code',
            'order_no'
        ];

        // パラメータ設定
        $listParam = [];
        $listParam['function_div'] = $function_div;

        // リクエスト送信
        if (!$this->sendOrderRequest(
            $server_url,
            $listSendKey,
            $orderId,
            $listParam,
            $Order)
        ) {
            return false;
        }

        //レスポンス値取得
        $results = (array)$this->getResults();

        //注文データ更新処理（取引状況）
        return $this->updateOrderInfo($Order, $results);
    }

    /**
     * 注文データ更新（取引情報照会）
     *
     * 取引情報照会で得られた情報を注文データへ更新する.
     * 条件は以下の通り
     *
     * (1)照会レスポンス結果が0件ではない
     * (2)注文番号が同じ
     * (3)支払方法が同じ
     *
     *
     * 支払い方法は以下情報を利用
     *
     * 【取引情報照会レスポンス】
     * 0:クレジットカード, 1:ネットコンビニ, 2:ネットバンク, 3:電子マネー
     *
     * 【注文データ保持情報】
     * 10:クレジットカード決済
     * 30:コンビニ決済
     * 42～47：電子マネー決済
     * 52：ネットバンク決済
     *
     * @param Order $Order 注文情報
     * @param array $results 取引情報照会レスポンス
     * @return boolean
     */
    private function updateOrderInfo($Order, $results = [])
    {
        // 受注決済情報取得
        $YamatoOrderPayment = $this->yamatoPaymentMethodRepository->findOneBy(['Payment' => $Order->getPayment()]);

        // 支払方法種別ID
        $paymentMethodId = $YamatoOrderPayment->getMemo03();

        /*
        * (1)照会レスポンス結果が0件ではない
        * (2)注文番号が同じ
        * (3)支払方法が同じ
        */
        if ($results['resultCount'] > 0
        && $results['resultData']['orderNo'] == $Order->getId()
        && (
                ($results['resultData']['settleMethodDiv'] == '0' && $paymentMethodId == $this->eccubeConfig['YAMATO_PAYID_CREDIT'])
             || ($results['resultData']['settleMethodDiv'] == '1' && $paymentMethodId == $this->eccubeConfig['YAMATO_PAYID_CVS'])
             || ($results['resultData']['settleMethodDiv'] == '2' && $paymentMethodId == $this->eccubeConfig['YAMATO_PAYID_NETBANK'])
             || ($results['resultData']['settleMethodDiv'] == '3' && $paymentMethodId >= $this->eccubeConfig['YAMATO_PAYID_EDY'] && $paymentMethodId <= $this->eccubeConfig['YAMATO_PAYID_MOBILEWAON'])
           )
        ) {
            //取引状況更新
            $results['action_status'] = $results['resultData']['statusInfo'];

            //決済ログの記録
            $saveResult = array_merge($results, $results['resultData']);
            unset($saveResult['resultData']);
            foreach($saveResult as $key => $val) {
                if(is_array($val)) {
                    if(empty($val) == false) {
                        $saveResult[$key] = CommonUtil::serializeData($val);
                    } else {
                        unset($saveResult[$key]);
                    }
                }
            }

            $this->setSendResults($saveResult);

            return true;
        }
        return false;
    }

    /**
     * 再与信
     *
     * @param Order $Order 注文情報
     * @return boolean
     */
    public function doReauth($Order)
    {
        //クレジットカード決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();
        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_CREDIT']) {
            $msg = '再与信エラー：再与信処理に対応していない決済です。';
            $this->setError($msg);
            return false;
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        //API設定
        $function_div = 'A11';
        $server_url = $this->getApiUrl($function_div);

        //送信キー
        $listSendKey = [
            'function_div',
            'trader_code',
            'order_no'
        ];

        //個別パラメタ
        $listParam = [];
        $listParam['function_div'] = $function_div;

        // リクエスト送信
        if (!$this->sendOrderRequest(
            $server_url,
            $listSendKey,
            $orderId,
            $listParam,
            $Order)
        ) {
            return false;
        }

        return true;
    }

    /**
     * グローバルＩＰアドレス照会
     *
     * @return false|strings
     */
    public function doGetGlobalIpAddress()
    {
        // API設定
        $function_div = 'H01';
        $server_url = $this->getApiUrl($function_div);

        // 送信キー
        $listSendKey = [
            'function_div',
            'trader_code'
        ];

        // パラメータ設定
        $listParam = [];
        $listParam['function_div'] = $function_div;

        $sendData = $this->getSendData($listSendKey, $listParam);

        $ret = $this->sendRequest($server_url, $sendData);

        // リクエスト送信
        if ($ret == false) {
            return false;
        }

        //レスポンス値取得
        $results = (array)$this->getResults();

        //注文データ更新処理（取引状況）
        return $results['ipAddress'];
    }
}