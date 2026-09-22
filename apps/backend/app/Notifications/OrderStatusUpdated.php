<?php

namespace App\Notifications;

use App\Domain\Enums\OrderStatus;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a registered customer their order reached one of the three states
 * PRD section 5 singles out: paid, shipped, delivered.
 *
 * Queued (QUEUE_CONNECTION=redis) so an admin confirming a payment or marking
 * an order shipped never waits on a mail server — same reasoning as
 * PaymentProofSubmitted.
 *
 * Mail-only: PRD's "email y/o WhatsApp" becomes an email that carries a wa.me
 * link to the store, the same shape PaymentProofSubmitted already uses for the
 * admin side, just pointed at the store's number instead of the customer's.
 * There is no automated way to push a WhatsApp message without the official
 * API (backlog, PRD section 8), so a real WhatsApp push is out of scope here.
 */
class OrderStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

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
            ->subject("Tu orden {$order->order_number} — {$order->status->label()}")
            ->greeting("Hola {$order->customer_name},")
            ->line($this->statusLine($order->status))
            ->line("Orden: {$order->order_number}")
            ->line("Total: {$order->payment_amount} {$order->paymentCurrency?->code}");

        if ($order->status === OrderStatus::Shipped) {
            if ($order->courier !== null) {
                $message->line("Courier: {$order->courier}");
            }

            if ($order->tracking_code !== null) {
                $message->line("Número de guía: {$order->tracking_code}");
            }
        }

        $message->action('Ver mi orden', $this->orderUrl());

        $whatsappUrl = $this->storeWhatsappUrl();

        if ($whatsappUrl !== null) {
            $message->line("¿Dudas sobre tu orden? Escríbenos por WhatsApp: {$whatsappUrl}");
        }

        return $message;
    }

    private function statusLine(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Paid => 'Confirmamos tu pago. Ya estamos preparando tu orden.',
            OrderStatus::Shipped => 'Tu orden va en camino.',
            OrderStatus::Delivered => 'Tu orden fue entregada. ¡Gracias por tu compra!',
            default => 'El estado de tu orden cambió.',
        };
    }

    private function orderUrl(): string
    {
        // The storefront lives in the frontend app (see docs/decisions.md), so
        // the link has to point there, not at this API.
        $frontend = rtrim((string) config('commerce.frontend_url'), '/');

        return "{$frontend}/orders/{$this->order->order_number}";
    }

    /**
     * A plain wa.me link to the store's own contact number, not the
     * customer's: this is the customer reaching out with a question, the
     * mirror image of the link PaymentProofSubmitted hands the admin.
     */
    private function storeWhatsappUrl(): ?string
    {
        $store = StoreSetting::query()->first();
        $phone = preg_replace('/\D/', '', (string) $store?->whatsapp_number);

        if ($phone === '' || $phone === null) {
            return null;
        }

        $text = rawurlencode("Hola, tengo una consulta sobre mi orden {$this->order->order_number}.");

        return "https://wa.me/{$phone}?text={$text}";
    }
}
