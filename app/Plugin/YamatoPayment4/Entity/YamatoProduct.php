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
use Eccube\Entity\Master\AbstractMasterEntity;

/**
 * YamatoProduct
 *
 * @ORM\Table(name="plg_yamato_product")
 * @ORM\Entity(repositoryClass="Plugin\YamatoPayment4\Repository\YamatoProductRepository")
 */
class YamatoProduct extends AbstractMasterEntity
{
    /**
     * @var integer
     */
    private $product_id;

    /**
     * Set product_id
     *
     * @param integer $productId
     *
     * @return Product
     */
    public function setProductId($productId)
    {
        $this->product_id = $productId;

        return $this;
    }

    /**
     * Get product_id
     *
     * @return integer
     */
    public function getProductId()
    {
        return $this->product_id;
    }

    /**
     * @var int
     *
     * @var Product
     * @ORM\ManyToOne(targetEntity="Eccube\Entity\Product")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="product_id", referencedColumnName="id")
     * })
     */
    private $Product;

    /**
     * @var string
     *
     * @ORM\Column(name="reserve_date", type="string", length=255, nullable=true)
     */
    private $reserve_date;

    /**
     * @var string
     *
     * @ORM\Column(name="not_deferred_flg", type="integer", nullable=false, options={"unsigned":true})
     */
    private $not_deferred_flg;

    /**
     * Set product.
     *
     * @param \Eccube\Entity\Product|null $plugin
     *
     * @return Product
     */
    public function setProduct(\Eccube\Entity\Product $product = null)
    {
        $this->Product = $product;

        return $this;
    }

    /**
     * Get product.
     *
     * @return \Eccube\Entity\Product|null
     */
    public function getProduct()
    {
        return $this->Product;
    }

    /**
     * Set reserve_date
     * @param string|null $reserve_date
     * @return YamatoProduct
     */
    public function setReserveDate($reserve_date)
    {
        $this->reserve_date = $reserve_date;

        return $this;
    }

    /**
     * Get reserve_date
     * @return string|null
     */
    public function getReserveDate()
    {
        return $this->reserve_date;
    }

    /**
     * Set not_deferred_flg
     * @param int $reserve_date
     * @return YamatoProduct
     */
    public function setNotDeferredFlg($not_deferred_flg)
    {
        $this->not_deferred_flg = $not_deferred_flg;

        return $this;
    }

    /**
     * Get not_deferred_flg
     * @return int
     */
    public function getNotDeferredFlg()
    {
        return $this->not_deferred_flg;
    }
}
