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
    'custom_domain_target' => env('STOREFRONT_CUSTOM_DOMAIN_TARGET', 'custom.storeboot.com'),
    'custom_domain_target_ip' => env('STOREFRONT_CUSTOM_DOMAIN_TARGET_IP'),

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

    'reserved_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('STOREFRONT_RESERVED_DOMAINS', 'storeboot.com,www.storeboot.com,app.storeboot.com,api.storeboot.com,admin.storeboot.com,custom.storeboot.com')),
    ))),

    'compound_public_suffixes' => [
        'co.uk',
        'com.au',
        'com.gh',
        'com.ng',
        'co.za',
        'org.uk',
        'net.au',
        'net.ng',
        'org.ng',
    ],
];
