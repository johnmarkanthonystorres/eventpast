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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'strapi' => [
        'url' => env('STRAPI_URL', 'http://localhost:1337'),
        'token' => env('STRAPI_API_TOKEN', ''),
        'webhook_secret' => env('STRAPI_WEBHOOK_SECRET', ''),
        'cache_duration' => env('STRAPI_CACHE_DURATION', 900), // 15 minutes
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
        'currency' => strtolower(env('STRIPE_CURRENCY', 'php')),
        // Minutes a buyer has to finish paying before the seat is released.
        // Stripe requires Checkout sessions to last at least 30 minutes.
        'checkout_minutes' => max(30, (int) env('STRIPE_CHECKOUT_MINUTES', 30)),
    ],

];
