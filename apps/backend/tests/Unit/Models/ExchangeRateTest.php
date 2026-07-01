<?php

namespace Tests\Unit\Models;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_rates_for_same_pair_coexist_and_latest_is_retrievable(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $ves = Currency::factory()->create(['code' => 'VES']);

        $older = ExchangeRate::create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $ves->id,
            'rate' => 36.500000,
            'source' => 'manual',
            'reference_amount' => null,
            'effective_at' => now()->subDay(),
            'created_by' => null,
        ]);

        $newer = ExchangeRate::create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $ves->id,
            'rate' => 38.750000,
            'source' => 'manual',
            'reference_amount' => null,
            'effective_at' => now(),
            'created_by' => null,
        ]);

        $count = ExchangeRate::where('from_currency_id', $usd->id)
            ->where('to_currency_id', $ves->id)
            ->count();

        $this->assertSame(2, $count);

        $latest = ExchangeRate::where('from_currency_id', $usd->id)
            ->where('to_currency_id', $ves->id)
            ->orderByDesc('effective_at')
            ->first();

        $this->assertTrue($latest->is($newer));
        $this->assertEqualsWithDelta(38.750000, (float) $latest->rate, 0.000001);
        $this->assertNotEqualsWithDelta((float) $older->rate, (float) $latest->rate, 0.000001);
    }
}
