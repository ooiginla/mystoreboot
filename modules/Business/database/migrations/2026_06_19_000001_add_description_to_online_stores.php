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
            if (! Schema::hasColumn('online_stores', 'description')) {
                $table->text('description')->nullable()->after('store_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            if (Schema::hasColumn('online_stores', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
