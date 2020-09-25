<?php
namespace Plugin\YamatoPayment4\Service\Client;

use Eccube\Entity\Order;
use Eccube\Entity\OrderItem;
use Eccube\Entity\Master\OrderItemType;

use Plugin\YamatoPayment4\Service\Client\BaseClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;

class DeferredClientService extends BaseClientService
{
    public function createRequestData($yamatoOrder) {
        $Order = $yamatoOrder->getOrder();
        $listParam = [
            // 基本情報
            'ycfStrCode' => $this->userSettings['ycf_str_code'],
            'orderNo' => $Order->getId(),
            'orderYmd' => date_format($Order->getCreateDate(), 'Ymd'),
            'shipYmd' => date('Ymd', strtotime('+' . $this->userSettings['ycf_ship_ymd'] . 'day', $Order->getCreateDate()->getTimestamp())),
            'name' => CommonUtil::convHalfToFull($Order->getName01() . ' ' . $Order->getName02()),
            'nameKana' => mb_convert_kana($Order->getKana01() . ' ' . $Order->getKana02(), 'k'),
            'postCode' => $Order->getPostalCode(),
            'address1' => CommonUtil::convHalfToFull($Order->getPref() . $Order->getAddr01()),
            'address2' => CommonUtil::convHalfToFull($Order->getAddr02()),
            'telNum' => $Order->getPhoneNumber(),
            'email' => $Order->getEmail(),
            'totalAmount' => intval($Order->getPaymentTotal()),
            'sendDiv' => CommonUtil::decideSendDiv($Order, $this->userSettings['ycf_send_div']),
            // 共通情報
            'requestDate' => date('YmdHis'),
            'password' => $this->userSettings['ycf_str_password'],
        ];

        // 項目が連番で複数になる可能性のある受注明細と配送先をそれぞれ追加する。
        $listParam = $this->pushOrderItems($listParam, $Order);
        $listParam = $this->pushShippings($listParam, $Order);

        return $listParam;
    }

    // 受注明細のリクエストパラメータを追加。
    protected function pushOrderItems($request_data, $Order) {
        $copy_Order = clone $Order;
        $OrderItems = $copy_Order->getOrderItems();

        // 明細が許容行数を超える場合、商品明細を丸めた明細を生成する。
        if ($OrderItems->count() > $this->eccubeConfig['DEFERRED_DELIV_ADDR_MAX']) {

            $arrTemp['product_name'] = '商品';
            $arrTemp['quantity'] = 1;
            $arrTemp['price'] = 0;
            $arrTemp['tax'] = 0;
            foreach ($OrderItems as $i => $OrderItem) {
                if ($OrderItem->isProduct()) {
                    $arrTemp['price'] += $OrderItem['price'];
                    $arrTemp['tax'] += $OrderItem['tax'];
                    unset($OrderItems[$i]);
                }
            }

            $tempOrderItem = new OrderItem();
            $tempOrderItem->setProductName($arrTemp['product_name']);
            $tempOrderItem->setPrice($arrTemp['price']);
            $tempOrderItem->setTax($arrTemp['tax']);
            $tempOrderItem->setQuantity($arrTemp['quantity']);
            $tempOrderItemType = new OrderItemType();
            $tempOrderItemType->setId(OrderItemType::PRODUCT);
            $tempOrderItem->setOrderItemType($tempOrderItemType);

            $copy_Order->addOrderItem($tempOrderItem);
        }

        $OrderItems = $copy_Order->getOrderItems();

        $seq = 0;
        foreach ($OrderItems as $val) {
            $seq++;

            // 商品名（文字数を切り詰める処理）
            $product_name = $val['product_name'];
            $product_name = CommonUtil::convertProhibitedKigo($product_name);
            $product_name = CommonUtil::convHalfToFull($product_name);
            $request_data['itemName' . $seq] = mb_substr($product_name, 0, 30, 'UTF-8');

            $request_data['itemCount' . $seq] =  intval($val['quantity']);
            // 商品の明細にのみ消費税を加算する。
            if ($val->getOrderItemType()->getId() == OrderItemType::PRODUCT) {
                $request_data['unitPrice' . $seq] =  intval($val['price']) + intval($val['tax']);
            } else {
                $request_data['unitPrice' . $seq] =  intval($val['price']);
            }
            $request_data['subTotal' . $seq] =   $request_data['unitPrice' . $seq] * intval($val['quantity']);
        }

        return $request_data;
    }

