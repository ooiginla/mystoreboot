<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Models\FinanceAccount;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Sales\Enums\DiscountType;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesCoupon;
use Modules\Sales\Models\SalesCashLocation;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesTillSession;
use Modules\Tenancy\Models\Tenant;

final class CreateSalesOrderAction
{
    public function __construct(
        private readonly PostInventoryMovementAction $postInventoryMovement,
        private readonly PostJournalEntryAction $postJournalEntry,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $userId): SalesOrder
    {
        return DB::transaction(function () use ($data, $userId): SalesOrder {
            $tenant = Tenant::query()->findOrFail($data['tenant_id']);
            $tillSession = SalesTillSession::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->where('branch_id', $data['branch_id'])
                ->where('status', 'open')
                ->find($data['sales_till_session_id']);

            if (! $tillSession) {
                throw ValidationException::withMessages([
                    'sales_till_session_id' => 'Open a till for this branch before creating POS sales.',
                ]);
            }

            $customer = Customer::query()->where('tenant_id', $tenant->id)->findOrFail($data['customer_id']);
            $isWalkIn = strcasecmp($customer->phone, 'WALK-IN') === 0;

            if ((bool) ($data['is_credit_sale'] ?? false) && $isWalkIn) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Credit sales cannot be assigned to the walk-in customer.',
                ]);
            }

            $items = collect((array) $data['items'])->map(function (array $item) use ($tenant, $data): array {
                $variant = ProductVariant::query()->with('product')->where('tenant_id', $tenant->id)->findOrFail($item['product_variant_id']);
                $quantity = (int) $item['quantity'];
                $unitPriceMinor = $this->moneyToMinor($item['unit_price']);
                $unitCostMinor = $this->unitCostForSale($tenant->id, (int) $data['inventory_location_id'], $variant);
                $lineSubtotalMinor = $quantity * $unitPriceMinor;
                $taxRate = $variant->tax_behavior === TaxBehavior::Taxable
                    ? (float) ($variant->tax_rate ?? $variant->product?->tax_rate ?? $tenant->default_tax_rate ?? 0)
                    : 0.0;

                return [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price_minor' => $unitPriceMinor,
                    'unit_cost_minor' => $unitCostMinor,
                    'line_subtotal_minor' => $lineSubtotalMinor,
                    'tax_minor' => (int) round($lineSubtotalMinor * ($taxRate / 100)),
                ];
            })->values();

            $subtotalMinor = $items->sum('line_subtotal_minor');
            $taxMinor = $items->sum('tax_minor');
            $shippingMinor = $this->moneyToMinor($data['shipping'] ?? 0);
            $coupon = $this->coupon($tenant->id, $data['coupon_code'] ?? null);
            $couponDiscountMinor = $coupon ? $this->discountMinor($coupon->discount_type->value, $coupon->discount_type === DiscountType::Percentage ? (float) $coupon->discount_percent : $coupon->discount_value_minor / 100, $subtotalMinor) : 0;
            $adminDiscountMinor = $this->discountMinor((string) ($data['admin_discount_type'] ?? DiscountType::Amount->value), (float) ($data['admin_discount_value'] ?? 0), $subtotalMinor);
            $totalMinor = max(0, $subtotalMinor + $taxMinor + $shippingMinor - $couponDiscountMinor - $adminDiscountMinor);
            $amountPaidMinor = $this->moneyToMinor($data['amount_paid'] ?? 0);
            $paidMinor = min($amountPaidMinor, $totalMinor);
            $changeDueMinor = max(0, $amountPaidMinor - $totalMinor);
            $paymentStatus = $paidMinor >= $totalMinor
                ? SalesPaymentStatus::Paid
                : ($paidMinor > 0 ? SalesPaymentStatus::PartiallyPaid : SalesPaymentStatus::Unpaid);

