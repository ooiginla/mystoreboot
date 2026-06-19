<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_stores', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fulfilment_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('username', 80)->unique();
            $table->string('store_name', 160);
            $table->string('logo_path', 255)->nullable();
            $table->string('hero_image_path', 255)->nullable();
            $table->string('hero_image_text', 120)->nullable();
            $table->string('hero_image_description', 255)->nullable();
            $table->string('hero_image_tag', 80)->nullable();
            $table->text('address')->nullable();
            $table->string('site_email', 160)->nullable();
            $table->string('store_phone', 40)->nullable();
            $table->string('store_whatsapp', 40)->nullable();
            $table->text('announcement')->nullable();
            $table->string('theme_primary_color', 20)->default('#006554');
            $table->string('theme_secondary_color', 20)->default('#f59e0b');
            $table->json('payment_methods')->nullable();
            $table->json('payment_settings')->nullable();
            $table->json('bank_accounts')->nullable();
            $table->json('shipping_options')->nullable();
            $table->json('social_accounts')->nullable();
            $table->json('pages')->nullable();
            $table->json('faqs')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index(['tenant_id', 'fulfilment_branch_id'], 'online_store_tenant_branch_idx');
        });

        Schema::create('online_store_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('online_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['online_store_id', 'product_category_id'], 'online_store_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_store_categories');
        Schema::dropIfExists('online_stores');
    }
};