    // 配送情報のリクエストパラメータを追加。
    protected function pushShippings($request_data, $Order) {
        $encoding = 'UTF-8';
        // 住所（文字数を切り詰める処理）
        $address = CommonUtil::convHalfToFull($request_data['address1'] . $request_data['address2']);
        $request_data['address1'] = mb_substr($address, 0, 25, $encoding);
        $request_data['address2'] = null;
        if (mb_substr($address, 25, 25, $encoding) != '') {
            $request_data['address2'] = mb_substr($address, 25, 25, $encoding);
        }
        $copy_Order = clone $Order;
        $Shippings = $copy_Order->getShippings();
        foreach ($Shippings as $i => $val) {
            $seq = $i + 1;
            if ($seq == 1) {
                // 1件目の項目に数字を振らない
                $seq = '';
            }
            $request_data['sendName' . $seq] =  CommonUtil::convHalfToFull($val['name01'] . '　' . $val['name02']);
            $request_data['sendPostCode' . $seq] =  $val['postal_code'];

            // お届け先・住所（文字数を切り詰める処理）
            $sendAddress = CommonUtil::convHalfToFull($val['pref']->getName() . $val['addr01'] . $val['addr02']);
            $request_data['sendAddress1' . $seq] = mb_substr($sendAddress, 0, 25, $encoding);
            $request_data['sendAddress2' . $seq] = null;
            if (mb_substr($sendAddress, 25, 25, $encoding) != '') {
                $request_data['sendAddress2' . $seq] = mb_substr($sendAddress, 25, 25, $encoding);
            }

            $request_data['sendTelNum' . $seq] =  $val['phone_number'];
        }

        return $request_data;
    }

    /**
     * クロネコ代金後払い決済を行う.
     *
     * @param Order $Order 受注情報
     * @param array $reqParam リクエストパラメータ
     * @return bool
     */
    public function doPaymentRequest($Order, $reqParam)
    {
        if(empty($this->paymentInfo)) {
            $this->setSetting($Order);
        }

        // 後払い決済URL
        $apiEndpoint = $this->getApiUrl($this->eccubeConfig['DEF_REQ_AUTH_REALTIME']);

        return $this->lfSendOrderRequest($apiEndpoint, $reqParam);
    }

    /**
     * 注文情報リクエスト
     *
     * @param string $url APIエンドポイント
     * @param array $reqParam リクエストパラメータ
     * @return bool
     */
    protected function lfSendOrderRequest($url, $reqParam)
    {
        //リクエスト送信
        $ret = $this->sendRequest($url, $reqParam);

        if ($ret) {
            $results = (array)$this->getResults();
        } else {
            $results = [];
            $results['error'] = $this->getError();
        }

        //決済情報設定
        $results['order_no'] = $reqParam['orderNo'];

        //審査結果
        if (isset($results['result']) && !is_null($results['result'])) {
            $results['result_code'] = $results['result'];

            //取引状況
            if ($results['result'] == $this->eccubeConfig['DEFERRED_AVAILABLE']) {
                $results['action_status'] = $this->eccubeConfig['DEFERRED_STATUS_AUTH_OK'];
            }
        }

        //決済金額総計
        if (isset($reqParam['totalAmount']) && !is_null($reqParam['totalAmount'])) {
            $results['totalAmount'] = $reqParam['totalAmount'];
        }

        //審査結果判定
        if (isset($results['result']) && $results['result'] != $this->eccubeConfig['DEFERRED_AVAILABLE']) {
            $this->setError($this->eccubeConfig['DEFFERED_FALL_AUTH']);
            return false;
        }

        if (!empty($this->error)) {
            return false;
        }

        return true;
    }

