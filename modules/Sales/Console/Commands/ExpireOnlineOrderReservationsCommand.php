<?php

declare(strict_types=1);

namespace Modules\Sales\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\Actions\ExpireOnlineOrderReservationsAction;

/**
 * Scheduled sweep that cancels expired unpaid online orders and releases their
 * reserved stock. Checkout also runs the same action lazily, so stock is freed
 * even when the scheduler is not running.
 */
final class ExpireOnlineOrderReservationsCommand extends Command
{
    protected $signature = 'sales:expire-reservations';

    protected $description = 'Auto-cancel expired unpaid online orders and release their reserved stock.';

    public function handle(ExpireOnlineOrderReservationsAction $action): int
    {
        $cancelled = $action->execute();

        $this->info("Released {$cancelled} expired online order reservation(s).");

        return self::SUCCESS;
    }
}
