<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentResult;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyAtm;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Pay-easy(銀行ATM)決済処理を行うクラス
 */
class PaymentHelperPayEasyAtm extends PaymentHelperPayEasy
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        return PayEasyAtm::class;
    }

    /**
     * 決済完了案内本文を返す
     *
     * @return string 案内本文
     */
    public function getMailBody()
    {
        $path = '@GmoPaymentGateway4/admin/mail/payeasy_atm.twig';
        return $this->twig->render($path, []);
    }
}