    /**
     * 後払い受注 出荷情報登録
     *
     * @param Order $Order 受注情報
     * @return array 処理結果配列
     */
    public function doShipmentEntry($Order, $ShippingRepository)
    {
        //後払い決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();

        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_DEFERRED'] && $orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_SMS_DEFERRED']) {
            $msg = '出荷情報登録に対応していない決済です。';
            $this->setError($msg);
            return [false,[]];
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        //API設定
        $server_url = $this->getApiUrl($this->eccubeConfig['DEF_ENTRY_SHIPMENT']);

        $listParam = [
            'ycfStrCode' => $this->userSettings['ycf_str_code'],
            'orderNo' => $orderId,
            'requestDate' => date('YmdHis'),
            'password' => $this->userSettings['ycf_str_password'],
        ];

        $Shippings = $ShippingRepository->findBy(['Order' => $Order]);

        $delivery_service_code = $this->userSettings["delivery_service_code"];
        if (empty($Shippings) && $delivery_service_code == '00') {
            return [false,[]];
        }

        $listSuccessSlip = [];
        foreach ($Shippings as $Shipping) {
            $shippingId = $Shipping->getId();
            $pre_tracking_no = $Shipping->getPreTrackingNumber();
            $tracking_no = $Shipping->getTrackingNumber();

            // 送り状登録済み かつ 前回から変更なしなら次の配送へ
            if (!is_null($pre_tracking_no) && $tracking_no == $pre_tracking_no) {
                continue;
            }

            // 送り状番号設定
            if($delivery_service_code == '00') {
                $listParam['processDiv'] = 0;
                $listParam['paymentNo'] = $tracking_no;
            } else {
                $listParam['processDiv'] = 2;
                $listParam['paymentNo'] = $orderId;
            }

            // 出荷情報変更の場合
            if (!is_null($pre_tracking_no)) {
                $listParam['processDiv'] = 1;
                $listParam['shipYmd'] = date('Ymd', strtotime('+' . $this->userSettings['ycf_ship_ymd'] . 'day', $Order->getCreateDate()->getTimestamp()));
                $listParam['beforePaymentNo'] = $pre_tracking_no;
            }

            // リクエスト送信
            if (!$this->sendRequest($server_url, $listParam)) {
                return [false,[]];
            }

            $listSuccessSlip[$shippingId] = $listParam['paymentNo'];

            if($delivery_service_code != '00') {
                break;
            }
        }

        return [true, $listSuccessSlip];
    }

    /**
     * 後払い受注 金額変更(減額)
     *
     * @param Order $Order 受注情報
     * @return bool
     */
    public function doChangePrice($Order)
    {
        //後払い決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();

        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_DEFERRED'] && $orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_SMS_DEFERRED']) {
            $msg = '金額変更に対応していない決済です。';
            $this->setError($msg);
            return false;
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        //API設定
        $server_url = $this->getApiUrl($this->eccubeConfig['DEF_CHANGE_AMOUNT']);

        $listParam = [
            'ycfStrCode' => $this->userSettings['ycf_str_code'],
            'orderNo' => $orderId,
            'shipYmd' => date('Ymd', strtotime('+' . $this->userSettings['ycf_ship_ymd'] . 'day', $Order->getCreateDate()->getTimestamp())),
            'postCode' => $Order->getPostalCode(),
            'address1' => CommonUtil::convHalfToFull($Order->getPref() . $Order->getAddr01()),
            'address2' => CommonUtil::convHalfToFull($Order->getAddr02()),
            'totalAmount' => intval($Order->getPaymentTotal()),
            'requestDate' => date('YmdHis'),
            'password' => $this->userSettings['ycf_str_password'],
        ];

        $listParam = $this->pushOrderItems($listParam, $Order);

        // リクエスト送信
        if (!$this->sendRequest($server_url, $listParam)) {
            return false;
        }

        return true;
    }

    /**
     * 後払い受注 与信取消
     *
     * @param Order $Order 受注情報
     * @return bool
     */
    public function doCancel($Order)
    {
        //後払い決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();

        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_DEFERRED'] && $orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_SMS_DEFERRED']) {
            $msg = '与信取消に対応していない決済です。';
            $this->setError($msg);
            return false;
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        $server_url = $this->getApiUrl($this->eccubeConfig['DEF_CANCEL_AUTH']);

        $listParam = [
            'ycfStrCode' => $this->userSettings['ycf_str_code'],
            'orderNo' => $orderId,
            'requestDate' => date('YmdHis'),
            'password' => $this->userSettings['ycf_str_password'],
        ];

        // リクエスト送信
        if (!$this->sendRequest($server_url, $listParam)) {
            return false;
        }

        return true;
    }

    /**
     * 後払い受注 与信結果取得
     *
     * @param Order $Order 受注情報
     * @return bool
     */
    public function doGetAuth($Order)
    {
        //後払い決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();

        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_DEFERRED'] && $orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_SMS_DEFERRED']) {
            $msg = '与信結果取得に対応していない決済です。';
            $this->setError($msg);
            return false;
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        $server_url = $this->getApiUrl($this->eccubeConfig['DEF_GET_AUTH_RESULT']);

        $listParam = [
            'ycfStrCode' => $this->userSettings['ycf_str_code'],
            'orderNo' => $orderId,
            'requestDate' => date('YmdHis'),
            'password' => $this->userSettings['ycf_str_password'],
        ];

        // リクエスト送信
        if (!$this->sendRequest($server_url, $listParam)) {
            return false;
        }

        return true;
    }

    /**
     * 後払い受注 取引状況取得
     *
     * @param Order $Order 受注情報
     * @return bool
     */
    public function doGetTradeInfo($Order)
    {
        //後払い決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();

        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_DEFERRED'] && $orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_SMS_DEFERRED']) {
            $msg = '取引状況取得に対応していない決済です。';
            $this->setError($msg);
            return false;
        }

        //決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        $server_url = $this->getApiUrl($this->eccubeConfig['DEF_GET_TRAN_STATUS']);

        $listParam = [
            'ycfStrCode' => $this->userSettings['ycf_str_code'],
            'orderNo' => $orderId,
            'requestDate' => date('YmdHis'),
            'password' => $this->userSettings['ycf_str_password'],
        ];

        // リクエスト送信
        if (!$this->sendRequest($server_url, $listParam)) {
            return false;
        }

        return true;
    }

    /**
     * 後払い受注 請求内容変更・請求書再発行
     *
     * @param Order $Order 受注情報
     * @return bool
     */
    public function doInvoiceReissue($Order, $mode)
    {
        // 後払い決済以外は対象外
        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        $orderPaymentId = $YamatoOrder->getMemo03();

        if ($orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_DEFERRED'] && $orderPaymentId != $this->eccubeConfig['YAMATO_PAYID_SMS_DEFERRED']) {
            $msg = '請求書再発行・請求内容変更 / 請求書取下げ に対応していない決済です。';
            $this->setError($msg);
            return false;
        }

        // 決済設定情報取得
        $orderId = $Order->getId();
        $this->setSetting($Order);

        $server_url = $this->getApiUrl($this->eccubeConfig['DEF_REISSUE_INVOICE']);

        $listParam = [
            'ycfStrCode' => $this->userSettings['ycf_str_code'],
            'orderNo' => $orderId,
            'password' => $this->userSettings['ycf_str_password'],
            'shipYmd' => date('Ymd', strtotime('+' . $this->userSettings['ycf_ship_ymd'] . 'day', $Order->getCreateDate()->getTimestamp())),
            'sendDiv' => CommonUtil::decideSendDiv($Order, $this->userSettings['ycf_send_div']),
            'postCode' => $Order->getPostalCode(),
            'address1' => CommonUtil::convHalfToFull($Order->getPref() . $Order->getAddr01()),
            'address2' => CommonUtil::convHalfToFull($Order->getAddr02()),
        ];

        if ($mode == 'invoice_reissue') {
            $listParam['requestContents'] = $this->eccubeConfig['DEFERRED_INVOICE_REISSUE'];
            $listParam['reasonReissue'] = 6;    // 再発行理由コード 6 → その他
            $listParam['reasonReissueEtc'] = '不明';
        } else {
            $listParam['requestContents'] = $this->eccubeConfig['DEFERRED_INVOICE_REISSUE_WITHDRAWN'];
        }

        $listParam = $this->pushOrderItems($listParam, $Order);
        $listParam = $this->pushShippings($listParam, $Order);

        return $this->lfSendOrderRequest($server_url, $listParam);
    }
}
