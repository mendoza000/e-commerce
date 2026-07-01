<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $ves = Currency::where('code', 'VES')->firstOrFail();

        $storeSetting = StoreSetting::updateOrCreate(
            ['store_name' => 'Tienda Demo'],
            [
                'base_currency_id' => $usd->id,
                'whatsapp_number' => '+584121234567',
            ]
        );

        $storeSetting->enabledCurrencies()->sync([$usd->id, $ves->id]);
    }
}
