<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->nullable();
            $table->text('description')->nullable();
            $table->string('collection_type', 30)->default('manual');
            $table->json('rules')->nullable();
            $table->string('badge_text', 40)->nullable();
            $table->string('badge_color', 20)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_visible', 'sort_order']);
        });

        Schema::create('product_collection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_collection_id')->constrained('product_collections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['product_collection_id', 'product_id'], 'product_collection_item_unique');
            $table->index(['product_id', 'product_collection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_collection_items');
        Schema::dropIfExists('product_collections');
    }
};
