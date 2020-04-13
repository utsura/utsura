<?php
namespace Plugin\YamatoPayment4\Service\Client;

use Plugin\YamatoPayment4\Service\Client\BaseClientService;
use Plugin\YamatoPayment4\Util\CommonUtil;
use Eccube\Entity\Order;

class CreditClientService extends BaseClientService
{
    /**
     * お預かり情報照会
     * @param integer $customer_id 顧客ID
     * @param array $arrParam パラメタ
     * @return bool
     */
    function doGetCard($customer_id, $arrParam=[])
    {
        $functionDiv = 'A03';

        //オプション未契約の場合はお預かり照会を行わない
        if($this->userSettings['use_option'] == '1') return false;
        //非会員の場合はお預かり照会を行わない
        if(is_null($customer_id) || $customer_id == '0') {
            return false;
        }
        //API設定
        $server_url = $this->getApiUrl($functionDiv);
        //送信キー
        $arrSendKey = [
                'function_div',
                'trader_code',
                'member_id',
                'authentication_key',
                'check_sum'
        ];

        //個別パラメタ
        $arrParam['function_div'] = $functionDiv;
        $arrParam['member_id'] = $customer_id;
        $arrParam['authentication_key'] = $customer_id;
        $arrParam['check_sum'] = $this->paymentUtil->getCheckSum($arrParam, $this->userSettings);

        return $this->sendUtilRequest($server_url, $arrSendKey, [], $arrParam);
    }

    /**
     * クレジットトークン決済を行う.
     *
     * @param OrderExtension $OrderExtension 注文情報
     * @param array $listParam その他情報
     * @param PaymentExtension $PaymentExtension 支払方法情報
     * @return bool
     */
    public function doPaymentRequest($Order, $listParam)
    {
        $ret = false;

        // 会員IDの取得
        $customerId = '0';
        if (!is_null($Order->getCustomer())) {
            $customerId = $Order->getCustomer()->getId();
        }

        if(empty($this->paymentInfo)) {
            $this->setSetting($Order);
        }

        // 送信キー
        $sendKey = [
                'function_div',
                'trader_code',
                'device_div',
                'order_no',
                'settle_price',
                'buyer_name_kanji',
                'buyer_tel',
                'buyer_email',
                'pay_way',
                'goods_name',
                'card_code_api',
                'token'
        ];

        // クレジットトークン決済URL
        $function_div = 'A08';
        $server_url = $this->getApiUrl($function_div);

        // 機能区分
        $listParam['function_div'] = $function_div;
        // 決済ステータスを「決済手続き中」で記録
        $listParam['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_WAIT'];

        // 支払方法設定：本人認証サービス(3Dセキュア) 0:利用しない 1:利用する
        $listParam['auth_div'] = ($this->paymentInfo['TdFlag'] == '0') ? '2' : '3';

        // 3Dセキュア未加入・迂回時(3Dセキュア未加入/デバイスPC[デバイスコード:2]以外の場合)
        $device = CommonUtil::getDeviceDivision();
        if ((isset($listParam['info_use_threeD']) && $listParam['info_use_threeD'] == $this->eccubeConfig['YAMATO_3D_EXCLUDED']) || ($device != 2 && $device != 1)) {
            $listParam['auth_div'] = '2';
        }

        //セキュリティコード
        //認証区分が「2：３Ｄセキュアなし、セキュリティコード認証あり」
        if ($listParam['auth_div'] == '2') {
            $sendKey[] = 'security_code';                //セキュリティコード
        }

        //加盟店ECサイトURL
        //認証区分が「2：３Ｄセキュアなし」
        if ($listParam['auth_div'] != '2') {
            $sendKey[] = 'trader_ec_url';                //加盟点ECサイトURL
            $listParam['trader_ec_url'] = $this->app->url('yamato_shopping_payment', ['mode' => '3dTran']);
            //決済ステータスを「3Dセキュア認証中」で記録
            $listParam['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_3D_WAIT'];
        }

        /*
         * オプションサービス区分 00：通常受注 01：オプションサービス受注
        * (条件)
        * オプションサービス契約済み ：use_option==0(必須)
        * 購入時カード預かり         ：register_card==1
        * 預かりカードでの購入       ：use_registed_card==1
        * 予約商品購入               ：tpl_is_reserve==true
        */
        if ($this->userSettings['use_option'] == '0'
        && ($listParam['register_card'] == '1'
           || $listParam['use_registed_card'] == '1'
           || $listParam['tpl_is_reserve'] == true)
        ) {
            $listParam['option_service_div'] = '01';
        } else {
            $listParam['option_service_div'] = '00';
        }

        // カード会社コード
        // トークン決済の場合はカード会社コード9:VISAで固定
        $listParam['card_code_api'] = '9';

        // 決済トークン
        $listParam['token'] = $listParam['webcollectToken'];

        // ECカートコード（EC-CUBE4で固定）
        $sendKey[] = 'ec_cart_code';
        $listParam['ec_cart_code'] = 'EC-CUBE4';

        $ret = $this->sendOrderRequest(
                $server_url,
                $sendKey,
                $Order->getId(),
                $listParam,
                $Order
        );

        return $ret;
    }

