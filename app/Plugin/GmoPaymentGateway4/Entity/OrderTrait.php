<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * 決済状況を保持するカラム
     *
     * dtb_order.gmo_payment_gateway_payment_status
     *
     * @var int
     * @ORM\Column(type="integer", options={"unsigned":true}, nullable=true)
     */
    private $gmo_payment_gateway_payment_status;

    /**
     * プラグイン設定
     *
     * @var Plugin\GmoPaymentGateway4\Entity\GmoConfig
     */
    private $GmoConfig;

    /**
     * 支払方法設定
     *
     * @var array
     */
    private $gmoMethodConfig;

    /**
     * 決済画面入力情報
     *
     * @var Plugin\GmoPaymentGateway4\Entity\GmoPaymentInput
     */
    private $GmoPaymentInput;

    /**
     * 支払方法のGMOPG追加情報.
     *
     * @var Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod
     */
    private $GmoPaymentMethod;

    /**
     * 注文のGMOPG追加情報.
     *
     * @var Plugin\GmoPaymentGateway4\Entity\GmoOrderPayment
     */
    private $GmoOrderPayment;

    /**
     * 受注修正（管理画面）向け表示情報
     *
     * @var array
     */
    private $gmoPaymentInfo = [];

    /**
     * 受注修正（管理画面）の支払方法からGMO-PG決済を削除するために
     * 必要な GMO-PG 決済の payment_id 一覧を保持する配列
     *
     * @var array
     */
    private $gmoPaymentIds = [];

    /**
     * @return int
     */
    public function getGmoPaymentGatewayPaymentStatus()
    {
        return $this->gmo_payment_gateway_payment_status;
    }

    /**
     * @return Plugin\GmoPaymentGateway4\Entity\GmoConfig
     */
    public function getGmoConfig()
    {
        return $this->GmoConfig;
    }

    /**
     * @return array
     */
    public function getGmoMethodConfig()
    {
        return $this->gmoMethodConfig;
    }

    /**
     * @return Plugin\GmoPaymentGateway4\Entity\GmoPaymentInput
     */
    public function getGmoPaymentInput()
    {
        return $this->GmoPaymentInput;
    }

    /**
     * @return Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod
     */
    public function getGmoPaymentMethod()
    {
        return $this->GmoPaymentMethod;
    }

    /**
     * @return Plugin\GmoPaymentGateway4\Entity\GmoOrderPayment
     */
    public function getGmoOrderPayment()
    {
        return $this->GmoOrderPayment;
    }

    /**
     * @return array
     */
    public function getGmoPaymentInfo()
    {
        return $this->gmoPaymentInfo;
    }

    /**
     * @return array
     */
    public function getGmoPaymentIds()
    {
        return $this->gmoPaymentIds;
    }

    /**
     * @param integer $payment_status
     *
     * @return $this
     */
    public function setGmoPaymentGatewayPaymentStatus($payment_status)
    {
        $this->gmo_payment_gateway_payment_status = null;

        if (!empty($payment_status)) {
            $this->gmo_payment_gateway_payment_status = $payment_status;
        }

        return $this;
    }

    /**
     * トータル金額を返す
     * 小数部（.00）を含む場合があるため除去する
     * 尚、文字として扱うため整数変換は行わない
     */
    public function getDecPaymentTotal()
    {
        $total = $this->getPaymentTotal();
        $vals = explode('.', $total);
        return $vals[0];
    }

    /**
     * @param Plugin\GmoPaymentGateway4\Entity\GmoConfig $GmoConfig|null
     */
    public function setGmoConfig(GmoConfig $GmoConfig = null)
    {
        $this->GmoConfig = $GmoConfig;
    }

    /**
     * @param array $gmoMethodConfig|null
     */
    public function setGmoMethodConfig(array $gmoMethodConfig = null)
    {
        $this->gmoMethodConfig = $gmoMethodConfig;
    }

    /**
     * @param Plugin\GmoPaymentGateway4\Entity\GmoPaymentInput $GmoPaymentInput|null
     */
    public function setGmoPaymentInput(GmoPaymentInput $GmoPaymentInput = null)
    {
        $this->GmoPaymentInput = $GmoPaymentInput;
    }

    /**
     * @param Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod $GmoPaymentMethod|null
     */
    public function setGmoPaymentMethod
        (GmoPaymentMethod $GmoPaymentMethod = null)
    {
        $this->GmoPaymentMethod = $GmoPaymentMethod;
    }

    /**
     * @param Plugin\GmoPaymentGateway4\Entity\GmoOrderPayment $GmoOrderPayment|null
     */
    public function setGmoOrderPayment(GmoOrderPayment $GmoOrderPayment = null)
    {
        $this->GmoOrderPayment = $GmoOrderPayment;
    }

    /**
     * @param array $gmoPaymentInfo
     */
    public function setGmoPaymentInfo(array $gmoPaymentInfo)
    {
        $this->gmoPaymentInfo = $gmoPaymentInfo;
    }

    /**
     * @param array $gmoPaymentIds
     */
    public function setGmoPaymentIds(array $gmoPaymentIds)
    {
        $this->gmoPaymentIds = $gmoPaymentIds;
    }
}
