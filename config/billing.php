<?php

/*
|--------------------------------------------------------------------------
| Billing
|--------------------------------------------------------------------------
|
| Plans live in the `plans` table so a super admin can change pricing without
| a deploy; the definitions below are the seed and the fallback. Amounts are
| stored in MINOR UNITS (kobo, cents). Never use a float for money — 0.1 + 0.2
| is not 0.3 in binary floating point, and that error compounds across an
| invoice run.
|
*/

return [

    'currencies' => [
        'NGN' => ['symbol' => '₦', 'minor' => 100],
        'USD' => ['symbol' => '$', 'minor' => 100],
    ],

    /*
     | Currency is chosen by the customer's country: Nigerian workspaces are
     | billed in naira (cards issued locally frequently fail on USD charges),
     | everyone else in dollars.
     */
    'default_currency' => 'USD',
    'ngn_countries' => ['NG'],

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    /*
     | How long an unpaid invoice may stay open before the workspace is
     | restricted. Sending stops; data is never deleted.
     */
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    'gateways' => [
        'default' => env('BILLING_DEFAULT_GATEWAY', 'paystack'),

        'paystack' => [
            'enabled' => (bool) env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'currencies' => ['NGN', 'USD', 'GHS', 'ZAR', 'KES'],
        ],

        'stripe' => [
            'enabled' => (bool) env('STRIPE_SECRET_KEY'),
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
            'currencies' => ['USD', 'EUR', 'GBP'],
        ],

        'manual' => [
            'enabled' => (bool) env('BILLING_MANUAL_ENABLED', true),
            'currencies' => ['NGN', 'USD'],
            'accounts' => [
                'NGN' => [
                    'bank_name' => env('BANK_NGN_NAME'),
                    'account_name' => env('BANK_NGN_ACCOUNT_NAME'),
                    'account_number' => env('BANK_NGN_ACCOUNT_NUMBER'),
                ],
                'USD' => [
                    'bank_name' => env('BANK_USD_NAME'),
                    'account_name' => env('BANK_USD_ACCOUNT_NAME'),
                    'account_number' => env('BANK_USD_ACCOUNT_NUMBER'),
                    'swift' => env('BANK_USD_SWIFT'),
                    'iban' => env('BANK_USD_IBAN'),
                ],
            ],
        ],
    ],

    /*
     | Seed definitions. `limits` of null means unlimited.
     */
    'plans' => [
        [
            'code' => 'free',
            'name' => 'Free',
            'description' => 'Kick the tyres. One relay, one list, no card.',
            'price' => ['NGN' => 0, 'USD' => 0],
            'limits' => ['contacts' => 500, 'emails_per_month' => 2_000, 'smtp_accounts' => 1, 'users' => 1],
            'sort_order' => 1,
        ],
        [
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'For a first real list.',
            'price' => ['NGN' => 1_200_000, 'USD' => 1_500],
            'limits' => ['contacts' => 5_000, 'emails_per_month' => 30_000, 'smtp_accounts' => 3, 'users' => 3],
            'sort_order' => 2,
        ],
        [
            'code' => 'growth',
            'name' => 'Growth',
            'description' => 'Automations, deeper analytics, a real relay pool.',
            'price' => ['NGN' => 4_700_000, 'USD' => 5_900],
            'limits' => ['contacts' => 50_000, 'emails_per_month' => 300_000, 'smtp_accounts' => 10, 'users' => 10],
            'sort_order' => 3,
        ],
        [
            'code' => 'scale',
            'name' => 'Scale',
            'description' => 'High-volume sending with priority queues.',
            'price' => ['NGN' => 12_700_000, 'USD' => 15_900],
            'limits' => ['contacts' => 500_000, 'emails_per_month' => 2_000_000, 'smtp_accounts' => 50, 'users' => 25],
            'sort_order' => 4,
        ],
        [
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'Custom volume, dedicated IPs, contracted support.',
            'price' => ['NGN' => null, 'USD' => null], // quoted
            'limits' => ['contacts' => null, 'emails_per_month' => null, 'smtp_accounts' => null, 'users' => null],
            'sort_order' => 5,
        ],
    ],
];
