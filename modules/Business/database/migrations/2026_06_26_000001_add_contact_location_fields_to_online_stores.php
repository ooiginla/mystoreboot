<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            if (! Schema::hasColumn('online_stores', 'city')) {
                $table->string('city', 120)->nullable()->after('address');
            }

            if (! Schema::hasColumn('online_stores', 'state')) {
                $table->string('state', 120)->nullable()->after('city');
            }

            if (! Schema::hasColumn('online_stores', 'country')) {
                $table->string('country', 120)->nullable()->after('state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            foreach (['country', 'state', 'city'] as $column) {
                if (Schema::hasColumn('online_stores', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
