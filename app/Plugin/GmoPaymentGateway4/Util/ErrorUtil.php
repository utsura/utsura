<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Util;

use Eccube\Common\EccubeConfig;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

class ErrorUtil
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var string エラー定義ファイル
     */
    const FILENAME = "pg_mulpay_errors.txt";

    /**
     * @var array エラー定義配列
     */
    protected $error = [];

    /**
     * ErrorUtil constructor.
     *
     * @param EccubeConfig $eccubeConfig
     */
    public function __construct(EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->_loadErrors($this->eccubeConfig['plugin_realdir'] . '/' .
                           PaymentUtil::PLUGIN_CODE . '/' .
                           ErrorUtil::FILENAME);
    }

    /**
     * エラーコードに該当するエラーメッセージを取得する
     *
     * @param string $code エラーコード
     * @return string エラーメッセージ
     */
    public function lfGetErrorInformation($code)
    {
        if (!$code) return false;
        if (!$this->error) return false;
        if (!isset($this->error[$code])) return false;
        return $this->error[$code];
    }

    /**
     * エラー定義ファイルの読み込み
     *
     * @param string $filename
     */
    private function _loadErrors($filename)
    {
        if ($this->error) return;
        $this->error = $this->_getErrors($filename);
        if (!$this->error) {
            echo trans('gmo_payment_gateway.com.error_file.error1');
        }
    }

    /**
     * エラー定義ファイルの読み込み
     *
     * @param string $filename
     */
    private function _getErrors($filename)
    {
        $error = array();

        $text = file_get_contents($filename);
        $arrText = explode("\n", $text);

        foreach ($arrText as $line) {
            $arrLine = explode("\t", $line);
            $struct = $this->_setStruct($arrLine);
            $code = $struct['code'];
            $error[$code] = $struct;
        }

        return $error;
    }

    /**
     * エラー定義１行分を連想配列にして返す
     *
     * @param array $arrLine １行分の配列
     */
    function _setStruct($arrLine = null)
    {
        $array = array();

        $array['code'] = (isset($arrLine[0])) ? $arrLine[0] : "";
        $array['no'] = (isset($arrLine[1])) ? $arrLine[1] : "";
        $array['s_code'] = (isset($arrLine[2])) ? $arrLine[2] : "";
        $array['d_code'] = (isset($arrLine[3])) ? $arrLine[3] : "";
        $array['status'] = (isset($arrLine[4])) ? $arrLine[4] : "";
        $array['payment'] = (isset($arrLine[5])) ? $arrLine[5] : "";
        $array['context'] = (isset($arrLine[6])) ? $arrLine[6] : "";
        $array['message'] = (isset($arrLine[7])) ? $arrLine[7] : "";

        return $array;
    }
}
