<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use App\Mail\OnlineOrderProgressMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Business\Models\OnlineStore;
use Modules\Sales\Models\SalesOrder;
use Throwable;

final class SendOnlineOrderProgressMailAction
{
    public function execute(SalesOrder $order, string $progress): void
    {
        if (! in_array($progress, [OnlineOrderProgressMail::PROCESSING, OnlineOrderProgressMail::OUT_FOR_DELIVERY], true)
            || $order->source !== 'online') {
            return;
        }

        $order->loadMissing([
            'customer',
            'items.variant.product.images',
            'items.variant.product.externalImages',
        ]);

        if (! filled($order->customer?->email)) {
            return;
        }

        $store = OnlineStore::query()
            ->with('tenant')
            ->where('tenant_id', $order->tenant_id)
            ->first();

        if (! $store) {
            return;
        }

        try {
            Mail::to($order->customer->email)->send(new OnlineOrderProgressMail($store, $order, $progress));
        } catch (Throwable $exception) {
            Log::warning('Online order progress email could not be sent.', [
                'sales_order_id' => $order->id,
                'order_number' => $order->order_number,
                'progress' => $progress,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
