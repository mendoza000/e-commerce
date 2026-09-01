<?php

namespace Database\Factories;

use App\Domain\Enums\PaymentMethodType;
use App\Models\Currency;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'type' => PaymentMethodType::PagoMovil,
            'label' => 'Pago Móvil',
            'currency_id' => Currency::factory(),
            'instructions' => [
                'bank' => 'Banco de Venezuela',
                'bank_code' => '0102',
                'phone' => '04121234567',
                'document_number' => 'V-12345678',
            ],
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function zelle(): static
    {
        return $this->state([
            'type' => PaymentMethodType::Zelle,
            'label' => 'Zelle',
            'instructions' => ['email' => 'pagos@tienda.test', 'holder_name' => 'Tienda Demo'],
        ]);
    }

    public function transferencia(): static
    {
        return $this->state([
            'type' => PaymentMethodType::TransferenciaNacional,
            'label' => 'Transferencia nacional',
            'instructions' => [
                'bank' => 'Banesco',
                'account_number' => '0134-0000-0000-0000-0000',
                'account_type' => 'Corriente',
                'holder_name' => 'Tienda Demo',
                'document_number' => 'J-123456789',
            ],
        ]);
    }

    public function efectivo(): static
    {
        return $this->state([
            'type' => PaymentMethodType::EfectivoContraEntrega,
            'label' => 'Efectivo contra entrega',
            'instructions' => ['contact_phone' => '04121234567'],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
