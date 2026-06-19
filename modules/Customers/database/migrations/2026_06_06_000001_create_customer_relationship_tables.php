<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Enums\FollowUpStatus;
use Modules\Customers\Enums\TicketPriority;
use Modules\Customers\Enums\TicketStatus;
use Modules\Customers\Enums\TicketType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('code', 80)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 60);
            $table->string('email', 160)->nullable();
            $table->date('birthday')->nullable();
            $table->date('anniversary')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 32)->default(CustomerStatus::Active->value)->index();
            $table->integer('loyalty_points')->default(0);
            $table->bigInteger('account_balance_minor')->default(0);
            $table->date('last_purchase_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'customer_group_id']);
        });

        Schema::create('customer_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 120)->nullable();
            $table->date('purchase_date');
            $table->string('product_summary', 500)->nullable();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->integer('loyalty_points_awarded')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'purchase_date']);
        });

        Schema::create('customer_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 180);
            $table->date('due_date');
            $table->string('channel', 60)->nullable();
            $table->string('status', 32)->default(FollowUpStatus::Pending->value)->index();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'due_date']);
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number', 80);
            $table->string('type', 40)->default(TicketType::Enquiry->value)->index();
            $table->string('category', 120)->nullable();
            $table->string('priority', 32)->default(TicketPriority::Normal->value)->index();
            $table->string('status', 32)->default(TicketStatus::Open->value)->index();
            $table->string('subject', 180);
            $table->text('description');
            $table->text('internal_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'ticket_number']);
            $table->index(['tenant_id', 'customer_id', 'status']);
        });

        Schema::create('support_ticket_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_internal')->default(false);
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_responses');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('customer_follow_ups');
        Schema::dropIfExists('customer_purchases');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }
};
