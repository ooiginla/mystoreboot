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
        Schema::table('reseller_products', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('product_categories')->nullOnDelete();
            $table->string('classification_status', 24)->default('pending')->after('category_id')->index();
            $table->timestamp('categorized_at')->nullable()->after('classification_status');
        });

        Schema::create('reseller_product_product_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reseller_product_id')->constrained('reseller_products')->cascadeOnDelete();
            $table->foreignId('product_tag_id')->constrained('product_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reseller_product_id', 'product_tag_id'], 'reseller_product_tag_unique');
        });

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

        Schema::table('reseller_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropIndex(['classification_status']);
            $table->dropColumn(['classification_status', 'categorized_at']);
        });
    }
};
