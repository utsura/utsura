<?php

namespace Plugin\YamatoPayment4;

use Eccube\Common\EccubeNav;

class YamatoPaymentNav implements EccubeNav
{
    /**
     * @return array
     */
    public static function getNav()
    {
        return [
            'order' => [
                'children' => [
                    'yamato_payment4_admin_payment_status' => [
                        'name' => 'yamato_payment.admin.nav.payment_list',
                        'url' => 'yamato_payment4_admin_payment_status',
                    ],
                    'yamato_payment4_admin_deferred_status' => [
                        'name' => 'yamato_payment.admin.nav.deferred_list',
                        'url' => 'yamato_payment4_admin_deferred_status',
                    ]
                ],
            ],
        ];
    }
}
