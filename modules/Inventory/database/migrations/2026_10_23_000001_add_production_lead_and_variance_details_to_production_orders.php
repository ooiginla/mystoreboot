<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->foreignId('production_lead_user_id')
                ->nullable()
                ->after('output_location_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('variance_reason', 40)->nullable()->after('actual_yield_quantity');
            $table->unsignedBigInteger('variance_value_minor')->default(0)->after('variance_reason');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_lead_user_id');
            $table->dropColumn(['variance_reason', 'variance_value_minor']);
        });
    }
};
