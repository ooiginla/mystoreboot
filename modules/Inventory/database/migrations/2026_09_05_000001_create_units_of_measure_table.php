<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Enums\UnitDimension;

/**
 * Units of measure (Phase 0, Workstream A). Every product's stock is kept in its
 * base unit; other units convert to the base via `to_base_factor` within the same
 * dimension (e.g. kg → g uses factor 1000). Units that are only meaningful per
 * product (pack, carton) carry a null factor and rely on the variant's own
 * purchase-to-base factor instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->string('dimension', 16)->default(UnitDimension::Count->value)->index();
            // How many base units one of this unit equals. Null = not universally
            // convertible (pack/carton/box) — depends on the product.
            $table->decimal('to_base_factor', 18, 6)->nullable();
            $table->boolean('is_base_for_dimension')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'dimension', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
