<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Subscriptions\Enums\SubscriptionStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billable_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_core')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('monthly_price_minor')->default(0);
            $table->unsignedInteger('yearly_price_minor')->default(0);
            $table->char('currency_code', 3)->default('NGN');
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('plan_module_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('billable_modules')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('limits')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'module_id']);
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default(SubscriptionStatus::Trialing->value)->index();
            $table->string('billing_interval', 16)->default('monthly');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('plan_module_entitlements');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('billable_modules');
    }
};
