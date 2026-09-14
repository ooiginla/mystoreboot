<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot traceability across stores. When a transfer mirrors a lot into the destination
 * location it creates a *new* batch row; without this link the chain breaks at the
 * store boundary and a recall cannot follow stock past its first move.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->foreignId('source_inventory_batch_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('inventory_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_inventory_batch_id');
        });
    }
};
