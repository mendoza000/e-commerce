<?php

namespace Database\Seeders;

use App\Domain\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => Role::Owner,
        ]);

        User::factory()->create([
            'name' => 'Staff Demo',
            'email' => 'staff@example.com',
            'role' => Role::Staff,
        ]);

        $this->call([
            CurrencySeeder::class,
            LocationSeeder::class,
            ExchangeRateSettingSeeder::class,
            ExchangeRateSeeder::class,
            StoreSettingSeeder::class,
            PaymentMethodSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
        ]);
    }
}
