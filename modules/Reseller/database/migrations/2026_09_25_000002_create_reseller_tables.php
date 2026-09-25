<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Reseller\Enums\PricingMode;
use Modules\Reseller\Enums\ProductAvailability;
use Modules\Reseller\Enums\ScanStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('pricing_mode', 24)->default(PricingMode::Percentage->value);
            $table->unsignedInteger('percentage_markup_basis_points')->default(1000);
            $table->unsignedBigInteger('fixed_markup_minor')->default(0);
            $table->boolean('auto_publish_products')->default(false);
            $table->string('scan_frequency', 24)->default('daily');
            $table->boolean('show_source_store')->default(true);
            $table->unsignedInteger('stale_after_hours')->default(36);
            $table->unsignedInteger('hide_after_missing_scans')->default(3);
            $table->timestamps();
        });

        Schema::create('reseller_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('website_url', 2048);
            $table->char('website_url_hash', 64);
            $table->string('logo_url', 2048)->nullable();
            $table->string('contact_email', 160)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('scan_frequency', 24)->nullable();
            $table->json('scan_configuration')->nullable();
            $table->boolean('auto_publish_products')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('last_scan_status', 24)->default(ScanStatus::NeverRun->value);
            $table->text('last_scan_message')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'website_url_hash'], 'reseller_supplier_tenant_url_unique');
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('reseller_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('reseller_suppliers')->cascadeOnDelete();
            $table->string('product_url', 2048);
            $table->char('product_url_hash', 64);
            $table->string('source_product_reference', 255)->nullable();
            $table->string('name', 255);
            $table->string('main_image_url', 2048)->nullable();
            $table->unsignedBigInteger('source_price_minor');
            $table->unsignedBigInteger('source_previous_price_minor')->nullable();
            $table->unsignedBigInteger('selling_price_minor');
            $table->unsignedBigInteger('selling_previous_price_minor')->nullable();
            $table->char('currency_code', 3);
            $table->string('availability', 24)->default(ProductAvailability::Unknown->value);
            $table->text('short_description')->nullable();
            $table->string('brand', 160)->nullable();
            $table->string('category', 160)->nullable();
            $table->string('sku', 160)->nullable();
            $table->json('variants')->nullable();
            $table->string('source_store_name', 160);
            $table->boolean('is_visible')->default(false);
            $table->boolean('is_excluded')->default(false);
            $table->timestamp('last_checked_at');
            $table->unsignedInteger('missing_scan_count')->default(0);
            $table->string('content_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'supplier_id', 'product_url_hash'], 'reseller_product_source_unique');
            $table->index(['tenant_id', 'is_visible', 'availability'], 'reseller_product_visibility_idx');
            $table->index(['tenant_id', 'supplier_id']);
        });

        Schema::create('reseller_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('order_reference', 64);
            $table->string('customer_name', 160);
            $table->string('customer_email', 160)->nullable();
            $table->string('customer_phone', 40);
            $table->json('delivery_address');
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('delivery_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('payment_status', 32)->default('pending');
            $table->string('order_status', 32)->default('pending');
            $table->string('fulfilment_status', 32)->default('unfulfilled');
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_reference']);
            $table->index(['tenant_id', 'order_status']);
        });

        Schema::create('reseller_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_order_id')->constrained('reseller_orders')->cascadeOnDelete();
            $table->foreignId('reseller_product_id')->nullable()->constrained('reseller_products')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('reseller_suppliers')->nullOnDelete();
            $table->string('product_name', 255);
            $table->string('source_store_name', 160);
            $table->string('product_url', 2048);
            $table->string('image_url', 2048)->nullable();
            $table->string('sku', 160)->nullable();
            $table->json('variant')->nullable();
            $table->unsignedBigInteger('source_price_minor');
            $table->unsignedInteger('percentage_markup_basis_points')->default(0);
            $table->unsignedBigInteger('fixed_markup_minor')->default(0);
            $table->unsignedBigInteger('unit_selling_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->string('fulfilment_status', 32)->default('unfulfilled');
            $table->string('tracking_reference', 160)->nullable();
            $table->string('tracking_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'reseller_order_id']);
            $table->index(['tenant_id', 'supplier_id']);
        });

        Schema::create('reseller_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_order_id')->constrained('reseller_orders')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('provider_reference', 160);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('type', 24)->default('payment');
            $table->string('status', 24)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'provider_reference'], 'reseller_payment_provider_unique');
            $table->index(['tenant_id', 'reseller_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_payments');
        Schema::dropIfExists('reseller_order_items');
        Schema::dropIfExists('reseller_orders');
        Schema::dropIfExists('reseller_products');
        Schema::dropIfExists('reseller_suppliers');
        Schema::dropIfExists('reseller_settings');
    }
};
