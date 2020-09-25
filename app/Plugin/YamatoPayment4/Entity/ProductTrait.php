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

use Eccube\Annotation\EntityExtension;
use Doctrine\ORM\Mapping as ORM;

/**
 * @EntityExtension("Eccube\Entity\Product")
 *
 */
trait ProductTrait
{
    /**
     * @var \DateTime 予約商品出荷予定日
     *
     * @ORM\Column(name="reserve_date", type="date", nullable=true)
     */
    private $reserve_date;

    /**
     * {@inheritdoc}
     */
    public function setReserveDate($reserve_date)
    {
        $this->reserve_date = $reserve_date;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getReserveDate()
    {
        return $this->reserve_date;
    }
}
