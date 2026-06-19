<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 140);
            $table->string('code', 50)->nullable();
            $table->string('location_type', 32)->default(InventoryLocationType::Branch->value)->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::create('inventory_stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->unsignedBigInteger('average_cost_minor')->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['inventory_location_id', 'product_variant_id'], 'stock_level_location_variant_unique');
            $table->index(['tenant_id', 'product_variant_id'], 'stock_levels_tenant_variant_index');
            $table->index(['tenant_id', 'inventory_location_id'], 'stock_levels_tenant_location_index');
            $table->index(['tenant_id', 'quantity_on_hand'], 'stock_levels_tenant_qty_index');
        });

        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 120)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('stock_condition', 32)->default(StockCondition::Sellable->value)->index();
            $table->integer('quantity_remaining')->default(0);
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'expiry_date']);
            $table->index(['tenant_id', 'stock_condition']);
            $table->index(['tenant_id', 'product_variant_id'], 'batches_tenant_variant_index');
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type', 32)->default(InventoryMovementType::StockIn->value)->index();
            $table->string('stock_condition', 32)->default(StockCondition::Sellable->value)->index();
            $table->integer('quantity');
            $table->integer('stock_after')->default(0);
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->unsignedBigInteger('movement_value_minor')->default(0);
            $table->string('batch_number', 120)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'movement_type', 'occurred_at'], 'movements_tenant_type_date_index');
            $table->index(['tenant_id', 'inventory_location_id', 'occurred_at'], 'movements_tenant_location_date_index');
            $table->index(['tenant_id', 'product_variant_id', 'occurred_at'], 'movements_tenant_variant_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('inventory_stock_levels');
        Schema::dropIfExists('inventory_locations');
    }
};
