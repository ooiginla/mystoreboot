<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: multi-item stock requisitions. A destination store requests stock from a
 * source store; once approved and fulfilled, stock transfers between the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('requisition_number', 120);
            $table->foreignId('source_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('status', 32)->default('submitted')->index();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'requisition_number']);
            $table->index(['tenant_id', 'status'], 'requisitions_tenant_status_idx');
        });

        Schema::create('stock_requisition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('requested_quantity', 15, 4)->default(0);
            $table->decimal('fulfilled_quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'stock_requisition_id'], 'requisition_items_tenant_req_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requisition_items');
        Schema::dropIfExists('stock_requisitions');
    }
};
