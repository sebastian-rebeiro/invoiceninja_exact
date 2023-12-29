<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Exact;

use \Illuminate\Http\Request;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\ExactPaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Validator;

use \TheLogicStudio\ExactPayments;
use \TheLogicStudio\ExactPayments\Models\Shared;
use \TheLogicStudio\ExactPayments\Models\Operations;

class ACH
{
    use MakesHash;

    public $exact;

    private $exact_api_key="";
    private $exact_accountid="";

    private $sdk;


    /**
     * Constructs a new CreditCard instance.
     *
     * @param ExactPaymentDriver $exact The payment driver instance.
    */
    public function __construct(ExactPaymentDriver $exact)
    {
        $this->exact = $exact;

        $this->exact_api_key = $this->exact->company_gateway->getConfigField('apikey');
        $this->exact_accountid =  $this->exact->company_gateway->getConfigField('accountid');

        $test_mode = $this->exact->company_gateway->getConfigField('testMode');

        $secret = new Shared\Security();
        $secret->apiKey =$this->exact_api_key;

        $builder = ExactPayments\ExactPayments::builder()
        ->setSecurity($secret);

        $SANDBOX = 0;
        $PRODUCTION = 1;

        if ($test_mode) {
            $this->sdk = $builder->setServerIndex($SANDBOX)->build();
        } else {
            $this->sdk = $builder->setServerIndex($PRODUCTION)->build();
        }
    }

    /**
     * Prepares and renders the authorization view.
     *
     * @param array $data Data to be used in the view.
     * @return mixed The rendered view.
     */
    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->exact;

