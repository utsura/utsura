<?php

namespace Plugin\PayPalCheckout\Service;

use Plugin\PayPalCheckout\Lib\PayPalHttp\HttpException as PayPalHttpException;
use Plugin\PayPalCheckout\Lib\PayPalHttp\HttpRequest as PayPalHttpRequest;
use Plugin\PayPalCheckout\Lib\PayPalHttp\HttpResponse as PayPalHttpResponse;
use Plugin\PayPalCheckout\Lib\PayPalCheckoutSdk\Core\PayPalHttpClient;
use Plugin\PayPalCheckout\Lib\PayPalCheckoutSdk\Core\ProductionEnvironment;
use Plugin\PayPalCheckout\Lib\PayPalCheckoutSdk\Core\SandboxEnvironment;
use Plugin\PayPalCheckout\Lib\PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use Plugin\PayPalCheckout\Lib\PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use Plugin\PayPalCheckout\Lib\PayPalCheckoutSdk\Orders\OrdersGetRequest;
use Plugin\PayPalCheckout\Lib\PayPalCheckoutSdk\Orders\OrdersPatchRequest;
use Plugin\PayPalCheckout\Lib\PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use Plugin\PayPalCheckout\Contracts\CaptureTransactionResponse;
use Plugin\PayPalCheckout\Contracts\EccubeAddressAccessible;
use Plugin\PayPalCheckout\Contracts\OrderResultResponse;
use Plugin\PayPalCheckout\Contracts\ReferenceTransactionResponse;
use Plugin\PayPalCheckout\Exception\PayPalRequestException;
use stdClass;

/**
 * Class PayPalRequestService
 * @package Plugin\PayPalCheckout\Service
 */
class PayPalRequestService
{
    /**
     * @var PayPalHttpClient|null
     */
    private $client;

    /**
     * PayPalRequestService constructor.
     */
    public function __construct()
    {
        $this->client = null;
    }

    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param bool $sandbox
     */
    public function setEnv(string $clientId, string $clientSecret, $sandbox = false): void
    {
        if ($sandbox) {
            /** @var SandboxEnvironment $env */
            $env = new SandboxEnvironment($clientId, $clientSecret);
        } else {
            /** @var ProductionEnvironment $env */
            $env = new ProductionEnvironment($clientId, $clientSecret);
        }
        /** @var PayPalHttpClient $client */
        $this->client = new PayPalHttpClient($env);
    }

    /**
     * @param string $total
     * @return string
     */
    public function roundedCurrencyFormat(string $total): string
    {
        return (string) ceil(floatval($total));
    }

    /**
     * @param stdClass $options
     * @return OrdersCreateRequest
     */
    public function prepareOrderTransaction(stdClass $options): OrdersCreateRequest
    {
        /** @var OrdersCreateRequest $request */
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->payPalPartnerAttributionId('ECCUBE4_Cart_EC_JP');
        $request->body = [
            'intent' => 'CAPTURE',
            'application_context' => $options->application_context,
            'purchase_units' => [
                0 => [
                    'reference_id' => '1',
                    'amount' => $options->amount,
                    'items' => $options->items
                ]
            ]
        ];
        if (property_exists($options, 'shipping')) {
            $request->body["purchase_units"][0]["shipping"] = $options->shipping;
        }
        return $request;
    }

