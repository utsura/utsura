<?php
namespace Plugin\YamatoPayment4\Util;

use Eccube\Common\EccubeConfig;

use Plugin\YamatoPayment4\Entity\Config;
use Plugin\YamatoPayment4\Repository\ConfigRepository;

/**
 * 決済モジュール用 汎用関数クラス
 */
class PluginUtil
{
    private $eccubeConfig;

    /**
     * コンストラクタ
     *
     * @param $eccubeConfig
     */
    public function __construct(EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * 決済方法の名前一覧を取得する
     *
     * @return array 決済方法一覧
     */
    public function getPaymentTypeNames()
    {
        return [
            $this->eccubeConfig['YAMATO_PAYID_CREDIT'] => $this->eccubeConfig['YAMATO_PAYNAME_CREDIT'],
            $this->eccubeConfig['YAMATO_PAYID_DEFERRED'] => $this->eccubeConfig['YAMATO_PAYNAME_DEFERRED'],
        ];
    }

    /**
     * 決済方式の内部名一覧を取得する
     *
     * @return array 支払方法コード
     */
    public function getPaymentTypeCodes()
    {
        return [
            $this->eccubeConfig['YAMATO_PAYID_CREDIT'] => $this->eccubeConfig['YAMATO_PAYNAME_CREDIT'],
            $this->eccubeConfig['YAMATO_PAYID_DEFERRED'] => $this->eccubeConfig['YAMATO_PAYNAME_DEFERRED'],
        ];
    }

    /**
     * マスター：動作モード 一覧取得
     */
    public function getExecMode()
    {
        return [
            "0" => 'テスト環境',
            "1" => '本番環境',
        ];
    }

    /**
     * マスター：他社配送設定一覧取得
     */
    public function getDeliveryServiceCode()
    {
        return [
            '00' => 'ヤマト配送',
            '99' => '他社配送',
        ];
    }

    /**
     * マスター：オプションサービス 一覧取得
     */
    public function getUseOption()
    {
        return [
            "0" => '契約済み',
            "1" => '未契約',
        ];
    }

    /**
     * マスター：予約販売機能 / メールの追跡情報表示機能 / スマホタイプ利用有無 一覧取得
     */
    public function getUtilization()
    {
        return [
            "1" => '利用する',
            "0" => '利用しない',
        ];
    }


    /**
     * マスター：請求書同梱 一覧取得
     */
    public function getSendDivision()
    {
        return [
            "0" => '同梱しない',
            "1" => '同梱する',
        ];
    }

    /**
     * 支払種別一覧を取得する
     *
     * @return array 支払回数
     */
    public function getCreditPayMethod()
    {
        return [
            '1' => '一括払い',
            '2' => '分割2回払い',
            '3' => '分割3回払い',
            '5' => '分割5回払い',
            '6' => '分割6回払い',
            '10' => '分割10回払い',
            '12' => '分割12回払い',
            '15' => '分割15回払い',
            '18' => '分割18回払い',
            '20' => '分割20回払い',
            '24' => '分割24回払い',
            '0' => 'リボ払い',
        ];
    }

    /**
     * 2ケタ表示(0付加)の月情報を取得
     *
     * @return array 月データ
     */
    public function getZeroMonth()
    {
        $month_array = [];
        for ($i = 1; $i <= 12; $i++) {
            $val = sprintf('%02d', $i);
            $month_array[$val] = $val;
        }

        return $month_array;
    }

    /**
     * 2ケタ表示(0付加)の年情報を取得
     *
     * @param string $star_year 開始年
     * @param string $end_year 終了年
     * @return array 年データ
     */
    public function getZeroYear($star_year = null, $end_year = null)
    {
        if (!$star_year) {
            $star_year = DATE('Y');
        }
        if (!$end_year) {
            $end_year = (DATE('Y') + 3);
        }
        $years = [];
        for ($i = $star_year; $i <= $end_year; $i++) {
            $key = substr($i, -2);
            $years[$key] = $key;
        }
        return $years;
    }

    public function getYamatoBulkPaymentStatus()
    {
        return [
            'commit'  => '出荷情報登録',
            'clear'   => '出荷情報取消',
            'amount'  => '金額変更',
            'inquiry' => '取引照会',
            'cancel'  => '決済取消',
            'reauth'  => '再与信',
        ];
    }

    public function getYamatoBulkDeferredStatus()
    {
        return [
            'commit'  => '出荷情報登録',
            'amount'  => '金額変更',
            'cancel'  => '与信取消',
            'get_auth' => '与信結果取得',
            'get_trade_info' => '取引状況取得',
            'invoice_reissue' => '請求内容変更・請求書再発行',
            'withdraw_reissue' => '請求書再発行取り下げ',
        ];
    }
}