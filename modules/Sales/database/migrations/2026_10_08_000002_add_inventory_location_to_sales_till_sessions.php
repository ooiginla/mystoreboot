<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_till_sessions', function (Blueprint $table): void {
            $table->foreignId('inventory_location_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'inventory_location_id', 'status'],
                'till_tenant_inventory_location_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('sales_till_sessions', function (Blueprint $table): void {
            $table->dropIndex('till_tenant_inventory_location_status_idx');
            $table->dropConstrainedForeignId('inventory_location_id');
        });
    }
};
