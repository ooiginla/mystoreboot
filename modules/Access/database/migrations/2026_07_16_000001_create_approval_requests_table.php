<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('type', 48);              // refund, void, inventory_adjustment, expense, journal, payroll
            $table->string('status', 24)->default('pending'); // pending, approved, rejected, cancelled
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->json('payload')->nullable();     // data needed to execute the action on approval
            $table->text('request_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
