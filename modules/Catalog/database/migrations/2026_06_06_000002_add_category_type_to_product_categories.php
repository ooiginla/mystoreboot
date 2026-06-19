<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Enums\CategoryType;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_categories', 'category_type')) {
            return;
        }

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->string('category_type', 32)
                ->default(CategoryType::Product->value)
                ->after('parent_id')
                ->index();

            $table->index(['tenant_id', 'category_type', 'status']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_categories', 'category_type')) {
            return;
        }

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'category_type', 'status']);
            $table->dropColumn('category_type');
        });
    }
};
