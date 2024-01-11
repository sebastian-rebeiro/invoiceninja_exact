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

use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\Payment;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\PaymentType;
class Webhooks {
    public $request;

    public function __construct(PaymentWebhookRequest $request) {
        $this->request = $request;
    }

    public function settlepayment() {
        $body = $this->request->all();

            $payment = Payment::query()
            ->where('transaction_reference', $body['paymentid'])
            ->where('company_id', $this->request->getCompany()->id)
            ->first();

            if (!$payment) {
                return response()->json(['payment'=>'Not Found'], 404);
            }

            $payment_status = match ($body['status']) {
                "success" => Payment::STATUS_COMPLETED,
                "failed" => Payment::STATUS_FAILED
            };

            $payment->status_id = $payment_status;
            $payment->save();

            return response()->json(['payment'=>$payment], 200);
    }
}