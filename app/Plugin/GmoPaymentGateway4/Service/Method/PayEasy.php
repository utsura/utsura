<?php

/*
 * Copyright(c) 2018 GMO Payment Gateway, Inc. All rights reserved.
 * http://www.gmo-pg.com/
 */

namespace Plugin\GmoPaymentGateway4\Service\Method;

use Plugin\GmoPaymentGateway4\Entity\GmoPaymentInput;
use Plugin\GmoPaymentGateway4\Util\PaymentUtil;

/**
 * Pay-easy(基底クラス)の決済処理を行う.
 */
class PayEasy extends GmoMethod
{
    /**
     * Pay-easy決済画面の入力値をフォームから取得して返す
     *
     * @return GmoPaymentInput
     */
    protected function getGmoPaymentInputFromForm()
    {
        // 入力項目なし
        return null;
    }

    /**
     * 決済処理の後に行う処理を実装する
     *   リダイレクトする場合は PaymentResult を返却
     *   リダイレクトしない場合は null を返却
     *
     * @param GmoPaymentInput $GmoPaymentInput
     * @return PaymentResult|null
     */
    protected function postRequest(GmoPaymentInput $GmoPaymentInput)
    {
        // 処理なし、リダイレクトなし
        return null;
    }
}
