<?php

declare(strict_types=1);

namespace Modules\Reseller\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Reseller\Services\RecoverSupplierProducts;

final class RecoverSupplierProductsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $supplierId) {}

    public function uniqueId(): string
    {
        return (string) $this->supplierId;
    }

    public function handle(RecoverSupplierProducts $recovery): void
    {
        $supplier = ResellerSupplier::query()->find($this->supplierId);

        if ($supplier) {
            $recovery->execute($supplier);
        }
    }
}
