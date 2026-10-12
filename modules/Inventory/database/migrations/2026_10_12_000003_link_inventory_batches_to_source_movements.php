<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->foreignId('source_inventory_movement_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('inventory_movements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_inventory_movement_id');
        });
    }
};
