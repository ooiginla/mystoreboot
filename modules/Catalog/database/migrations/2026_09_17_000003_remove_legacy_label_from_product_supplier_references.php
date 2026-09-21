<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_supplier_references', 'label')) {
            Schema::table('product_supplier_references', function (Blueprint $table): void {
                $table->dropColumn('label');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_supplier_references', 'label')) {
            Schema::table('product_supplier_references', function (Blueprint $table): void {
                $table->string('label', 120)->nullable()->after('product_id');
            });
        }
    }
};