    /**
     * @param OrdersCreateRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    public function orderTransaction(OrdersCreateRequest $transaction): PayPalHttpResponse
    {
        /** @var PayPalHttpResponse $response */
        $response = $this->send($transaction);
        return new class($response) extends PayPalHttpResponse implements OrderResultResponse {
            /**
             *  constructor.
             * @param PayPalHttpResponse $response
             */
            function __construct(PayPalHttpResponse $response)
            {
                parent::__construct($response->statusCode, $response->result, $response->headers);
            }

            /**
             * @return string
             */
            public function getOrderingId(): string
            {
                return $this
                    ->result
                    ->id;
            }
        };
    }

    /**
     * @param string $orderId
     * @return OrdersGetRequest
     */
    public function prepareOrderDetailTransaction(string $orderId): OrdersGetRequest
    {
        /** @var OrdersGetRequest $request */
        $request = new OrdersGetRequest($orderId);
        return $request;
    }

    /**
     * @param OrdersGetRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    public function orderDetailTransaction(OrdersGetRequest $transaction): PayPalHttpResponse
    {
        /** @var PayPalHttpResponse $response */
        $response = $this->send($transaction);
        return new class($response) extends PayPalHttpResponse implements OrderResultResponse, EccubeAddressAccessible {
            /**
             *  constructor.
             * @param PayPalHttpResponse $response
             */
            function __construct(PayPalHttpResponse $response)
            {
                parent::__construct($response->statusCode, $response->result, $response->headers);
            }

            /**
             * @return string
             */
            public function getOrderingId(): string
            {
                return $this
                    ->result
                    ->id;
            }

            /**
             * @return array
             */
            public function getEccubeAddressFormat(): array
            {
                /** @var array $fullName */
                $fullName = explode(' ', $this->result->purchase_units[0]->shipping->name->full_name);
                return [
                    'name01' => $fullName[1],
                    'name02' => $fullName[0],
                    'kana01' => null,
                    'kana02' => null,
                    'company_name' => null,
                        'postal_code' => $this->result->purchase_units[0]->shipping->address->postal_code,
                        'pref' => $this->result->purchase_units[0]->shipping->address->admin_area_1,
                        'addr01' => $this->result->purchase_units[0]->shipping->address->admin_area_2,
                        'addr02' => $this->result->purchase_units[0]->shipping->address->address_line_1,
                    'phone_number' => null,
                    'email' => $this->result->payer->email_address
                ];
            }
        };
    }

    /**
     * @param string $orderId
     * @param stdClass $options
     * @return OrdersPatchRequest
     */
    public function prepareOrderPatchTransaction(string $orderId, stdClass $options): OrdersPatchRequest
    {
        /** @var OrdersPatchRequest $request */
        $request = new OrdersPatchRequest($orderId);
        $request->body = [
            0 => [
                'op' => 'replace',
                "path" => "/purchase_units/@reference_id=='1'/amount",
                'value' => $options->amount
            ]
        ];
        return $request;
    }

    /**
     * @param OrdersPatchRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    public function orderPatchTransaction(OrdersPatchRequest $transaction): PayPalHttpResponse
    {
        /** @var PayPalHttpResponse $response */
        $response = $this->send($transaction);
        return new class($response, $transaction) extends PayPalHttpResponse implements OrderResultResponse {
            /**
             * @var string
             */
            private $orderingId;

            /**
             *  constructor.
             * @param PayPalHttpResponse $response
             * @param OrdersPatchRequest $transaction
             */
            function __construct(PayPalHttpResponse $response, OrdersPatchRequest $transaction)
            {
                parent::__construct($response->statusCode, $response->result, $response->headers);
                /** @var array $parsedUrl */
                $parsedUrl = parse_url($transaction->path);
                $this->orderingId = basename($parsedUrl['path']);
            }

            /**
             * @return string
             */
            public function getOrderingId(): string
            {
                return $this->orderingId;
            }
        };
    }

    /**
     * @param string $orderId
     * @return OrdersCaptureRequest
     */
    public function prepareCaptureTransaction(string $orderId): OrdersCaptureRequest
    {
        /** @var OrdersCaptureRequest $request */
        $request = new OrdersCaptureRequest($orderId);
        return $request;
    }

    /**
     * @param OrdersCaptureRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    public function captureTransaction(OrdersCaptureRequest $transaction): PayPalHttpResponse
    {
        /** @var PayPalHttpResponse $response */
        $response = $this->send($transaction);
        return new class($response) extends PayPalHttpResponse implements CaptureTransactionResponse {
            /**
             *  constructor.
             * @param PayPalHttpResponse $response
             */
            function __construct(PayPalHttpResponse $response)
            {
                parent::__construct($response->statusCode, $response->result, $response->headers);
            }

            /**
             * @return string
             */
            public function getCaptureTransactionId(): string
            {
                return $this
                    ->result
                    ->purchase_units[0]
                    ->payments
                    ->captures[0]
                    ->id;
            }
        };
    }

    /**
     * @param string $captureId
     * @return CapturesRefundRequest
     */
    public function prepareRefoundTransaction(string $captureId): CapturesRefundRequest
    {
        /** @var CapturesRefundRequest $request */
        $request = new CapturesRefundRequest($captureId);
        return $request;
    }

    /**
     * @param CapturesRefundRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    public function refoundTransaction(CapturesRefundRequest $transaction): PayPalHttpResponse
    {
        return $this->send($transaction);
    }

    /**
     * @param stdClass $options
     * @return PayPalHttpRequest
     */
    public function prepareBillingAgreementToken(stdClass $options): PayPalHttpRequest
    {
        $request = new class() extends PayPalHttpRequest {
            /**
             *  constructor.
             */
            function __construct()
            {
                parent::__construct("/v1/billing-agreements/agreement-tokens", "POST");
                $this->headers["Content-Type"] = "application/json";
            }

            /**
             * @param $payPalClientMetadataId
             */
            public function payPalClientMetadataId($payPalClientMetadataId)
            {
                $this->headers["PayPal-Client-Metadata-Id"] = $payPalClientMetadataId;
            }

            /**
             * @param $payPalRequestId
             */
            public function payPalRequestId($payPalRequestId)
            {
                $this->headers["PayPal-Request-Id"] = $payPalRequestId;
            }

            /**
             * @param $prefer
             */
            public function prefer($prefer)
            {
                $this->headers["Prefer"] = $prefer;
            }
        };
        $request->prefer('return=representation');
        $request->body = [
            "description" => "Billing Agreement",
            "shipping_address" => $options->shipping_address,
            "payer" => [
                "payment_method" => "PAYPAL"
            ],
            "plan" => [
                "type" => "MERCHANT_INITIATED_BILLING",
                "merchant_preferences" => [
                    "return_url" => "https://www.paypal.com/checkoutnow/error",
                    "cancel_url" => "https://www.paypal.com/checkoutnow/error",
                    "notify_url" => "http://localhost/notify",
                    "accepted_pymt_type" => "INSTANT",
                    "skip_shipping_address" => false,
                    "immutable_shipping_address" => true
                ]
            ]
        ];
        return $request;
    }

    /**
     * @param PayPalHttpRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    public function billingAgreementToken(PayPalHttpRequest $transaction): PayPalHttpResponse
    {
        return $this->send($transaction);
    }

    /**
     * @param string $billingToken
     * @return PayPalHttpRequest
     */
    public function prepareBillingAgreement(string $billingToken): PayPalHttpRequest
    {
        $request = new class() extends PayPalHttpRequest {
            /**
             *  constructor.
             */
            function __construct()
            {
                parent::__construct("/v1/billing-agreements/agreements", "POST");
                $this->headers["Content-Type"] = "application/json";
            }

            /**
             * @param $payPalClientMetadataId
             */
            public function payPalClientMetadataId($payPalClientMetadataId)
            {
                $this->headers["PayPal-Client-Metadata-Id"] = $payPalClientMetadataId;
            }

            /**
             * @param $payPalRequestId
             */
            public function payPalRequestId($payPalRequestId)
            {
                $this->headers["PayPal-Request-Id"] = $payPalRequestId;
            }

            /**
             * @param $prefer
             */
            public function prefer($prefer)
            {
                $this->headers["Prefer"] = $prefer;
            }
        };
        $request->prefer('return=representation');
        $request->body = [
            "token_id" => $billingToken
        ];
        return $request;
    }

    /**
     * @param PayPalHttpRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    public function billingAgreement(PayPalHttpRequest $transaction): PayPalHttpResponse
    {
        return $this->send($transaction);
    }

    /**
     * @param string $billingAgreementId
     * @param stdClass $options
     * @return PayPalHttpRequest
     */
    public function prepareReferenceTransaction(string $billingAgreementId, stdClass $options): PayPalHttpRequest
    {
        $request = new class() extends PayPalHttpRequest {
            /**
             *  constructor.
             */
            function __construct()
            {
                parent::__construct("/v1/payments/payment", "POST");
                $this->headers["Content-Type"] = "application/json";
            }

            /**
             * @param $payPalClientMetadataId
             */
            public function payPalClientMetadataId($payPalClientMetadataId)
            {
                $this->headers["PayPal-Client-Metadata-Id"] = $payPalClientMetadataId;
            }

            /**
             * @param $payPalRequestId
             */
            public function payPalRequestId($payPalRequestId)
            {
                $this->headers["PayPal-Request-Id"] = $payPalRequestId;
            }

            /**
             * @param $prefer
             */
            public function prefer($prefer)
            {
                $this->headers["Prefer"] = $prefer;
            }
        };
        $request->prefer('return=representation');
        $request->body = [
            "intent" => "sale",
            "payer" => [
                "payment_method" => "PAYPAL",
                "funding_instruments" => [
                    [
                        "billing"=> [
                            "billing_agreement_id" => $billingAgreementId
                        ]
                    ]
                ]
            ],
            "transactions" => [
                [
                    "amount"=> $options->amount,
                    "description" => "Payment transaction.",
                    "custom" => "Payment custom field.",
                    "note_to_payee" => "Note to payee field.",
                    "invoice_number" => $options->invoice_number,
                    "item_list"=> [
                        "items" => $options->items,
                        "shipping_address" => $options->shipping_address
                    ]
                ]
            ],
            "redirect_urls" => [
                "return_url" => "https=>//example.com/return",
                "cancel_url" => "https=>//example.com/cancel"
            ]
        ];
        return $request;
    }

    /**
     * @param PayPalHttpRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    public function referenceTransactionPayment(PayPalHttpRequest $transaction): PayPalHttpResponse
    {
        /** @var PayPalHttpResponse $response */
        $response = $this->send($transaction);
        return new class($response) extends PayPalHttpResponse implements ReferenceTransactionResponse {
            /**
             *  constructor.
             * @param PayPalHttpResponse $response
             */
            function __construct(PayPalHttpResponse $response)
            {
                parent::__construct($response->statusCode, $response->result, $response->headers);
            }

            /**
             * @return string
             */
            public function getBillingAgreementId(): string
            {
                return $this
                    ->result
                    ->payer
                    ->funding_instruments[0]
                    ->billing
                    ->billing_agreement_id;
            }
        };
    }

    /**
     * @param PayPalHttpRequest $transaction
     * @return PayPalHttpResponse
     * @throws PayPalRequestException
     */
    private function send(PayPalHttpRequest $transaction): PayPalHttpResponse
    {
        try {
            /** @var PayPalHttpResponse $response */
            $response = $this->client->execute($transaction);
        } catch (PayPalHttpException $e) {
            throw new PayPalRequestException($e->getMessage(), $e->statusCode, $e);
        }
        return $response;
    }
}
