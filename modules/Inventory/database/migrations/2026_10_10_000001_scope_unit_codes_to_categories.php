<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->dropUnique('units_of_measure_tenant_id_code_unique');
            $table->unique(
                ['tenant_id', 'unit_category_id', 'code'],
                'units_measure_tenant_category_code_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->dropUnique('units_measure_tenant_category_code_unique');
            $table->unique(['tenant_id', 'code']);
        });
    }
};
