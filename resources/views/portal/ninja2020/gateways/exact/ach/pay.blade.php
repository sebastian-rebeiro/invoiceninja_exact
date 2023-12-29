@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Transfer', 'card_title' => 'Bank Transfer'])

@section('gateway_head')
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{$payment_method_id}}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
        <input type="hidden" name="token" id="token"/>
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="submit" style="display: none" id="form_btn">
        <input type="hidden" name="payment_token" id="payment_token">
    </form>

    <div id="exact_errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        Bank Transfer
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => 'Pay with Bank Transfer'])
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
            <a href="{{route('client.payment_methods.create')}}?method=2" class="button button-primary bg-primary" style="font-size: .7rem;
    padding: .5rem 0.5rem;
    background-color: #9ebed9;">
                <span class="ml-1 cursor-pointer">Connect New Account</span>
            </a>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')

@endsection

@section('gateway_footer')
    @vite('resources/js/clients/payments/forte-ach-payment.js')
@endsection
