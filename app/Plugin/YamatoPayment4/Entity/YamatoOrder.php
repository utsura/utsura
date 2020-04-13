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
 * YamatoProduct
 *
 * @ORM\Table(name="plg_yamato_order")
 * @ORM\Entity(repositoryClass="Plugin\YamatoPayment4\Repository\YamatoOrderRepository")
 */
class YamatoOrder
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var Eccube\Entity\Order
     * @ORM\ManyToOne(targetEntity="Eccube\Entity\Order", inversedBy="YamatoOrder")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     * })
     */
    private $Order;

    /**
     * Set order.
     *
     * @param \Eccube\Entity\Order|null $plugin
     *
     * @return Order
     */
    public function setOrder(\Eccube\Entity\Order $order = null)
    {
        $this->Order = $order;

        return $this;
    }

    /**
     * Get order.
     *
     * @return \Eccube\Entity\Order|null
     */
    public function getOrder()
    {
        return $this->Order;
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
     * 注文情報登録
     * @param array $memo02
     * @return $this
     */
    public function setMemo02($memo02)
    {
        $this->memo02 = CommonUtil::serializeData($memo02);

        return $this;
    }

    /**
     * 注文情報取得
     * @return array
     */
    public function getMemo02()
    {
        $data = CommonUtil::unSerializeData($this->memo02);
        if (empty($data)) {
            $data = [];
        }
        return $data;
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
     * 決済ステータス登録
     * @param string $memo04
     * @return $this
     */
    public function setMemo04($memo04)
    {
        $this->memo04 = $memo04;

        return $this;
    }

    /**
     * 決済ステータス取得
     * @return string
     */
    public function getMemo04()
    {
        return $this->memo04;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function setMemo05($data)
    {
        $this->memo05 = CommonUtil::serializeData($data);

        return $this;
    }

    /**
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
     * 決済ログ登録
     * @param string $memo09
     * @return $this
     */
    public function setMemo09($memo09)
    {
        $this->memo09 = CommonUtil::serializeData($memo09);

        return $this;
    }

    /**
     * 決済ログ取得
     * @return string
     */
    public function getMemo09()
    {
        $data = CommonUtil::unSerializeData($this->memo09);
        if (empty($data)) {
            $data = [];
        }
        return $data;
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
