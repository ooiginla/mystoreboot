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
            if (! Schema::hasColumn('online_stores', 'slides')) {
                $table->json('slides')->nullable()->after('hero_image_tag');
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            if (Schema::hasColumn('online_stores', 'slides')) {
                $table->dropColumn('slides');
            }
        });
    }
};
