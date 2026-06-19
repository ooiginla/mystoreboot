<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name', 140);
            $table->string('account_name', 160);
            $table->string('account_number', 80);
            $table->string('currency_code', 3)->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();

            $table->index(['tenant_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bank_accounts');
    }
};
