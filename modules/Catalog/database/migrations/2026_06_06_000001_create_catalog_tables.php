<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('category_type', 32)->default(CategoryType::Product->value)->index();
            $table->string('name', 140);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'parent_id', 'status']);
            $table->index(['tenant_id', 'category_type', 'status']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name', 180);
            $table->string('slug', 200);
            $table->string('brand', 120)->nullable()->index();
            $table->string('product_type', 32)->default(ProductType::Product->value)->index();
            $table->text('description')->nullable();
            $table->boolean('has_variants')->default(false)->index();
            $table->unsignedBigInteger('base_price_minor')->default(0);
            $table->unsignedBigInteger('base_cost_price_minor')->default(0);
            $table->unsignedBigInteger('discount_price_minor')->nullable();
            $table->string('tax_behavior', 32)->default(TaxBehavior::Taxable->value)->index();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('status', 32)->default(ProductStatus::Active->value)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'product_type', 'status']);
            $table->index(['tenant_id', 'category_id', 'status']);
            $table->index(['tenant_id', 'brand']);
        });

        Schema::create('product_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('value', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_option_id', 'value']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('variant_name', 220);
            $table->string('sku', 120);
            $table->string('barcode', 120)->nullable();
            $table->unsignedBigInteger('selling_price_minor')->default(0);
            $table->unsignedBigInteger('cost_price_minor')->default(0);
            $table->unsignedBigInteger('compare_at_price_minor')->nullable();
            $table->unsignedBigInteger('discount_price_minor')->nullable();
            $table->string('tax_behavior', 32)->default(TaxBehavior::Taxable->value)->index();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('status', 32)->default(ProductStatus::Active->value)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'product_id', 'status']);
        });

        Schema::create('product_variant_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_variant_id', 'product_option_value_id'], 'variant_option_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
