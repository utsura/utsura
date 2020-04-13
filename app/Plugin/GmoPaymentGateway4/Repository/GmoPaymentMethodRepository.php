<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Repository;

use Eccube\Repository\AbstractRepository;
use Plugin\GmoPaymentGateway4\Entity\GmoPaymentMethod;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * GmoPaymentMethodRepository
 */
class GmoPaymentMethodRepository extends AbstractRepository
{
    /**
     * GmoPaymentMethodRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, GmoPaymentMethod::class);
    }

    /**
     * クラス名を指定して取得する
     *
     * @param string $class クラス名
     * @return GmoPaymentMethod
     */
    public function getFromClass($class)
    {
        return $this->findOneBy(['memo03' => $class]);
    }

    /**
     * 設定データ(memo05)を取得する
     *
     * @param string $class クラス名
     * @return array 設定データ
     */
    public function getGmoPaymentMethodConfig($class)
    {
        $GmoPaymentMethod = $this->getFromClass($class);
        if (is_null($GmoPaymentMethod)) {
            return [];
        }

        return $GmoPaymentMethod->getPaymentMethodConfig();
    }

    /**
     * 保存処理
     *
     * @param string $class クラス名
     * @param array $data データ配列
     */
    public function saveGmoPaymentMethod($class, array $data)
    {
        $GmoPaymentMethod = $this->getFromClass($class);
        if (is_null($GmoPaymentMethod)) {
            return;
        }

        $GmoPaymentMethod->setPaymentMethodConfig($data);
        $GmoPaymentMethod->setUpdateDate(new \DateTime());

        $this->getEntityManager()->persist($GmoPaymentMethod);
        $this->getEntityManager()->flush();
    }
}
