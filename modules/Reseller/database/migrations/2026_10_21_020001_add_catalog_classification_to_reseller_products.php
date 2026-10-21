<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Actions\EnsureDefaultProductCategoryAction;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reseller_products', 'category_id')) {
            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('category');
            });
        }

        if (! Schema::hasForeignKey('reseller_products', ['category_id'])) {
            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('product_categories')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('reseller_products', 'classification_status')) {
            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->string('classification_status', 24)
                    ->default('pending')
                    ->after('category_id')
                    ->index();
            });
        }

        if (! Schema::hasIndex('reseller_products', ['classification_status'])) {
            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->index('classification_status');
            });
        }

        if (! Schema::hasColumn('reseller_products', 'categorized_at')) {
            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->timestamp('categorized_at')->nullable()->after('classification_status');
            });
        }

        if (! Schema::hasTable('reseller_product_product_tag')) {
            Schema::create('reseller_product_product_tag', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reseller_product_id')->constrained('reseller_products')->cascadeOnDelete();
                $table->foreignId('product_tag_id')->constrained('product_tags')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['reseller_product_id', 'product_tag_id'], 'reseller_product_tag_unique');
            });
        }

        DB::table('reseller_products')
            ->distinct()
            ->pluck('tenant_id')
            ->each(function (string $tenantId): void {
                $category = app(EnsureDefaultProductCategoryAction::class)->execute($tenantId);

                DB::table('reseller_products')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('category_id')
                    ->update(['category_id' => $category->id]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_product_product_tag');

        if (Schema::hasForeignKey('reseller_products', ['category_id'])) {
            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->dropForeign(['category_id']);
            });
        }

        if (Schema::hasColumn('reseller_products', 'classification_status')) {
            if (Schema::hasIndex('reseller_products', ['classification_status'])) {
                Schema::table('reseller_products', function (Blueprint $table): void {
                    $table->dropIndex(['classification_status']);
                });
            }

            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->dropColumn('classification_status');
            });
        }

        if (Schema::hasColumn('reseller_products', 'categorized_at')) {
            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->dropColumn('categorized_at');
            });
        }

        if (Schema::hasColumn('reseller_products', 'category_id')) {
            Schema::table('reseller_products', function (Blueprint $table): void {
                $table->dropColumn('category_id');
            });
        }
    }
};
