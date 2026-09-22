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

    /*
    |--------------------------------------------------------------------------
    | Storefront / Admin URL
    |--------------------------------------------------------------------------
    |
    | The admin panel is embedded in the Next.js app (docs/decisions.md), so
    | links sent from the backend — admin notifications, for instance — have to
    | point at the frontend rather than at this API.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | Payment Review Window
    |--------------------------------------------------------------------------
    |
    | Once a customer uploads a payment proof the order stops being a plain
    | "abandoned checkout": the reservation is extended by this many minutes to
    | give the admin time to review it. It is deliberately NOT infinite —
    | otherwise anyone could freeze stock forever by uploading a junk file.
    | Defaults to 72 hours.
    |
    */

    'payment_review_minutes' => (int) env('PAYMENT_REVIEW_MINUTES', 4320),

    /*
    |--------------------------------------------------------------------------
    | Payment Proof Uploads
    |--------------------------------------------------------------------------
    |
    | Proofs are private by default: the `local` disk resolves to
    | storage/app/private, which is never web-accessible. Images are
    | re-encoded and downscaled on upload; PDFs are stored untouched.
    |
    */

    'payment_proof' => [
        'disk' => env('PAYMENT_PROOF_DISK', 'local'),
        'directory' => 'payment-proofs',
        'max_kilobytes' => (int) env('PAYMENT_PROOF_MAX_KB', 5120),
        'mimes' => ['jpeg', 'jpg', 'png', 'webp', 'pdf'],
        'image_max_width' => 1600,
        'image_quality' => 75,
    ],

];
