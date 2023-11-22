@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="year-invalid" content="{{ ctrans('texts.year_invalid') }}">
    <meta name="month-invalid" content="{{ ctrans('texts.month_invalid') }}">
    <meta name="credit-card-invalid" content="{{ ctrans('texts.credit_card_invalid') }}">

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>
    <script src="https://api.exactpaysandbox.com/js/v1/exact.js"></script>

    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="token" id="token">
        <input type="hidden" name="card_brand" id="card_brand">
        <input type="hidden" name="expiry_month" id="expiry_month">
        <input type="hidden" name="expiry_year" id="expiry_year">
        <input type="hidden" name="last4" id="last4">

        <input type="hidden" name="email-address" id="email-address">
        <input type="hidden" name="address" id="address">
        <input type="hidden" name="apartment" id="apartment">
        <input type="hidden" name="city" id="city">
        <input type="hidden" name="province" id="province">
        <input type="hidden" name="postal-code" id="postal-code">

    </form>

    @if(Session::has('error'))
            <div class="alert alert-failure mb-4" id="errors">{{ Session::get('error') }}</div>
        @endif
        <div id="exact_errors"></div>
        @if ($errors->any())
            <div class="alert alert-failure mb-4">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.exact.includes.credit_card')

    <div class="bg-white px-4 py-5 flex justify-end">
            <button type="button"
                onclick="submitCard()"
                class="button button-primary bg-primary {{ $class ?? '' }}">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                <span>{{ $slot ?? ctrans('texts.add_payment_method') }}</span>
            </button>
            <input type="submit" style="display: none" id="form_btn">
        </div>
@endsection

@section('gateway_footer')
    <script>
        var preauthData = @json($preauth);
        const exact = ExactJS(preauthData.accessToken.token)
        
        const components = exact.components({orderId: preauthData.id})

        components.addCard('test'
        , {
            billingAddress: {
                type: "minimal"
            }
        }
        );
        // components.addComponent('card_number', 'card-number');
        // components.addComponent('expiration_date', 'expiry');
        // components.addComponent('cvv', 'cvd');
        
        function submitCard(){ 
            exact.tokenize();
        }
        
        console.log(preauthData);

        exact.on("payment-complete", (payload) => {
            // add the token details to your form
            document.getElementById('token').value  = payload.token;
            document.getElementById('card_brand').value  = payload.card_brand;
            document.getElementById('expiry_month').value  = payload.expiry_month;
            document.getElementById('expiry_year').value  = payload.expiry_year;
            document.getElementById('last4').value  = payload.last4;
            document.getElementById('myForm').submit();
            // submit your form to your backend
            document.forms.server_response.submit();
        });

        exact.on("payment-failed", (payload) => {
            var errors = '<div class="alert alert-failure mb-4"><ul><li>'+ params.response_description +'</li></ul></div>';
            document.getElementById("exact_errors").innerHTML = errors;
            console.log(payload)
        });

    </script>

    @if($gateway->company_gateway->getConfigField('testMode'))
        <script src="https://jstest.authorize.net/v1/Accept.js" charset="utf-8"></script>
    @else
        <script src="https://js.authorize.net/v1/Accept.js" charset="utf-8"></script>
    @endif

    <script src="{{ asset('js/clients/payment_methods/authorize-authorize-card.js') }}"></script>
@endsection
