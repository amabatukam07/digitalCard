<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações de Domínio — DigitalCard
    |--------------------------------------------------------------------------
    */

    // Limite máximo permitido para percentual de cashback por produto (S10)
    'max_cashback_percent' => (float) env('MAX_CASHBACK_PERCENT', 15.0),

    // Valor mínimo de subtotal (após cupom) para frete grátis em reais (F2)
    'free_shipping_min_total' => (float) env('FREE_SHIPPING_MIN_TOTAL', 299.00),

    // Configurações do Gateway PIX
    'pix' => [
        'key' => env('PIX_SIMULATED_KEY', 'pix@digitalcard.com.br'),
        'merchant_name' => env('PIX_MERCHANT_NAME', 'DIGITALCARD PAGAMENTOS'),
        'merchant_city' => env('PIX_MERCHANT_CITY', 'MACEIO'),
        'expiration_minutes' => (int) env('PIX_EXPIRATION_MINUTES', 30),
        'webhook_secret' => env('PIX_WEBHOOK_SECRET', 'digitalcard-dev-webhook-secret'),
        'webhook_tolerance_seconds' => (int) env('PIX_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],
];
