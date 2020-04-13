<?php

/*
 * AmazonPay for EC-CUBE4
 * Copyright(c) 2018 IPLOGIC CO.,LTD. All Rights Reserved.
 *
 * http://www.iplogic.co.jp/
 *
 * This program is not free software.
 * It applies to terms of service.
 *
 */

namespace Plugin\YamatoPayment4\Entity;

use Eccube\Annotation\EntityExtension;
use Doctrine\ORM\Mapping as ORM;

/**
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="Plugin\YamatoPayment4\Entity\YamatoOrder", mappedBy="Order", cascade={"persist", "remove"})
     */
    private $YamatoOrder;

    private $yamatoOrderPayment;

    private $yamatoOrderStatus;

    /**
     * {@inheritdoc}
     */
    public function setYamatoOrder(\Plugin\YamatoPayment4\Entity\YamatoOrder $YamatoOrder = null)
    {
        $this->YamatoOrder = $YamatoOrder;

        $this->setYamatoOrderPayment($this->YamatoOrder->getMemo03());
        $this->setYamatoOrderStatus($this->YamatoOrder->getMemo04());

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getYamatoOrder()
    {
        return $this->YamatoOrder;
    }

    /**
     * {@inheritdoc}
     */
    public function setYamatoOrderPayment($yamatoOrderPaymentId)
    {
        $this->yamatoOrderPayment = $yamatoOrderPaymentId;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getYamatoOrderPayment()
    {
        return $this->yamatoOrderPayment;
    }

    /**
     * {@inheritdoc}
     */
    public function setYamatoOrderStatus($yamatoOrderStatusId)
    {
        $this->yamatoOrderStatus = $yamatoOrderStatusId;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getYamatoOrderStatus()
    {
        return $this->yamatoOrderStatus;
    }
}
