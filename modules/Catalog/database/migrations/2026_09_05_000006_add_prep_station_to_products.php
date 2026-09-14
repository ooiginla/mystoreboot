<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0, Workstream C: which kitchen arm makes a product. Nullable — single-arm
 * tenants leave it null and depletion falls back to the sales point's store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('prep_station_id')->nullable()->after('category_id')
                ->constrained('prep_stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prep_station_id');
        });
    }
};
