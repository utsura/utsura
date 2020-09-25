<?php

namespace Plugin\YamatoPayment4\Service\Client;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Plugin\YamatoPayment4\Entity\YamatoOrder;
use Plugin\YamatoPayment4\Repository\ConfigRepository;
use Plugin\YamatoPayment4\Repository\YamatoOrderRepository;
use Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository;
use Plugin\YamatoPayment4\Util\CommonUtil;
use Plugin\YamatoPayment4\Util\PaymentUtil;

class BaseClientService
{
    protected $eccubeConfig;
    protected $yamatoConfigRepository;
    protected $yamatoPaymentMethodRepository;
    protected $yamatoOrderRepository;
    protected $router;
    protected $userSettings;
    protected $paymentUtil;
    protected $paymentId;
    protected $paymentMethod;
    protected $paymentInfo;
    protected $moduleSetting;
    public $error = [];
    public $results = null;
    public $sendResults = [];
    public $sendData = null;

    public function __construct(
        EccubeConfig $eccubeConfig,
        ConfigRepository $yamatoConfigRepository,
        YamatoPaymentMethodRepository $yamatoPaymentMethodRepository,
        YamatoOrderRepository $yamatoOrderRepository,
        RouterInterface $router
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->yamatoConfigRepository = $yamatoConfigRepository;
        $this->userSettings = $this->yamatoConfigRepository->get()->getSubData();
        $this->paymentUtil = new PaymentUtil($this->eccubeConfig, $this->yamatoConfigRepository);
        $this->yamatoPaymentMethodRepository = $yamatoPaymentMethodRepository;
        $this->yamatoOrderRepository = $yamatoOrderRepository;
        $this->router = $router;
    }

    public function setSetting(Order $Order)
    {
        $paymentMethodRepository = $this->yamatoPaymentMethodRepository->findOneBy(['Payment' => $Order->getPayment()]);
        $this->paymentId = $paymentMethodRepository->getPayment()->getId();
        $this->paymentMethod = $paymentMethodRepository->getPaymentMethod();
        $this->paymentInfo = $paymentMethodRepository->getMemo05();
        if ($paymentMethodRepository->getMemo03() == $this->eccubeConfig['YAMATO_PAYID']['CREDIT']) {
            $this->moduleSettings = $this->paymentUtil->getModuleSettings($Order, $this->paymentInfo);
            $this->moduleSettings['Payment'] = [
                'paymentId' => $this->paymentId,
                'paymentMethod' => $this->paymentMethod,
                'paymentInfo' => $this->paymentInfo,
            ];
        }
    }

    public function updatePluginPurchaseLog($data)
    {
        $url = $this->router->generate('yamato_payment4_log', [], UrlGeneratorInterface::ABSOLUTE_URL);

        unset($data['error']);
        CommonUtil::printLog('yamato_payment4_log:send', $data);

        $checkKeys = ['order_id', 'status'];
        $data['hash'] = CommonUtil::getArrayToHash($data, $checkKeys);
        $data['checkKey'] = implode(',', $checkKeys);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $message = curl_error($curl);
        $info['message'] = $message;
        $info['result'] = $header = substr($result, 0, $info['header_size']);
        curl_close($curl);

        CommonUtil::printLog('yamato_payment4_log:recv', $info);
    }

    public function setEccubeConfig($eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
    }

    public function getUserSettings()
    {
        return $this->userSettings;
    }

    public function setUserSettings($userSettings)
    {
        $this->userSettings = $userSettings;
    }

    public function getPaymentInfo()
    {
        return $this->paymentInfo;
    }

    public function setPaymentInfo($paymentInfo)
    {
        $this->paymentInfo = $paymentInfo;
    }

    public function getModuleSetting()
    {
        return $this->moduleSettings;
    }

    public function setModuleSetting($moduleSettings)
    {
        $this->moduleSettings = $moduleSettings;
    }

    /**
     * エラーメッセージ設定.
     *
     * @param string $msg
     */
    public function setError($msg)
    {
        $this->error[] = $msg;

        CommonUtil::printLog('setError', $msg);
    }

