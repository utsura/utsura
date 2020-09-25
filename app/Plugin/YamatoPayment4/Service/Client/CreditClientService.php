<?php

namespace Plugin\YamatoPayment4\Service\Client;

use Eccube\Common\EccubeConfig;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\OrderRepository;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Util\CommonUtil;
use Eccube\Entity\Order;

class CreditClientService extends BaseClientService
{
    public $productRepository;
    public $orderRepository;

    public function __construct(
        EccubeConfig $eccubeConfig,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        ConfigRepository $yamatoConfigRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        RouterInterface $router
    ) {
        parent::__construct(
            $eccubeConfig,
            $yamatoConfigRepository,
            $yamatoPaymentMethodRepository,
            $yamatoOrderRepository,
            $router
        );

        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * 3Dセキュア実行を行う.
     *
     * @param Order $Order     受注データ
     * @param array $listParam その他情報
     *
     * @return bool
     */
    public function doSecureTran($Order, $listParam)
    {
        $this->setResults($listParam);
        $memo05 = null;

        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        //決済データ確認
        if (!empty($YamatoOrder)) {
            $memo05 = $YamatoOrder->getMemo05();
        }
        if (!empty($memo05)) {
            $payData = $memo05;
        } else {
            $this->setError('3Dセキュア認証遷移エラー 決済データが受注情報に見つかりませんでした。');

            return false;
        }

        //取引ID確認
        if (isset($payData['order_no']) && $payData['order_no'] != $Order->getId()) {
            $this->setError('3Dセキュア認証遷移エラー 取引IDが一致しませんでした。');

            return false;
        }

        // 送信キー
        $sendKey = [
            'function_div',
            'trader_code',
            'order_no',
            'comp_cd',
            'card_no',
            'card_exp',
            'item_price',
            'item_tax',
            'cust_cd',
            'shop_id',
            'term_cd',
            'crd_res_cd',
            'res_ve',
            'res_pa',
            'res_code',
            'three_d_inf',
            'three_d_tran_id',
            'send_dt',
            'hash_value',
            'three_d_token',
        ];

        // 3DセキュアURL
        $function_div = 'A02';
        $server_url = $this->getApiUrl($function_div);

        //機能区分
        $listParam['function_div'] = $function_div;
        //決済状況を「3Dセキュア認証中」で記録
        $listParam['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_3D_WAIT'];
        //カード番号
        $listParam['card_no'] = $listParam['CARD_NO'];
        //有効期限
        $listParam['card_exp'] = $listParam['CARD_EXP'];
        //3Dトークン
        $listParam['threeDToken'] = $payData['threeDToken'];

        //3Dセキュア実行
        $ret = $this->sendOrderRequest($server_url, $sendKey, $Order->getId(), $listParam, $Order);

        return $ret;
    }

    /**
     * 3Dセキュア実行を行う.(トークン決済使用).
     *
     * @param Order $Order     受注データ
     * @param array $listParam その他情報
     *
     * @return bool
     */
    public function doSecureTranToken($Order, $listParam)
    {
        $this->setResults($listParam);
        $memo05 = null;

        $YamatoOrder = $this->yamatoOrderRepository->findOneBy(['Order' => $Order]);
        //決済データ確認
        if (!empty($YamatoOrder)) {
            $memo05 = $YamatoOrder->getMemo05();
        }
        if (!empty($memo05)) {
            $payData = $memo05;
        } else {
            $this->setError('3Dセキュア認証遷移エラー 決済データが受注情報に見つかりませんでした。');

            return false;
        }

        //取引ID確認
        if (isset($payData['order_no']) && $payData['order_no'] != $Order->getId()) {
            $this->setError('3Dセキュア認証遷移エラー 取引IDが一致しませんでした。');

            return false;
        }

        $sendKey = [
            'function_div',
            'trader_code',
            'order_no',
            'comp_cd',
            'token',
            'card_exp',
            'item_price',
            'item_tax',
            'cust_cd',
            'shop_id',
            'term_cd',
            'crd_res_cd',
            'res_ve',
            'res_pa',
            'res_code',
            'three_d_inf',
            'three_d_tran_id',
            'send_dt',
            'hash_value',
            'three_d_token',
        ];

        // 3DセキュアURL
        $function_div = 'A09';
        $server_url = $this->getApiUrl($function_div);

        //機能区分
        $listParam['function_div'] = $function_div;
        //決済状況を「3Dセキュア認証中」で記録
        $listParam['action_status'] = $this->eccubeConfig['YAMATO_ACTION_STATUS_3D_WAIT'];
        // 決済トークン
        $listParam['token'] = $listParam['TOKEN'];
        //有効期限
        $listParam['card_exp'] = $listParam['CARD_EXP'];
        //3Dトークン
        $listParam['threeDToken'] = $payData['threeDToken'];

        //3Dセキュア実行
        $ret = $this->sendOrderRequest($server_url, $sendKey, $Order->getId(), $listParam, $Order);

        return $ret;
    }

    /**
     * お預かり情報照会.
     *
     * @param int   $customer_id 顧客ID
     * @param array $arrParam    パラメタ
     *
     * @return bool
     */
    public function doGetCard($customer_id, $arrParam = [])
    {
        $functionDiv = 'A03';

        //オプション未契約の場合はお預かり照会を行わない
        if ($this->userSettings['use_option'] == '1') {
            return false;
        }
        //非会員の場合はお預かり照会を行わない
        if (is_null($customer_id) || $customer_id == '0') {
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
                'check_sum',
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
     * @param OrderExtension   $OrderExtension   注文情報
     * @param array            $listParam        その他情報
     * @param PaymentExtension $PaymentExtension 支払方法情報
     *
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

        if (empty($this->paymentInfo)) {
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
                'token',
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
            $listParam['trader_ec_url'] = $this->router->generate('yamato_three_d_secure_receive', ['orderId' => $Order->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
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

        //予約商品購入の場合
        if ($listParam['option_service_div'] == '01' && $listParam['tpl_is_reserve'] == true) {
            $sendKey[] = 'scheduled_shipping_date';       //出荷予定日
            //出荷予定日取得
            $maxScheduledShippingDate = $this->getMaxScheduledShippingDate($Order->getId());
            //パラメータ用に整形してセット
            $listParam['scheduled_shipping_date'] = $maxScheduledShippingDate->format('Ymd');
        }

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
     * 定期バッチ用の決済メソッド.
     *
     * @param Order $Order, array $listParam
     *
     * @return bool
     */
    public function doPaymentRequestForPeriodicBatch($Order, $listParam)
    {
        $ret = false;

        // 会員IDの取得
        if (!is_null($Order->getCustomer())) {
            $listParam['member_id'] = $Order->getCustomer()->getId();
        }

        if (empty($this->paymentInfo)) {
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
                'check_sum',
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
     * カード預かり情報取得（整理済）.
     *
     * 預かり情報１件の場合と２件以上の場合で配列の構造を合わせる
     *
     * @param array $listCardInfos
     *
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
        if (isset($listResults['cardUnit']) && $listResults['cardUnit'] > 0) {
            $lastCreditDate = 0;
            foreach ((array) $listResults['cardData'] as $n => $cardData) {
                $listResults['cardData'][$n]['data'] = 'カード番号：'.$cardData['maskingCardNo'];
                $listResults['cardData'][$n]['data'] .= '　有効期限：'.substr($cardData['cardExp'], 2).'年';
                $listResults['cardData'][$n]['data'] .= substr($cardData['cardExp'], 0, 2).'月';
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
     * クレジットカードお預かり情報削除.
     *
     * @param int   $customer_id 顧客ID
     * @param array $arrParam    パラメタ
     *
     * @return bool
     */
    public function doDeleteCard($customer_id, $arrParam = [])
    {
        //オプション未契約の場合はお預かり照会を行わない
        if ($this->userSettings['use_option'] == '1') {
            return false;
        }

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
            'last_credit_date',
        ];

        //個別パラメタ
        $arrParam['function_div'] = $functionDiv;
        $arrParam['member_id'] = $customer_id;
        $arrParam['authentication_key'] = $customer_id;
        $arrParam['check_sum'] = $this->paymentUtil->getCheckSum($arrParam, $this->userSettings);

        return $this->sendUtilRequest($server_url, $arrSendKey, [], $arrParam);
    }

    /**
     * クレジットカードお預かり情報登録.
     */
    public function doRegistCard($Customer, $dummyOrder, $arrParam = [])
    {
        //オプション未契約の場合はreturn
        if ($this->userSettings['use_option'] == '1') {
            return false;
        }

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
            'token',
        ];

        //個別パラメタ
        $arrParam['function_div'] = $functionDiv;
        $arrParam['auth_div'] = '2';      //3Dセキュア利用しない
        $arrParam['pay_way'] = '1';      //支払回数は強制的に「一括払い」
        $arrParam['option_service_div'] = '01';     //登録時のため 01:オプションサービス受注
        $arrParam['member_id'] = $Customer->getId();
        // カード会社コード
        // トークン決済の場合はカード会社コード9:VISAで固定
        $arrParam['card_code_api'] = '9';
        // ECカートコード（EC-CUBE4で固定）
        $arrSendKey[] = 'ec_cart_code';
        $arrParam['ec_cart_code'] = 'EC-CUBE4';

        return $this->sendUtilRequest($server_url, $arrSendKey, $dummyOrder, $arrParam);
    }

    /**
     * ダミー受注生成.
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
     * ダミー受注をキャンセル.
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
                'order_no',
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

    /**
     * 購入データと商品テーブルから予約商品出荷予定日を取得する.
     *
     * １注文に対し複数の予約商品が存在する場合は
     * 出荷予定日が一番未来の日付を返す
     *
     * @param int $order_id
     *
     * @return \DateTime $maxScheduledDate YYYY-MM-DD
     */
    public function getMaxScheduledShippingDate($order_id)
    {
        $OrderItems = $this->orderRepository
            ->find($order_id)
            ->getOrderItems();

        /*
         * 購入商品明細情報取得
         * (1)商品種別ID
         * (2)予約商品出荷予定日
         */
        $maxScheduledDate = null;
        foreach ($OrderItems as $OrderItem) {
            if (!$OrderItem->isProduct()) {
                continue;
            }
            /* @var OrderItem $OrderItem */
            // 商品種別取得
            $saleTypeId = $OrderItem
                ->getProductClass()
                ->getSaleType()
                ->getId();

            // 予約商品の場合
            if ($saleTypeId == $this->eccubeConfig['SALE_TYPE_ID_RESERVE']) {
                // 商品マスタ拡張データ取得
                /** @var Product $Product */
                $Product = $this->productRepository
                    ->find($OrderItem->getProduct()->getId());
                if (is_null($Product)) {
                    continue;
                }
                // 予約商品出荷予定日取得
                $reserveDate = $Product->getReserveDate();
                // 予約商品出荷予定日が一番未来の日付を取得する
                if (!empty($reserveDate)
                    && $maxScheduledDate < $reserveDate
                ) {
                    $maxScheduledDate = $reserveDate;
                }
            }
        }

        return $maxScheduledDate;
    }

    /**
     * 予約販売可否判別
     * 　予約商品購入
     * 　モジュール設定値　オプションあり
     * 　再与信日期限を超えていない場合.
     *
     * @param bool  $reserveFlg
     * @param Order $Order
     *
     * @return bool
     */
    public function isReserve($reserveFlg, Order $Order)
    {
        if (!$reserveFlg) {
            return false;
        }

        //オプションサービスを契約していない場合（0:契約済 1:未契約）
        if ($this->userSettings['use_option'] != '0') {
            return false;
        }

        //出荷予定日取得
        $scheduled_shipping_date = $this->getMaxScheduledShippingDate($Order->getId());
        if (is_null($scheduled_shipping_date)) {
            return false;
        }

        return $this->isWithinReCreditLimit($scheduled_shipping_date);
    }

    /**
     * 注文に予約商品が含まれているか判別.
     *
     * @param Order $Order
     *
     * @return bool 予約商品を含む場合、true
     */
    public function isReservedOrder($Order)
    {
        // 受注明細取得
        $OrderItems = $Order->getOrderItems();
        /* @var OrderItem $OrderItem */
        foreach ($OrderItems as $OrderItem) {
            if (!$OrderItem->isProduct()) {
                continue;
            }
            // 商品種別取得
            $saleTypeId = $OrderItem
                ->getProductClass()
                ->getSaleType()
                ->getId();
            // 予約商品の場合
            if ($saleTypeId == $this->eccubeConfig['SALE_TYPE_ID_RESERVE']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 予約商品再与信期限内チェック.
     *
     * @param \DateTime $scheduled_shipping_date YYYY-MM-DD
     *
     * @return bool 予約商品の再与信期限日（出荷予定日含む10日前）前の場合、true
     */
    public function isWithinReCreditLimit($scheduled_shipping_date)
    {
        //出荷予定日の9日前
        //再与信は出荷予定日を含む10日前のため9日前として算出
        $today = new \DateTime('midnight');
        $reCreditDate = clone $scheduled_shipping_date;

        return $today < $reCreditDate->modify("-{$this->eccubeConfig['YAMATO_DEADLINE_RECREDIT']} days");
    }
}
