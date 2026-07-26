<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Business\Support\OnlineStoreContentDefaults;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('online_stores')
            ->orderBy('id')
            ->each(function (object $store): void {
                $pages = $this->decodeArray($store->pages ?? null);
                $defaultPages = OnlineStoreContentDefaults::pages(
                    (string) ($store->store_name ?? ''),
                    $store->site_email ?? null,
                );

                foreach ($defaultPages as $key => $content) {
                    if (trim((string) ($pages[$key] ?? '')) === '') {
                        $pages[$key] = $content;
                    }
                }

                $faqs = $this->decodeArray($store->faqs ?? null);
                if ($faqs === []) {
                    $faqs = OnlineStoreContentDefaults::faqs((string) ($store->store_name ?? ''));
                }

                DB::table('online_stores')->where('id', $store->id)->update([
                    'pages' => json_encode($pages, JSON_THROW_ON_ERROR),
                    'faqs' => json_encode($faqs, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Published store content is intentionally preserved on rollback.
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
