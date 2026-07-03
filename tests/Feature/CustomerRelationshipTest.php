<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class CustomerRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_listing_is_paginated_and_group_names_filter_customers(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'CRM Shop',
            'slug' => 'crm-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $vipGroup = CustomerGroup::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'VIP Customers',
            'code' => 'VIP',
            'description' => 'High value buyers',
        ]);
        $retailGroup = CustomerGroup::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Retail Customers',
            'code' => 'RET',
            'description' => 'Walk-in buyers',
        ]);

        foreach (range(1, 21) as $index) {
            Customer::query()->create([
                'tenant_id' => $tenant->id,
                'customer_group_id' => $vipGroup->id,
                'first_name' => 'VIP',
                'last_name' => 'Customer '.$index,
                'phone' => '08000000'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'email' => 'vip'.$index.'@example.test',
                'status' => CustomerStatus::Active->value,
            ]);
        }

        $retailCustomer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_group_id' => $retailGroup->id,
            'first_name' => 'Retail',
            'last_name' => 'Customer',
            'phone' => '0811111111',
            'email' => 'retail@example.test',
            'status' => CustomerStatus::Active->value,
        ]);

        $user = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.customers.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('customers_page=2', false)
            ->assertSee('group_id='.$vipGroup->id, false)
            ->assertSee('VIP Customers');

        $this->actingAs($user)
            ->get(route('admin.customers.index', ['tenant' => $tenant->id, 'group_id' => $vipGroup->id]))
            ->assertOk()
            ->assertSee('customers_page=2', false)
            ->assertSee('VIP Customer 21')
            ->assertDontSee('data-dialog-open="customer-view-'.$retailCustomer->id.'">Retail Customer', false);
    }
}
