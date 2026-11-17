<?php

declare(strict_types=1);

namespace Modules\Business\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tenancy\Models\Tenant;

/**
 * Wipes a tenant's TRANSACTIONAL data so they get a clean dashboard and clean
 * reports, while leaving the business intact: settings, products, catalogue,
 * configuration, branches, roles, users, customers, vendors, payment accounts
 * and the online store are all preserved.
 *
 * What it removes: everything that posts to the ledger or shows up on reports —
 * journals, expenses, sales orders & payments & returns, till sessions, online
 * settlements, wallet activity, inventory movements, stock counts/requisitions,
 * purchase orders & goods receipts, production runs, reseller orders, kitchen
 * tickets, customer purchase/balance history, and payroll runs.
 *
 * What it resets (rows kept, values zeroed): inventory stock levels back to 0,
 * wallet balances to 0, and each customer's loyalty points & account balance to 0.
 */
final class ResetTenantTransactionsAction
{
    /**
     * Transactional tables to purge, ordered leaf-first. Every table is scoped by
     * tenant_id. Any missing table (module not installed) is skipped. All related
     * foreign keys use cascade/null-on-delete, so order never causes violations.
     *
     * @var list<string>
     */
    private const PURGE_TABLES = [
        // Finance / ledger
        'finance_journal_lines',
        'finance_journal_entries',
        'finance_bank_movements',
        'finance_petty_cash_transactions',
        'finance_expenses',

        // Online store settlements (before sales rows they reference)
        'online_collected_payments',
        'online_payment_settlements',

        // Sales
        'sales_order_item_modifiers',
        'kitchen_order_ticket_items',
        'kitchen_order_tickets',
        'sales_return_items',
        'sales_returns',
        'sales_order_refunds',
        'sales_order_payments',
        'sales_order_items',
        'sales_orders',
        'sales_till_closing_counts',
        'sales_till_movements',
        'sales_till_sessions',

        // Wallet
        'wallet_transactions',
        'wallet_withdrawals',

        // Inventory movement history & stock operations
        'inventory_movement_batches',
        'inventory_movements',
        'inventory_batches',
        'stock_count_items',
        'stock_counts',
        'stock_requisition_items',
        'stock_requisitions',

        // Procurement
        'goods_receipt_items',
        'goods_receipts',
        'vendor_payments',
        'purchase_order_items',
        'purchase_orders',

        // Production (staff pivots cascade from their parents)
        'production_waste_records',
        'production_work_orders',
        'production_order_items',
        'production_orders',
        'production_plans',

        // Reseller
        'reseller_order_items',
        'reseller_payments',
        'reseller_orders',

        // Customer transactional history (customer records themselves are kept)
        'customer_purchases',
        'customer_balance_adjustments',

        // HR payroll (staff roster & shifts are kept)
        'hr_payroll_items',
        'hr_payroll_runs',
        'hr_staff_bonuses',
        'hr_staff_deductions',
        'hr_attendance_records',
    ];

    /**
     * @return array{deleted: int}
     */
    public function execute(Tenant $tenant): array
    {
        $tenantId = (string) $tenant->id;

        return DB::transaction(function () use ($tenantId): array {
            $deleted = 0;

            foreach (self::PURGE_TABLES as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                    continue;
                }

                $deleted += DB::table($table)->where('tenant_id', $tenantId)->delete();
            }

            $this->resetInventory($tenantId);
            $this->resetWallets($tenantId);
            $this->resetCustomers($tenantId);

            return ['deleted' => $deleted];
        });
    }

    /**
     * Keep every stock-level row (so products stay stocked at their locations) but
     * zero the on-hand, reserved and valuation figures so reports read clean.
     */
    private function resetInventory(string $tenantId): void
    {
        if (! Schema::hasTable('inventory_stock_levels')) {
            return;
        }

        $values = array_filter([
            'quantity_on_hand' => Schema::hasColumn('inventory_stock_levels', 'quantity_on_hand') ? 0 : null,
            'quantity_reserved' => Schema::hasColumn('inventory_stock_levels', 'quantity_reserved') ? 0 : null,
            'average_cost_minor' => Schema::hasColumn('inventory_stock_levels', 'average_cost_minor') ? 0 : null,
        ], static fn ($value): bool => $value !== null);

        if (Schema::hasColumn('inventory_stock_levels', 'last_movement_at')) {
            $values['last_movement_at'] = null;
        }

        if ($values !== []) {
            DB::table('inventory_stock_levels')->where('tenant_id', $tenantId)->update($values);
        }
    }

    private function resetWallets(string $tenantId): void
    {
        if (! Schema::hasTable('wallets') || ! Schema::hasColumn('wallets', 'tenant_id')) {
            return;
        }

        $values = array_filter([
            'available_balance_minor' => Schema::hasColumn('wallets', 'available_balance_minor') ? 0 : null,
            'pending_balance_minor' => Schema::hasColumn('wallets', 'pending_balance_minor') ? 0 : null,
        ], static fn ($value): bool => $value !== null);

        if ($values !== []) {
            DB::table('wallets')->where('tenant_id', $tenantId)->update($values);
        }
    }

    private function resetCustomers(string $tenantId): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasColumn('customers', 'tenant_id')) {
            return;
        }

        $values = array_filter([
            'loyalty_points' => Schema::hasColumn('customers', 'loyalty_points') ? 0 : null,
            'account_balance_minor' => Schema::hasColumn('customers', 'account_balance_minor') ? 0 : null,
        ], static fn ($value): bool => $value !== null);

        if ($values !== []) {
            DB::table('customers')->where('tenant_id', $tenantId)->update($values);
        }
    }
}
