<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_settings', function (Blueprint $table): void {
            $table->boolean('auto_publish_products')->default(true)->change();
        });

        DB::table('reseller_settings')
            ->where('auto_publish_products', false)
            ->whereColumn('created_at', 'updated_at')
            ->update(['auto_publish_products' => true]);
    }

    public function down(): void
    {
        Schema::table('reseller_settings', function (Blueprint $table): void {
            $table->boolean('auto_publish_products')->default(false)->change();
        });
    }
};
