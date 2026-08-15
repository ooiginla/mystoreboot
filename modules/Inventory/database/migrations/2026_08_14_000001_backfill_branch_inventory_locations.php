<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('branches')
            ->where('status', 'active')
            ->orderBy('id')
            ->each(function (object $branch): void {
                $location = DB::table('inventory_locations')
                    ->where('tenant_id', $branch->tenant_id)
                    ->where('branch_id', $branch->id)
                    ->orderBy('id')
                    ->first();

                if ($location) {
                    DB::table('inventory_locations')
                        ->where('id', $location->id)
                        ->update([
                            'status' => 'active',
                            'updated_at' => now(),
                        ]);

                    return;
                }

                $code = filled($branch->code) && ! DB::table('inventory_locations')
                    ->where('tenant_id', $branch->tenant_id)
                    ->where('code', $branch->code)
                    ->exists()
                        ? $branch->code
                        : null;

                DB::table('inventory_locations')->insert([
                    'tenant_id' => $branch->tenant_id,
                    'branch_id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $code,
                    'location_type' => 'branch',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Keep operational inventory locations; they may contain stock history.
    }
};
