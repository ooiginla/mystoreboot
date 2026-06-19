<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Sales\Enums\DiscountType;
use Modules\Sales\Enums\ReturnStatus;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('discount_type', 32)->default(DiscountType::Amount->value);
            $table->unsignedBigInteger('discount_value_minor')->default(0);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 80);
            $table->string('invoice_number', 80);
            $table->string('receipt_number', 80);
            $table->string('order_status', 40)->default(SalesOrderStatus::Completed->value)->index();
            $table->string('payment_status', 40)->default(SalesPaymentStatus::Unpaid->value)->index();
            $table->date('order_date');
            $table->boolean('is_credit_sale')->default(false)->index();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('shipping_minor')->default(0);
            $table->unsignedBigInteger('coupon_discount_minor')->default(0);
            $table->unsignedBigInteger('admin_discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->unsignedBigInteger('change_due_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->string('payment_method', 80)->nullable();
            $table->string('delivery_method', 120)->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->unique(['tenant_id', 'invoice_number']);
            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'branch_id', 'order_date']);
            $table->index(['tenant_id', 'customer_id', 'payment_status']);
        });

        Schema::create('sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('item_name', 240);
            $table->string('sku', 120)->nullable();
            $table->integer('quantity');
            $table->integer('quantity_returned')->default(0);
            $table->unsignedBigInteger('unit_price_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('sales_order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->string('payment_method', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference_number', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sales_order_id', 'payment_date']);
        });

        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('return_number', 80);
            $table->date('return_date');
            $table->string('status', 32)->default(ReturnStatus::Approved->value);
            $table->unsignedBigInteger('refund_minor')->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'return_number']);
        });

        Schema::create('sales_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->unsignedBigInteger('refund_minor')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('sales_order_payments');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_coupons');
    }
};
