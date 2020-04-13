<?php

namespace Plugin\YamatoPayment4;

use Eccube\Common\EccubeTwigBlock;

class YamatoPaymentTwigBlock implements EccubeTwigBlock
{
    /**
     * @return array
     */
    public static function getTwigBlock()
    {
        return [
            '@YamatoPayment4/credit.twig',
            '@YamatoPayment4/credit_confirm.twig',
            '@YamatoPayment4/admin/payment_register.twig',
        ];
    }
}
