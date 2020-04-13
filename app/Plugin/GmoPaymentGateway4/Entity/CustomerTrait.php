<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait CustomerTrait
{
    /**
     * 顧客のGMOPG追加情報.
     *
     * @var Plugin\GmoPaymentGateway4\Entity\GmoMember
     */
    private $GmoMember;

    /**
     * @return Plugin\GmoPaymentGateway4\Entity\GmoMember
     */
    public function getGmoMember()
    {
        return $this->GmoMember;
    }

    /**
     * @param Plugin\GmoPaymentGateway4\Entity\GmoMember $GmoMember|null
     */
    public function setGmoMember(GmoMember $GmoMember = null)
    {
        $this->GmoMember = $GmoMember;
    }
}
