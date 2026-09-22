<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_pending_payment_can_only_move_to_submitted_or_cancelled(): void
    {
        $status = OrderStatus::PendingPayment;

        $this->assertTrue($status->canTransitionTo(OrderStatus::PaymentSubmitted));
        $this->assertTrue($status->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse($status->canTransitionTo(OrderStatus::Paid));
        $this->assertFalse($status->canTransitionTo(OrderStatus::Shipped));
    }

    public function test_a_rejected_proof_can_send_the_order_back_to_pending_payment(): void
    {
        $this->assertTrue(OrderStatus::PaymentSubmitted->canTransitionTo(OrderStatus::PendingPayment));
    }

    public function test_delivered_and_cancelled_are_final(): void
    {
        $this->assertTrue(OrderStatus::Delivered->isFinal());
        $this->assertTrue(OrderStatus::Cancelled->isFinal());
        $this->assertFalse(OrderStatus::Paid->isFinal());
    }

    public function test_only_unpaid_statuses_hold_a_reservation(): void
    {
        $this->assertTrue(OrderStatus::PendingPayment->holdsReservation());
        $this->assertTrue(OrderStatus::PaymentSubmitted->holdsReservation());

        // Once paid the stock is deducted for good, so there is nothing left
        // for the expiry sweeper to release.
        $this->assertFalse(OrderStatus::Paid->holdsReservation());
        $this->assertFalse(OrderStatus::Cancelled->holdsReservation());
    }

    public function test_no_status_lists_itself_as_a_valid_target(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertFalse(
                $status->canTransitionTo($status),
                "{$status->value} should not transition to itself."
            );
        }
    }
}
