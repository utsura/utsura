<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Util;

use Plugin\GmoPaymentGateway4\Service\Method\CreditCard;

/**
 * 決済モジュール用 汎用関数クラス
 */
class PaymentUtil
{
    /**
     * プラグインコード定数
     */
    const PLUGIN_CODE = "GmoPaymentGateway4";

    public static function &getInstance()
    {
        static $PaymentUtil;

        if (empty($PaymentUtil)) {
            $PaymentUtil = new PaymentUtil();
        }

        return $PaymentUtil;
    }

    /**
     * ログ出力（エラー）
     *
     * @param mixed $msg
     * @param array $masks 配列キーを指定してマスク
     */
    public static function logError($msg, $masks = ["Pass", "Token"])
    {
        $text = $msg;
        if (is_array($msg)) {
            $text = print_r(PaymentUtil::arrayMaskValue($msg, $masks), true);
        } elseif (is_object($msg)) {
            $text = get_class($msg);
        }
        logs(PaymentUtil::PLUGIN_CODE)->error($text);
    }

    /**
     * ログ出力（情報）
     *
     * @param mixed $msg
     * @param array $masks 配列キーを指定してマスク
     */
    public static function logInfo($msg, $masks = ["Pass", "Token"])
    {
        $text = $msg;
        if (is_array($msg)) {
            $text = print_r(PaymentUtil::arrayMaskValue($msg, $masks), true);
        } elseif (is_object($msg)) {
            $text = get_class($msg);
        }
        logs(PaymentUtil::PLUGIN_CODE)->info($text);
    }

    /**
     * ログ出力（デバッグ）
     *
     * @param mixed $msg
     * @param array $masks 配列キーを指定してマスク
     */
    public static function logDebug($msg, $masks = ["Pass", "Token"])
    {
        $text = $msg;
        if (is_array($msg)) {
            $text = print_r(PaymentUtil::arrayMaskValue($msg, $masks), true);
        } elseif (is_object($msg)) {
            $text = get_class($msg);
        }
        logs(PaymentUtil::PLUGIN_CODE)->debug($text);
    }

