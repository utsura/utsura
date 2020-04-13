<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GmoMember
 *
 * @ORM\Table(name="plg_gmo_payment_gateway_member")
 * @ORM\Entity(repositoryClass="Plugin\GmoPaymentGateway4\Repository\GmoMemberRepository")
 */
class GmoMember
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
     * @ORM\Column(name="customer_id", type="integer", options={"unsigned":true})
     */
    private $customer_id;

    /**
     * @var text
     *
     * @ORM\Column(name="member_id", type="text", nullable=true)
     */
    private $member_id;

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
     * 登録済みのクレジットカード配列
     * @var array
     */
    private $creditCards = [];

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
    public function getCustomerId()
    {
        return $this->customer_id;
    }

    /**
     * @return text
     */
    public function getMemberId()
    {
        return $this->member_id;
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
     * 登録済みのクレジットカード配列を返す
     *
     * @return array 登録済みのクレジットカード配列
     */
    public function getCreditCards()
    {
        return $this->creditCards;
    }

    /**
     * @param integer $customer_id
     *
     * @return $this;
     */
    public function setCustomerId($customer_id)
    {
        $this->customer_id = $customer_id;

        return $this;
    }

    /**
     * @param text $member_id
     *
     * @return $this;
     */
    public function setMemberId($member_id)
    {
        $this->member_id = $member_id;

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
     * 登録済みのクレジットカードを設定する
     *
     * @param array $creditCards
     * @return $this;
     */
    public function setCreditCards(array $creditCards)
    {
        $this->creditCards = $creditCards;

        return $this;
    }
}
