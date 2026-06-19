<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Procurement\Enums\PaymentStatus;
use Modules\Procurement\Enums\PurchaseOrderStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('code', 60)->nullable();
            $table->string('contact_name', 140)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('phone', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('po_number', 80);
            $table->string('status', 32)->default(PurchaseOrderStatus::PendingApproval->value)->index();
            $table->string('payment_status', 32)->default(PaymentStatus::Unpaid->value)->index();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('shipping_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'po_number']);
            $table->index(['tenant_id', 'vendor_id', 'status']);
            $table->index(['tenant_id', 'expected_delivery_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->string('vendor_sku', 120)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'product_variant_id']);
            $table->index(['tenant_id', 'inventory_location_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 80);
            $table->date('received_at');
            $table->string('delivery_status', 32)->default('received')->index();
            $table->string('reference_number', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'purchase_order_id']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_received');
            $table->string('batch_number', 120)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date');
            $table->unsignedBigInteger('amount_minor');
            $table->string('payment_method', 80)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vendor_id', 'payment_date']);
            $table->index(['tenant_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('vendors');
    }
};