            if (! (bool) ($data['is_credit_sale'] ?? false) && $paidMinor < $totalMinor) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'Full payment is required unless this is marked as a credit sale.',
                ]);
            }

            $order = SalesOrder::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $data['branch_id'],
                'customer_id' => $customer->id,
                'user_id' => $userId,
                'sales_till_session_id' => $tillSession->id,
                'sales_coupon_id' => $coupon?->id,
                'order_number' => $this->number('SO', $tenant->id),
                'invoice_number' => $this->number('INV', $tenant->id),
                'receipt_number' => $this->number('RCT', $tenant->id),
                'order_status' => SalesOrderStatus::Completed->value,
                'payment_status' => $paymentStatus->value,
                'order_date' => $data['order_date'],
                'is_credit_sale' => (bool) ($data['is_credit_sale'] ?? false),
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'shipping_minor' => $shippingMinor,
                'coupon_discount_minor' => $couponDiscountMinor,
                'admin_discount_minor' => $adminDiscountMinor,
                'total_minor' => $totalMinor,
                'paid_minor' => $paidMinor,
                'change_due_minor' => $changeDueMinor,
                'payment_method' => $data['payment_method'] ?? null,
                'delivery_method' => $data['delivery_method'] ?? null,
                'delivery_status' => $data['delivery_status'] ?? 'delivered',
                'delivery_address' => $data['delivery_address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $variant = $item['variant'];
                $order->items()->create([
                    'tenant_id' => $tenant->id,
                    'product_variant_id' => $variant->id,
                    'item_name' => $variant->product?->name.' / '.$variant->variant_name,
                    'sku' => $variant->sku,
                    'quantity' => $item['quantity'],
                    'unit_price_minor' => $item['unit_price_minor'],
                    'unit_cost_minor' => $item['unit_cost_minor'],
                    'tax_minor' => $item['tax_minor'],
                    'line_total_minor' => $item['line_subtotal_minor'] + $item['tax_minor'],
                ]);

                $this->postInventoryMovement->execute([
                    'tenant_id' => $tenant->id,
                    'inventory_location_id' => $data['inventory_location_id'],
                    'product_variant_id' => $variant->id,
                    'movement_type' => InventoryMovementType::StockOut->value,
                    'stock_condition' => StockCondition::Sellable->value,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost_minor'] / 100,
                    'reference_type' => 'sales_order',
                    'reference_number' => $order->order_number,
                    'notes' => 'Sold through POS.',
                    'occurred_at' => $data['order_date'],
                ]);
            }

            if ($paidMinor > 0) {
                $order->payments()->create([
                    'tenant_id' => $tenant->id,
                    'sales_till_session_id' => $tillSession->id,
                    'payment_date' => $data['order_date'],
                    'payment_method' => $data['payment_method'] ?? 'Cash',
                    'amount_minor' => $paidMinor,
                ]);
            }

            $this->syncCustomerBalance($customer, $order);
            $cogsMinor = (int) $items->sum(fn (array $item): int => $item['quantity'] * $item['unit_cost_minor']);
            $discountMinor = $couponDiscountMinor + $adminDiscountMinor;

            $this->postJournalEntry->execute(
                $tenant->id,
                (string) $data['order_date'],
                'Sales order '.$order->order_number,
                [
                    ['account_code' => $this->cashAccountFor($data['payment_method'] ?? 'Cash', $tillSession), 'debit_minor' => $paidMinor, 'party_type' => 'customer', 'party_id' => $customer->id],
                    ['account_code' => '1100', 'debit_minor' => $order->balance_minor, 'party_type' => 'customer', 'party_id' => $customer->id],
                    ['account_code' => '4020', 'debit_minor' => $discountMinor],
                    ['account_code' => '4000', 'credit_minor' => $subtotalMinor],
                    ['account_code' => '2100', 'credit_minor' => $taxMinor],
                    ['account_code' => '4010', 'credit_minor' => $shippingMinor],
                    ['account_code' => '5000', 'debit_minor' => $cogsMinor],
                    ['account_code' => '1200', 'credit_minor' => $cogsMinor],
                ],
                'sales_order',
                $order->id,
                'created',
            );

            if ($paidMinor > 0 && $this->isCashMethod($data['payment_method'] ?? 'Cash')) {
                $this->ensureTillCashLocation($tillSession)->increment('balance_minor', $paidMinor);
            }

            return $order->refresh()->load(['customer', 'branch', 'cashier', 'items.variant.product', 'payments']);
        });
    }

    private function coupon(string $tenantId, ?string $code): ?SalesCoupon
    {
        if (! $code) {
            return null;
        }

        return SalesCoupon::query()
            ->where('tenant_id', $tenantId)
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()))
            ->first();
    }

    private function discountMinor(string $type, float $value, int $subtotalMinor): int
    {
        if ($value <= 0) {
            return 0;
        }

        $discount = $type === DiscountType::Percentage->value
            ? (int) round($subtotalMinor * (min(100, $value) / 100))
            : $this->moneyToMinor($value);

        return min($subtotalMinor, max(0, $discount));
    }

    private function syncCustomerBalance(Customer $customer, SalesOrder $order): void
    {
        $customer->update([
            'account_balance_minor' => max(0, $customer->account_balance_minor + $order->balance_minor),
            'last_purchase_at' => $order->order_date,
        ]);
    }

    private function number(string $prefix, string $tenantId): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) (SalesOrder::query()->where('tenant_id', $tenantId)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }

    private function unitCostForSale(string $tenantId, int $inventoryLocationId, ProductVariant $variant): int
    {
        $averageCostMinor = (int) InventoryStockLevel::query()
            ->where('tenant_id', $tenantId)
            ->where('inventory_location_id', $inventoryLocationId)
            ->where('product_variant_id', $variant->id)
            ->lockForUpdate()
            ->value('average_cost_minor');

        return $averageCostMinor > 0
            ? $averageCostMinor
            : (int) ($variant->cost_price_minor ?: $variant->product?->base_cost_price_minor ?: 0);
    }

    private function cashAccountFor(?string $paymentMethod, SalesTillSession $tillSession): string
    {
        return $this->isCashMethod($paymentMethod)
            ? $this->ensureTillCashLocation($tillSession)->financeAccount->code
            : '1000';
    }

    private function isCashMethod(?string $paymentMethod): bool
    {
        return str_contains(strtolower((string) $paymentMethod), 'cash');
    }

    private function ensureTillCashLocation(SalesTillSession $tillSession): SalesCashLocation
    {
        if ($tillSession->cashLocation?->financeAccount) {
            return $tillSession->cashLocation;
        }

        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => 'CT-'.$tillSession->id,
        ], [
            'name' => 'Cashier Till '.$tillSession->session_number,
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ]);

        $location = SalesCashLocation::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => 'CT-'.$tillSession->id,
        ], [
            'branch_id' => $tillSession->branch_id,
            'sales_till_session_id' => $tillSession->id,
            'user_id' => $tillSession->user_id,
            'finance_account_id' => $account->id,
            'name' => 'Cashier Till '.$tillSession->session_number,
            'location_type' => 'till',
            'is_active' => true,
        ]);

        if (! $tillSession->cash_location_id) {
            $tillSession->update(['cash_location_id' => $location->id]);
        }

        return $location->load('financeAccount');
    }
}
