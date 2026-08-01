<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('city', 120)->nullable()->after('address');
        });
        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->string('city', 120)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', fn (Blueprint $table) => $table->dropColumn('city'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('city'));
    }
};