    /**
     * エラーメッセージ取得.
     *
     * @return array
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 送信データ設定.
     *
     * @param string $msg
     */
    public function setSendedData($data)
    {
        $this->sendData = $data;

        CommonUtil::printLog('setSendData', $data);
    }

    /**
     * 送信データ取得.
     *
     * @return array
     */
    public function getSendedData()
    {
        return $this->sendData;
    }

    /**
     * 通信結果を設定する.
     *
     * @param array $results
     */
    protected function setResults($results)
    {
        $this->results = $results;

        CommonUtil::printLog('setResults', $results);
    }

    /**
     * 通信結果を設定する.
     *
     * @return array $results
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * 送信・受信結果を設定する.
     *
     * @param unknown $results
     */
    public function setSendResults($results)
    {
        $this->sendResults = $results;
        CommonUtil::printLog('setSendResults', $results);
    }

    /**
     * 送信・受信結果を設定する.
     *
     * @return array $results
     */
    public function getSendResults()
    {
        return $this->sendResults;
    }

    /**
     * ヤマト決済API URLをテスト環境と本番環境に振り分ける.
     *
     * @param string $code APIコード
     *
     * @return string API URL
     */
    protected function getApiUrl($code)
    {
        $api_url = ($this->userSettings['exec_mode'] == '1') ? 'api.url' : 'api.test.gateway';

        return isset($this->eccubeConfig[$api_url][$code]) ? $this->eccubeConfig[$api_url][$code] : '';
    }

    /**
     * 初期化する.
     */
    public function clear()
    {
        $this->error = [];
        $this->results = null;
        $this->sendResults = [];
        $this->sendData = [];
    }

    /**
     * 送信用データ取得.
     *
     * @param array            $sendKey          送信項目
     * @param array            $listParam        その他情報
     * @param OrderExtension   $OrderExtension   注文情報
     * @param PaymentExtension $PaymentExtension 支払方法情報
     *
     * @return array
     */
    protected function getSendData(array $sendKey, array $listParam, $Order = null)
    {
        // 支払方法設定取得 (plg_yamato_payment_method#memo05)
        $paymentConfig = $this->paymentInfo;

        $sendData = [];
        foreach ($sendKey as $key) {
            switch ($key) {
                case 'trader_code':
                    $sendData[$key] = $this->userSettings['shop_id'];
                    break;
                case 'device_div':
                    $sendData[$key] = CommonUtil::getDeviceDivision();
                    break;
                case 'order_no':
                    $sendData[$key] = $Order->getId();
                    break;
                case 'settle_price':
                case 'new_price':
                    $sendData[$key] = intval($Order->getPaymentTotal());
                    break;
                case 'buyer_name_kanji':
                    $sendData[$key] = CommonUtil::convertProhibitedChar($Order->getName01().'　'.$Order->getName02());
                    break;
                case 'buyer_name_kana':
                    $sendData[$key] = CommonUtil::convertProhibitedChar($Order->getKana01().'　'.$Order->getKana02());
                    break;
                case 'buyer_tel':
                    $sendData[$key] = $Order->getPhoneNumber();
                    break;
                case 'buyer_email':
                    $sendData[$key] = $Order->getEmail();
                    break;
                case 'goods_name':
                    $sendData[$key] = $this->getItemName($Order);
                    break;
                case 'card_code_api':
                    if (empty($listParam[$key])) {
                        $sendData[$key] = '9'; // 固定
                    } else {
                        $sendData[$key] = $listParam[$key];
                    }
                    break;
                case 'reserve_1':
                    $sendData[$key] = 'EC-CUBE';
                    break;
                case 'last_credit_date':
                    $sendData[$key] = (isset($listParam['lastCreditDate']))
                        ? $listParam['lastCreditDate']
                        : '';
                    break;
                case 'card_exp':
                    $card_exp = isset($listParam['card_exp']) ? $listParam['card_exp'] : '';
                    $card_exp_month = isset($listParam['card_exp_month']) ? $listParam['card_exp_month'] : '';
                    $card_exp_year = isset($listParam['card_exp_year']) ? $listParam['card_exp_year'] : '';
                    $sendData[$key] = (!empty($card_exp))
                        ? $card_exp
                        : $card_exp_month.$card_exp_year;
                    break;
                //3Dセキュア用
                case 'comp_cd':
                case 'item_price':
                case 'item_tax':
                case 'cust_cd':
                case 'shop_id':
                case 'term_cd':
                case 'crd_res_cd':
                case 'res_ve':
                case 'res_pa':
                case 'res_code':
                case 'send_dt':
                case 'hash_value':
                    $paramName = strtoupper($key);
                    $sendData[$key] = (isset($listParam[$paramName]))
                        ? $listParam[$paramName]
                        : '';
                    break;
                case 'three_d_inf':
                    $sendData[$key] = (isset($listParam['3D_INF']))
                        ? $listParam['3D_INF']
                        : '';
                    break;
                case 'three_d_tran_id':
                    $sendData[$key] = (isset($listParam['3D_TRAN_ID']))
                        ? $listParam['3D_TRAN_ID']
                        : '';
                    break;
                case 'three_d_token':
                    $sendData[$key] = (isset($listParam['threeDToken']))
                        ? $listParam['threeDToken']
                        : '';
                    break;
                default:
                    //優先順位
                    //$listParam > $paymentConfig > $this->userSettings
                    if (isset($listParam[$key])) {
                        $sendData[$key] = $listParam[$key];
                    } elseif (isset($paymentConfig[$key])) {
                        $sendData[$key] = $paymentConfig[$key];
                    } elseif (isset($this->userSettings[$key])) {
                        $sendData[$key] = $this->userSettings[$key];
                    }
                    break;
            }
        }

        return $sendData;
    }

