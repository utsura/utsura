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
 * Config
 *
 * @ORM\Table(name="plg_yamato_plugin")
 * @ORM\Entity(repositoryClass="Plugin\YamatoPayment4\Repository\ConfigRepository")
 */
class Config
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
     * @var string
     *
     * @ORM\Column(name="plugin_code", type="string", length=255, nullable=true)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="plugin_name", type="string", length=255, nullable=true)
     */
    private $name;

    /**
     * @var text
     *
     * @ORM\Column(name="sub_data", type="text", nullable=true)
     */
    private $sub_data;

    /**
     * @var text
     *
     * @ORM\Column(name="b2_data", type="text", nullable=true)
     */
    private $b2_data;

    /**
     * @var int
     *
     * @ORM\Column(name="auto_update_flg", type="integer", options={"unsigned":true})
     */
    private $auto_update_flg;

    /**
     * @var int
     *
     * @ORM\Column(name="del_flg", type="integer", options={"unsigned":true})
     */
    private $del_flg;

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
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     *
     * @return $this;
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this;
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubData()
    {
        $data = CommonUtil::unSerializeData($this->sub_data);
        if (empty($data)) {
            $data = [];
        }
        return $data;
    }

    /**
     * @param string $sub_data
     *
     * @return $this
     */
    public function setSubData($sub_data = [])
    {
        if(is_array($sub_data)) {
            $sub_data = CommonUtil::serializeData($sub_data);
        }
        $this->sub_data = $sub_data;

        return $this;
    }

    /**
     * @return string
     */
    public function getB2Data()
    {
        $data = CommonUtil::unSerializeData($this->b2_data);
        if (empty($data)) {
            $data = [];
        }
        return $data;
    }

    /**
     * @param string $sub_data
     *
     * @return $this
     */
    public function setB2Data($b2_data = [])
    {
        if(is_array($b2_data)) {
            $b2_data = CommonUtil::serializeData($b2_data);
        }
        $this->b2_data = $b2_data;

        return $this;
    }

    /**
     * @return integer
     */
    public function getAutoUpdateFlg()
    {
        return $this->auto_update_flg;
    }

    /**
     * @param integer $autoUpdateFlg
     * @return $this
     */
    public function setAutoUpdateFlg($autoUpdateFlg)
    {
        $this->auto_update_flg = $autoUpdateFlg;

        return $this;
    }

    /**
     * @return integer
     */
    public function getDelFlg()
    {
        return $this->del_flg;
    }

    /**
     * @param integer $delFlg
     * @return $this
     */
    public function setDelFlg($delFlg)
    {
        $this->del_flg = $delFlg;

        return $this;
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

    public function setSubDataToArray($sub_data)
    {
        foreach($sub_data as $key => $val) {
            $this->$key = $val;
        }
        return $this;
    }
}