    /**
     * 定期バッチ用の決済メソッド
     *
     * @param Order $Order, array $listParam
     * @return bool
     */
    public function doPaymentRequestForPeriodicBatch($Order, $listParam)
    {
        $ret = false;

        // 会員IDの取得
        if (!is_null($Order->getCustomer())) {
            $listParam['member_id'] = $Order->getCustomer()->getId();
        }

        if(empty($this->paymentInfo)) {
            $this->setSetting($Order);
        }

        // 送信キー
        $sendKey = [
                'function_div',
                'trader_code',
                'device_div',
                'order_no',
                'settle_price',
                'buyer_name_kanji',
                'buyer_tel',
                'buyer_email',
                'pay_way',
                'goods_name',
                'option_service_div',
                'card_key',
                'last_credit_date',
                'auth_div',
                'member_id',
                'authentication_key',
                'card_code_api',
                'check_sum'
        ];

        // 定期バッチからの決済ならばトークンを利用しない
        $function_div = 'A01';
        $server_url = $this->getApiUrl($function_div);

        // 機能区分
        $listParam['function_div'] = $function_div;
        // 決済ステータスを「決済手続き中」で記録
        $listParam['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_WAIT'];

        // auth_div = 0 : 3Dセキュアとセキュリティコードを利用しない
        $listParam['auth_div'] = '0';

        // オプションサービス受注
        $listParam['option_service_div'] = '01';

        // カードキーは1固定
        $listParam['card_key'] = '1';

        $this->doGetCard($listParam['member_id']);
        $cardInfo = $this->getArrCardInfo($this->results);
        $listParam['lastCreditDate'] = $cardInfo['cardData'][0]['lastCreditDate'];

        $listParam['authentication_key'] = $this->moduleSettings['authentication_key'];

        $listParam['check_sum'] = $this->paymentUtil->getCheckSum($listParam, $this->userSettings);

        $listParam['pay_way'] = '1';

        // カード会社コード
        // トークン決済の場合はカード会社コード9:VISAで固定
        $listParam['card_code_api'] = '9';

        $ret = $this->sendOrderRequest(
                $server_url,
                $sendKey,
                $Order->getId(),
                $listParam,
                $Order
        );

        return $ret;
    }

    /**
     * カード預かり情報取得（整理済）
     *
     * 預かり情報１件の場合と２件以上の場合で配列の構造を合わせる
     * @param array $listCardInfos
     * @return array $listResults
     */
    public static function getArrCardInfo($listCardInfos = [])
    {
        $listResults = [];
        if (isset($listCardInfos['cardUnit']) && $listCardInfos['cardUnit'] == '1') {
            $listTmp = [];
            foreach ($listCardInfos as $key => $val) {
                if ($key == 'cardData') {
                    $listTmp[$key][0] = $val;
                } else {
                    $listTmp[$key] = $val;
                }
            }
            $listResults = $listTmp;
        } else {
            $listResults = $listCardInfos;
        }

        $default_card_key = null;
        if (isset($listResults['cardUnit']) && $listResults["cardUnit"] > 0) {
            $lastCreditDate = 0;
            foreach ((array)$listResults['cardData'] as $n => $cardData) {

                $listResults['cardData'][$n]['data']  = 'カード番号：' . $cardData['maskingCardNo'];
                $listResults['cardData'][$n]['data'] .= '　有効期限：' . substr($cardData['cardExp'], 2) . '年';
                $listResults['cardData'][$n]['data'] .= substr($cardData['cardExp'], 0, 2) . '月';
                $listResults['cardData'][$n]['card_key'] = $cardData['cardKey'];

                // カードの最終利用日を取得
                if ($lastCreditDate <= $cardData['lastCreditDate']) {
                    $default_card_key = $cardData['cardKey'];
                    $lastCreditDate = $cardData['lastCreditDate'];
                }
            }
        } else {
            $listResults['cardData'] = [];
        }

        $listResults['default_card_key'] = $default_card_key;
        return $listResults;
    }

