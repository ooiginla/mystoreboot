<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
            $table->boolean('is_protected')->default(false)->after('is_system');
            $table->string('template_key', 64)->nullable()->after('is_protected');
            $table->json('limits')->nullable()->after('template_key');
            $table->text('summary')->nullable()->after('limits');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['description', 'is_protected', 'template_key', 'limits', 'summary']);
        });
    }
};
