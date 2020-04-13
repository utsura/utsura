<?php

namespace Plugin\YamatoPayment4\Entity;

use Eccube\Annotation\EntityExtension;
use Doctrine\ORM\Mapping as ORM;

/**
 * @EntityExtension("Eccube\Entity\Shipping")
 */
trait ShippingTrait
{
    /**
     * @ORM\Column(type="string", length=12, nullable=true)
     */
    public $pre_tracking_number;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public $tracking_information;

    /**
     * {@inheritdoc}
     */
    public function setPreTrackingNumber($pre_tracking_number)
    {
        $this->pre_tracking_number = $pre_tracking_number;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPreTrackingNumber()
    {
        return $this->pre_tracking_number;
    }

    /**
     * {@inheritdoc}
     */
    public function setTrackingInformation($tracking_information)
    {
        $this->tracking_information = $tracking_information;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTrackingInformation()
    {
        return $this->tracking_information;
    }
}