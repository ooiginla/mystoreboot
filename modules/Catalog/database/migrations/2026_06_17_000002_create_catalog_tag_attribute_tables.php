<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_attribute_value_product');
        Schema::dropIfExists('product_product_tag');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attribute_definitions');
        Schema::dropIfExists('product_tags');

        Schema::create('product_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('product_attribute_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_attribute_definition_id');
            $table->string('value', 140);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_attribute_definition_id', 'value'], 'product_attribute_value_unique');
            $table->foreign('product_attribute_definition_id', 'prod_attr_values_definition_fk')
                ->references('id')
                ->on('product_attribute_definitions')
                ->cascadeOnDelete();
        });

        Schema::create('product_product_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('product_tag_id');
            $table->timestamps();

            $table->unique(['product_id', 'product_tag_id']);
            $table->foreign('product_id', 'prod_tag_product_fk')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_tag_id', 'prod_tag_tag_fk')->references('id')->on('product_tags')->cascadeOnDelete();
        });

        Schema::create('product_attribute_value_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('product_attribute_value_id');
            $table->timestamps();

            $table->unique(['product_id', 'product_attribute_value_id'], 'product_attribute_value_product_unique');
            $table->foreign('product_id', 'prod_attr_product_fk')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_attribute_value_id', 'prod_attr_value_fk')->references('id')->on('product_attribute_values')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_value_product');
        Schema::dropIfExists('product_product_tag');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attribute_definitions');
        Schema::dropIfExists('product_tags');
    }
};
