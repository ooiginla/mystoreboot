<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Customers\Models\Customer;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class SalesOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_paid_order_can_be_cancelled_to_customer_credit_and_marked_refunded(): void
    {
        [$tenant, $branch, $customer] = $this->salesFixture();
        $user = User::factory()->create(['is_platform_admin' => true]);
        $order = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'order_number' => 'SO-CANCEL-001',
            'invoice_number' => 'INV-CANCEL-001',
            'receipt_number' => 'RCT-CANCEL-001',
            'order_status' => SalesOrderStatus::Pending->value,
            'payment_status' => SalesPaymentStatus::PartiallyPaid->value,
            'order_date' => now()->toDateString(),
            'total_minor' => 150000,
            'paid_minor' => 50000,
            'payment_method' => 'Bank transfer',
        ]);
        $order->payments()->create([
            'tenant_id' => $tenant->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank transfer',
            'amount_minor' => 50000,
        ]);

        $this->actingAs($user)
            ->get(route('admin.sales.index', ['tenant' => $tenant->id]).'#orders')
            ->assertOk()
            ->assertSee('Cancel Order');

        $this->actingAs($user)
            ->post(route('admin.sales.orders.cancel', $order))
            ->assertRedirect(route('admin.sales.index', ['tenant' => $tenant->id]).'#orders');

        $order->refresh();
        $this->assertSame(SalesOrderStatus::Cancelled, $order->order_status);
        $this->assertSame(SalesPaymentStatus::CustomerCredit, $order->payment_status);
        $this->assertSame(0, $order->refunded_minor);

        $creditJournal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'sales_order')
            ->where('source_id', $order->id)
            ->where('source_event', 'cancelled_to_customer_credit')
            ->firstOrFail();

        $this->assertTrue($creditJournal->lines->contains(fn ($line): bool => $line->account->code === '1100' && $line->debit_minor === 50000));
        $this->assertTrue($creditJournal->lines->contains(fn ($line): bool => $line->account->code === '2300' && $line->credit_minor === 50000));

        $this->actingAs($user)
            ->get(route('admin.sales.index', ['tenant' => $tenant->id]).'#orders')
            ->assertOk()
            ->assertSee('Customer credit')
            ->assertSee('Customer credit held')
            ->assertSee('Mark as Refunded');

        $this->actingAs($user)
            ->post(route('admin.sales.orders.mark-refunded', $order))
            ->assertRedirect(route('admin.sales.index', ['tenant' => $tenant->id]).'#orders');

        $order->refresh();
        $this->assertSame(SalesOrderStatus::Cancelled, $order->order_status);
        $this->assertSame(SalesPaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(50000, $order->refunded_minor);

        $refundJournal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'sales_order')
            ->where('source_id', $order->id)
            ->where('source_event', 'refunded_cancelled_order')
            ->firstOrFail();

        $this->assertTrue($refundJournal->lines->contains(fn ($line): bool => $line->account->code === '2300' && $line->debit_minor === 50000));
        $this->assertTrue($refundJournal->lines->contains(fn ($line): bool => $line->account->code === '1000' && $line->credit_minor === 50000));
    }

    public function test_pending_unpaid_order_can_be_cancelled_without_refund_credit(): void
    {
        [$tenant, $branch, $customer] = $this->salesFixture();
        $user = User::factory()->create(['is_platform_admin' => true]);
        $order = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'order_number' => 'SO-CANCEL-002',
            'invoice_number' => 'INV-CANCEL-002',
            'receipt_number' => 'RCT-CANCEL-002',
            'order_status' => SalesOrderStatus::Pending->value,
            'payment_status' => SalesPaymentStatus::Pending->value,
            'order_date' => now()->toDateString(),
            'total_minor' => 150000,
            'paid_minor' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('admin.sales.orders.cancel', $order))
            ->assertRedirect(route('admin.sales.index', ['tenant' => $tenant->id]).'#orders');

        $order->refresh();
        $this->assertSame(SalesOrderStatus::Cancelled, $order->order_status);
        $this->assertSame(SalesPaymentStatus::Unpaid, $order->payment_status);
        $this->assertFalse(FinanceJournalEntry::query()->where('source_type', 'sales_order')->where('source_id', $order->id)->exists());
    }

    /**
     * @return array{Tenant, Branch, Customer}
     */
    private function salesFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Cancellation Shop',
            'slug' => 'cancellation-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Head Office',
            'code' => 'HO',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'phone' => '08030000000',
            'email' => 'ada@example.test',
        ]);

        return [$tenant, $branch, $customer];
    }
}
