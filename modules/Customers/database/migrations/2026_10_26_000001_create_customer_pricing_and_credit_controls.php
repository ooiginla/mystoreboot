<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_price_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('code', 80);
            $table->boolean('is_active')->default(true)->index();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('allow_additional_discounts')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('customer_price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_price_list_id')->constrained('customer_price_lists')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('minimum_quantity', 15, 4)->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->timestamps();

            $table->unique(
                ['customer_price_list_id', 'product_variant_id', 'minimum_quantity'],
                'customer_price_list_tier_unique',
            );
            $table->index(['tenant_id', 'product_variant_id'], 'customer_price_list_variant_idx');
        });

        Schema::create('customer_specific_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('minimum_quantity', 15, 4)->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('allow_additional_discounts')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['customer_id', 'product_variant_id', 'minimum_quantity'],
                'customer_specific_price_tier_unique',
            );
            $table->index(['tenant_id', 'product_variant_id'], 'customer_specific_price_variant_idx');
        });

        Schema::table('customer_groups', function (Blueprint $table): void {
            $table->foreignId('customer_price_list_id')->nullable()->after('tenant_id')
                ->constrained('customer_price_lists')->nullOnDelete();
            $table->boolean('credit_enabled')->default(false)->after('description');
            $table->unsignedBigInteger('credit_limit_minor')->nullable()->after('credit_enabled');
            $table->unsignedSmallInteger('credit_terms_days')->nullable()->after('credit_limit_minor');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('customer_price_list_id')->nullable()->after('customer_group_id')
                ->constrained('customer_price_lists')->nullOnDelete();
            // Keep existing credit-sale behaviour for records created outside the CRM
            // form; the UI can deliberately choose group inheritance or disable credit.
            $table->string('credit_policy', 20)->default('enabled')->after('account_balance_minor');
            $table->unsignedBigInteger('credit_limit_minor')->nullable()->after('credit_policy');
            $table->unsignedSmallInteger('credit_terms_days')->nullable()->after('credit_limit_minor');
            $table->boolean('credit_hold')->default(false)->after('credit_terms_days');
        });

        // Preserve the existing behaviour for customers already allowed to make credit
        // purchases. Walk-in remains explicitly unable to carry a receivable balance.
        DB::table('customers')->where('phone', '!=', 'WALK-IN')->update(['credit_policy' => 'enabled']);
        DB::table('customers')->where('phone', 'WALK-IN')->update(['credit_policy' => 'disabled']);

        Schema::create('customer_balance_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('adjustment_date');
            $table->string('direction', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->bigInteger('balance_before_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('reason', 240);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'adjustment_date'], 'customer_balance_adjustment_idx');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->date('credit_due_date')->nullable()->after('is_credit_sale');
            $table->unsignedBigInteger('credit_limit_snapshot_minor')->nullable()->after('credit_due_date');
            $table->foreignId('credit_overridden_by')->nullable()->after('credit_limit_snapshot_minor')
                ->constrained('users')->nullOnDelete();
            $table->text('credit_override_reason')->nullable()->after('credit_overridden_by');
        });

        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('base_unit_price_minor')->default(0)->after('unit_price_minor');
            $table->string('pricing_source', 40)->default('retail')->after('base_unit_price_minor');
            $table->string('pricing_label', 140)->default('Retail')->after('pricing_source');
            $table->unsignedBigInteger('pricing_reference_id')->nullable()->after('pricing_label');
            $table->decimal('price_tier_minimum_quantity', 15, 4)->default(1)->after('pricing_reference_id');
        });

        DB::table('sales_order_items')->update([
            'base_unit_price_minor' => DB::raw('unit_price_minor'),
            'pricing_source' => 'retail',
            'pricing_label' => 'Retail',
            'price_tier_minimum_quantity' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'base_unit_price_minor', 'pricing_source', 'pricing_label',
                'pricing_reference_id', 'price_tier_minimum_quantity',
            ]);
        });
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credit_overridden_by');
            $table->dropColumn(['credit_due_date', 'credit_limit_snapshot_minor', 'credit_override_reason']);
        });
        Schema::dropIfExists('customer_balance_adjustments');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_price_list_id');
            $table->dropColumn(['credit_policy', 'credit_limit_minor', 'credit_terms_days', 'credit_hold']);
        });
        Schema::table('customer_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_price_list_id');
            $table->dropColumn(['credit_enabled', 'credit_limit_minor', 'credit_terms_days']);
        });
        Schema::dropIfExists('customer_specific_prices');
        Schema::dropIfExists('customer_price_list_items');
        Schema::dropIfExists('customer_price_lists');
    }
};
