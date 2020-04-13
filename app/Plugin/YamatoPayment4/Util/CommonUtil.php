<?php

namespace Plugin\YamatoPayment4\Util;

use Eccube\Util\StringUtil;


class CommonUtil
{
    /**
     * unSerializeした配列を返す
     *
     * @param string $data
     * @return mixed
     */
    public static function unSerializeData($data)
    {
        return unserialize($data);
    }

    /**
     * serializeした文字列を返す
     *
     * @param array $data
     * @return string
     */
    public static function serializeData($data)
    {
        if(is_array($data) == false) {
            $data = [$data];
        }
        return serialize($data);
    }

    public static function getArrayToHash($array, $useKeyName = [])
    {
        if(is_array($array) == false) {
            $array[] = $array;
        }
        ksort($array);
        $hashBaseStr = '';
        foreach($array as $key => $val) {
            if(empty($useKeyName) || in_array($key, $useKeyName)) {
                $hashBaseStr .= $key.":".$val.",";
            }
        }
        if($hashBaseStr) {
            return hash("sha256", $hashBaseStr);
        }

        return '';
    }

    /**
     * 連想配列をエンコードする
     *
     * @param array $param
     * @param string $encod default:UTF-8
     * @return array
     */
    public static function paramConvertEncoding(array $param, $encod = 'UTF-8')
    {
        if(is_array($param) == false) {
            $param = [$param];
        }

        $return = [];
        foreach ($param as $key => $value) {
            // UTF-8以外は文字コード変換を行う
            $detectEncode = StringUtil::characterEncoding(substr($value, 0, 6));
            $return[$key] = ($detectEncode != $encod) ? mb_convert_encoding($value, $encod, $detectEncode) : $value;
        }

        return $return;
    }

    /**
     * エンコードチェック
     * $char_code > SJIS-win > $char_code で欠落のないことを確認する
     *
     * @param array $listData
     * @param string $char_code 文字コード
     * @return array $listData
     */
    public static function checkEncode(array $listData, $char_code = 'UTF-8')
    {
        foreach ($listData as $key => $val) {
            //未設定、配列、単語空白以外の場合はスキップ
            if (!$val || is_array($val) || preg_match('/^[\w\s]+$/i', $val)) {
                continue;
            }
            //CHAR_CODE > SJIS-WIN > CHAR_CODEで欠落のないことを確認
            $temp = mb_convert_encoding($val, 'SJIS-win', $char_code);
            $temp = mb_convert_encoding($temp, $char_code, 'SJIS-win');
            if ($val !== $temp) {
                $temp = mb_convert_encoding($val, $char_code, 'SJIS-win');
                $temp = mb_convert_encoding($temp, 'SJIS-win', $char_code);
                if ($val === $temp) {
                    $listData[$key] = mb_convert_encoding($val, $char_code, 'SJIS-win');
                } else {
                    $listData[$key] = 'unknown encoding strings';
                }
            }
        }
        return $listData;
    }

    /**
     * 禁止文字を全角スペースに置換する。
     *
     * @param string $value 対象文字列
     * @param string $encoding
     * @return string 結果
     */
    public static function convertProhibitedChar($value, $encoding = 'utf-8')
    {
        $ret = $value;
        for ($i = 0; $i < mb_strlen($value); $i++) {
            $tmp = mb_substr($value, $i, 1, $encoding);
            if (self::isProhibitedChar($tmp)) {
                $ret = str_replace($tmp, "　", $ret);
            }
        }
        return $ret;
    }

    /**
     * 禁止文字か判定を行う。
     *
     * @param string $value 判定対象
     * @return boolean 禁止文字の場合、true
     */
    public static function isProhibitedChar($value)
    {
        $check_char = mb_convert_encoding($value, "SJIS-win", "UTF-8");

        $listProhibited = ['815C', '8160', '8161', '817C', '8191', '8192', '81CA'];
        foreach ($listProhibited as $prohibited) {
            if (hexdec($prohibited) == hexdec(bin2hex($check_char))) {
                return true;
            }
        }

        if (hexdec('8740') <= hexdec(bin2hex($check_char)) && hexdec('879E') >= hexdec(bin2hex($check_char))) {
            return true;
        }
        if ((hexdec('ED40') <= hexdec(bin2hex($check_char)) && hexdec('ED9E') >= hexdec(bin2hex($check_char)))
         || (hexdec('ED9F') <= hexdec(bin2hex($check_char)) && hexdec('EDFC') >= hexdec(bin2hex($check_char)))
         || (hexdec('EE40') <= hexdec(bin2hex($check_char)) && hexdec('EE9E') >= hexdec(bin2hex($check_char)))
         || (hexdec('FA40') <= hexdec(bin2hex($check_char)) && hexdec('FA9E') >= hexdec(bin2hex($check_char)))
         || (hexdec('FA9F') <= hexdec(bin2hex($check_char)) && hexdec('FAFC') >= hexdec(bin2hex($check_char)))
         || (hexdec('FB40') <= hexdec(bin2hex($check_char)) && hexdec('FB9E') >= hexdec(bin2hex($check_char)))
         || (hexdec('FB9F') <= hexdec(bin2hex($check_char)) && hexdec('FBFC') >= hexdec(bin2hex($check_char)))
         || (hexdec('FC40') <= hexdec(bin2hex($check_char)) && hexdec('FC4B') >= hexdec(bin2hex($check_char)))
        ) {
            return true;
        }
        if ((hexdec('EE9F') <= hexdec(bin2hex($check_char)) && hexdec('EEFC') >= hexdec(bin2hex($check_char)))
         || (hexdec('F040') <= hexdec(bin2hex($check_char)) && hexdec('F9FC') >= hexdec(bin2hex($check_char)))
        ) {
            return true;
        }

        return false;
    }

