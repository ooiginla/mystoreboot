<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Procurement\Actions\RecordVendorPaymentAction;
use Modules\Procurement\Enums\PaymentStatus;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Vendor;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class VendorPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_payment_credits_selected_current_asset_account(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Procurement Shop',
            'slug' => 'procurement-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Supply Vendor',
            'status' => 'active',
        ]);
        $purchaseOrder = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-001',
            'status' => PurchaseOrderStatus::Approved,
            'payment_status' => PaymentStatus::Unpaid,
            'order_date' => '2026-06-25',
            'subtotal_minor' => 500000,
            'tax_minor' => 0,
            'shipping_minor' => 0,
            'total_minor' => 500000,
            'paid_minor' => 0,
        ]);

        $payment = app(RecordVendorPaymentAction::class)->execute([
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => $purchaseOrder->id,
            'payment_date' => '2026-06-25',
            'amount' => '5000',
            'payment_method' => 'Bank transfer',
            'payment_account_code' => '1010',
        ]);

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'vendor_payment')
            ->where('source_id', $payment->id)
            ->where('source_event', 'paid')
            ->firstOrFail();

        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '2000' && $line->debit_minor === 500000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1010' && $line->credit_minor === 500000));
        $this->assertSame(PaymentStatus::Paid, $purchaseOrder->refresh()->payment_status);
    }
}
