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
            $table->boolean('maintenance_mode')->default(false)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            $table->dropColumn('maintenance_mode');
        });
    }
};