    /**
     * 禁止半角記号を半角スペースに変換する。
     *
     * @param string $value
     * @return string 変換した値
     */
    public static function convertProhibitedKigo($value, $rep = " ")
    {
        $listProhibitedKigo = [
            '!', '"', '$', '%', '&', '\'', '(', ')', '+', ',', '.', ';', '=', '?', '[', '\\', ']', '^', '_', '`', '{', '|', '}', '~',
        ];
        foreach ($listProhibitedKigo as $prohibitedKigo) {
            if (strstr($value, $prohibitedKigo)) {
                $value = str_replace($prohibitedKigo, $rep, $value);
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
        $ret = '';
        $value = mb_convert_encoding($value, "SJIS-win", "UTF-8");
        for ($i = 1; $i <= mb_strlen($value); $i++) {
            $tmp = mb_substr($value, 0, $i, "SJIS-win");
            if (strlen($tmp) <= $len) {
                $ret = mb_convert_encoding($tmp, "UTF-8", "SJIS-win");
            } else {
                break;
            }
        }
        return $ret;
    }

    /**
     * 端末区分を取得する
     *
     *  1:スマートフォン
     *  2:PC
     *  3:携帯電話
     *
     * @return integer 端末区分
     */
    public static function getDeviceDivision()
    {
        $result = 2;
//         if (self::isSmartPhone()) {
//             $result = 1;
//         }
        return $result;
    }

    /**
     * 日付(YYYYMMDD)をフォーマットして返す
     *
     * @param string $format
     * @param integer $number 日付(YYYYMMDD)
     * @return string フォーマット後の日付
     */
    public static function getDateFromNumber($format = 'Ymd', $number)
    {
        $number = (string)$number;
        $shortFlag = (strlen($number) < 9) ? true : false;
        $year = substr($number, 0, 4);
        $month = substr($number, 4, 2);
        $day = substr($number, 6, 2);
        $hour = ($shortFlag) ? '0' : substr($number, 8, 2);
        $minute = ($shortFlag) ? '0' : substr($number, 10, 2);
        $second = ($shortFlag) ? '0' : substr($number, 12, 2);

        return (checkdate($month, $day, $year))? date($format, mktime($hour, $minute, $second, $month, $day, $year)) : date($format);
    }

    public static function printLog($message, $data = [])
    {
        if(is_array($message)) {
            $message = print_r($message,true);
        }
        if($data) {
            $message .= ($message ? "\n" : "") . print_r($data, true);
        }

        logs('YamatoPayment4')->info($message);
    }

    /**
     * 送り先区分を取得する
     *
     * @param Order $Order 受注データ
     * @param $plugin_setting プラグイン設定 0: 同梱しない、1: 同梱する
     * @return int $sendDiv 請求書の送り先 0:自分送り、1:自分以外、2:商品に同梱
     */
    public static function decideSendDiv($Order, $plugin_setting)
    {
        if ($Order->isMultiple()) {
            return 1;
        }

        if (self::isGift($Order)) {
            return 1;
        }

        if ($plugin_setting === 0) {
            return 0;
        } 

        return 2;
    }

    /**
     * 注文者住所と配送先住所・氏名が同一か判定する
     */
    public static function isGift($Order) {
        $Shippings = $Order->getShippings();

        // 複数配送は判定対象外
        if ($Order->isMultiple()) {
            return false;
        }

        if (
            $Order->getName01() != $Shippings[0]['name01']
            || $Order->getName02() != $Shippings[0]['name02']
            || $Order->getKana01() != $Shippings[0]['kana01']
            || $Order->getKana02() != $Shippings[0]['kana02']
            || $Order->getPhoneNumber() != $Shippings[0]['phone_number']
            || $Order->getPostalCode() != $Shippings[0]['postal_code']
            || $Order->getPref() != $Shippings[0]['pref']
            || $Order->getAddr01() != $Shippings[0]['addr01']
            || $Order->getAddr02() != $Shippings[0]['addr02']
        ) {
            return true;
        } else {
            return false;
        } 
    }

    /**
     * 半角→全角変換
     *
     * @param string $data
     * @param string $encoding
     * @return string
     */
    public static function convHalfToFull($data, $encoding = 'utf-8')
    {
        $data = str_replace('"','”',$data);
        $data = str_replace("'","’",$data);
        $data = str_replace("\\","￥",$data);
        $data = str_replace("~","～",$data);
        $data = mb_convert_kana($data, 'KVAS', $encoding);

        return $data;
    }

    /**
     * 11桁の数列をハイフン区切りの表示用文字列にパースする
     * 
     * @param int $number
     * @return string $dispPhoneNumber
     */
    public static function parsePhoneNumber($number)
    {
        // 電話番号を表示用文字列にパース
        $splitPhoneNumber01 = substr($number, 0, 3);
        $splitPhoneNumber02 = substr($number, 3, 4);
        $splitPhoneNumber03 = substr($number, 7, 4);
        $dispPhoneNumber = $splitPhoneNumber01 . '-' . $splitPhoneNumber02 . '-' . $splitPhoneNumber03; 
        
        return $dispPhoneNumber;
    }
}