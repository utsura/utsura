<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GmoConfig
 *
 * @ORM\Table(name="plg_gmo_payment_gateway_config")
 * @ORM\Entity(repositoryClass="Plugin\GmoPaymentGateway4\Repository\GmoConfigRepository")
 */
class GmoConfig
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
     * @ORM\Column(name="connect_server_type", type="integer", options={"unsigned":true})
     */
    private $connect_server_type;

    /**
     * @var string
     *
     * @ORM\Column(name="server_url", type="string", length=255)
     */
    private $server_url;

    /**
     * @var string
     *
     * @ORM\Column(name="kanri_server_url", type="string", length=255)
     */
    private $kanri_server_url;

    /**
     * @var string
     *
     * @ORM\Column(name="site_id", type="string", length=16)
     */
    private $site_id;

    /**
     * @var string
     *
     * @ORM\Column(name="site_pass", type="string", length=16)
     */
    private $site_pass;

    /**
     * @var string
     *
     * @ORM\Column(name="shop_id", type="string", length=16)
     */
    private $shop_id;

    /**
     * @var string
     *
     * @ORM\Column(name="shop_pass", type="string", length=16)
     */
    private $shop_pass;

    /**
     * @var int
     *
     * @ORM\Column(name="card_regist_flg", type="integer", options={"unsigned":true})
     */
    private $card_regist_flg;

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
    public function getConnectServerType()
    {
        return $this->connect_server_type;
    }

    /**
     * @return string
     */
    public function getServerUrl()
    {
        return $this->server_url;
    }

    /**
     * @return string
     */
    public function getJsServerUrl()
    {
        preg_match('@^(.*://[^/]+)(.*)$@i', $this->server_url, $matches);
        return $matches[1];
    }

    /**
     * @return string
     */
    public function getKanriServerUrl()
    {
        return $this->kanri_server_url;
    }

    /**
     * @return string
     */
    public function getSiteId()
    {
        return $this->site_id;
    }

    /**
     * @return string
     */
    public function getSitePass()
    {
        return $this->site_pass;
    }

    /**
     * @return string
     */
    public function getShopId()
    {
        return $this->shop_id;
    }

    /**
     * @return string
     */
    public function getShopPass()
    {
        return $this->shop_pass;
    }

    /**
     * @return int
     */
    public function getCardRegistFlg()
    {
        return $this->card_regist_flg;
    }

    /**
     * @param integer $connect_server_type
     *
     * @return $this;
     */
    public function setConnectServerType($connect_server_type)
    {
        $this->connect_server_type = $connect_server_type;

        return $this;
    }

    /**
     * @param string $server_url
     *
     * @return $this;
     */
    public function setServerUrl($server_url)
    {
        $this->server_url = $server_url;

        return $this;
    }

    /**
     * @param string $kanri_server_url
     *
     * @return $this;
     */
    public function setKanriServerUrl($kanri_server_url)
    {
        $this->kanri_server_url = $kanri_server_url;

        return $this;
    }

    /**
     * @param string $site_id
     *
     * @return $this;
     */
    public function setSiteId($site_id)
    {
        $this->site_id = $site_id;

        return $this;
    }

    /**
     * @param string $site_pass
     *
     * @return $this;
     */
    public function setSitePass($site_pass)
    {
        $this->site_pass = $site_pass;

        return $this;
    }

    /**
     * @param string $shop_id
     *
     * @return $this;
     */
    public function setShopId($shop_id)
    {
        $this->shop_id = $shop_id;

        return $this;
    }

    /**
     * @param string $shop_pass
     *
     * @return $this;
     */
    public function setShopPass($shop_pass)
    {
        $this->shop_pass = $shop_pass;

        return $this;
    }

    /**
     * @param integer $card_regist_flg
     *
     * @return $this;
     */
    public function setCardRegistFlg($card_regist_flg)
    {
        $this->card_regist_flg = $card_regist_flg;

        return $this;
    }
}
