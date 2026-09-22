<?php

namespace App\Domain\Exceptions;

use App\Domain\Enums\OrderStatus;
use RuntimeException;

/**
 * Raised when code asks an order for a status change the business does not
 * allow (see OrderStatus::allowedTransitions). Rendered as a 422 for API
 * requests in bootstrap/app.php.
 */
class InvalidOrderTransition extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        parent::__construct(
            "Una orden en estado \"{$from->label()}\" no puede pasar a \"{$to->label()}\"."
        );
    }
}
