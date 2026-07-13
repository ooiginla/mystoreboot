<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class StorebootPlanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $modules = [
            ['name' => 'Business Setup', 'slug' => 'business', 'is_core' => true],
            ['name' => 'Users & Access Control', 'slug' => 'access', 'is_core' => true],
            ['name' => 'Subscriptions', 'slug' => 'subscriptions', 'is_core' => true],
            ['name' => 'Products & Services', 'slug' => 'catalog', 'is_core' => false],
            ['name' => 'Inventory Management', 'slug' => 'inventory', 'is_core' => false],
            ['name' => 'Sales & Invoicing', 'slug' => 'sales', 'is_core' => false],
            ['name' => 'Customers & CRM', 'slug' => 'customers', 'is_core' => false],
            ['name' => 'Vendors & Procurement', 'slug' => 'procurement', 'is_core' => false],
            ['name' => 'Expenses & Accounting', 'slug' => 'finance', 'is_core' => false],
            ['name' => 'HR & Payroll', 'slug' => 'hrpayroll', 'is_core' => false],
            ['name' => 'Analytics Dashboard', 'slug' => 'analytics', 'is_core' => false],
            ['name' => 'Customer-Facing Storefront', 'slug' => 'storefront', 'is_core' => false],
            ['name' => 'Recommendation Engine', 'slug' => 'recommendations', 'is_core' => false],
        ];

        foreach ($modules as $module) {
            DB::table('billable_modules')->updateOrInsert(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'description' => null,
                    'is_core' => $module['is_core'],
                    'is_active' => ! in_array($module['slug'], ['recommendations'], true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $plans = [
            [
                'name' => 'All Modules Trial',
                'slug' => 'all-modules-trial',
                'sort_order' => 1,
                'monthly_price_minor' => 0,
                'yearly_price_minor' => 0,
                'limits' => ['trial' => true],
                'modules' => ['business', 'access', 'subscriptions', 'catalog', 'inventory', 'sales', 'customers', 'procurement', 'finance', 'hrpayroll', 'analytics', 'storefront'],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'sort_order' => 10,
                'monthly_price_minor' => 0,
                'yearly_price_minor' => 0,
                'limits' => ['branches' => 1, 'users' => 2, 'products' => 100, 'invoices_per_month' => 100],
                'modules' => ['business', 'access', 'subscriptions', 'catalog', 'inventory', 'sales', 'finance'],
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'sort_order' => 20,
                'monthly_price_minor' => 1500000,
                'yearly_price_minor' => 15000000,
                'limits' => ['branches' => 3, 'users' => 10, 'products' => 1000, 'invoices_per_month' => 1000],
                'modules' => ['business', 'access', 'subscriptions', 'catalog', 'inventory', 'sales', 'customers', 'procurement', 'finance', 'analytics'],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'sort_order' => 30,
                'monthly_price_minor' => 3500000,
                'yearly_price_minor' => 35000000,
                'limits' => ['branches' => 10, 'users' => 30, 'products' => 10000, 'invoices_per_month' => 10000],
                'modules' => ['business', 'access', 'subscriptions', 'catalog', 'inventory', 'sales', 'customers', 'procurement', 'finance', 'hrpayroll', 'analytics', 'storefront'],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'sort_order' => 40,
                'monthly_price_minor' => 0,
                'yearly_price_minor' => 0,
                'limits' => ['custom' => true],
                'modules' => ['business', 'access', 'subscriptions', 'catalog', 'inventory', 'sales', 'customers', 'procurement', 'finance', 'hrpayroll', 'analytics', 'storefront'],
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'sort_order' => $plan['sort_order'],
                    'monthly_price_minor' => $plan['monthly_price_minor'],
                    'yearly_price_minor' => $plan['yearly_price_minor'],
                    'currency_code' => 'NGN',
                    'limits' => json_encode($plan['limits'], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $planId = DB::table('plans')->where('slug', $plan['slug'])->value('id');

            foreach ($plan['modules'] as $moduleSlug) {
                $moduleId = DB::table('billable_modules')->where('slug', $moduleSlug)->value('id');

                DB::table('plan_module_entitlements')->updateOrInsert(
                    ['plan_id' => $planId, 'module_id' => $moduleId],
                    [
                        'is_enabled' => true,
                        'limits' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }
}
