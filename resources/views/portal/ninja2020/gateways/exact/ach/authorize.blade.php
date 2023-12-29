@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Details', 'card_title' => 'Bank Details'])

@section('gateway_head')
<style>
    #collapsible {
        background-color: #eee;
        color: #444;
        cursor: pointer;
        padding: 18px;
        width: 100%;
        border: none;
        text-align: left;
        outline: none;
        font-size: 15px;
    }

    /* Add a background color to the button if it is clicked on (add the .active class with JS), and when you move the mouse over it (hover) */
    .active, #collapsible:hover {
        background-color: #ccc; 
    }

    /* Style the collapsible content. Note: hidden by default */
    #collapsible-content {
        padding: 0 18px;
        display: none;
        overflow: hidden;
        background-color: #f1f1f1;
    }
</style>
@endsection

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
        <button id="collapsible" type="button" onclick="var content = document.getElementById('collapsible-content');if (content.style.display === 'block') {content.style.display = 'none';} else {content.style.display = 'block';}">Terms</button>
        <div id="collapsible-content">
            @component('portal.ninja2020.components.general.card-element-single', ['title' => 'SEPA', 'show_title' => false])
            <p>By adding this to your payment methods, you accept this Agreement and authorize {{ $company->present()->name() }} to debit the specified bank account for any amount owed for charges arising from the use of services and/or purchase of products.</p>
            <br>
            <p>Payments will be debited from the specified account when an invoice becomes due.</p>
            <br>
            <p>Where a scheduled debit date is not a business day, {{ $company->present()->name() }} will debit on the next business day.</p>
            <br>
            <p>You agree that any payments due will be debited from your account immediately upon acceptance of this Agreement and that confirmation of this Agreement may be sent within 5 (five) days of acceptance of this Agreement. You further agree to be notified of upcoming debits up to 1 (one) day before payments are collected.</p>
            <br>
            <p>You have certain recourse rights if any debit does not comply with this agreement. For example, you have the right to receive reimbursement for any debit that is not authorized or is not consistent with this PAD Agreement. To obtain more information on your recourse rights, contact your financial institution.</p>
            <br>
            <p>You may amend or cancel this authorization at any time by providing the merchant with thirty (30) days notice at {{ $company->present()->email() }}. To obtain a sample cancellation form, or further information on cancelling a PAD agreement, please contact your financial institution.</p>
            <br>
            @endcomponent
        </div>
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
