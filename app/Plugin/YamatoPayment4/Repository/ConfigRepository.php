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

namespace Plugin\YamatoPayment4\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

use Plugin\YamatoPayment4\Entity\Config;

class ConfigRepository extends AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Config::class);
    }

    public function get($id = 1)
    {
        return $this->findOneBy([]);
    }

    /**
     * DBから設定を抽出し、有効な決済方法を返す
     * 
     * @return array $subData['enable_payment_type'] 有効に設定されている決済方法
     */
    public function parseEnablePayments()
    {
        $Config = $this->get();
        $subData = $Config->getSubData();

        if(in_array(60, $subData['enable_payment_type'], true) && $subData['ycf_sms_flg'] == 1) {
            $subData['enable_payment_type'][] = 80;
        }

        return $subData['enable_payment_type'];
    }
}
