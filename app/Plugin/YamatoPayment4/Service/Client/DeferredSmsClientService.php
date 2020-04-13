<?php
namespace Plugin\YamatoPayment4\Service\Client;

use Eccube\Entity\Order;

use Plugin\YamatoPayment4\Util\CommonUtil;

class DeferredSmsClientService extends DeferredClientService
{

    /**
     * SMS認証画面からのAPIリクエストパラメータを生成する。
     * 
     * @param Order $Order 受注情報
     * @param array $data フォームからの入力値
     * 
     * @return array $params APIへのリクエストパラメータ
     */
    public function createSmsRequestData($Order, $data) {
        // 1桁ずつ入力された認証コードを結合する
        $ninCode = $data['auth_param01'] . $data['auth_param02'] . $data['auth_param03'] . $data['auth_param04'];

        $params = [
            'ycfStrCode' => $this->userSettings['ycf_str_code'],
            'orderNo' => $Order->getId(),
            'ninCode' => mb_convert_kana($ninCode, 'n', 'utf-8'),
            'requestDate' => date('YmdHis'),
            'password' => $this->userSettings['ycf_str_password'],
        ];

        return $params;
    }

    /**
     * SMS認証APIにリクエストする
     * 
     * @param array $params リクエストパラメータ
     * 
     * @return boolean $result レスポンス解析ステータス
     */
    public function requestSmsAuth($params) {
        $ret = $this->sendRequest($this->getApiUrl('KAASA0020APIAction'), $params);

        if ($ret === false) {
            return false;
        }

        $status = false;
        $results = $this->getResults();

        switch($results['result']) {
            case $this->eccubeConfig['DEFERRED_SMS_AVAILABLE']:
                $status = true;
                break;
            case $this->eccubeConfig['DEFERRED_SMS_CODE_INVALID']:
                $this->setError($this->eccubeConfig['MESSAGE_DEFERRED_SMS_CODE_INVALID']);
                break;
            case $this->eccubeConfig['DEFERRED_SMS_CODE_EXPIRED']:
                $this->setError($this->eccubeConfig['MESSAGE_DEFERRED_SMS_CODE_EXPIRED']);
                break;
            case $this->eccubeConfig['DEFERRED_SMS_BAD_DELIVERY']:
                $this->setError($this->eccubeConfig['MESSAGE_DEFERRED_SMS_BAD_DELIVERY']);
                break;
            case $this->eccubeConfig['DEFERRED_SMS_BAD_STATUS']:
                $this->setError($this->eccubeConfig['MESSAGE_DEFERRED_SMS_BAD_STATUS']);
                break;
            default:
                $this->setError($this->eccubeConfig['MESSAGE_DEFERRED_SMS_GENERAL']);
                break;
        }

        return $status;
    }

    public function createPaymentRequestData($yamatoOrder, $phone_number) {
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
            'telNum' => $phone_number,
            'email' => $Order->getEmail(),
            'totalAmount' => intval($Order->getPaymentTotal()),
            'sendDiv' => 3,
            // 共通情報
            'requestDate' => date('YmdHis'),
            'password' => $this->userSettings['ycf_str_password'],
        ];

        // 項目が連番で複数になる可能性のある受注明細と配送先をそれぞれ追加する。
        $listParam = $this->pushOrderItems($listParam, $Order);
        $listParam = $this->pushShippings($listParam, $Order);

        return $listParam;
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

        /**
         * 審査結果判定
         * API仕様上 SMS認証待ち受け or 審査不通 のレスポンスが返るのでそれを評価する。
         */
        if($results['result'] == $this->eccubeConfig['DEFERRED_UNDER_EXAM']) {
            return $results['result'];
        } else {
            $this->setError($this->eccubeConfig['DEFFERED_FALL_AUTH']);
            return 0;
        }
    }
}