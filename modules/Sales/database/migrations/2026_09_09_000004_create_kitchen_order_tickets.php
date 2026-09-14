<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6b: kitchen order tickets.
 *
 * Routing was already solved in Phase 0 — `products.prep_station_id` says which arm of the
 * kitchen makes a thing. Firing a round groups its items by station and writes one ticket
 * per station, so the grill sees the suya, the bar sees the drinks, and neither is
 * distracted by the other's work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_order_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prep_station_id')->nullable()->constrained('prep_stations')->nullOnDelete();
            $table->string('ticket_number', 60);
            $table->unsignedTinyInteger('course')->default(1);
            // queued | preparing | ready | served | cancelled
            $table->string('status', 32)->default('queued')->index();
            $table->timestamp('fired_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'ticket_number']);
            // The KDS reads exactly this: one station's live queue, oldest first.
            $table->index(['tenant_id', 'prep_station_id', 'status'], 'kot_station_status_idx');
        });

        Schema::create('kitchen_order_ticket_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_order_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('status', 32)->default('queued');
            $table->timestamps();

            $table->index(['tenant_id', 'kitchen_order_ticket_id'], 'kot_items_tenant_ticket_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_order_ticket_items');
        Schema::dropIfExists('kitchen_order_tickets');
    }
};
