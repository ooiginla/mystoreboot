<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_orders')
            ->where('order_status', 'draft')
            ->update(['order_status' => 'pending']);
    }

    public function down(): void
    {
        // Draft orders cannot be distinguished from genuine pending orders after migration.
    }
};
