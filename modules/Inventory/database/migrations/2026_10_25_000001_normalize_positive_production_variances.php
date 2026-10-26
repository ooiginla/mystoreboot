<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_orders') || ! Schema::hasColumn('production_orders', 'variance_reason')) {
            return;
        }

        DB::table('production_orders')
            ->whereColumn('actual_yield_quantity', '>', 'planned_quantity')
            ->update(['variance_reason' => 'overproduction']);
    }

    public function down(): void
    {
        // This is a data correction: the previous, misleading loss reason cannot be reconstructed.
    }
};
