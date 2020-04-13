<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GmoPaymentMethod
 *
 * @ORM\Table(name="plg_gmo_payment_gateway_payment_method")
 * @ORM\Entity(repositoryClass="Plugin\GmoPaymentGateway4\Repository\GmoPaymentMethodRepository")
 */
class GmoPaymentMethod
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="payment_id", type="integer", options={"unsigned":true})
     */
    private $payment_id;

    /**
     * @var text
     *
     * @ORM\Column(name="payment_method", type="text")
     */
    private $payment_method;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_date", type="datetimetz")
     */
    private $create_date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="update_date", type="datetimetz")
     */
    private $update_date;

    /**
     * @var text
     *
     * @ORM\Column(name="memo01", type="text", nullable=true)
     */
    private $memo01;

    /**
     * @var text
     *
     * @ORM\Column(name="memo02", type="text", nullable=true)
     */
    private $memo02;

    /**
     * @var text
     *
     * @ORM\Column(name="memo03", type="text", nullable=true)
     */
    private $memo03;

    /**
     * @var text
     *
     * @ORM\Column(name="memo04", type="text", nullable=true)
     */
    private $memo04;

    /**
     * @var text
     *
     * @ORM\Column(name="memo05", type="text", nullable=true)
     */
    private $memo05;

    /**
     * @var text
     *
     * @ORM\Column(name="memo06", type="text", nullable=true)
     */
    private $memo06;

    /**
     * @var text
     *
     * @ORM\Column(name="memo07", type="text", nullable=true)
     */
    private $memo07;

    /**
     * @var text
     *
     * @ORM\Column(name="memo08", type="text", nullable=true)
     */
    private $memo08;

    /**
     * @var text
     *
     * @ORM\Column(name="memo09", type="text", nullable=true)
     */
    private $memo09;

    /**
     * @var text
     *
     * @ORM\Column(name="memo10", type="text", nullable=true)
     */
    private $memo10;

    /**
     * @var text
     *
     * @ORM\Column(name="plugin_code", type="text", nullable=true)
     */
    private $plugin_code;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getPaymentId()
    {
        return $this->payment_id;
    }

    /**
     * @return text
     */
    public function getPaymentMethod()
    {
        return $this->payment_method;
    }

    /**
     * @return \DateTime
     */
    public function getCreateDate()
    {
        return $this->create_date;
    }

    /**
     * @return \DateTime
     */
    public function getUpdateDate()
    {
        return $this->update_date;
    }

    /**
     * @return text
     */
    public function getMemo01()
    {
        return $this->memo01;
    }

    /**
     * @return text
     */
    public function getMemo02()
    {
        return $this->memo02;
    }

    /**
     * @return text
     */
    public function getMemo03()
    {
        return $this->memo03;
    }

    /**
     * @return text
     */
    public function getMemo04()
    {
        return $this->memo04;
    }

    /**
     * @return text
     */
    public function getMemo05()
    {
        return $this->memo05;
    }

    /**
     * @return text
     */
    public function getMemo06()
    {
        return $this->memo06;
    }

    /**
     * @return text
     */
    public function getMemo07()
    {
        return $this->memo07;
    }

    /**
     * @return text
     */
    public function getMemo08()
    {
        return $this->memo08;
    }

    /**
     * @return text
     */
    public function getMemo09()
    {
        return $this->memo09;
    }

    /**
     * @return text
     */
    public function getMemo10()
    {
        return $this->memo10;
    }

    /**
     * @return text
     */
    public function getPluginCode()
    {
        return $this->plugin_code;
    }

    /**
     * 支払方法の設定配列を返す
     *
     * @return array 設定配列
     */
    public function getPaymentMethodConfig()
    {
        $memo05 = $this->getMemo05();
        if (empty($memo05)) {
            return [];
        }

        return json_decode($memo05, true);
    }

    /**
     * @param integer $payment_id
     *
     * @return $this;
     */
    public function setPaymentId($payment_id)
    {
        $this->payment_id = $payment_id;

        return $this;
    }

    /**
     * @param text $payment_method
     *
     * @return $this;
     */
    public function setPaymentMethod($payment_method)
    {
        $this->payment_method = $payment_method;

        return $this;
    }

    /**
     * @param \DateTime $create_date
     *
     * @return $this;
     */
    public function setCreateDate($create_date)
    {
        $this->create_date = $create_date;

        return $this;
    }

    /**
     * @param \DateTime $update_date
     *
     * @return $this;
     */
    public function setUpdateDate($update_date)
    {
        $this->update_date = $update_date;

        return $this;
    }

    /**
     * @param text $memo01
     *
     * @return $this;
     */
    public function setMemo01($memo01)
    {
        $this->memo01 = $memo01;

        return $this;
    }

    /**
     * @param text $memo02
     *
     * @return $this;
     */
    public function setMemo02($memo02)
    {
        $this->memo02 = $memo02;

        return $this;
    }

    /**
     * @param text $memo03
     *
     * @return $this;
     */
    public function setMemo03($memo03)
    {
        $this->memo03 = $memo03;

        return $this;
    }

    /**
     * @param text $memo04
     *
     * @return $this;
     */
    public function setMemo04($memo04)
    {
        $this->memo04 = $memo04;

        return $this;
    }

    /**
     * @param text $memo05
     *
     * @return $this;
     */
    public function setMemo05($memo05)
    {
        $this->memo05 = $memo05;

        return $this;
    }

    /**
     * @param text $memo06
     *
     * @return $this;
     */
    public function setMemo06($memo06)
    {
        $this->memo06 = $memo06;

        return $this;
    }

    /**
     * @param text $memo07
     *
     * @return $this;
     */
    public function setMemo07($memo07)
    {
        $this->memo07 = $memo07;

        return $this;
    }

    /**
     * @param text $memo08
     *
     * @return $this;
     */
    public function setMemo08($memo08)
    {
        $this->memo08 = $memo08;

        return $this;
    }

    /**
     * @param text $memo09
     *
     * @return $this;
     */
    public function setMemo09($memo09)
    {
        $this->memo09 = $memo09;

        return $this;
    }

    /**
     * @param text $memo10
     *
     * @return $this;
     */
    public function setMemo10($memo10)
    {
        $this->memo10 = $memo10;

        return $this;
    }

    /**
     * @param text $plugin_code
     *
     * @return $this;
     */
    public function setPluginCode($plugin_code)
    {
        $this->plugin_code = $plugin_code;

        return $this;
    }

    /**
     * 支払方法の設定配列をセットする
     *
     * @param array $data データ配列
     * @return $this;
     */
    public function setPaymentMethodConfig(array $data)
    {
        return $this->setMemo05(json_encode($data));
    }
}
