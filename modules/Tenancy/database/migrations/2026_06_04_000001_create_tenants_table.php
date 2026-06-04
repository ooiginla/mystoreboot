<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->string('slug', 120)->unique();
            $table->string('status', 32)->default('trialing')->index();
            $table->string('business_type', 64)->nullable()->index();
            $table->char('country_code', 2)->default('NG');
            $table->string('timezone', 64)->default('Africa/Lagos');
            $table->char('currency_code', 3)->default('NGN');
            $table->json('settings')->nullable();
            $table->timestamp('trial_ends_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
