<?php

use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fields = new \stdClass;
        $fields->testMode = false;
        $fields->apikey = "";
        $fields->accountid = "";
        
        $exact = new Gateway;
        $exact->id = 66;
        $exact->name = 'ExactPay'; 
        $exact->key = 'aaloczyxwsn3v3vhb777qmcdgfiekam8'; 
        $exact->provider = 'Exact';
        $exact->is_offsite = false;
        $exact->fields = \json_encode($fields);
        $exact->visible = 1;
        $exact->site_url = 'https://exactpay.com/';
        $exact->default_gateway_type_id = 1;
        $exact->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
