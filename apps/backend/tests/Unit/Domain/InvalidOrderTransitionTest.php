<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\OrderStatus;
use App\Domain\Exceptions\InvalidOrderTransition;
use PHPUnit\Framework\TestCase;

class InvalidOrderTransitionTest extends TestCase
{
    public function test_it_keeps_both_ends_of_the_rejected_move(): void
    {
        $exception = new InvalidOrderTransition(OrderStatus::Delivered, OrderStatus::Cancelled);

        $this->assertSame(OrderStatus::Delivered, $exception->from);
        $this->assertSame(OrderStatus::Cancelled, $exception->to);
    }

    /**
     * The message reaches the admin panel as a 422 body, so it uses the
     * human-readable labels rather than the raw enum values.
     */
    public function test_the_message_names_both_statuses_in_spanish(): void
    {
        $exception = new InvalidOrderTransition(OrderStatus::PendingPayment, OrderStatus::Shipped);

        $this->assertSame(
            'Una orden en estado "Pendiente de pago" no puede pasar a "Enviada".',
            $exception->getMessage()
        );
    }

    public function test_every_status_has_a_label_for_the_message(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame($status->value, $status->label());
        }
    }
}
