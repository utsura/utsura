<?php

namespace Plugin\PayPalCheckout\Contracts;

/**
 * Interface CaptureTransactionResponse
 * @package Plugin\PayPalCheckout\Contracts
 */
interface CaptureTransactionResponse
{
    /**
     * @return string
     */
    public function getCaptureTransactionId(): string;
}
