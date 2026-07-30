<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_till_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_payment_account_id')->nullable()->constrained()->nullOnDelete();
            $table->date('refund_date');
            $table->string('payment_method', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference_number', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sales_order_id', 'refund_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_refunds');
    }
};
