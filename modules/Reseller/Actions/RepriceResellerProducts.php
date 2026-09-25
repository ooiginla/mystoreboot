<?php

declare(strict_types=1);

namespace Modules\Reseller\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Reseller\Models\ResellerSetting;

final class RepriceResellerProducts
{
    public function __construct(private readonly CalculateResellerPrice $calculator) {}

    public function execute(ResellerSetting $settings): int
    {
        $updated = 0;

        DB::transaction(function () use ($settings, &$updated): void {
            ResellerProduct::query()
                ->where('tenant_id', $settings->tenant_id)
                ->where('is_excluded', false)
                ->chunkById(200, function ($products) use ($settings, &$updated): void {
                    foreach ($products as $product) {
                        $previous = $product->source_previous_price_minor;
                        $product->update([
                            'selling_price_minor' => $this->calculator->execute($product->source_price_minor, $settings),
                            'selling_previous_price_minor' => $previous === null
                                ? null
                                : $this->calculator->execute($previous, $settings),
                        ]);
                        $updated++;
                    }
                });
        });

        return $updated;
    }
}
