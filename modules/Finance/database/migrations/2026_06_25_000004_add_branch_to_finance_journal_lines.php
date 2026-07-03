<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_journal_lines', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('finance_account_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'branch_id'], 'finance_journal_lines_tenant_branch_idx');
        });

        $this->backfillFromSource('sales_order', 'sales_orders', 'branch_id');
        $this->backfillViaParent('sales_order_payment', 'sales_order_payments', 'sales_order_id', 'sales_orders', 'branch_id');
        $this->backfillViaParent('sales_return', 'sales_returns', 'sales_order_id', 'sales_orders', 'branch_id');
        $this->backfillFromSource('sales_till_session', 'sales_till_sessions', 'branch_id');
        $this->backfillViaParent('sales_till_movement', 'sales_till_movements', 'sales_till_session_id', 'sales_till_sessions', 'branch_id');
        $this->backfillViaParent('hr_staff_deduction', 'hr_staff_deductions', 'hr_staff_id', 'hr_staff', 'branch_id');
        $this->backfillPayrollRuns();
    }

    public function down(): void
    {
        Schema::table('finance_journal_lines', function (Blueprint $table): void {
            $table->dropIndex('finance_journal_lines_tenant_branch_idx');
            $table->dropConstrainedForeignId('branch_id');
        });
    }

    private function backfillFromSource(string $sourceType, string $sourceTable, string $branchColumn): void
    {
        if (! Schema::hasTable($sourceTable) || ! Schema::hasColumn($sourceTable, $branchColumn)) {
            return;
        }

        $entries = DB::table('finance_journal_entries')
            ->where('source_type', $sourceType)
            ->whereNotNull('source_id')
            ->get(['id', 'source_id']);
        $branchIds = DB::table($sourceTable)
            ->whereIn('id', $entries->pluck('source_id')->all())
            ->pluck($branchColumn, 'id');

        foreach ($entries as $entry) {
            $branchId = $branchIds->get($entry->source_id);

            if ($branchId) {
                DB::table('finance_journal_lines')->where('finance_journal_entry_id', $entry->id)->update(['branch_id' => $branchId]);
            }
        }
    }

    private function backfillViaParent(string $sourceType, string $sourceTable, string $parentColumn, string $parentTable, string $branchColumn): void
    {
        if (! Schema::hasTable($sourceTable) || ! Schema::hasColumn($sourceTable, $parentColumn) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $branchColumn)) {
            return;
        }

        $entries = DB::table('finance_journal_entries')
            ->where('source_type', $sourceType)
            ->whereNotNull('source_id')
            ->get(['id', 'source_id']);
        $parentIds = DB::table($sourceTable)
            ->whereIn('id', $entries->pluck('source_id')->all())
            ->pluck($parentColumn, 'id');
        $branchIds = DB::table($parentTable)
            ->whereIn('id', $parentIds->filter()->values()->all())
            ->pluck($branchColumn, 'id');

        foreach ($entries as $entry) {
            $parentId = $parentIds->get($entry->source_id);
            $branchId = $parentId ? $branchIds->get($parentId) : null;

            if ($branchId) {
                DB::table('finance_journal_lines')->where('finance_journal_entry_id', $entry->id)->update(['branch_id' => $branchId]);
            }
        }
    }

    private function backfillPayrollRuns(): void
    {
        if (! Schema::hasTable('hr_payroll_items') || ! Schema::hasColumn('hr_payroll_items', 'branch_id')) {
            return;
        }

        $entries = DB::table('finance_journal_entries')
            ->where('source_type', 'hr_payroll_run')
            ->whereNotNull('source_id')
            ->get(['id', 'source_id']);

        foreach ($entries as $entry) {
            $branchIds = DB::table('hr_payroll_items')
                ->where('hr_payroll_run_id', $entry->source_id)
                ->whereNotNull('branch_id')
                ->distinct()
                ->pluck('branch_id');

            if ($branchIds->count() === 1) {
                DB::table('finance_journal_lines')->where('finance_journal_entry_id', $entry->id)->update(['branch_id' => $branchIds->first()]);
            }
        }
    }
};
