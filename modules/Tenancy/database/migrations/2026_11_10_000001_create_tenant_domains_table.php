<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 255)->unique();
            $table->string('domain_type', 32)->index();
            $table->string('status', 32)->default('PENDING')->index();
            $table->string('verification_token', 255)->nullable();
            $table->string('ssl_status', 32)->default('PENDING')->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ssl_issued_at')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_dns_check_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'domain_type']);
            $table->index(['tenant_id', 'status']);
        });

        if (! Schema::hasTable('online_stores')) {
            return;
        }

        $baseDomain = trim((string) config('storefront.base_domain')) ?: 'storeboot.com';

        DB::table('online_stores')
            ->select(['tenant_id', 'subdomain', 'username'])
            ->orderBy('id')
            ->each(function (object $store) use ($baseDomain): void {
                $subdomain = trim((string) ($store->subdomain ?: $store->username));

                if ($subdomain === '') {
                    return;
                }

                DB::table('tenant_domains')->insertOrIgnore([
                    'tenant_id' => $store->tenant_id,
                    'domain' => strtolower($subdomain.'.'.$baseDomain),
                    'domain_type' => 'STOREBOOT_SUBDOMAIN',
                    'status' => 'ACTIVE',
                    'ssl_status' => 'ACTIVE',
                    'is_primary' => true,
                    'verified_at' => now(),
                    'ssl_issued_at' => now(),
                    'activated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
