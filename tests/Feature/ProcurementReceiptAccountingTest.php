<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Procurement\Actions\ApprovePurchaseOrderAction;
use Modules\Procurement\Actions\ReceivePurchaseOrderAction;
use Modules\Procurement\Actions\RecordVendorPaymentAction;
use Modules\Procurement\Enums\PaymentStatus;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Vendor;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ProcurementReceiptAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_is_recognized_on_receipt_with_freight_in_inventory_cost(): void
    {
        [$purchaseOrder, $item, $branch, $location, $variant] = $this->purchaseContext();
        $user = User::factory()->create(['is_platform_admin' => true]);

        app(ApprovePurchaseOrderAction::class)->execute($purchaseOrder, $user);

        $this->assertFalse(FinanceJournalEntry::query()
            ->where('source_type', 'purchase_order')
            ->where('source_id', $purchaseOrder->id)
            ->exists());

        $receipt = app(ReceivePurchaseOrderAction::class)->execute($purchaseOrder->load('items'), [
            'receipt_number' => 'GRN-001',
            'received_at' => '2026-07-25',
            'reference_number' => 'SUP-DEL-001',
            'notes' => null,
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'quantity_received' => 2,
                'batch_number' => null,
                'expiry_date' => null,
            ]],
        ]);

        $this->assertSame(100000, $receipt->subtotal_minor);
        $this->assertSame(7500, $receipt->tax_minor);
        $this->assertSame(10000, $receipt->shipping_minor);
        $this->assertSame(117500, $receipt->total_minor);

        $stock = InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->sole();
        $movement = InventoryMovement::query()->where('reference_type', 'goods_receipt')->sole();

        $this->assertSame(2, $stock->quantity_on_hand);
        $this->assertSame(55000, $stock->average_cost_minor);
        $this->assertSame(110000, $movement->movement_value_minor);
        $this->assertSame($receipt->id, $movement->reference_id);

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'goods_receipt')
            ->where('source_id', $receipt->id)
            ->where('source_event', 'received')
            ->sole();

        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1200' && $line->branch_id === $branch->id && $line->debit_minor === 110000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1320' && $line->branch_id === $branch->id && $line->debit_minor === 7500));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '2000' && $line->branch_id === $branch->id && $line->credit_minor === 117500));
        $this->assertFalse($journal->lines->contains(fn ($line): bool => $line->account->code === '1210'));

        $payment = app(RecordVendorPaymentAction::class)->execute([
            'tenant_id' => $purchaseOrder->tenant_id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'purchase_order_id' => $purchaseOrder->id,
            'payment_date' => '2026-07-25',
            'amount' => '1175',
            'payment_method' => 'Bank transfer',
            'payment_account_code' => '1010',
        ]);
        $paymentJournal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'vendor_payment')
            ->where('source_id', $payment->id)
            ->sole();

        $this->assertTrue($paymentJournal->lines->contains(fn ($line): bool => $line->account->code === '2000' && $line->debit_minor === 117500));
        $this->assertFalse($paymentJournal->lines->contains(fn ($line): bool => $line->account->code === '1220'));
    }

    public function test_vendor_advance_is_cleared_when_goods_are_received(): void
    {
        [$purchaseOrder, $item] = $this->purchaseContext('PO-ADV-001');
        $user = User::factory()->create(['is_platform_admin' => true]);
        app(ApprovePurchaseOrderAction::class)->execute($purchaseOrder, $user);

        app(RecordVendorPaymentAction::class)->execute([
            'tenant_id' => $purchaseOrder->tenant_id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'purchase_order_id' => $purchaseOrder->id,
            'payment_date' => '2026-07-24',
            'amount' => '500',
            'payment_method' => 'Bank transfer',
            'payment_account_code' => '1010',
        ]);

        $receipt = app(ReceivePurchaseOrderAction::class)->execute($purchaseOrder->load('items'), [
            'receipt_number' => 'GRN-ADV-001',
            'received_at' => '2026-07-25',
            'reference_number' => null,
            'notes' => null,
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'quantity_received' => 2,
                'batch_number' => null,
                'expiry_date' => null,
            ]],
        ]);

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'goods_receipt')
            ->where('source_id', $receipt->id)
            ->where('source_event', 'received')
            ->sole();

        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1220' && $line->credit_minor === 50000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '2000' && $line->credit_minor === 67500));
    }

    public function test_partial_receipts_allocate_tax_and_freight_without_exceeding_the_purchase_order(): void
    {
        [$purchaseOrder, $item, , $location, $variant] = $this->purchaseContext('PO-PARTIAL-001');
        $user = User::factory()->create(['is_platform_admin' => true]);
        app(ApprovePurchaseOrderAction::class)->execute($purchaseOrder, $user);

        $firstReceipt = app(ReceivePurchaseOrderAction::class)->execute($purchaseOrder->load('items'), [
            'receipt_number' => 'GRN-PARTIAL-001',
            'received_at' => '2026-07-24',
            'reference_number' => null,
            'notes' => null,
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'quantity_received' => 1,
                'batch_number' => null,
                'expiry_date' => null,
            ]],
        ]);
        $secondReceipt = app(ReceivePurchaseOrderAction::class)->execute($purchaseOrder->refresh()->load('items'), [
            'receipt_number' => 'GRN-PARTIAL-002',
            'received_at' => '2026-07-25',
            'reference_number' => null,
            'notes' => null,
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'quantity_received' => 1,
                'batch_number' => null,
                'expiry_date' => null,
            ]],
        ]);

        $this->assertSame(50000, $firstReceipt->subtotal_minor);
        $this->assertSame(3750, $firstReceipt->tax_minor);
        $this->assertSame(5000, $firstReceipt->shipping_minor);
        $this->assertSame(50000, $secondReceipt->subtotal_minor);
        $this->assertSame(3750, $secondReceipt->tax_minor);
        $this->assertSame(5000, $secondReceipt->shipping_minor);
        $this->assertSame(117500, $purchaseOrder->receipts()->sum('total_minor'));

        $stock = InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->sole();
        $this->assertSame(2, $stock->quantity_on_hand);
        $this->assertSame(55000, $stock->average_cost_minor);
    }

    /**
     * @return array{PurchaseOrder, mixed, Branch, InventoryLocation, ProductVariant}
     */
    private function purchaseContext(string $poNumber = 'PO-001'): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Receipt Accounting Shop '.$poNumber,
            'slug' => strtolower(str_replace([' ', '/'], '-', 'receipt-'.$poNumber)),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'location_type' => InventoryLocationType::Branch->value,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Imported Rice',
            'slug' => 'imported-rice',
            'product_type' => ProductType::Product->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => '50kg',
            'sku' => 'RICE-50KG',
            'status' => ProductStatus::Active->value,
        ]);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Rice Supplier',
            'status' => 'active',
        ]);
        $purchaseOrder = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'po_number' => $poNumber,
            'status' => PurchaseOrderStatus::PendingApproval->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'order_date' => '2026-07-24',
            'subtotal_minor' => 100000,
            'tax_minor' => 7500,
            'shipping_minor' => 10000,
            'total_minor' => 117500,
            'paid_minor' => 0,
        ]);
        $item = $purchaseOrder->items()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $location->id,
            'quantity_ordered' => 2,
            'quantity_received' => 0,
            'unit_cost_minor' => 50000,
            'line_total_minor' => 100000,
        ]);

        return [$purchaseOrder, $item, $branch, $location, $variant];
    }
}