        return render('gateways.exact.ach.authorize', $data);
    }

    /**
     * Handles the response after a credit card authorization.
     *
     * @param \Illuminate\Http\Request $request The request object containing credit card details.
     * @return mixed A redirect to the payment methods index.
     */
    public function authorizeResponse(Request $request)
    {
        $test = $this->createACHToken($request->routingNumber, $request->accountNumber, "checking");
        var_dump($test['request']);

        if ($test['statuscode'] != 200) {
            return $this->exact->handleResponseError($test['statuscode'], $test['response'], $test['request']);
        } 

        $payment_meta = new \stdClass;
        $payment_meta->brand = (string)ctrans('texts.ach');
        $payment_meta->last4 = (string) $request->accountNumber;
        $payment_meta->last4 = substr($request->accountNumber, -4);
        $payment_meta->exp_year = '-';
        $payment_meta->type = GatewayType::BANK_TRANSFER;

        SystemLogger::dispatch(
            ['response' => $test['response']],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_EXACT,
            $this->exact->client,
            $this->exact->client->company,
        );

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $test['response']->token,
            'payment_method_id' => $request->gateway_type_id,
        ];

        $this->exact->storeGatewayToken($data);

        return redirect()->route('client.payment_methods.index')->withSuccess('Payment Method added.');
    }

    /**
     * Prepares and renders the payment view.
     *
     * @param array $data Data to be used in the view.
     * @return mixed The rendered view.
     */
    public function paymentView(array $data)
    {
        // $this->exact->payment_hash->data = array_merge((array) $this->exact->payment_hash->data, $data);
        // $this->exact->payment_hash->save();

        $data['gateway'] = $this->exact;
        return render('gateways.exact.credit_card.pay', $data);
    }

    /**
     * Handles the response after a payment is made.
     *
     * @param PaymentResponseRequest $request The payment response request object.
     * @return mixed A redirect to the invoices page.
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        // $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();
        // $invoice_totals = $payment_hash->data->total->invoice_totals;
        // // $this->cdebug(['data' => $payment_hash]);
        // $token = $payment_hash->data->tokens[(int)$payment_hash->data->payment_method_id - 1];

        // $request = new Operations\AccountPostPaymentRequest();
        // $request->newPayment = new Shared\NewPayment();
        // $request->newPayment->amount = (int)($payment_hash->data->amount_with_fee * 100);
        // $request->newPayment->capture = true;
        // $request->newPayment->paymentMethod = new Operations\PaymentMethod();
        // $request->newPayment->paymentMethod->token = $token->token;
        // $request->accountId = $this->exact_accountid;

        // try {
        //     $response = $this->sdk->payments->accountPostPayment($request);
        //     // $this->cdebug(['request'> $request, 'response' => $response ,'data' => $payment_hash]);
        // } catch (\Throwable $th) {
        //     SystemLogger::dispatch(
        //         ['request' => $request],
        //         SystemLog::CATEGORY_GATEWAY_RESPONSE,
        //         SystemLog::EVENT_GATEWAY_FAILURE,
        //         SystemLog::TYPE_EXACT,
        //         $this->exact->client,
        //         $this->exact->client->company,
        //     );
        //     throw $th;
        // }

        // if ($response->statusCode != 201) {
        //     return $this->exact->handleResponseError($response->statusCode, $response, $request);
        // } 
            
        // SystemLogger::dispatch(
        //     ['response' => $response ,'data' => $payment_hash],
        //     SystemLog::CATEGORY_GATEWAY_RESPONSE,
        //     SystemLog::EVENT_GATEWAY_SUCCESS,
        //     SystemLog::TYPE_EXACT,
        //     $this->exact->client,
        //     $this->exact->client->company,
        // );

        // $data = [
        //     'payment_type' => PaymentType::parseCardType(strtolower($token->meta->brand)) ?: PaymentType::CREDIT_CARD_OTHER,
        //     'amount' => $payment_hash->data->amount_with_fee,
        //     'gateway_type_id' => GatewayType::CREDIT_CARD,
        //     'transaction_reference' => $response->twoHundredAndOneApplicationJsonPayment["paymentId"],
        //     'custom_value1' => $response->twoHundredAndOneApplicationJsonPayment["authorization"]
        // ];
        // $this->exact->createPayment($data, Payment::STATUS_COMPLETED);
            
        return redirect('client/invoices')->withSuccess('Invoice paid.');
    }

    /**
     * Logs debugging information.
     *
     * @param array $data The data to be logged.
     */
    public function cdebug(array $data) {
        SystemLogger::dispatch(
            ['data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_ERROR,
            SystemLog::TYPE_EXACT,
            $this->exact->client,
            $this->exact->client->company,
        );
    }

    private function createACHToken($routingNumber, $accountNumber, $accountType)
    {
        if ($this->exact->company_gateway->getConfigField('testMode')) {
            $url = "https://api.exactpaysandbox.com/account/$this->exact_accountid/payment-method";
        } else {
            $url = "https://api-p2.exactpay.com/account/$this->exact_accountid/payment-method";
        } 

        // $url = "http://localhost:8080";

        $body = '{
            "type": "ach",
            "ach": {
              "routingNumber": "'.$routingNumber.'",
              "accountNumber": "'.$accountNumber.'",
              "bankAccountType": "'.$accountType.'"
            },
            "billingDetails": {
              "address": {
                "line1": "'.$this->exact->client->address1.'",
                "city": "'.$this->exact->client->city.'",
                "state": "QC",
                "postalCode": "'.$this->exact->client->postal_code.'",
                "country": "CA"
              },
              "name": "'.$this->exact->client->contacts()->first()->first_name.' '.$this->exact->client->contacts()->first()->last_name.'",
              "email": "'.$this->exact->client->contacts()->first()->email.'",
              "phone": "'.$this->exact->client->phone.'"
            }
        }';

        $headers = array(
            "Content-Type: application/json",
            "Authorization: $this->exact_api_key",
        );

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$body,
            CURLOPT_HTTPHEADER => $headers,
            ]);

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            $response=json_decode($response);
        } catch (\Throwable $th) {
            throw $th;
        }

        $data = ['response' => $response, 'statuscode' => $httpcode, 'request' => $body];

        return $data;
    }
}