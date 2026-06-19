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
            if (! Schema::hasColumn('online_stores', 'hero_image_text')) {
                $table->string('hero_image_text', 120)->nullable()->after('hero_image_path');
            }

            if (! Schema::hasColumn('online_stores', 'hero_image_description')) {
                $table->string('hero_image_description', 255)->nullable()->after('hero_image_text');
            }

            if (! Schema::hasColumn('online_stores', 'hero_image_tag')) {
                $table->string('hero_image_tag', 80)->nullable()->after('hero_image_description');
            }

            if (! Schema::hasColumn('online_stores', 'store_whatsapp')) {
                $table->string('store_whatsapp', 40)->nullable()->after('store_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            if (Schema::hasColumn('online_stores', 'store_whatsapp')) {
                $table->dropColumn('store_whatsapp');
            }

            if (Schema::hasColumn('online_stores', 'hero_image_tag')) {
                $table->dropColumn('hero_image_tag');
            }

            if (Schema::hasColumn('online_stores', 'hero_image_description')) {
                $table->dropColumn('hero_image_description');
            }

            if (Schema::hasColumn('online_stores', 'hero_image_text')) {
                $table->dropColumn('hero_image_text');
            }
        });
    }
};
