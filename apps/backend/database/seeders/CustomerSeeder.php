<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\State;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $miranda = State::where('name', 'Miranda')->first();
        $mirandaMunicipality = $miranda?->municipalities()->first();
        $mirandaParish = $mirandaMunicipality?->parishes()->first();

        Customer::factory()->create([
            'name' => 'Ana Pérez',
            'state_id' => $miranda?->id,
            'municipality_id' => $mirandaMunicipality?->id,
            'parish_id' => $mirandaParish?->id,
        ]);

        $zulia = State::where('name', 'Zulia')->first();
        $zuliaMunicipality = $zulia?->municipalities()->first();
        $zuliaParish = $zuliaMunicipality?->parishes()->first();

        Customer::factory()->create([
            'name' => 'Carlos Gómez',
            'state_id' => $zulia?->id,
            'municipality_id' => $zuliaMunicipality?->id,
            'parish_id' => $zuliaParish?->id,
        ]);

        Customer::factory()->create([
            'name' => 'María Rodríguez',
        ]);
    }
}
