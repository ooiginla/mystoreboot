<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_payment_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_payment_accounts', 'bank_code')) {
                $table->string('bank_code', 20)->nullable()->after('provider_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_payment_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('business_payment_accounts', 'bank_code')) {
                $table->dropColumn('bank_code');
            }
        });
    }
};
