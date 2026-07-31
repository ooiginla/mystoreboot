<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant storefront domains
    |--------------------------------------------------------------------------
    |
    | Leave the base domain empty during local development to keep generating
    | the legacy /store/{username} URLs. In production, set it to storeboot.com
    | so public links are generated as https://{subdomain}.storeboot.com.
    |
    */
    'base_domain' => env('STOREFRONT_BASE_DOMAIN'),
    'scheme' => env('STOREFRONT_SCHEME', 'https'),

    'reserved_subdomains' => [
        'admin',
        'api',
        'app',
        'assets',
        'auth',
        'billing',
        'cdn',
        'dashboard',
        'help',
        'mail',
        'status',
        'store',
        'support',
        'www',
    ],
];
