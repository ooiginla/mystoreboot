<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6a: a restaurant check IS a sales order, extended with the dine-in dimension.
 *
 * Everything after "guest asks for the bill" — payments, part-payments, change, receipts,
 * till sessions, COGS, GL posting, returns, refunds — already lives on sales_orders and is
 * tested. A parallel `restaurant_checks` table would duplicate that whole stack and drift.
 *
 * `service_area_id IS NULL` means "ordinary retail order": every existing code path keeps
 * behaving exactly as it does today, so the supermarket till is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('service_area_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->after('service_area_id')->constrained()->nullOnDelete();
            $table->foreignId('server_user_id')->nullable()->after('restaurant_table_id')->constrained('users')->nullOnDelete();
            // Guests seated — drives cover averages, not just a display field.
            $table->unsignedSmallInteger('cover_count')->nullable()->after('server_user_id');
            $table->timestamp('opened_at')->nullable()->after('cover_count');
            // open | bill_printed | settled | voided. Null for retail orders.
            $table->string('check_status', 32)->nullable()->after('opened_at')->index();
            $table->unsignedBigInteger('service_charge_minor')->default(0)->after('tax_minor');
            // Snapshot: rates change, old bills must not silently re-rate.
            $table->decimal('service_charge_rate', 5, 2)->nullable()->after('service_charge_minor');
            $table->foreignId('parent_sales_order_id')->nullable()->after('check_status')->constrained('sales_orders')->nullOnDelete();

            $table->index(['tenant_id', 'check_status'], 'sales_orders_tenant_check_status_idx');
        });

        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->unsignedTinyInteger('course')->default(1)->after('sku');
            $table->unsignedSmallInteger('seat_number')->nullable()->after('course');
            // Null means "still on the pad" — not yet sent to the kitchen, freely editable.
            // This column is the pivot of the whole phase: it gates stock depletion, and it
            // is what separates a free cancel from a void that must be written off as waste.
            $table->timestamp('fired_at')->nullable()->after('seat_number');
            $table->timestamp('voided_at')->nullable()->after('fired_at');
            $table->string('void_reason', 160)->nullable()->after('voided_at');
            $table->foreignId('voided_by_user_id')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();

            $table->index(['tenant_id', 'fired_at'], 'sales_order_items_tenant_fired_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->dropIndex('sales_order_items_tenant_fired_idx');
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropColumn(['course', 'seat_number', 'fired_at', 'voided_at', 'void_reason']);
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex('sales_orders_tenant_check_status_idx');
            $table->dropConstrainedForeignId('parent_sales_order_id');
            $table->dropConstrainedForeignId('server_user_id');
            $table->dropConstrainedForeignId('restaurant_table_id');
            $table->dropConstrainedForeignId('service_area_id');
            $table->dropColumn(['cover_count', 'opened_at', 'check_status', 'service_charge_minor', 'service_charge_rate']);
        });
    }
};