    /**
     * クレジットカードお預かり情報削除
     *
     * @param integer $customer_id 顧客ID
     * @param array $arrParam パラメタ
     * @return bool
     */
    public function doDeleteCard($customer_id, $arrParam = [])
    {
        //オプション未契約の場合はお預かり照会を行わない
        if($this->userSettings['use_option'] == '1') return false;

        //非会員の場合はお預かり情報削除を行わない
        if (is_null($customer_id) || $customer_id == '0') {
            return false;
        }

        //API設定
        $functionDiv = 'A05';

        //API設定
        $server_url = $this->getApiUrl($functionDiv);
        //送信キー
        $arrSendKey = [
            'function_div',
            'trader_code',
            'member_id',
            'authentication_key',
            'check_sum',
            'card_key',
            'last_credit_date'
        ];

        //個別パラメタ
        $arrParam['function_div'] = $functionDiv;
        $arrParam['member_id'] = $customer_id;
        $arrParam['authentication_key'] = $customer_id;
        $arrParam['check_sum'] = $this->paymentUtil->getCheckSum($arrParam, $this->userSettings);

        return $this->sendUtilRequest($server_url, $arrSendKey, [], $arrParam);
    }

    /**
     * クレジットカードお預かり情報登録
     */
    public function doRegistCard($Customer, $dummyOrder, $arrParam = [])
    {
        //オプション未契約の場合はreturn
        if($this->userSettings['use_option'] == '1') return false;

        //API設定
        $functionDiv = 'A08';

        //API設定
        $server_url = $this->getApiUrl($functionDiv);
        // 送信キー
        $arrSendKey = [
            'function_div',
            'trader_code',
            'device_div',
            'order_no',
            'settle_price',
            'buyer_name_kanji',
            'buyer_tel',
            'buyer_email',
            'pay_way',
            'goods_name',
            'card_code_api',
            'token'
        ];

        //個別パラメタ
        $arrParam['function_div'] = $functionDiv;
        $arrParam['auth_div']           = '2';      //3Dセキュア利用しない
        $arrParam['pay_way']            = '1';      //支払回数は強制的に「一括払い」
        $arrParam['option_service_div'] = '01';     //登録時のため 01:オプションサービス受注
        $arrParam['member_id']          = $Customer->getId();
        // カード会社コード
        // トークン決済の場合はカード会社コード9:VISAで固定
        $arrParam['card_code_api'] = '9';
        // ECカートコード（EC-CUBE4で固定）
        $arrSendKey[] = 'ec_cart_code';
        $arrParam['ec_cart_code'] = 'EC-CUBE4';

        return $this->sendUtilRequest($server_url, $arrSendKey, $dummyOrder, $arrParam);

    }

    /**
     * ダミー受注生成
     */
    public function getDummyOrder($Customer)
    {
        $Order = new Order();

        $Order->setCustomer($Customer);
        $Order->setPaymentTotal(1);
        $Order->copyProperties(
            $Customer,
            [
                'id',
                'create_date',
                'update_date',
                'del_flg',
            ]
        );

        return $Order;
    }

    /**
     * ダミー受注をキャンセル
     */
    public function doCancelDummyOrder($dummyOrder)
    {
        //決済設定情報取得
        $orderId = $dummyOrder->getId();
        // $this->setSetting($dummyOrder);

        //API設定
        $function_div = 'A06';
        $server_url = $this->getApiUrl($function_div);

        //送信キー
        $listSendKey = array(
                'function_div',
                'trader_code',
                'order_no'
        );

        //個別パラメタ
        $listParam = array();
        $listParam['function_div'] = $function_div;

        // リクエスト送信
        if (!$this->sendOrderRequest(
                    $server_url,
                    $listSendKey,
                    $orderId,
                    $listParam,
                    $dummyOrder)
        ) {
            return false;
        }

        return true;
    }
}
