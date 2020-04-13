<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Repository;

use Eccube\Repository\AbstractRepository;
use Eccube\Repository\CustomerRepository;
use Plugin\GmoPaymentGateway4\Entity\GmoMember;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * GmoMemberRepository
 */
class GmoMemberRepository extends AbstractRepository
{
    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * GmoMemberRepository constructor.
     *
     * @param RegistryInterface $registry
     * @param CustomerRepository $customerRepository
     */
    public function __construct(
        RegistryInterface $registry,
        CustomerRepository $customerRepository)
    {
        parent::__construct($registry, GmoMember::class);

        $this->customerRepository = $customerRepository;
    }

    /**
     * GMO-PG 会員IDを取得する
     *
     * @param string $customerId
     * @return string 会員ID
     */
    public function getGmoMemberId($customerId)
    {
        $Customer =
            $this->customerRepository->findOneBy(['id' => $customerId]);
        if (is_null($Customer)) {
            return null;
        }

        $GmoMember = $this->findOneBy(['customer_id' => $customerId]);
        if (is_null($GmoMember) || empty($GmoMember->getMemberId())) {
            return null;
        }

        return $GmoMember->getMemberId();
    }

    /**
     * GMO-PG 会員IDを生成する
     *
     * @param string $customerId
     * @return boolean/string
     */
    public function createGmoMemberId($customerId)
    {
        // Get customer data
        $Customer =
            $this->customerRepository->findOneBy(['id' => $customerId]);

        if (is_null($Customer)) {
            PaymentUtil::logError
                ('Customer is not found. id = ' . $customerId);
            return null;
        }

        PaymentUtil::logInfo('Create MemberID for id = ' . $customerId);

        // Get create date
        $createDate = $Customer->getCreateDate()->format('YmdHis');
        $raw = $customerId . '_' . $createDate;

        do {
            $raw = $raw . '_' . rand();
            $gmoMemberId = sha1($raw);

            // Check duplicate at database if existed repeat action to
            // create new gmo member id
            $data = $this->findOneBy(['member_id' => $gmoMemberId]);
        } while (!is_null($data));

        PaymentUtil::logInfo('MemberID is ' . $gmoMemberId);

        return $gmoMemberId;
    }

    /**
     * GMO-PG 会員IDを登録する
     *
     * @param string $customerId
     * @param string $gmoMemberId
     */
    public function updateOrCreate($customerId, $gmoMemberId)
    {
        $now = new \DateTime("now");
        $GmoMember = $this->findOneBy(['customer_id' => $customerId]);
        if (is_null($GmoMember)) {
            $GmoMember = new GmoMember();
            $GmoMember->setCustomerId($customerId);
            $GmoMember->setMemberId($gmoMemberId);
            $GmoMember->setCreateDate($now);
            $GmoMember->setUpdateDate($now);
            PaymentUtil::logInfo
                ("Create GmoMember customer_id = " . $customerId);
        } else {
            $GmoMember->setMemberId($gmoMemberId);
            $GmoMember->setUpdateDate($now);
            PaymentUtil::logInfo
                ("Update GmoMember customer_id = " . $customerId);
        }

        $this->getEntityManager()->persist($GmoMember);
    }
}
