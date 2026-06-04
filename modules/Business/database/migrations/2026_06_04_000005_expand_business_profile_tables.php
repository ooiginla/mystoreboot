<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('registration_number', 80)->nullable()->after('business_type');
            $table->string('phone', 40)->nullable()->after('registration_number');
            $table->string('email', 160)->nullable()->after('phone');
            $table->string('website', 180)->nullable()->after('email');
            $table->text('address')->nullable()->after('website');
            $table->string('logo_path', 255)->nullable()->after('address');
            $table->string('tax_identifier', 80)->nullable()->after('currency_code');
            $table->decimal('default_tax_rate', 5, 2)->default(0)->after('tax_identifier');
            $table->json('opening_hours')->nullable()->after('default_tax_rate');

            $table->index(['business_type', 'status']);
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->after('status');
            $table->char('currency_code', 3)->nullable()->after('timezone');
            $table->decimal('default_tax_rate', 5, 2)->nullable()->after('currency_code');
            $table->json('opening_hours')->nullable()->after('default_tax_rate');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['timezone', 'currency_code', 'default_tax_rate', 'opening_hours']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['business_type', 'status']);
            $table->dropColumn([
                'registration_number',
                'phone',
                'email',
                'website',
                'address',
                'logo_path',
                'tax_identifier',
                'default_tax_rate',
                'opening_hours',
            ]);
        });
    }
};
