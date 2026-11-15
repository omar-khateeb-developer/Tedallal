<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tedallal WhatsApp Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the self-hosted multi-tenant WhatsApp microservice
    | powered by Baileys, running locally on port 8082.
    |
    */

    'gateway_url' => env('WHATSAPP_GATEWAY_URL', 'http://127.0.0.1:8082'),

    'gateway_secret' => env('WHATSAPP_GATEWAY_SECRET', 'tedallal_wa_gateway_2026_secure_key'),

    'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),

    'anti_ban' => [
        'min_delay_seconds' => (int) env('WHATSAPP_MIN_DELAY_SECONDS', 5),
        'max_delay_seconds' => (int) env('WHATSAPP_MAX_DELAY_SECONDS', 12),
        'simulate_typing' => (bool) env('WHATSAPP_SIMULATE_TYPING', true),
    ],
];

