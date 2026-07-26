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
        Schema::create('tenant_module_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('billable_modules')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_id']);
        });

        $now = now();
        DB::table('billable_modules')->insertOrIgnore([
            'name' => 'Retail POS',
            'slug' => 'retail-pos',
            'description' => 'Live counter selling with till opening, closing, and reconciliation.',
            'is_core' => false,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $retailPosId = DB::table('billable_modules')->where('slug', 'retail-pos')->value('id');
        if ($retailPosId) {
            DB::table('plans')->pluck('id')->each(function (int $planId) use ($retailPosId, $now): void {
                DB::table('plan_module_entitlements')->insertOrIgnore([
                    'plan_id' => $planId,
                    'module_id' => $retailPosId,
                    'is_enabled' => true,
                    'limits' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_entitlements');
        DB::table('plan_module_entitlements')
            ->where('module_id', DB::table('billable_modules')->where('slug', 'retail-pos')->value('id'))
            ->delete();
        DB::table('billable_modules')->where('slug', 'retail-pos')->delete();
    }
};
