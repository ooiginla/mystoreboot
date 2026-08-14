<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stamp the payout mode in force when each online payment was collected, plus a settled_at
 * timestamp. This makes the settlement report a permanent, mode-aware audit trail that
 * survives any number of payout-mode switches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_collected_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('online_collected_payments', 'payout_mode')) {
                $table->string('payout_mode', 40)->nullable()->index();
            }
            if (! Schema::hasColumn('online_collected_payments', 'settled_at')) {
                $table->timestamp('settled_at')->nullable();
            }
        });

        // Existing collections predate wallet modes, so they were all direct-settle.
        DB::table('online_collected_payments')->whereNull('payout_mode')->update(['payout_mode' => 'auto_subaccount']);
        DB::table('online_collected_payments')->where('is_settled', true)->whereNull('settled_at')
            ->update(['settled_at' => DB::raw('verified_at')]);
    }

    public function down(): void
    {
        Schema::table('online_collected_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('online_collected_payments', 'payout_mode')) {
                $table->dropColumn('payout_mode');
            }
            if (Schema::hasColumn('online_collected_payments', 'settled_at')) {
                $table->dropColumn('settled_at');
            }
        });
    }
};
