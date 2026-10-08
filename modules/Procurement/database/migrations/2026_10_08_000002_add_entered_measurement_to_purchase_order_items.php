<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('entered_quantity', 15, 4)->nullable()->after('quantity_ordered');
            $table->foreignId('entered_unit_id')
                ->nullable()
                ->after('entered_quantity')
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->string('entered_unit_code', 30)->nullable()->after('entered_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropForeign(['entered_unit_id']);
            $table->dropColumn(['entered_quantity', 'entered_unit_id', 'entered_unit_code']);
        });
    }
};
