<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Payment")
 */
trait PaymentTrait
{
    /**
     * 支払方法のGMOPG追加情報.
     *
     * @var Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod
     */
    private $GmoPaymentMethod;

    /**
     * @return Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod
     */
    public function getGmoPaymentMethod()
    {
        return $this->GmoPaymentMethod;
    }

    /**
     * @param Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod $GmoPaymentMethod|null
     */
    public function setGmoPaymentMethod
        (GmoPaymentMethod $GmoPaymentMethod = null)
    {
        $this->GmoPaymentMethod = $GmoPaymentMethod;
    }
}