    /**
     * 注文情報リクエスト送信
     *
     * @param string           $url              宛先URL
     * @param array            $sendKey          送信項目
     * @param int              $order_id         受注ID
     * @param array            $listParam        その他情報
     * @param PaymentExtension $PaymentExtension 支払方法情報
     *
     * @return bool
     */
    protected function sendOrderRequest($url, $sendKey, $order_id, $listParam, $Order)
    {
        if (is_null($this->paymentInfo)) {
            $this->setSetting($Order);
        }

        if (empty($Order)) {
            return false;
        }

        // 送信データ作成
        $sendData = $this->getSendData($sendKey, $listParam, $Order);
        $this->setSendedData($sendData);

        // リクエスト送信
        $ret = $this->sendRequest($url, $sendData);
        if ($ret) {
            $results = (array) $this->getResults();
        //unset($results['threeDAuthHtml']);
        } else {
            $results = [];
            $results['error'] = $this->getError();
        }

        // 決済情報設定
        $results['order_no'] = $order_id;

        // 送信パラメータのマージ
        $paramNames = [
            'function_div',
            'settle_price',
            'device_div',
            'option_service_div',
            'auth_div',
            'pay_way',
            'card_key',
            'card_code_api',
            'scheduled_shipping_date',
            'slip_no',
            'new_price',
        ];
        foreach ($paramNames as $paramName) {
            if (isset($sendData[$paramName]) && !is_null($sendData[$paramName])) {
                $results[$paramName] = $sendData[$paramName];
            }
        }

        // 送信パラメータのマージ
        $paramNames = [
            'action_status',
            'cvs',
        ];
        foreach ($paramNames as $paramName) {
            if (isset($listParam[$paramName]) && !is_null($listParam[$paramName])) {
                $results[$paramName] = $listParam[$paramName];
            }
        }

        $this->setSendResults($results);

        if (!empty($this->error)) {
            return false;
        }

        return true;
    }

    /**
     * ユーティリティリクエスト.
     *
     * @param string $url        API URL
     * @param array  $arrSendKey 送信キー
     * @param array  $Order      注文情報
     * @param array  $arrParam   その他パラメタ
     *
     * @return bool $ret リクエスト結果
     */
    protected function sendUtilRequest($url, $arrSendKey, $Order, $arrParam)
    {
        //リクエスト用データ取得
        $arrSendData = $this->getSendData($arrSendKey, $arrParam, $Order);
        $this->setSendedData($arrSendData);
        //リクエスト送信
        return $this->sendRequest($url, $arrSendData);
    }

