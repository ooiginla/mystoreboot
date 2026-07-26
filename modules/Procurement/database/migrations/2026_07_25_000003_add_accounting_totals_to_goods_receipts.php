<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('subtotal_minor')->default(0)->after('delivery_status');
            $table->unsignedBigInteger('tax_minor')->default(0)->after('subtotal_minor');
            $table->unsignedBigInteger('shipping_minor')->default(0)->after('tax_minor');
            $table->unsignedBigInteger('total_minor')->default(0)->after('shipping_minor');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropColumn(['subtotal_minor', 'tax_minor', 'shipping_minor', 'total_minor']);
        });
    }
};
