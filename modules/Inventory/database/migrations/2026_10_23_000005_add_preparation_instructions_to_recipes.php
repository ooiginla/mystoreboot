<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->text('preparation_instructions')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropColumn('preparation_instructions');
        });
    }
};
