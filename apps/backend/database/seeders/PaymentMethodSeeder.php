<?php

namespace Database\Seeders;

use App\Domain\Enums\PaymentMethodType;
use App\Models\Currency;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $ves = Currency::where('code', 'VES')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $methods = [
            [
                'type' => PaymentMethodType::PagoMovil,
                'label' => 'Pago Móvil',
                'currency_id' => $ves->id,
                'position' => 1,
                'instructions' => [
                    'bank' => 'Banco de Venezuela',
                    'bank_code' => '0102',
                    'phone' => '04121234567',
                    'document_number' => 'V-12345678',
                    'notes' => 'Envía el comprobante apenas realices el pago.',
                ],
            ],
            [
                'type' => PaymentMethodType::TransferenciaNacional,
                'label' => 'Transferencia nacional',
                'currency_id' => $ves->id,
                'position' => 2,
                'instructions' => [
                    'bank' => 'Banesco',
                    'account_number' => '0134-0000-0000-0000-0000',
                    'account_type' => 'Corriente',
                    'holder_name' => 'Tienda Demo, C.A.',
                    'document_number' => 'J-123456789',
                ],
            ],
            [
                'type' => PaymentMethodType::Zelle,
                'label' => 'Zelle',
                'currency_id' => $usd->id,
                'position' => 3,
                'instructions' => [
                    'email' => 'pagos@tiendademo.test',
                    'holder_name' => 'Tienda Demo',
                ],
            ],
            [
                'type' => PaymentMethodType::EfectivoContraEntrega,
                'label' => 'Efectivo contra entrega',
                'currency_id' => $usd->id,
                'position' => 4,
                'instructions' => [
                    'contact_phone' => '04121234567',
                    'notes' => 'Ten el monto exacto disponible al recibir el pedido.',
                ],
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['type' => $method['type']],
                [...$method, 'is_active' => true],
            );
        }
    }
}
