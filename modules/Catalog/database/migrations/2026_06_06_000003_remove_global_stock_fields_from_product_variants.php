<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            if (Schema::hasColumn('product_variants', 'stock_quantity')) {
                $table->dropIndex(['tenant_id', 'stock_quantity']);
                $table->dropColumn('stock_quantity');
            }

            if (Schema::hasColumn('product_variants', 'reorder_level')) {
                $table->dropIndex(['tenant_id', 'reorder_level']);
                $table->dropColumn('reorder_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_variants', 'stock_quantity')) {
                $table->integer('stock_quantity')->default(0)->after('discount_price_minor');
                $table->index(['tenant_id', 'stock_quantity']);
            }

            if (! Schema::hasColumn('product_variants', 'reorder_level')) {
                $table->integer('reorder_level')->default(0)->after('stock_quantity');
                $table->index(['tenant_id', 'reorder_level']);
            }
        });
    }
};
