<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('reversal_of_movement_id')
                ->nullable()
                ->unique()
                ->constrained('inventory_movements')
                ->nullOnDelete();
            $table->foreignId('reversed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign(['reversed_by_user_id']);
            $table->dropColumn('reversed_by_user_id');
            $table->dropForeign(['reversal_of_movement_id']);
            $table->dropUnique(['reversal_of_movement_id']);
            $table->dropColumn('reversal_of_movement_id');
        });
    }
};
