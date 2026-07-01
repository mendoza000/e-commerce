<?php

namespace App\Domain\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentSubmitted = 'payment_submitted';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
