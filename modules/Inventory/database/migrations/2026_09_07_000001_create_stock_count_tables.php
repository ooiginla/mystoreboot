<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: stock-take sessions. Opening a count snapshots what the system believes is
 * on hand at a location; counters then enter what they physically found. Posting the
 * count writes adjustment movements for every line that differs, so the variance is
 * both visible and reflected in stock + the GL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('count_number', 120);
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('status', 32)->default('counting')->index();
            // Blind counts hide the system quantity from the counter until review.
            $table->boolean('is_blind')->default(true);
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'count_number']);
            $table->index(['tenant_id', 'status'], 'stock_counts_tenant_status_idx');
        });

        Schema::create('stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            // What the system said was on hand when the count was opened.
            $table->decimal('system_quantity', 15, 4)->default(0);
            // Null until somebody physically counts the line; 0 means "counted, found none".
            $table->decimal('counted_quantity', 15, 4)->nullable();
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->timestamps();

            $table->unique(['stock_count_id', 'product_variant_id'], 'stock_count_items_unique_line');
            $table->index(['tenant_id', 'stock_count_id'], 'stock_count_items_tenant_count_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};
