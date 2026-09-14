<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // A required group blocks the item from going on the check until chosen.
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('min_select')->default(0);
            // Null means no upper limit — "any toppings".
            $table->unsignedTinyInteger('max_select')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('modifier_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // Signed: "extra meat" adds money, "half portion" can take it off.
            $table->bigInteger('price_delta_minor')->default(0);
            // Optional effect on ingredients, per one of the item sold. Negative removes
            // ("no onions"), positive adds ("extra chicken").
            $table->foreignId('component_product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('component_quantity', 15, 4)->default(0);
            $table->foreignId('component_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('modifier_group_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['modifier_group_id', 'product_id']);
        });

        Schema::create('sales_order_item_modifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained()->cascadeOnDelete();
            // Snapshot of what was chosen: renaming or deleting an option later must
            // not rewrite a bill that has already been printed or paid.
            $table->foreignId('modifier_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name', 120);
            $table->string('option_name', 120);
            $table->bigInteger('price_delta_minor')->default(0);
            $table->foreignId('component_product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('component_quantity', 15, 4)->default(0);
            $table->foreignId('component_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'sales_order_item_id'], 'soi_modifiers_tenant_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_item_modifiers');
        Schema::dropIfExists('modifier_group_product');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
    }
};
