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
        Schema::create('global_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'key']);
        });

        DB::table('global_configs')->insert([
            [
                'tenant_id' => null,
                'key' => 'ONLINE_STOREBOOT_CHARGE',
                'value' => json_encode([
                    'percentage_rate' => 1.5,
                    'fixed_amount_minor' => 100000,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => null,
                'key' => 'PAYMENT_GATEWAY_CHARGE',
                'value' => json_encode([
                    'percentage_rate' => 1.5,
                    'fixed_amount_minor' => 10000,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('global_configs');
    }
};
