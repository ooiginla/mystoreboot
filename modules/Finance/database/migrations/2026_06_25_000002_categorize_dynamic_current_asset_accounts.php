<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('finance_accounts')
            ->where('type', 'asset')
            ->where('code', 'like', 'BANK-%')
            ->update([
                'category' => 'Current Assets',
                'description' => 'Business bank account used to hold cash and receive payments.',
            ]);

        DB::table('finance_accounts')
            ->where('type', 'asset')
            ->where('code', 'like', 'BV-%')
            ->update([
                'category' => 'Current Assets',
                'description' => 'Cash held in a branch safe vault.',
            ]);

        DB::table('finance_accounts')
            ->where('type', 'asset')
            ->where('code', 'like', 'CT-%')
            ->update([
                'category' => 'Current Assets',
                'description' => 'Cash held in a cashier till for point-of-sale transactions.',
            ]);
    }
};
