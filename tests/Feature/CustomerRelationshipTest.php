<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Enums\TicketPriority;
use Modules\Customers\Enums\TicketStatus;
use Modules\Customers\Enums\TicketType;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Models\SupportTicket;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class CustomerRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_dialog_shows_overall_current_year_and_current_month_sales_values(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $tenant = $this->tenant();
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Sales',
            'last_name' => 'Customer',
            'phone' => '08012345678',
            'status' => CustomerStatus::Active->value,
        ]);

        $this->salesOrder($tenant, $customer, 'PREVIOUS-YEAR', '2025-12-15', 100000);
        $this->salesOrder($tenant, $customer, 'CURRENT-YEAR', '2026-04-10', 200000);
        $this->salesOrder($tenant, $customer, 'CURRENT-MONTH', '2026-09-05', 300000, 50000);
        $this->salesOrder($tenant, $customer, 'CANCELLED', '2026-09-06', 900000, 0, SalesOrderStatus::Cancelled);

        $this->assertSame(4, SalesOrder::query()->where('customer_id', $customer->id)->count());
        $this->assertSame(
            550000,
            (int) SalesOrder::query()
                ->where('customer_id', $customer->id)
                ->where('order_status', '!=', SalesOrderStatus::Cancelled->value)
                ->sum(\DB::raw('total_minor - refunded_minor')),
        );

        $this->actingAs(User::factory()->create(['is_platform_admin' => true]))
            ->get(route('admin.customers.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('aria-label="Customer sales values"', false)
            ->assertSeeInOrder([
                'Overall sales',
                'NGN 5,500.00',
                'Current year sales',
                'NGN 4,500.00',
                'Current month sales',
                'NGN 2,500.00',
            ]);
    }

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

    public function test_ticket_view_has_tagged_metadata_and_quick_actions(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create(['is_platform_admin' => true]);
        $ticket = $this->ticket($tenant);

        $this->actingAs($user)
            ->get(route('admin.customers.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('id="ticket-view-'.$ticket->id.'"', false)
            ->assertSee('<span class="tag danger">Complaint</span>', false)
            ->assertSee('<span class="tag danger">High</span>', false)
            ->assertSee('<span class="tag warning">Open</span>', false)
            ->assertSee(route('admin.customers.tickets.claim', $ticket), false)
            ->assertSee(route('admin.customers.tickets.status.update', $ticket), false)
            ->assertSee('Claim ticket')
            ->assertSee('Update status');
    }

    public function test_user_can_claim_an_unassigned_ticket_but_cannot_take_another_users_ticket(): void
    {
        $tenant = $this->tenant();
        $claimant = User::factory()->create(['is_platform_admin' => true]);
        $otherUser = User::factory()->create(['is_platform_admin' => true]);
        $ticket = $this->ticket($tenant);

        $this->actingAs($claimant)
            ->post(route('admin.customers.tickets.claim', $ticket))
            ->assertRedirect(route('admin.customers.index', ['tenant' => $tenant->id]).'#tickets')
            ->assertSessionHas('status');

        $this->assertSame($claimant->id, $ticket->refresh()->assigned_to);

        $this->actingAs($otherUser)
            ->post(route('admin.customers.tickets.claim', $ticket))
            ->assertSessionHasErrors('assigned_to');

        $this->assertSame($claimant->id, $ticket->refresh()->assigned_to);
    }

    public function test_ticket_status_can_be_updated_from_the_view_and_resolution_time_is_maintained(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create(['is_platform_admin' => true]);
        $ticket = $this->ticket($tenant);

        $this->actingAs($user)
            ->patch(route('admin.customers.tickets.status.update', $ticket), [
                'status' => TicketStatus::Resolved->value,
            ])
            ->assertRedirect(route('admin.customers.index', ['tenant' => $tenant->id]).'#tickets')
            ->assertSessionHas('status');

        $this->assertSame(TicketStatus::Resolved, $ticket->refresh()->status);
        $this->assertNotNull($ticket->resolved_at);

        $this->actingAs($user)
            ->patch(route('admin.customers.tickets.status.update', $ticket), [
                'status' => TicketStatus::Open->value,
            ])
            ->assertRedirect(route('admin.customers.index', ['tenant' => $tenant->id]).'#tickets');

        $this->assertSame(TicketStatus::Open, $ticket->refresh()->status);
        $this->assertNull($ticket->resolved_at);

        $this->actingAs($user)
            ->from(route('admin.customers.index', ['tenant' => $tenant->id]))
            ->patch(route('admin.customers.tickets.status.update', $ticket), [
                'status' => 'invalid-status',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(TicketStatus::Open, $ticket->refresh()->status);
    }

    private function ticket(Tenant $tenant): SupportTicket
    {
        return SupportTicket::query()->create([
            'tenant_id' => $tenant->id,
            'ticket_number' => 'TCK-TEST-001',
            'type' => TicketType::Complaint->value,
            'priority' => TicketPriority::High->value,
            'status' => TicketStatus::Open->value,
            'subject' => 'Damaged delivery',
            'description' => 'The delivered item was damaged.',
        ]);
    }

    private function salesOrder(
        Tenant $tenant,
        Customer $customer,
        string $reference,
        string $date,
        int $totalMinor,
        int $refundedMinor = 0,
        SalesOrderStatus $status = SalesOrderStatus::Completed,
    ): SalesOrder {
        return SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'order_number' => 'SO-'.$reference,
            'invoice_number' => 'INV-'.$reference,
            'receipt_number' => 'RCT-'.$reference,
            'order_status' => $status->value,
            'payment_status' => SalesPaymentStatus::Paid->value,
            'order_date' => $date,
            'total_minor' => $totalMinor,
            'paid_minor' => $totalMinor,
            'refunded_minor' => $refundedMinor,
            'payment_method' => 'Cash',
        ]);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Support Shop',
            'slug' => 'support-shop-'.str()->random(6),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
