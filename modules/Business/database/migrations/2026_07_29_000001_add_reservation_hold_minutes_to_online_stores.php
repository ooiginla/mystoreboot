<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            // How long an unpaid online order holds its stock reservation before
            // it is auto-cancelled and the stock is released for other shoppers.
            $table->unsignedSmallInteger('reservation_hold_minutes')->default(30)->after('maintenance_mode');
        });
    }

    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table): void {
            $table->dropColumn('reservation_hold_minutes');
        });
    }
};
