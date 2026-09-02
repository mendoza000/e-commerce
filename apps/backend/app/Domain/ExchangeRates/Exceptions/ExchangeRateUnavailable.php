<?php

namespace App\Domain\ExchangeRates\Exceptions;

use RuntimeException;

/**
 * The source could not produce a usable rate. Always caught by the scheduled
 * refresh: a failed fetch is recorded on the setting and the last known rate
 * stays in force — checkout never depends on an external API being up.
 */
class ExchangeRateUnavailable extends RuntimeException {}
