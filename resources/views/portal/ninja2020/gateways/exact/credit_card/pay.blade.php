@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>

    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
        <input type="hidden" name="token" id="token"/>
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->hashed_id }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                        <button class="ml-1 cursor-pointer">**** {{ optional($token->meta)->last4 }}</button>
                    </label>
            @endforeach
        @endisset

        <label>
            <a href="{{route('client.payment_methods.create')}}?method=1" class="button button-primary bg-primary" style="font-size: .7rem;
    padding: .5rem 0.5rem;
    background-color: #9ebed9;">
                <!-- <input
                    type="radio"
                    id="toggle-payment-with-credit-card"
                    class="form-radio cursor-pointer"
                    name="payment-type"
                    checked/> -->
                <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
            </a>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    <!-- @include('portal.ninja2020.gateways.authorize.includes.credit_card') -->
    @include('portal.ninja2020.gateways.exact.includes.pay_now')
@endsection

@section('gateway_footer')
    @if($gateway->company_gateway->getConfigField('testMode'))
        <!-- <script src="https://jstest.authorize.net/v1/Accept.js" charset="utf-8"></script> -->
    @else
        <!-- <script src="https://js.authorize.net/v1/Accept.js" charset="utf-8"></script> -->
    @endif

    <!-- <script src="{{ asset('js/clients/payments/authorize-credit-card-payment.js') }}"></script> -->
@endsection