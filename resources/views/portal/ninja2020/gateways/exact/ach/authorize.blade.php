@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Details', 'card_title' => 'Bank Details'])

@section('gateway_content')
    @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
    @endif
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

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}" method="post" id="server_response">
        @csrf

        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="accountNumber" id="accountNumber">
        <input type="hidden" name="routingNumber" id="routingNumber">
        <input type="hidden" name="accountType" id="accountType">

        <div class="alert alert-failure mb-4" hidden id="errors"></div>
        
        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.transit-number')])
            <input class="input w-full" id="transit-number" type="text" required>
        @endcomponent
        
        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.institution-number')])
            <input class="input w-full" id="institution-number" type="text" required>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
            <input class="input w-full" id="account-number" type="text" required>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element-single')
            <input type="checkbox" class="form-checkbox mr-1" name="accept_terms" id="accept-terms" required>
            <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->user()->company->present()->name, 'email' => auth('contact')->user()->client->company->settings->email]) }}</label>
        @endcomponent

        <div class="bg-white px-4 py-5 flex justify-end">
            <button type="button"
                onclick="submitACH()"
                class="button button-primary bg-primary {{ $class ?? '' }}">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                <span>{{ $slot ?? ctrans('texts.add_payment_method') }}</span>
            </button>
            <input type="submit" style="display: none" id="form_btn">
        </div>
    </form>

@endsection

@section('gateway_footer')
    <script>
        function submitACH(){
            var account_number = document.getElementById('account-number').value;
            var institution_number = document.getElementById('institution-number').value;
            var transit_number = document.getElementById('transit-number').value;
            var routing_number = transit_number + institution_number;

            document.getElementById('accountNumber').value = account_number
            document.getElementById('routingNumber').value = routing_number
            document.getElementById('server_response').submit();
        }
    </script>
@endsection