    /**
     * 汎用リクエスト送信
     *
     * @param string $url        宛先URL
     * @param array  $sendParams 送信データ
     *
     * @return bool
     */
    protected function sendRequest($url, $sendParams)
    {
        CommonUtil::printLog('BaseClientService::sendRequest start');
        CommonUtil::printLog('$url = '.$url);
        CommonUtil::printLog('$sendParams = ['.print_r(array($sendParams), true).']');

        foreach ($sendParams as $key => $value) {
            // UTF-8以外は文字コード変換を行う
            $encode = mb_detect_encoding($value);
            $listData[$key] = ($encode != 'UTF-8') ? mb_convert_encoding($value, 'UTF-8', $encode) : $value;
        }

        // 通信実行
        try {
            $c = curl_init();
            curl_setopt($c, CURLOPT_URL, $url);
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($listData));
            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($c, CURLOPT_SSLVERSION, 0);
            curl_setopt($c, CURLOPT_TIMEOUT, $this->eccubeConfig['YAMATO_API_HTTP_TIMEOUT']);

            $response = curl_exec($c);
            $info = curl_getinfo($c);
            $errno = curl_errno($c);
            $error = curl_error($c);
            curl_close($c);
        } catch (\Exception $e) {
            $msg = '通信エラー: '.$e->getMessage()."\n";
            $msg .= $e->getTraceAsString();
            CommonUtil::printLog($msg);
            $this->setError($msg);

            return false;
        }

        // ステータス取得
        $r_code = $info['http_code'];
        if ($r_code != 200) {
            $msg = 'HTTPレスポンスエラー:CODE:'.$r_code;
            CommonUtil::printLog($msg);
            $this->setError($msg);

            return false;
        }

        // レスポンス取得
        if (empty($response)) {
            $msg = 'レスポンスデータエラー: レスポンスがありません。';
            CommonUtil::printLog($msg);
            $this->setError($msg);

            return false;
        }

        // レスポンス解析
        $listRet = $this->parseResponse($response);
        $this->setResults($listRet);
        if (!empty($this->error)) {
            return false;
        }

        CommonUtil::printLog('BaseClientService::sendRequest end');

