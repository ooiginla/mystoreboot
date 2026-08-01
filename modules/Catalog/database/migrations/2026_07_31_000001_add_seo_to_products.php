<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Cached AI-enriched SEO metadata: meta_title, meta_description,
            // keywords[], tags[], image_alt, generated_at. The storefront always
            // renders SEO directly from live product data as a fallback, so this
            // is an enrichment layer, never a hard dependency.
            // This migration runs before custom_fields is introduced. Do not
            // position this column relative to a column that does not exist yet
            // on a fresh/production database.
            $table->json('seo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('seo');
        });
    }
};
