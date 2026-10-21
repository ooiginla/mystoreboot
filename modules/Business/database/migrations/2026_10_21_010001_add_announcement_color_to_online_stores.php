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
            $table->string('announcement_color', 7)->default('#000000')->after('announcement');
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            $table->dropColumn('announcement_color');
        });
    }
};
