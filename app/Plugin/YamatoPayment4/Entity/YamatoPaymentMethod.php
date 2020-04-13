<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\YamatoPayment4\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\YamatoPayment4\Util\CommonUtil;

/**
 * YamatoPaymentMethod
 *
 * @ORM\Table(name="plg_yamato_payment_method")
 * @ORM\Entity(repositoryClass="Plugin\YamatoPayment4\Repository\YamatoPaymentMethodRepository")
 */
class YamatoPaymentMethod
{
    /**
     * @var Payment
     * @ORM\ManyToOne(targetEntity="Eccube\Entity\Payment")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="payment_id", referencedColumnName="id")
     * })
     */
    private $Payment;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

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
     * Set payment.
     *
     * @param \Eccube\Entity\Payment|null $plugin
     *
     * @return Payment
     */
    public function setPayment(\Eccube\Entity\Payment $payment = null)
    {
        $this->Payment = $payment;

        return $this;
    }

    /**
     * Get payment.
     *
     * @return \Eccube\Entity\Payment|null
     */
    public function getPayment()
    {
        return $this->Payment;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set payment_method
     *
     * @param integer $payment_method
     *
     * @return PaymentMethod
     */
    public function setPaymentMethod($payment_method)
    {
        $this->payment_method = $payment_method;

        return $this;
    }

    /**
     * Get payment_method
     *
     * @return string
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
     * @param \DateTime $createDate
     * @return $this
     */
    public function setCreateDate($createDate)
    {
        $this->create_date = $createDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdateDate()
    {
        return $this->update_date;
    }

    /**
     * @param \DateTime $updateDate
     * @return $this
     */
    public function setUpdateDate($updateDate)
    {
        $this->update_date = $updateDate;

        return $this;
    }

    /**
     * @param string $memo01
     * @return $this
     */
    public function setMemo01($memo01)
    {
        $this->memo01 = $memo01;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemo01()
    {
        return $this->memo01;
    }

    /**
     * @param string $memo02
     * @return $this
     */
    public function setMemo02($memo02)
    {
        $this->memo02 = $memo02;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemo02()
    {
        return $this->memo02;
    }

    /**
     * 支払方法種別ID登録
     * @param string $memo03
     * @return $this
     */
    public function setMemo03($memo03)
    {
        $this->memo03 = $memo03;

        return $this;
    }

    /**
     * 支払方法種別ID取得
     * @return string
     */
    public function getMemo03()
    {
        return $this->memo03;
    }

    /**
     * @param string $memo04
     * @return $this
     */
    public function setMemo04($memo04)
    {
        $this->memo04 = $memo04;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemo04()
    {
        return $this->memo04;
    }

    /**
     * 支払方法設定登録
     * @param array $data
     * @return $this
     */
    public function setMemo05($data)
    {
        $this->memo05 = CommonUtil::serializeData($data);

        return $this;
    }

    /**
     * 支払方法設定取得
     * @return array
     */
    public function getMemo05()
    {
        $data = CommonUtil::unSerializeData($this->memo05);
        if (empty($data)) {
            $data = [];
        }
        return $data;
    }

    /**
     * @param string $memo06
     * @return $this
     */
    public function setMemo06($memo06)
    {
        $this->memo06 = $memo06;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemo06()
    {
        return $this->memo06;
    }

    /**
     * @param string $memo07
     * @return $this
     */
    public function setMemo07($memo07)
    {
        $this->memo07 = $memo07;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemo07()
    {
        return $this->memo07;
    }

    /**
     * @param string $memo08
     * @return $this
     */
    public function setMemo08($memo08)
    {
        $this->memo08 = $memo08;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemo08()
    {
        return $this->memo08;
    }

    /**
     * @param string $memo09
     * @return $this
     */
    public function setMemo09($memo09)
    {
        $this->memo09 = $memo09;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemo09()
    {
        return $this->memo09;
    }

    /**
     * @param string $memo10
     * @return $this
     */
    public function setMemo10($memo10)
    {
        $this->memo10 = $memo10;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemo10()
    {
        return $this->memo10;
    }
}
