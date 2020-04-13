<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service;

use Eccube\Entity\Order;
use Eccube\Service\Payment\PaymentResult;
use Plugin\GmoPaymentGateway4\Service\Method\PayEasyNet;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Pay-easy(ネットバンク)決済処理を行うクラス
 */
class PaymentHelperPayEasyNet extends PaymentHelperPayEasy
{
    /**
     * GMO-PG 支払方法別のクラス名称を取得する
     *
     * @return string 支払方法別のクラス名称
     */
    protected function getGmoPaymentMethodClass()
    {
        return PayEasyNet::class;
    }

    /**
     * 決済完了案内本文を返す
     *
     * @return string 案内本文
     */
    public function getMailBody()
    {
        $path = '@GmoPaymentGateway4/admin/mail/payeasy_net.twig';
        return $this->twig->render($path, []);
    }

    /**
     * 金融機関選択画面へリダイレクトする情報を返す
     *
     * @return PaymentResult
     */
    public function redirectToSelectBankPage()
    {
        PaymentUtil::logInfo
            ('PaymentHelperPayEasyNet::redirectToSelectBankPage start.');

        $sendData = [];
        $results = $this->getResults();

        $sendData['PaymentURL'] = $results['PaymentURL'];

        $template = '@GmoPaymentGateway4/payments/payeasy_netbank.twig';
        $contents = $this->twig->render($template, ['sendData' => $sendData]);

        $result = new PaymentResult();
        $result->setSuccess(true);
        $result->setResponse(Response::create($contents));

        PaymentUtil::logInfo
            ('PaymentHelperPayEasyNet::redirectToSelectBankPage end.');

        return $result;
    }
}
