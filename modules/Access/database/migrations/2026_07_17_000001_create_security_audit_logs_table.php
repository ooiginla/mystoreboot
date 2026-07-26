<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 160)->nullable();   // denormalised so history survives user deletion
            $table->string('action', 64);                    // role.created, membership.updated, approval.approved, permission.denied, ...
            $table->string('category', 32)->default('access'); // access, approvals, security
            $table->string('subject_type', 120)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('description', 400);
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
    }
};
