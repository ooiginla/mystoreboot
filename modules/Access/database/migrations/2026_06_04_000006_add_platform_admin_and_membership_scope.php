<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->after('password')->index();
        });

        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('role_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_platform_admin');
        });
    }
};
