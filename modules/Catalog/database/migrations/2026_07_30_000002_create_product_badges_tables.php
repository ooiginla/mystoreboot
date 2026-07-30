<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('slug', 60);
            $table->string('background_color', 7)->default('#111827');
            $table->string('text_color', 7)->default('#ffffff');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_visible', 'sort_order']);
        });

        Schema::create('product_badge_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_badge_id')->constrained('product_badges')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_badge_product');
        Schema::dropIfExists('product_badges');
    }
};
