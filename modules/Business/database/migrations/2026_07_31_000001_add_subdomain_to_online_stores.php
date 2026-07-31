<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            $table->string('subdomain', 63)->nullable()->unique()->after('username');
        });

        $reserved = collect(config('storefront.reserved_subdomains', []))
            ->map(fn (mixed $value): string => strtolower((string) $value))
            ->all();
        $used = [];

        DB::table('online_stores')
            ->select(['id', 'username'])
            ->orderBy('id')
            ->each(function (object $store) use (&$used, $reserved): void {
                $base = trim(Str::slug((string) $store->username), '-');
                $base = substr($base !== '' ? $base : 'store-'.$store->id, 0, 63);

                if (in_array($base, $reserved, true)) {
                    $base = substr('shop-'.$base, 0, 63);
                }

                $candidate = $base;
                $suffix = 2;

                while (in_array($candidate, $used, true)) {
                    $ending = '-'.$suffix++;
                    $candidate = substr($base, 0, 63 - strlen($ending)).$ending;
                }

                $used[] = $candidate;

                DB::table('online_stores')
                    ->where('id', $store->id)
                    ->update(['subdomain' => $candidate]);
            });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            $table->dropUnique(['subdomain']);
            $table->dropColumn('subdomain');
        });
    }
};
