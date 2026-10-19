<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_search_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('search_text');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id'], 'product_search_documents_tenant_product_idx');
        });

        // SQLite is used by the automated test suite and has no MySQL FULLTEXT index.
        // The production index is created on a new, empty table, so no catalogue rows
        // are rebuilt, replaced or removed by this migration.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE product_search_documents ADD FULLTEXT INDEX product_search_documents_text_fulltext (search_text)');
        }

        Schema::create('catalog_revisions', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['tenant_id', 'name'], 'products_tenant_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_tenant_name_idx');
        });

        Schema::dropIfExists('catalog_revisions');
        Schema::dropIfExists('product_search_documents');
    }
};
