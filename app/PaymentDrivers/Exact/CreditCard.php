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

class CreditCard
{
    use MakesHash;

    public $exact;

    private $exact_base_uri="";
    private $exact_api_key="";
    private $exact_accountid="";


    public function __construct(ExactPaymentDriver $exact)
    {
        $this->exact = $exact;

        $this->exact_base_uri = "https://api.exactpaysandbox.com/account/642ddcf848ff9dd598c0041d/";
        $this->exact_api_key = "";
        
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->exact;
        return render('gateways.exact.credit_card.authorize', $data);
    }

    public function authorizeResponse($request)
    {}


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