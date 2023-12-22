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

use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\SystemLog;
use App\PaymentDrivers\Exact\ACH;
use App\PaymentDrivers\Exact\CreditCard;
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
        GatewayType::CREDIT_CARD => CreditCard::class, //maps GatewayType => Implementation class
    ];

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        // $types[] = GatewayType::BANK_TRANSFER;

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
        $security = new Shared\Security();
        $security->apiKey = $this->company_gateway->getConfigField("Apikey");

        $builder = ExactPayments\ExactPayments::builder()
        ->setSecurity($security);

        $SANDBOX = 0;
        $PRODUCTION = 1;

        if ($this->company_gateway->getConfigField("testMode")) {
            $sdk = $builder->setServerIndex($SANDBOX)->build();
        } else {
            $sdk = $builder->setServerIndex($PRODUCTION)->build();
        }
        
        $request = new Operations\AccountRefundPaymentRequest();

        $request->referencedPayment = new Shared\ReferencedPayment();
        $request->referencedPayment->amount = (int)($amount * 100);
        $request->referencedPayment->authorization = $payment->custom_value1;
        $request->accountId = $this->company_gateway->getConfigField("Accountid");
        $request->paymentId = $payment->transaction_reference;

        try {
            $response = $sdk->payments->accountRefundPayment($request);
        } catch (\Throwable $th) {
            SystemLogger::dispatch(
                ['request' => $request],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_EXACT,
                $this->client,
                $this->client->company,
            );
            throw $th;
        }

        // $forte_base_uri = "https://sandbox.forte.net/api/v3/";
        // if ($this->company_gateway->getConfigField('testMode') == false) {
        //     $forte_base_uri = "https://api.forte.net/v3/";
        // }
        // $forte_api_access_id = $this->company_gateway->getConfigField('apiAccessId');
        // $forte_secure_key = $this->company_gateway->getConfigField('secureKey');
        // $forte_auth_organization_id = $this->company_gateway->getConfigField('authOrganizationId');
        // $forte_organization_id = $this->company_gateway->getConfigField('organizationId');
        // $forte_location_id = $this->company_gateway->getConfigField('locationId');

        // try {
        //     $curl = curl_init();

        //     curl_setopt_array($curl, [
        //         CURLOPT_URL => $forte_base_uri.'organizations/'.$forte_organization_id.'/locations/'.$forte_location_id.'/transactions',
        //         CURLOPT_RETURNTRANSFER => true,
        //         CURLOPT_ENCODING => '',
        //         CURLOPT_MAXREDIRS => 10,
        //         CURLOPT_TIMEOUT => 0,
        //         CURLOPT_FOLLOWLOCATION => true,
        //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //         CURLOPT_CUSTOMREQUEST => 'POST',
        //         CURLOPT_POSTFIELDS =>'{
        //              "action":"reverse", 
        //              "authorization_amount":'.$amount.',
        //              "original_transaction_id":"'.$payment->transaction_reference.'",
        //              "authorization_code": "9ZQ754"
        //       }',
        //         CURLOPT_HTTPHEADER => [
        //           'Content-Type: application/json',
        //           'X-Forte-Auth-Organization-Id: '.$forte_organization_id,
        //           'Authorization: Basic '.base64_encode($forte_api_access_id.':'.$forte_secure_key)
        //         ],
        //       ]);

        //     $response = curl_exec($curl);
        //     $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        //     curl_close($curl);

        //     $response=json_decode($response);
        // } catch (\Throwable $th) {
        //     $message = [
        //         'action' => 'error',
        //         'data' => $th,
        //     ];

        //     SystemLogger::dispatch(
        //         $message,
        //         SystemLog::CATEGORY_GATEWAY_RESPONSE,
        //         SystemLog::EVENT_GATEWAY_FAILURE,
        //         SystemLog::TYPE_FORTE,
        //         $this->client,
        //         $this->client->company,
        //     );
        // }

        if ($response->statusCode != 201) {
            $this->handleResponseError($response->statusCode, $response, $request);

            return [
                'transaction_reference' => $payment->transaction_reference,
                'transaction_response' => $response,
                'success' => false,
                'description' => $payment->paymentables,
                'code' => $response->statusCode
            ];
        }
        $message = [
            'action' => 'refund', 
            'server_response' => $response,
            'data' => $payment->paymentables,
        ];


        // if ($httpcode>299) {
        //     SystemLogger::dispatch(
        //         $message,
        //         SystemLog::CATEGORY_GATEWAY_RESPONSE,
        //         SystemLog::EVENT_GATEWAY_FAILURE,
        //         SystemLog::TYPE_FORTE,
        //         $this->client,
        //         $this->client->company,
        //     );
            
        //     return [
        //         'transaction_reference' => $payment->transaction_reference,
        //         'transaction_response' => $response,
        //         'success' => false,
        //         'description' => $payment->paymentables,
        //         'code' => 422,
        //     ];
        // }

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_FORTE,
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

    // public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    // {
    //     return $this->payment_method->yourTokenBillingImplmentation(); //this is your custom implementation from here
    // }

    public function handleResponseError(int $statuscode, mixed $response, mixed $request)
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
                'data' => $request->order,
                'message' => $customMessage
            ],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_ERROR,
            SystemLog::TYPE_EXACT,
            $this->client,
            $this->client->company,
        );

        $error = ['code' => $statuscode, 'message' => $statuscode];

        return render('gateways.unsuccessful', $error);
    }
}
