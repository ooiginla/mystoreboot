<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The first local version used this name before supplier references and
        // storefront fallback images were separated into distinct concepts.
        if (Schema::hasTable('product_sourcing_links') && ! Schema::hasTable('product_supplier_references')) {
            Schema::rename('product_sourcing_links', 'product_supplier_references');
        }

        if (Schema::hasColumn('product_supplier_references', 'label')) {
            Schema::table('product_supplier_references', function (Blueprint $table): void {
                $table->dropColumn('label');
            });
        }

        Schema::create('product_external_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('alt_text', 180)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'sort_order'], 'product_external_images_product_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_external_images');
    }
};
