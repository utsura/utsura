<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4;

use Eccube\Common\EccubeNav;

class GmoPaymentGateway4Nav implements EccubeNav
{
    /**
     * @return array
     */
    public static function getNav()
    {
        return [
            'order' => [
                'children' => [
                    'gmo_payment_gateway_admin_payment_status' => [
                        'name' => 'gmo_payment_gateway.admin.nav.payment_list',
                        'url' => 'gmo_payment_gateway_admin_payment_status',
                    ],
                ],
            ],
        ];
    }
}