    /**
     * 処理区分を返す
     *
     * @param string 決済クラス名
     * @return array 処理区分
     */
    public function getJobCds($method_class)
    {
        $arrJobCds = [
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.jobcd.capture') => 'CAPTURE',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.jobcd.auth') => 'AUTH',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.jobcd.sauth') => 'SAUTH',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.jobcd.check') => 'CHECK',
        ];

        if ($method_class !== CreditCard::class) {
            // クレジットカード以外
            unset($arrJobCds[trans('gmo_payment_gateway.admin.' .
                                   'payment_edit.jobcd.sauth')]);
            unset($arrJobCds[trans('gmo_payment_gateway.admin.' .
                                   'payment_edit.jobcd.check')]);
        }

        return $arrJobCds;
    }

    /**
     * 支払い種別を返す
     *
     * @return array 支払い種別
     */
    public static function getCreditPayMethod()
    {
        $arrPayMethod = [
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method1') => '1-0',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method2') => '2-2',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method3') => '2-3',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method4') => '2-4',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method5') => '2-5',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method6') => '2-6',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method7') => '2-7',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method8') => '2-8',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method9') => '2-9',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method10') => '2-10',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method11') => '2-11',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method12') => '2-12',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method13') => '2-13',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method14') => '2-14',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method15') => '2-15',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method16') => '2-16',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method17') => '2-17',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method18') => '2-18',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method19') => '2-19',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method20') => '2-20',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method21') => '2-21',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method22') => '2-22',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method23') => '2-23',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method24') => '2-24',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method26') => '2-26',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method30') => '2-30',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method32') => '2-32',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method34') => '2-34',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method36') => '2-36',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method37') => '2-37',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method40') => '2-40',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method42') => '2-42',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method48') => '2-48',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method50') => '2-50',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method54') => '2-54',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method60') => '2-60',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method72') => '2-72',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method84') => '2-84',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method_1b') => '3-0',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method_2b') => '4-2',
            trans('gmo_payment_gateway.admin.' .
                  'payment_edit.credit.method_r') => '5-0',
        ];

        return $arrPayMethod;
    }

    /**
     * フィルターした支払い種別を返す
     *
     * @param array $filter フィルター配列
     * @return array 支払い種別
     */
    public function getFilterCreditPayMethod(array $filter = [])
    {
        $arrPayMethod = $this->getCreditPayMethod();

        // フィルターなしは空を返す
        if (empty($filter)) {
            return [];
        }

        $filterPayMethod = [];
        sort($filter, SORT_NATURAL);

        foreach ($filter as $method) {
            foreach ($arrPayMethod as $key => $value) {
                if ($value === $method) {
                    $filterPayMethod[$key] = $value;
                    break;
                }
            }
        }

        return $filterPayMethod;
    }

    /**
     * 支払い方法名を返す
     *
     * @param string $value 支払い方法値
     * @return array 支払い方法名
     */
    public static function getCreditPayMethodName($value)
    {
        // フィルターなしはすべて返す
        if (empty($value)) {
            return "";
        }

        $arrPayMethod = PaymentUtil::getCreditPayMethod();

        return array_search($value, $arrPayMethod);
    }

    public static function convCVSText($txt)
    {
        return mb_convert_kana($txt, 'KASV', 'UTF-8');
    }

    public static function convTdTenantName($shop_name)
    {
        if (empty($shop_name)) return '';
        $shop_name = mb_convert_encoding($shop_name, "EUC-JP", "UTF-8");
        $enc_name = base64_encode($shop_name);
        if (strlen($enc_name) <= 25) {
            return $enc_name;
        }
        return '';
    }

    /**
     * 禁止文字か判定を行う。
     *
     * @param string $value 判定対象
     * @return boolean 結果
     */
    public static function isProhibitedChar($value)
    {
        $check_char = mb_convert_encoding($value, "SJIS-win", "UTF-8");
        if (hexdec('8740') <= hexdec(bin2hex($check_char)) &&
            hexdec('879E') >= hexdec(bin2hex($check_char))) {
            return true;
        }
        if ((hexdec('ED40') <= hexdec(bin2hex($check_char)) &&
             hexdec('ED9E') >= hexdec(bin2hex($check_char))) ||
            (hexdec('ED9F') <= hexdec(bin2hex($check_char)) &&
             hexdec('EDFC') >= hexdec(bin2hex($check_char))) ||
            (hexdec('EE40') <= hexdec(bin2hex($check_char)) &&
             hexdec('EE9E') >= hexdec(bin2hex($check_char))) ||
            (hexdec('FA40') <= hexdec(bin2hex($check_char)) &&
             hexdec('FA9E') >= hexdec(bin2hex($check_char))) ||
            (hexdec('FA9F') <= hexdec(bin2hex($check_char)) &&
             hexdec('FAFC') >= hexdec(bin2hex($check_char))) ||
            (hexdec('FB40') <= hexdec(bin2hex($check_char)) &&
             hexdec('FB9E') >= hexdec(bin2hex($check_char))) ||
            (hexdec('FB9F') <= hexdec(bin2hex($check_char)) &&
             hexdec('FBFC') >= hexdec(bin2hex($check_char))) ||
            (hexdec('FC40') <= hexdec(bin2hex($check_char)) &&
             hexdec('FC4B') >= hexdec(bin2hex($check_char)))
        ) {
            return true;
        }
        if ((hexdec('EE9F') <= hexdec(bin2hex($check_char)) &&
             hexdec('EEFC') >= hexdec(bin2hex($check_char))) ||
            (hexdec('F040') <= hexdec(bin2hex($check_char)) &&
             hexdec('F9FC') >= hexdec(bin2hex($check_char)))
        ) {
            return true;
        }

        return false;
    }

    /**
     * 禁止文字を全角スペースに置換する。
     *
     * @param string $value 対象文字列
     * @return string 結果
     */
    public static function convertProhibitedChar($value)
    {
        $ret = $value;

        for ($i = 0; $i < mb_strlen($value); $i++) {
            $tmp = mb_substr($value, $i, 1);
            if (PaymentUtil::isProhibitedChar($tmp)) {
                $ret = str_replace($tmp, "　", $value);
            }
        }

        return $ret;
    }

    /**
     * 禁止半角記号を半角スペースに変換する。
     *
     * @param string $value
     * @return string 変換した値
     */
    public static function convertProhibitedKigo($value)
    {
        $prohiditedKigos = array('^','`','{','|','}','~','&','<','>','"','\'');

        foreach ($prohiditedKigos as $prohidited_kigo) {
            if (strstr($value, $prohidited_kigo)) {
                $value = str_replace($prohidited_kigo, " ", $value);
            }
        }

        return $value;
    }

    /**
     * 文字列から指定バイト数を切り出す。
     *
     * @param string $value
     * @param integer $len
     * @return string 結果
     */
    public static function subString($value, $len)
    {
        $value = mb_convert_encoding($value, "SJIS", "UTF-8");

        for ($i = 1; $i <= mb_strlen($value); $i++) {
            $tmp = mb_substr($value, 0, $i);
            if (strlen($tmp) <= $len) {
                $ret = mb_convert_encoding($tmp, "UTF-8", "SJIS");
            } else {
                break;
            }
        }

        return $ret;
    }

    /**
     * 日付をISO8601形式にフォーマットする
     *
     * @param string $date
     * @return string ISO8601 format date
     **/
    function formatISO8601($date)
    {
        $n = sscanf($date, '%4s%2s%2s%2s%2s%2s',
                    $year, $month, $day, $hour, $min, $sec);
        return sprintf('%s-%s-%s %s:%s:%s',
                       $year, $month, $day, $hour, $min, $sec);
    }

    /**
     * 配列データからログに記録しないデータをマスクする
     *
     * @param array $listData
     * @return array マスク後データ
     */
    function setMaskData($listData)
    {
        foreach ($listData as $key => $val) {
            switch ($key) {
                case 'CardNo':
                    $listData[$key] = str_repeat('*', 13) . substr($val, -3);
                    break;
                case 'SecurityCode':
                    $listData[$key] = str_repeat('*', 4);
                    break;
                case 'MemberName':
                case 'CustomerName':
                case 'CustomerKana':
                case 'ShopPass':
                case 'SitePass':
                case 'MemberName':
                case 'MailAddress':
                    $listData[$key] = str_repeat('*', 6);
                    break;
                default:
                    break;
            }
        }
        return $listData;
    }

    /**
     * 配列の指定キー項目の値をマスクする.
     *
     * @param array $arrData
     * @param array $maskKeys 部分一致
     */
    private static function arrayMaskValue($arrData,
                                           $maskKeys = array("Pass", "Token"))
    {
        if (!is_array($arrData)) {
            return $arrData;
        }

        foreach ($arrData as $key => $value) {
            if (is_array($value)) {
                $arrData[$key] =
                    PaymentUtil::arrayMaskValue($value, $maskKeys);
            } else {
                foreach ($maskKeys as $maskKey) {
                    if (stripos($key, $maskKey) !== false) {
                        $arrData[$key] = str_repeat("*", strlen($value));
                        break;
                    }
                }
            }
        }

        return $arrData;
    }

    /**
     * Get zero month
     *
     * @return array
     */
    public static function getZeroMonth() {
        $month_array = [];

        for ($i = 1; $i <= 12; $i++) {
            $val = sprintf('%02d', $i);
            $month_array[$val] = $val;
        }

        return $month_array;
    }

    /**
     * Get zero year
     *
     * @param type $star_year
     * @param type $end_year
     * @return array
     */
    public function getZeroYear($star_year, $end_year) {
        $year = $star_year;
        if (!$year)
            $year = date('Y');

        if (!$end_year)
            $end_year = (date('Y') + 3);

        $year_array = [];

        for ($i = $year; $i <= $end_year; $i++) {
            $key = substr($i, -2);
            $year_array[$key] = $key;
        }

        return $year_array;
    }

    /**
     * 24時間の時の配列を返す
     *
     * @param boolean $none 選択なしを含むかどうか
     * @return array
     */
    public static function getHours($none = true)
    {
        $hours = [];

        if ($none) {
            $hours[''] = '';
        }

        for ($i = 0; $i < 24; ++$i) {
            $hours[] = sprintf("%d", $i);
        }

        return $hours;
    }

    /**
     * 分の配列を返す
     *
     * @param boolean $none 選択なしを含むかどうか
     * @return array
     */
    public static function getMinutes($none = true)
    {
        $minutes = [];

        if ($none) {
            $minutes[''] = '';
        }

        for ($i = 0; $i < 60; ++$i) {
            $minutes[] = sprintf("%d", $i);
        }

        return $minutes;
    }
}
