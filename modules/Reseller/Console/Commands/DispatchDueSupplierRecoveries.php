<?php

declare(strict_types=1);

namespace Modules\Reseller\Console\Commands;

use Illuminate\Console\Command;
use Modules\Reseller\Jobs\RecoverSupplierProductsJob;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Reseller\Services\RecoverSupplierProducts;
use Modules\Reseller\Support\SupplierScanSchedule;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Modules\Tenancy\Enums\CommerceMode;

final class DispatchDueSupplierRecoveries extends Command
{
    protected $signature = 'reseller:recover-products {--sync : Run recovery in this process}';

    protected $description = 'Dispatch product recovery for due reseller supplier websites';

    public function handle(
        RecoverSupplierProducts $recovery,
        TenantModuleAccess $modules,
        SupplierScanSchedule $schedule,
    ): int {
        $count = 0;

        ResellerSupplier::query()
            ->with('tenant')
            ->where('is_active', true)
            ->whereHas('tenant', fn ($query) => $query->where('commerce_mode', CommerceMode::Reseller->value))
            ->orderBy('id')
            ->each(function (ResellerSupplier $supplier) use ($recovery, $modules, $schedule, &$count): void {
                if (! $modules->allows($supplier->tenant, 'reseller')) {
                    return;
                }

                $settings = ResellerSetting::query()->firstOrCreate(['tenant_id' => $supplier->tenant_id]);
                if (! $schedule->isDue($supplier, $settings)) {
                    return;
                }

                if ($this->option('sync')) {
                    $recovery->execute($supplier);
                } else {
                    RecoverSupplierProductsJob::dispatch($supplier->id);
                }

                $count++;
            });

        $this->info("Scheduled {$count} supplier recoveries.");

        return self::SUCCESS;
    }
}
