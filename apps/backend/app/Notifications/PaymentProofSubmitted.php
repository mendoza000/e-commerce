<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the store's admins that an order is waiting for their review.
 *
 * Queued (QUEUE_CONNECTION=redis) so the customer's upload request never waits
 * on a mail server.
 */
class PaymentProofSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly PaymentProof $proof,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('paymentCurrency');

        $message = (new MailMessage)
            ->subject("Nuevo comprobante de pago — orden {$order->order_number}")
            ->greeting('Nuevo comprobante recibido')
            ->line("Orden: {$order->order_number}")
            ->line("Cliente: {$order->customer_name} ({$order->customer_phone})")
            ->line("Monto: {$order->payment_amount} {$order->paymentCurrency?->code}")
            ->line("Archivo: {$this->proof->original_name}");

        if ($this->proof->reference !== null) {
            $message->line("Referencia informada: {$this->proof->reference}");
        }

        $message->action('Revisar la orden', $this->adminUrl());

        $whatsappUrl = $this->whatsappUrl();

        if ($whatsappUrl !== null) {
            $message->line("Escribirle al cliente por WhatsApp: {$whatsappUrl}");
        }

        return $message;
    }

    private function adminUrl(): string
    {
        // The admin panel lives in the frontend app (see docs/decisions.md), so
        // the link has to point there, not at this API.
        $frontend = rtrim((string) config('commerce.frontend_url'), '/');

        return "{$frontend}/admin/orders/{$this->order->order_number}";
    }

    /**
     * A plain wa.me link, not an API integration: the admin taps it and writes
     * the message themselves (PRD section 6).
     */
    private function whatsappUrl(): ?string
    {
        $phone = preg_replace('/\D/', '', (string) $this->order->customer_phone);

        if ($phone === '' || $phone === null) {
            return null;
        }

        $store = StoreSetting::query()->first();
        $storeName = $store?->store_name ?? 'la tienda';

        $text = rawurlencode(
            "Hola {$this->order->customer_name}, recibimos tu comprobante de la orden {$this->order->order_number} en {$storeName}."
        );

        return "https://wa.me/{$phone}?text={$text}";
    }
}