        return true;
    }

    /**
     * レスポンスを解析する.
     *
     * @param string $string レスポンス
     *
     * @return array 解析結果
     */
    public function parseResponse($xml)
    {
        // XMLパーサを生成する。
        $arrXML = json_decode(json_encode(simplexml_load_string($xml)), true);

        if (isset($arrXML['errorCode']) && !empty($arrXML['errorCode'])) {
            $error_message = $this->getErrorMessageByErrorCode($arrXML['errorCode']);
            $this->setError($error_message);
        }

        return $arrXML;
    }

    /**
     * エラーコードに対応するエラーメッセージを取得する.
     *
     * @param string $errorCode エラーコード
     *
     * @return string エラーメッセージ
     */
    public function getErrorMessageByErrorCode($errorCode)
    {
        $errMsgList = $this->eccubeConfig['yamato_error_msg'];
        $message = isset($errMsgList[$errorCode]) ? $errMsgList[$errorCode] : '';

        return 'エラーコード: '.$errorCode.' '.$message;
    }

    public function getPaymentCode(\Eccube\Entity\Order $Order)
    {
        return $this->yamatoPaymentMethodRepository->findOneBy(['Payment' => $Order->getPayment()])->getMemo03();
    }

    /**
     * 受注情報に決済情報をセット.
     *
     * @param YamatoOrder $yamatoOrder 受注情報
     * @param array       $payData     決済レスポンス array('key'=>'value')
     *
     * @return YamatoOrder
     */
    public function setOrderPayData(YamatoOrder $yamatoOrder, array $payData)
    {
        //決済情報チェック
        CommonUtil::printLog('$payData', $payData);
        $payData = (array) CommonUtil::checkEncode($payData);

        //受注情報から決済ログ取得
        $listLog = $yamatoOrder->getMemo09();
        $listLog[] = [date('Y-m-d H:i:s') => $payData];
        $yamatoOrder->setMemo09($listLog);

        //受注情報から決済データ取得
        $paymentData = $yamatoOrder->getMemo05();
        CommonUtil::printLog('$paymentData', $paymentData);
        //決済データのマージ
        foreach ($payData as $key => $val) {
            if (empty($val) && !empty($paymentData[$key])) {
                unset($payData[$key]);
            }
        }
        $paymentData = array_merge($paymentData, (array) $payData);
        $yamatoOrder->setMemo05($paymentData);

        //決済種別の記録
        if (isset($payData['payment_code'])) {
            $yamatoOrder->setMemo03($payData['payment_code']);
        }

        //決済状況の記録
        if (isset($payData['action_status'])) {
            $yamatoOrder->setMemo04($payData['action_status']);
        }

        //審査結果の記録
        if (isset($payData['result_code'])) {
            $yamatoOrder->setMemo06($payData['result_code']);
        }

        //決済金額の記録
        if (isset($payData['settle_price'])) {
            $yamatoOrder->setMemo07($payData['settle_price']);
        }

        // 伝票番号or荷物問い合わせURL
        if (isset($payData['slip'])) {
            $yamatoOrder->setMemo10($payData['slip']);
        }

        return $yamatoOrder;
    }

    /**
     * 受注データに決済情報をセット.
     *
     * @param YamatoOrder $yamatoOrder 受注決済情報
     * @param array       $payData     決済レスポンス
     *
     * @return YamatoOrderPayment
     */
    public function setOrderPaymentViewData(YamatoOrder $yamatoOrder, array $payData)
    {
        $arrPaymentConfig = $this->paymentInfo;
        $order_id = $yamatoOrder->getOrder()->getId();

        $memo02 = $yamatoOrder->getMemo02();
        $memo05 = $yamatoOrder->getMemo05();

        $listData = [];

        // 送信日時(yyyyMMddHHmmss)
        if (isset($payData['returnDate']) && !is_null($payData['returnDate'])) {
            $listData['returnDate']['name'] = '注文日時';
            if (isset($memo02['returnDate'])) {
                $listData['returnDate']['value'] = $memo02['returnDate']['value'];
            } else {
                $listData['returnDate']['value'] = CommonUtil::getDateFromNumber('Y年m月d日 H時i分s秒', $payData['returnDate']);
            }
        }
        // ご注文番号
        if (!is_null($order_id)) {
            $listData['OrderId']['name'] = 'ご注文番号';
            $listData['OrderId']['value'] = $order_id;
        }
        // 与信承認番号
        if (isset($memo05['crdCResCd']) && !is_null($memo05['crdCResCd'])) {
            $listData['crdCResCd']['name'] = '与信承認番号';
            $listData['crdCResCd']['value'] = $memo05['crdCResCd'];
        }

        /* セブン-イレブン決済 */
        // 払込票番号
        if (isset($payData['billingNo']) && !is_null($payData['billingNo'])) {
            $listData['billingNo']['name'] = '払込票番号';
            $listData['billingNo']['value'] = $payData['billingNo'];
        }
        // 払込票URL
        if (isset($payData['billingUrl']) && !is_null($payData['billingUrl'])) {
            $listData['billingUrl']['name'] = '払込票URL';
            $listData['billingUrl']['value'] = $payData['billingUrl'];
        }

        /* ファミリーマート決済 */
        // 企業コード
        if (isset($payData['companyCode']) && !is_null($payData['companyCode'])) {
            $listData['companyCode']['name'] = '企業コード';
            $listData['companyCode']['value'] = $payData['companyCode'];
        }
        // 注文番号(ファミリーマート)
        if (isset($payData['orderNoF']) && !is_null($payData['orderNoF'])) {
            $listData['orderNoF']['name'] = '注文番号(ファミリーマート)';
            $listData['orderNoF']['value'] = $payData['orderNoF'];
        }

        /* ローソン、サークルKサンクス、ミニストップ、セイコーマート決済 */
        // 受付番号
        if (isset($payData['econNo']) && !is_null($payData['econNo'])) {
            $listData['econNo']['name'] = '受付番号';
            $listData['econNo']['value'] = $payData['econNo'];
        }

        /* コンビニ決済共通 */
        // 支払期限日
        if (isset($payData['expiredDate']) && !is_null($payData['expiredDate'])) {
            $listData['expiredDate']['name'] = '支払期限日';
            $listData['expiredDate']['value'] = CommonUtil::getDateFromNumber('Y年m月d日', $payData['expiredDate']);
        }

        // 決済完了案内タイトル（クレジット）
        if (isset($arrPaymentConfig['order_mail_title']) && !is_null($arrPaymentConfig['order_mail_title'])
        && isset($arrPaymentConfig['order_mail_body']) && !is_null($arrPaymentConfig['order_mail_body'])
        ) {
            $listData['order_mail_title']['name'] = $arrPaymentConfig['order_mail_title'];
            $listData['order_mail_title']['value'] = $arrPaymentConfig['order_mail_body'];
        }

        // 決済完了案内タイトル（コンビニ）
        if (isset($payData['cvs']) && !is_null($payData['cvs'])) {
            $title_key = 'order_mail_title_'.$payData['cvs'];
            $body_key = 'order_mail_body_'.$payData['cvs'];
            if (isset($arrPaymentConfig[$title_key]) && !is_null($arrPaymentConfig[$title_key])
            && isset($arrPaymentConfig[$body_key]) && !is_null($arrPaymentConfig[$body_key])
            ) {
                $listData[$title_key]['name'] = $arrPaymentConfig[$title_key];
                $listData[$title_key]['value'] = $arrPaymentConfig[$body_key];
            }
        }

        if (!empty($listData)) {
            // 受注データ更新
            $listData['title']['value'] = '1';
            $listData['title']['name'] = $this->paymentMethod;

            $yamatoOrder->setMemo02($listData);

            if (isset($payData['cvs']) && !is_null($payData['cvs'])) {
                // 注文完了画面および注文受付メールに付与するメッセージをセットする
                $this->setCompleteMessageForCvs($yamatoOrder, $payData['cvs']);
            }
        }

        return $yamatoOrder;
    }

    /**
     * 商品名取得.
     *
     * @param Order $Order 受注データ
     *
     * @return string 商品名
     */
    protected function getItemName(Order $Order)
    {
        $OrderDetails = $Order->getOrderItems();

        if ($OrderDetails->count() == 0) {
            return '';
        }

        $ret = 'お買い上げ商品';
        $ret .= '(4.0)';

        return $ret;
    }

    /**
     * 注文完了画面および注文受付メールに付与するメッセージをセットする.
     *
     * @param YamatoOrder $yamatoOrder
     * @param int         $cvsId
     */
    protected function setCompleteMessageForCvs(YamatoOrder $yamatoOrder, $cvsId)
    {
        $yamatoOrderInfo = $yamatoOrder->getMemo02();
        $completeMailMessage = <<< EOM
***********************************************
コンビニ決済情報
***********************************************

EOM;

        $completeMessage = <<< EOM
***********************************************<br/>
コンビニ決済情報<br/>
***********************************************<br/>
<br/>
EOM;

        $keyLists = [
            'returnDate',
            'OrderId',
            'billingNo',
            'billingUrl',
            'companyCode',
            'orderNoF',
            'econNo',
            'expiredDate',
            "order_mail_title_{$cvsId}",
        ];

        foreach ($keyLists as $key) {
            if (isset($yamatoOrderInfo[$key]) && !is_null($yamatoOrderInfo[$key])) {
                $target = $yamatoOrderInfo[$key];

                $completeMailMessage = <<< EOM
{$completeMailMessage}
{$target['name']}：{$target['value']}
EOM;

                if (strpos($key, 'order_mail_title_') !== false) {
                    // 注文完了画面で表示させるため、決済完了案内本文 の改行コードを br タグに置き換える
                    $target['value'] = nl2br($target['value']);
                }

                $completeMessage = <<< EOM
{$completeMessage}
{$target['name']}：{$target['value']}<br/>
EOM;
            }
        }

        $yamatoOrder
            ->getOrder()
            ->setCompleteMailMessage($completeMailMessage)
            ->setCompleteMessage($completeMessage);
    }
}
