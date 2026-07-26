<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->index(
                ['tenant_id', 'reference_type', 'reference_id'],
                'inventory_movements_source_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inventory_movements_source_index');
            $table->dropColumn('reference_id');
        });
    }
};
