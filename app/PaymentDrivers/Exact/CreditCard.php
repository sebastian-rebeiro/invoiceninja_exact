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


    public function __construct(ExactPaymentDriver $exact)
    {
        $this->exact = $exact;

        $this->exact_api_key = $this->forte->company_gateway->getConfigField('Apikey');
        $this->exact_accoutid =  $this->forte->company_gateway->getConfigField('Accountid');

        $secret = new Shared\Security();
        $secret->apiKey =$this->exact_api_key;

        $this->sdk = ExactPayments\ExactPayments::builder()
        ->setSecurity($secret)
        ->build();
    }

    public function authorizeView(array $data)
    {
        try {
            $request = new Operations\PostAccountAccountIdOrdersRequest();
            $request->accountId = $this->exact_accoutid;

            $request->order = new Shared\Order();
            $request->order->amount = 1;
            $request->order->capture = false;
            $request->order->reference = new Shared\Reference();
            $request->order->reference->referenceNo = $this->forte->client->id;

            $response = $this->sdk->orders->postAccountAccountIdOrders($request);

            if ($response->orderResponse !== null) {
                $data['preauth'] = $response->orderResponse;
            } else {
                // throw new Exception("API ERROR");
                SystemLogger::dispatch(
                    ['response' => $response, 'data' => $request->order],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_EXACT,
                    $this->exact->client,
                    $this->exact->client->company,
                );
            }
        }
        catch (\Throwable $th) {
            throw $th;
        }

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
            'payment_method_id' => 1
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
    {}
}