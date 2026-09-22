<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | CriptoYa is only ever called by the scheduled exchange-rates:refresh
    | command — never during checkout, which reads the last stored rate. See
    | App\Domain\ExchangeRates\Providers\CriptoYaRateProvider.
    */
    'criptoya' => [
        'base_url' => env('CRIPTOYA_BASE_URL', 'https://criptoya.com/api'),
        'exchange' => env('CRIPTOYA_EXCHANGE', 'binancep2p'),
        'timeout' => (int) env('CRIPTOYA_TIMEOUT', 8),
        'retries' => (int) env('CRIPTOYA_RETRIES', 2),
    ],

];
