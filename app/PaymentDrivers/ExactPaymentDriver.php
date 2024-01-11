<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\SystemLog;
use App\PaymentDrivers\Exact\ACH;
use App\PaymentDrivers\Exact\CreditCard;
use App\PaymentDrivers\Exact\Webhooks;
use App\Utils\Traits\MakesHash;

use \TheLogicStudio\ExactPayments;
use \TheLogicStudio\ExactPayments\Models\Shared;
use \TheLogicStudio\ExactPayments\Models\Operations;

class ExactPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class, //maps GatewayType => Implementation class
        GatewayType::ACSS => ACH::class,
    ];

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;
        $types[] = GatewayType::ACSS;

        return $types;
    }

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_EXACT; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data); //this is your custom implementation from here
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);  //this is your custom implementation from here
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $secret = new Shared\Security();
        $secret->apiKey = $this->company_gateway->getConfigField('apikey');

        $builder = ExactPayments\ExactPayments::builder()
        ->setSecurity($secret);

        $SANDBOX = 0;
        $PRODUCTION = 1;

        if ($this->company_gateway->getConfigField('testMode')) {
            $sdk = $builder->setServerIndex($SANDBOX)->build();
        } else {
            $sdk = $builder->setServerIndex($PRODUCTION)->build();
        }
        
        $payment_request = new Operations\AccountGetPaymentRequest();
        $payment_request->accountId = $this->company_gateway->getConfigField('accountid');
        $payment_request->paymentId = $payment->transaction_reference;
        
        try {
            $payment_response = $sdk->payments->accountGetPayment($payment_request);
        } catch (\Throwable $th) {
            SystemLogger::dispatch(
                ['request' => $payment_request],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_EXACT,
                $this->client,
                $this->client->company,
            );
            throw $th;
        }
        if ($payment_response->statusCode != 200) {
            $error_msg = $this->handleResponseError($payment_response->statusCode, $payment_response, $payment_request, true);
                
            // $message = [
            //     'action' => 'refund', 
            //     'server_response' => $error_msg,
            //     'data' => $payment->paymentables,
            // ];

            return [
                'transaction_reference' => $payment->transaction_reference,
                'transaction_response' => $payment_response,
                'success' => false,
                'description' => $error_msg,
                'code' => $payment_response->statusCode
            ];
        }
        $authorization = $payment_response->twoHundredApplicationJsonPayment['authorization'];
        
        $refund_request = new Operations\AccountRefundPaymentRequest();
        
        $refund_request->referencedPayment = new Shared\ReferencedPayment();
        $refund_request->referencedPayment->amount = (int)($amount * 100);
        $refund_request->referencedPayment->authorization = $authorization;
        $refund_request->accountId = $this->company_gateway->getConfigField("accountid");
        $refund_request->paymentId = $payment->transaction_reference;
        
        try {
            $response = $sdk->payments->accountRefundPayment($refund_request);
        } catch (\Throwable $th) {
            SystemLogger::dispatch(
                ['request' => $refund_request],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_EXACT,
                $this->client,
                $this->client->company,
            );
            throw $th;
        }
                    
        if ($response->statusCode != 201) {
            $message = $this->handleResponseError($response->statusCode, $response, $refund_request, true);
                        
            return [
                'transaction_reference' => $payment->transaction_reference,
                'transaction_response' => $response,
                'success' => false,
                'description' => $message,
                'code' => $response->statusCode
            ];
        }
        
        
        $message = [
            'action' => 'refund', 
            'server_response' => $response,
            'data' => $payment->paymentables,
        ];
                    
        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_EXACT,
            $this->client,
            $this->client->company,
        );
                    
        return [
            'transaction_reference' => $payment->transaction_reference,
            'transaction_response' => $response,
            'success' => true,
            'description' => $payment->paymentables,
            'code' => $response->statusCode,
        ];
    }

    public function processWebhookRequest(PaymentWebhookRequest $request) {
        $handler = new Webhooks($request);

        $webhook = $request->type;
        $response = match ($webhook) {
            'payment.settle' => $handler->settlepayment(),
            default => response()->json(['status'=>'Reached'], 200)
        }; 
        
        return $response;
        // return response()->json(['status'=>'Reached'], 200);
    }

    public function handleResponseError(int $statuscode, mixed $response, mixed $request, bool $message = false)
    {
        $customMessage = match ($statuscode) {
            400 => "Bad Request",
            403 => "Forbidden",
            404 => "Not Found",
            406 => "Not Acceptable",
            428 => "Precondition Required",
            500 => "Internal Server Error",
            503 => "Service Unavailable",
            default => "Unknown Error"
        };
                        
        SystemLogger::dispatch(
            [
                'response' => $response,
                'request' => $request,
                'message' => $customMessage
            ],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_ERROR,
            SystemLog::TYPE_EXACT,
            $this->client,
            $this->client->company,
        );

        $error = ['code' => $statuscode, 'message' => $customMessage];
        if ($message) {
            return $customMessage;
        }

        return render('gateways.unsuccessful', $error);
    }
}
