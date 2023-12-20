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

class CreditCard
{
    use MakesHash;

    public $exact;

    private $exact_api_key="";
    private $exact_accountid="";

    private $sdk;


    public function __construct(ExactPaymentDriver $exact)
    {
        $this->exact = $exact;

        $this->exact_api_key = $this->exact->company_gateway->getConfigField('Apikey');
        $this->exact_accountid =  $this->exact->company_gateway->getConfigField('Accountid');

        $test_mode = $this->exact->company_gateway->getConfigField('testMode');

        $secret = new Shared\Security();
        $secret->apiKey =$this->exact_api_key;

        $builder = ExactPayments\ExactPayments::builder()
        ->setSecurity($secret);

        if ($test_mode) {
            $this->sdk = $builder->build();
        } else {
            $this->sdk = $builder->setServerIndex(1)->build();
        }
    }

    public function authorizeView(array $data)
    {
        $request = new Operations\PostAccountAccountIdOrdersRequest();

        $request->accountId = $this->exact_accountid;
        $request->order = new Shared\Order();
        $request->order->amount = 1;
        $request->order->capture = false;
        $request->order->reference = new Shared\Reference();
        $request->order->reference->referenceNo = $this->exact->client->id;
        try {
            $response = $this->sdk->orders->postAccountAccountIdOrders($request);
        }
        catch (\Throwable $th) {
            throw $th;
        }
        if ($response->statusCode === 403) {
            SystemLogger::dispatch(
                ['response' => $response, 'data' => $request->order],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_EXACT,
                $this->exact->client,
                $this->exact->client->company,
            );
            $error = ['code' => 403, 'message' => "Invalid credentials"];
            return render('gateways.unsuccessful', $error);
        }
        else if (is_null($response->orderResponse)) {
            // throw new Exception("API ERROR");
            SystemLogger::dispatch(
                ['response' => $response, 'data' => $request->order],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_EXACT,
                $this->exact->client,
                $this->exact->client->company,
            );
            $error = ['code' => 500, 'message' => "Undefined Gateway Error"];
            return render('gateways.unsuccessful', $error);
        }
        $data['preauth'] = $response->orderResponse;
        $data['gateway'] = $this->exact;
        return render('gateways.exact.credit_card.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $payment_meta = new \stdClass;
        $payment_meta->exp_month = $request->expiry_month;
        $payment_meta->exp_year = $request->expiry_year;
        $payment_meta->brand = $request->card_brand;
        $payment_meta->last4 = $request->last4;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $request->token,
            'payment_method_id' => GatewayType::CREDIT_CARD,
        ];

        $this->exact->storeGatewayToken($data);
        
        return redirect()->route('client.payment_methods.index')->withSuccess('Payment Method added.');
    }


    public function paymentView(array $data)
    {
        $this->exact->payment_hash->data = array_merge((array) $this->exact->payment_hash->data, $data);
        $this->exact->payment_hash->save();

        $data['gateway'] = $this->exact;
        return render('gateways.exact.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();
        // $invoice_totals = $payment_hash->data->total->invoice_totals;
        // $this->cdebug(['data' => $payment_hash]);
        $token = $payment_hash->data->tokens[(int)$payment_hash->data->payment_method_id - 1];

        $request = new Operations\AccountPostPaymentRequest();
        $request->newPayment = new Shared\NewPayment();
        $request->newPayment->amount = (int)($payment_hash->data->amount_with_fee * 100);
        $request->newPayment->capture = true;
        $request->newPayment->paymentMethod = new Operations\PaymentMethod();
        $request->newPayment->paymentMethod->token = $token->token;
        $request->accountId = $this->exact_accountid;

        try {
            $response = $this->sdk->payments->accountPostPayment($request);
            // $this->cdebug(['request'> $request, 'response' => $response ,'data' => $payment_hash]);
        } catch (\Throwable $th) {
            SystemLogger::dispatch(
                ['data' => $request],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_EXACT,
                $this->exact->client,
                $this->exact->client->company,
            );
            throw $th;
        }

        if ($response->twoHundredAndOneApplicationJsonPayment !== null) {
            
            SystemLogger::dispatch(
                ['request'=> gettype($response->twoHundredAndOneApplicationJsonPayment), 'response' => $response ,'data' => $payment_hash],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_EXACT,
                $this->exact->client,
                $this->exact->client->company,
            );

            $data = [
                'payment_type' => PaymentType::parseCardType(strtolower($token->meta->brand)) ?: PaymentType::CREDIT_CARD_OTHER,
                'amount' => $payment_hash->data->amount_with_fee,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'transaction_reference' => $response->twoHundredAndOneApplicationJsonPayment->paymentid,
                // 'authorization' => $response->twoHundredAndOneApplicationJsonPayment->authorization
            ];
            $payment = $this->exact->createPayment($data, Payment::STATUS_COMPLETED);
            return redirect('client/invoices')->withSuccess('Invoice paid.');

        } else if ($response->statusCode === 403) {
            SystemLogger::dispatch(
                ['response' => $response, 'data' => $request],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_EXACT,
                $this->exact->client,
                $this->exact->client->company,
            );
            $error = ['code' => 403, 'message' => "Invalid credentials"];
            return render('gateways.unsuccessful', $error);
        }
    }

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
}