<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Entity;

use Plugin\GmoPaymentGateway4\Service\PaymentHelperCvs;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\Form\FormInterface;

/**
 * 決済入力画面の入力値を保持するクラス
 * ※ plg_gmo_payment_gateway_order_payment#memo01 に保存する情報
 */
class GmoPaymentInput
{
    const COLS = [
        // クレジット入力情報
        'payment_type',
        'token',
        'mask_card_no',
        'expire_month',
        'expire_year',
        'card_name1',
        'card_name2',
        'security_code',
        'credit_pay_methods',
        'register_card',
        'CardSeq',
        'credit_pay_methods2',
        // コンビニ入力情報
        'Convenience',
        // その他支払方法の入力情報
        // ...
    ];

    /**
     * @var Plugin\GmoPaymentGateway4\Service\PaymentHelperCvs
     */
    private $PaymentHelperCvs;

    /**
     * コンストラクタ
     */
    public function __construct(PaymentHelperCvs $PaymentHelperCvs = null)
    {
        foreach (GmoPaymentInput::COLS as $column) {
            $this->$column = "";
        }

        $this->PaymentHelperCvs = $PaymentHelperCvs;
    }

    /**
     * @return string
     */
    public function getDispMonthYear()
    {
        return $this->expire_year .
            trans('gmo_payment_gateway.shopping.credit.col2.year') .
            $this->expire_month .
            trans('gmo_payment_gateway.shopping.credit.col2.month');
    }

    /**
     * @return string
     */
    public function getCreditPayMethodName()
    {
        return PaymentUtil::getCreditPayMethodName($this->credit_pay_methods);
    }

    /**
     * @return string
     */
    public function getCreditPayMethod2Name()
    {
        return PaymentUtil::getCreditPayMethodName($this->credit_pay_methods2);
    }

    /**
     * カード登録を行うかどうかを返す
     *
     * @return boolean true: 登録する, false: 登録しない
     */
    public function isRegisterCard()
    {
        if ($this->payment_type == "0" &&
            $this->register_card == "1") {
            // クレジットカード入力決済で
            // 登録するを選択している場合
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getConveniName()
    {
        if (is_null($this->PaymentHelperCvs)) {
            return "";
        }

        return $this->PaymentHelperCvs->getConveniName($this->Convenience);
    }

    /**
     * 配列にして返す
     * @return array 連想配列
     */
    public function getArrayData()
    {
        $result = [];

        foreach (GmoPaymentInput::COLS as $column) {
            $result[$column] = $this->$column;
        }

        return $result;
    }

    /**
     * 配列から値をセット
     *
     * @param array $data
     * @return GmoPaymentInput
     */
    public function setArrayData(array $data = null)
    {
        foreach (GmoPaymentInput::COLS as $column) {
            $this->$column = "";
            if (isset($data[$column])) {
                $this->$column = $data[$column];
            }
        }

        return $this;
    }

    /**
     * フォームから値をセット
     *
     * @param FormInterface $form
     * @return GmoPaymentInput
     */
    public function setFormData(FormInterface $form)
    {
        foreach (GmoPaymentInput::COLS as $column) {
            $this->$column = "";

            if (isset($form[$column])) {
                $this->$column = $form[$column]->getData();
            }
        }

        return $this;
    }
}
