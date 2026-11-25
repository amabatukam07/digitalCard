<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações de Domínio — NexoSupri (Modelo A — Crédito Exclusivo)
    |--------------------------------------------------------------------------
    */

    'brand_name' => 'NexoSupri',
    'slogan' => 'Sua empresa cuida dos negócios. A NexoSupri cuida das compras.',
    'positioning' => 'NexoSupri — abastecimento empresarial com controle, praticidade e previsibilidade.',

    // Conversão padrão do Modelo A: 1 crédito = R$ 1,00 de poder de compra exclusivo
    'credit_rate' => 1.00,

    // Parâmetros gerenciais padrão de precificação e tributação de referência
    'default_markup_percent' => (float) env('NEXOSUPRI_DEFAULT_MARKUP', 35.0),
    'default_das_rate_percent' => (float) env('NEXOSUPRI_DEFAULT_DAS_RATE', 4.0),
    'default_variable_expenses_percent' => (float) env('NEXOSUPRI_DEFAULT_VARIABLE_EXPENSES', 0.0),

    // Limites operacionais e alertas
    'low_balance_alert_threshold' => (float) env('NEXOSUPRI_LOW_BALANCE_THRESHOLD', 200.00),
    'min_credit_purchase' => (float) env('NEXOSUPRI_MIN_CREDIT_PURCHASE', 500.00),
    'free_shipping_min_total' => (float) env('FREE_SHIPPING_MIN_TOTAL', 500.00),
    'terms_version' => env('NEXOSUPRI_TERMS_VERSION', 'Modelo-A-v1'),

    // Configurações do Gateway PIX (simulado em local/testing)
    'pix' => [
        'key' => env('PIX_SIMULATED_KEY', 'pix@nexosupri.com.br'),
        'merchant_name' => env('PIX_MERCHANT_NAME', 'NEXOSUPRI ABASTECIMENTO'),
        'merchant_city' => env('PIX_MERCHANT_CITY', 'SAO PAULO'),
        'expiration_minutes' => (int) env('PIX_EXPIRATION_MINUTES', 30),
        'webhook_secret' => env('PIX_WEBHOOK_SECRET', 'nexosupri-dev-webhook-secret'),
        'webhook_tolerance_seconds' => (int) env('PIX_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],
];
