<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stock Reservation Window
    |--------------------------------------------------------------------------
    |
    | Number of minutes a pending order holds its stock reservation before
    | it is eligible for automatic cancellation by the scheduled
    | orders:release-expired-reservations command.
    |
    */

    'reservation_minutes' => (int) env('RESERVATION_MINUTES', 45),

];
