<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Sales\Enums\TicketStatus;
use Modules\Sales\Models\KitchenOrderTicket;
use Modules\Sales\Support\DineInSettings;
use Modules\Tenancy\Models\Tenant;

/**
 * Kitchen display. Each prep station gets its own full-screen board of live tickets.
 */
final class KdsController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $stations = InventoryLocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_prep_station', true)
            ->orderBy('name')
            ->get();

        // Live queue depth per station, so the picker itself is useful during service.
        $queues = KitchenOrderTicket::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [TicketStatus::Queued->value, TicketStatus::Preparing->value, TicketStatus::Ready->value])
            ->selectRaw('prep_location_id, count(*) as live')
            ->groupBy('prep_location_id')
            ->pluck('live', 'prep_location_id');

        return view('sales::admin.kds.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'stations' => $stations,
            'queues' => $queues,
            'unassigned' => KitchenOrderTicket::query()
                ->where('tenant_id', $tenant->id)
                ->whereNull('prep_location_id')
                ->whereIn('status', [TicketStatus::Queued->value, TicketStatus::Preparing->value, TicketStatus::Ready->value])
                ->count(),
        ]);
    }

    /**
     * One station's board. Rendered on its own bare layout — a wall screen has no use for
     * admin navigation, and every pixel of chrome is a pixel not showing a ticket.
     */
    public function station(Request $request, InventoryLocation $station): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($station->tenant_id === $tenant->id, 403);
        abort_unless((bool) $station->is_prep_station, 404);

        return view('sales::admin.kds.station', [
            'tenant' => $tenant,
            'station' => $station,
            'tickets' => $this->liveTickets($tenant, $station->id),
            'thresholds' => DineInSettings::kdsThresholds($tenant),
            'indexUrl' => route('admin.sales.kds.index', ['tenant' => $tenant->id]),
            'selfUrl' => route('admin.sales.kds.station', ['station' => $station->id, 'tenant' => $tenant->id]),
        ]);
    }

    public function advance(Request $request, KitchenOrderTicket $ticket): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($ticket->tenant_id === $tenant->id, 403);

        $next = $ticket->status->next();

        if ($next === null) {
            return back()->withErrors(['status' => 'This ticket is already finished.']);
        }

        $ticket->update(array_filter([
            'status' => $next->value,
            'started_at' => $next === TicketStatus::Preparing ? now() : $ticket->started_at,
            'ready_at' => $next === TicketStatus::Ready ? now() : $ticket->ready_at,
            'served_at' => $next === TicketStatus::Served ? now() : $ticket->served_at,
        ], fn ($value): bool => $value !== null));

        return back();
    }

    /**
     * @return EloquentCollection<int, KitchenOrderTicket>
     */
    private function liveTickets(Tenant $tenant, int $stationId): EloquentCollection
    {
        return KitchenOrderTicket::query()
            ->with(['items.orderItem.modifiers', 'order.table'])
            ->where('tenant_id', $tenant->id)
            ->where('prep_location_id', $stationId)
            ->whereIn('status', [TicketStatus::Queued->value, TicketStatus::Preparing->value, TicketStatus::Ready->value])
            // Oldest first: the kitchen works the queue from the top, always.
            ->orderBy('fired_at')
            ->get();
    }

    private function tenantFromRequest(Request $request, ?EloquentCollection &$tenants, ?User &$user): Tenant
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);

        $tenantId = $request->string('tenant')->toString();
        if ($tenantId !== '') {
            abort_unless($tenants->contains('id', $tenantId), 403);
            $tenant = Tenant::query()->find($tenantId);
        } else {
            $tenant = $tenants->first();
        }

        abort_if(! $tenant, 403);

        return $tenant;
    }

    /**
     * @return EloquentCollection<int, Tenant>
     */
    private function visibleTenantsFor(User $user): EloquentCollection
    {
        if ($user->is_platform_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
            ->orderBy('name')
            ->get();
    }
}
