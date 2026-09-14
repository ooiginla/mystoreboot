<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Access\Support\ApprovalService;
use Modules\Inventory\Support\Quantity;
use Modules\Sales\Actions\MergeChecksAction;
use Modules\Sales\Actions\SplitCheckAction;
use Modules\Sales\Actions\VoidCheckAction;
use Modules\Sales\Actions\VoidCheckItemAction;
use Modules\Sales\Http\Controllers\Concerns\ResolvesRestaurantTenant;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Sales\Support\PendingVoids;

/**
 * 6e — the messy end of service: voids after food has gone out, splitting one table's
 * bill, and merging two bills into one.
 */
final class CheckChangesController extends Controller
{
    use ResolvesRestaurantTenant;

    /**
     * Void a sent line, or part of it. When the business requires approval and this
     * person cannot approve their own voids, it goes to a manager and the line stays on
     * the bill — marked as waiting — until someone decides.
     */
    public function voidItem(
        Request $request,
        SalesOrder $order,
        SalesOrderItem $item,
        VoidCheckItemAction $action,
        ApprovalService $approvals,
    ): RedirectResponse {
        $tenant = $this->checkFor($request, $order, $tenants, $user);
        abort_unless($item->sales_order_id === $order->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:120'],
            'quantity' => ['nullable', 'numeric', 'min:0.0001', 'max:999999'],
        ]);

        $reason = $this->reasonText($data);
        $quantity = isset($data['quantity']) ? Quantity::round((float) $data['quantity']) : (float) $item->quantity;

        if ($approvals->shouldDivert($tenant, $user, 'sales_void', 'sales.void.approve')) {
            $this->assertVoidable($order, $item, $quantity);

            $approvals->create($tenant, $user, 'sales_void', "Void · {$item->item_name} · {$this->tableLabel($order)}", [
                'branch_id' => $order->branch_id,
                'amount_minor' => (int) round($quantity * (int) $item->unit_price_minor),
                'payload' => [
                    'kind' => 'item',
                    'check_id' => $order->id,
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'reason' => $reason,
                ],
                'description' => Quantity::format($quantity)." × {$item->item_name} on {$order->order_number}",
                'request_note' => $reason,
            ]);

            return redirect()->to($this->checkUrl($request, $order))
                ->with('status', "Void of {$item->item_name} sent to a manager. It stays on the bill until it is approved.");
        }

        $action->execute($item, $user, $reason, $quantity);

        return redirect()->to($this->checkUrl($request, $order))
            ->with('status', Quantity::format($quantity)." × {$item->item_name} voided and recorded as waste.");
    }

    public function voidCheck(Request $request, SalesOrder $order, VoidCheckAction $action, ApprovalService $approvals): RedirectResponse
    {
        $tenant = $this->checkFor($request, $order, $tenants, $user);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        $reason = $this->reasonText($data);
        $order->loadMissing('items');

        if ($order->firedItems()->isEmpty()) {
            return redirect()->to($this->checkUrl($request, $order))
                ->withErrors(['check' => 'Nothing on this check has gone to the kitchen — cancel it instead.']);
        }

        if ($approvals->shouldDivert($tenant, $user, 'sales_void', 'sales.void.approve')) {
            if (PendingVoids::wholeCheck(PendingVoids::forCheck($order))) {
                return redirect()->to($this->checkUrl($request, $order))
                    ->withErrors(['check' => 'Voiding this check is already waiting for a manager.']);
            }

            $approvals->create($tenant, $user, 'sales_void', "Void whole check · {$this->tableLabel($order)}", [
                'branch_id' => $order->branch_id,
                'amount_minor' => (int) $order->total_minor,
                'payload' => ['kind' => 'check', 'check_id' => $order->id, 'reason' => $reason],
                'description' => "{$order->order_number} — everything on the bill",
                'request_note' => $reason,
            ]);

            return redirect()->to($this->checkUrl($request, $order))
                ->with('status', 'Voiding this check has been sent to a manager for approval.');
        }

        $action->execute($order, $user, $reason);

        return redirect()->to($this->floorUrl($request))
            ->with('status', "{$order->order_number} voided. Everything sent to the kitchen was recorded as waste.");
    }

    public function split(Request $request, SalesOrder $order, SplitCheckAction $action): RedirectResponse
    {
        $this->checkFor($request, $order);

        $data = $request->validate([
            'moves' => ['required', 'array'],
            'moves.*' => ['nullable', 'numeric', 'min:0'],
            'covers' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $split = $action->execute($order, $data['moves'], (int) ($data['covers'] ?? 1));

        return redirect()->to($this->checkUrl($request, $split))
            ->with('status', "New bill {$split->order_number} opened with the items you moved. The rest stay on {$order->order_number}.");
    }

    /** Pull another open check onto this one. */
    public function merge(Request $request, SalesOrder $order, MergeChecksAction $action): RedirectResponse
    {
        $tenant = $this->checkFor($request, $order, $tenants, $user);

        $data = $request->validate(['source_check_id' => ['required', 'integer']]);

        $source = SalesOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('service_area_id')
            ->findOrFail((int) $data['source_check_id']);

        $action->execute($order, $source, $user);

        return redirect()->to($this->checkUrl($request, $order))
            ->with('status', "{$source->order_number} merged into this check.");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reasonText(array $data): string
    {
        $note = trim((string) ($data['note'] ?? ''));

        return trim((string) $data['reason']).($note !== '' ? ' — '.$note : '');
    }

    private function tableLabel(SalesOrder $order): string
    {
        return $order->table ? 'Table '.$order->table->name : $order->order_number;
    }

    /**
     * Refuse an impossible request before it reaches a manager's queue.
     */
    private function assertVoidable(SalesOrder $order, SalesOrderItem $item, float $quantity): void
    {
        if (! $order->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'This check is no longer open.']);
        }

        if ($item->voided_at !== null || ! $item->isFired()) {
            throw ValidationException::withMessages(['items' => "{$item->item_name} cannot be voided — it is not a sent item on this bill."]);
        }

        if ($quantity <= 0 || $quantity > (float) $item->quantity + 0.00001) {
            throw ValidationException::withMessages(['quantity' => 'Void between 1 and '.Quantity::format($item->quantity).'.']);
        }

        if (array_key_exists($item->id, PendingVoids::itemQuantities(PendingVoids::forCheck($order)))) {
            throw ValidationException::withMessages(['items' => "A void of {$item->item_name} is already waiting for a manager."]);
        }
    }
}
