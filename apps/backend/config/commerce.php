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

    /*
    |--------------------------------------------------------------------------
    | Product Images
    |--------------------------------------------------------------------------
    |
    | Unlike payment proofs, catalogue images are meant to be seen by anyone
    | browsing the store: they live on the `public` disk and are served by the
    | web server through the storage:link symlink, never streamed through the
    | API. Uploads go through the same shared ImageStorageService the proofs
    | use, so both are re-encoded and downscaled the same way.
    |
    */

    'product_image' => [
        'disk' => env('PRODUCT_IMAGE_DISK', 'public'),
        'directory' => 'products',
        'max_kilobytes' => (int) env('PRODUCT_IMAGE_MAX_KB', 5120),
        'mimes' => ['jpeg', 'jpg', 'png', 'webp'],
        'image_max_width' => 1600,
        'image_quality' => 82,
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalogue Limits
    |--------------------------------------------------------------------------
    |
    | The variant generator multiplies option values by each other, so a
    | careless "generate every combination" over four options can ask for
    | thousands of rows. The cap turns that into a 422 the admin can read
    | instead of a request that times out halfway through.
    |
    */

    'catalog' => [
        'max_variants_per_product' => (int) env('MAX_VARIANTS_PER_PRODUCT', 500),
        'max_images_per_product' => (int) env('MAX_IMAGES_PER_PRODUCT', 30),
    ],

];
