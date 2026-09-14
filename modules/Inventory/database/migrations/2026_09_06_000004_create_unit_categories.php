<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unit categories: a named set of measurement units with one base and multipliers,
 * so "carton" can mean different amounts for different goods (a carton of biscuits
 * vs. a carton of drinks). Existing units are moved into a default "General"
 * category. A product is later assigned a category and stocks in its base unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->foreignId('unit_category_id')->nullable()->after('tenant_id')
                ->constrained('unit_categories')->nullOnDelete();
        });

        // Backfill: give every tenant that has units a "General" category and move
        // their existing units into it.
        DB::table('units_of_measure')->distinct()->pluck('tenant_id')->each(function ($tenantId): void {
            $tenantId = (string) $tenantId;
            $categoryId = DB::table('unit_categories')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => 'General',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('units_of_measure')->where('tenant_id', $tenantId)->update(['unit_category_id' => $categoryId]);
        });
    }

    public function down(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_category_id');
        });

        Schema::dropIfExists('unit_categories');
    }
};
