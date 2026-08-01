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
        Schema::create('product_custom_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('values');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        DB::table('products')
            ->whereNotNull('custom_fields')
            ->orderBy('id')
            ->get()
            ->each(function (object $product): void {
                $fields = json_decode((string) $product->custom_fields, true);
                if (! is_array($fields)) {
                    return;
                }

                foreach ($fields as $field) {
                    $name = trim((string) ($field['key'] ?? ''));
                    $values = array_values(array_filter(array_map('trim', (array) ($field['values'] ?? []))));
                    if ($name === '' || $values === []) {
                        continue;
                    }

                    DB::table('product_custom_definitions')->updateOrInsert(
                        ['tenant_id' => $product->tenant_id, 'name' => $name],
                        ['values' => json_encode($values, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_custom_definitions');
    }
};
