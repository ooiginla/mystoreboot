<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Catalog\Actions\EnsureDefaultProductCategoryAction;
use Modules\Tenancy\Models\Tenant;

return new class extends Migration
{
    public function up(): void
    {
        $ensureDefaultCategory = app(EnsureDefaultProductCategoryAction::class);

        Tenant::query()
            ->cursor()
            ->each(fn (Tenant $tenant) => $ensureDefaultCategory->execute($tenant->id));
    }

    public function down(): void
    {
        // Data migration: keep categories and product assignments intact on rollback.
    }
};
