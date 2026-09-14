<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: lot traceability. Until now batches only ever accumulated — nothing consumed
 * them. Outbound movements now draw down batches FEFO, and each allocation is recorded
 * here so a movement can be traced back to the exact lots it took.
 *
 * One movement can span several batches, so this is a pivot rather than a column on
 * inventory_movements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movement_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete();
            // Always positive: the amount this movement drew from (or added to) that batch.
            $table->decimal('quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'inventory_movement_id'], 'movement_batches_tenant_movement_idx');
            $table->index(['tenant_id', 'inventory_batch_id'], 'movement_batches_tenant_batch_idx');
        });

        // FEFO reads batches by location + variant ordered on expiry; index that path.
        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'inventory_location_id', 'product_variant_id', 'expiry_date'],
                'batches_fefo_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->dropIndex('batches_fefo_idx');
        });

        Schema::dropIfExists('inventory_movement_batches');
    }
};
