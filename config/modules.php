<?php

declare(strict_types=1);
use Modules\Access\Providers\AccessServiceProvider;
use Modules\Analytics\Providers\AnalyticsServiceProvider;
use Modules\Business\Providers\BusinessServiceProvider;
use Modules\Catalog\Providers\CatalogServiceProvider;
use Modules\Customers\Providers\CustomersServiceProvider;
use Modules\Finance\Providers\FinanceServiceProvider;
use Modules\HrPayroll\Providers\HrPayrollServiceProvider;
use Modules\Inventory\Providers\InventoryServiceProvider;
use Modules\Logistics\Providers\LogisticsServiceProvider;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\Procurement\Providers\ProcurementServiceProvider;
use Modules\Recommendations\Providers\RecommendationsServiceProvider;
use Modules\Sales\Providers\SalesServiceProvider;
use Modules\Storefront\Providers\StorefrontServiceProvider;
use Modules\Subscriptions\Providers\SubscriptionsServiceProvider;
use Modules\Tenancy\Providers\TenancyServiceProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Storeboot Module Registry
    |--------------------------------------------------------------------------
    |
    | The platform is a modular monolith. Modules are loaded in dependency order
    | and can later be exposed or hidden per subscription plan at runtime.
    |
    */

    'registry' => [
        'Platform' => [
            'enabled' => true,
            'provider' => PlatformServiceProvider::class,
            'depends_on' => [],
        ],
        'Tenancy' => [
            'enabled' => true,
            'provider' => TenancyServiceProvider::class,
            'depends_on' => ['Platform'],
        ],
        'Access' => [
            'enabled' => true,
            'provider' => AccessServiceProvider::class,
            'depends_on' => ['Platform', 'Tenancy'],
        ],
        'Subscriptions' => [
            'enabled' => true,
            'provider' => SubscriptionsServiceProvider::class,
            'depends_on' => ['Platform', 'Tenancy'],
        ],
        'Business' => [
            'enabled' => true,
            'provider' => BusinessServiceProvider::class,
            'depends_on' => ['Tenancy', 'Access', 'Subscriptions'],
        ],
        'Catalog' => [
            'enabled' => true,
            'provider' => CatalogServiceProvider::class,
            'depends_on' => ['Business'],
        ],
        'Inventory' => [
            'enabled' => true,
            'provider' => InventoryServiceProvider::class,
            'depends_on' => ['Business', 'Catalog'],
        ],
        'Sales' => [
            'enabled' => true,
            'provider' => SalesServiceProvider::class,
            'depends_on' => ['Business', 'Catalog', 'Inventory', 'Customers'],
        ],
        'Customers' => [
            'enabled' => true,
            'provider' => CustomersServiceProvider::class,
            'depends_on' => ['Business'],
        ],
        'Procurement' => [
            'enabled' => true,
            'provider' => ProcurementServiceProvider::class,
            'depends_on' => ['Business', 'Catalog', 'Inventory'],
        ],
        'Finance' => [
            'enabled' => true,
            'provider' => FinanceServiceProvider::class,
            'depends_on' => ['Business', 'Sales', 'Procurement'],
        ],
        'HrPayroll' => [
            'enabled' => true,
            'provider' => HrPayrollServiceProvider::class,
            'depends_on' => ['Business'],
        ],
        'Logistics' => [
            'enabled' => false,
            'provider' => LogisticsServiceProvider::class,
            'depends_on' => ['Business', 'Sales', 'Inventory'],
        ],
        'Analytics' => [
            'enabled' => true,
            'provider' => AnalyticsServiceProvider::class,
            'depends_on' => ['Business', 'Inventory', 'Sales', 'Finance'],
        ],
        'Storefront' => [
            'enabled' => true,
            'provider' => StorefrontServiceProvider::class,
            'depends_on' => ['Business', 'Catalog', 'Sales', 'Customers'],
        ],
        'Recommendations' => [
            'enabled' => false,
            'provider' => RecommendationsServiceProvider::class,
            'depends_on' => ['Analytics'],
        ],
    ],
];
