<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\FulfilStockRequisitionAction;
use Modules\Inventory\Actions\ReserveRequisitionStockAction;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Http\Requests\RequisitionRequest;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Inventory\Support\Quantity;
use Modules\Tenancy\Models\Tenant;

final class RequisitionController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $variants = ProductVariant::query()
            ->with(['product', 'baseUnit'])
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($query) => $query->whereIn('product_type', array_map(fn (ProductType $t) => $t->value, ProductType::stockable())))
            ->orderBy('sku')
            ->get();
        $units = UnitOfMeasure::query()->where('tenant_id', $tenant->id)->orderBy('code')->get();

        // Available stock per location per variant, so the requester can see what the
        // source actually holds before asking for it. Built here rather than in Blade —
        // @json mis-parses parentheses inside string literals.
        $jsStock = [];
        foreach (InventoryStockLevel::query()->where('tenant_id', $tenant->id)->get() as $level) {
            $jsStock[(string) $level->inventory_location_id][(string) $level->product_variant_id] = round($level->quantity_available, 4);
        }

        return view('inventory::admin.requisitions.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'requisitions' => StockRequisition::query()
                ->with(['items.componentVariant.product', 'items.unit', 'sourceLocation', 'destinationLocation'])
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->limit(80)
                ->get(),
            'locations' => InventoryLocation::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'variants' => $variants,
            'units' => $units,
            'jsUnits' => $units->map(fn ($u): array => ['id' => $u->id, 'code' => $u->code, 'cat' => $u->unit_category_id])->values()->all(),
            'jsItems' => $variants->map(fn ($v): array => [
                'id' => $v->id,
                'label' => ($v->product?->name ?? 'Item').' / '.$v->variant_name.' ('.$v->sku.')',
                'type' => $v->product?->product_type?->value,
            ])->values()->all(),
            'jsVariantCat' => $variants->mapWithKeys(fn ($v): array => [$v->id => $v->product?->unit_category_id])->all(),
            'jsStock' => $jsStock,
            'jsVariantUnit' => $variants->mapWithKeys(fn ($v): array => [$v->id => $v->baseUnit?->code])->all(),
        ]);
    }

    public function store(RequisitionRequest $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        // The form request can only validate ids exist, not that they belong to this
        // tenant — the active tenant is not resolved until here. Without these checks a
        // requisition could name another tenant's store or product.
        $sourceId = $this->tenantLocationId($tenant->id, $request->integer('source_location_id'));
        $destinationId = $this->tenantLocationId($tenant->id, $request->integer('destination_location_id'));

        $requisition = StockRequisition::query()->create([
            'tenant_id' => $tenant->id,
            'requisition_number' => $this->nextNumber($tenant->id),
            'source_location_id' => $sourceId,
            'destination_location_id' => $destinationId,
            'status' => RequisitionStatus::Submitted->value,
            'requested_by_user_id' => $user->id,
            'notes' => $request->input('notes'),
        ]);

        foreach ((array) $request->input('items', []) as $item) {
            $requisition->items()->create([
                'tenant_id' => $tenant->id,
                'product_variant_id' => $this->tenantVariantId($tenant->id, (int) ($item['product_variant_id'] ?? 0)),
                'unit_id' => ! empty($item['unit_id']) ? $this->tenantUnitId($tenant->id, (int) $item['unit_id']) : null,
                'requested_quantity' => Quantity::round((float) ($item['requested_quantity'] ?? 0)),
            ]);
        }

        return redirect()->to($this->redirectTo($request))->with('status', 'Requisition submitted.');
    }

    public function approve(Request $request, StockRequisition $requisition, ReserveRequisitionStockAction $reservation): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($requisition->tenant_id === $tenant->id, 403);
        abort_unless($requisition->status === RequisitionStatus::Submitted, 422);

        // Hold the stock so an approval actually promises something.
        $reservation->reserve($requisition);
        $requisition->update(['status' => RequisitionStatus::Approved->value, 'approved_by_user_id' => $user->id]);

        return redirect()->to($this->redirectTo($request))->with('status', 'Requisition approved and stock reserved at the source.');
    }

    public function reject(Request $request, StockRequisition $requisition): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($requisition->tenant_id === $tenant->id, 403);
        abort_unless($requisition->status === RequisitionStatus::Submitted, 422);

        $requisition->update(['status' => RequisitionStatus::Rejected->value]);

        return redirect()->to($this->redirectTo($request))->with('status', 'Requisition rejected.');
    }

    public function fulfil(Request $request, StockRequisition $requisition, FulfilStockRequisitionAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($requisition->tenant_id === $tenant->id, 403);

        // Apply any per-line fulfilled quantities the fulfiller entered (defaults to requested).
        $fulfilInput = (array) $request->input('fulfilled', []);
        if ($fulfilInput !== []) {
            foreach ($requisition->items as $item) {
                if (array_key_exists($item->id, $fulfilInput)) {
                    $item->update(['fulfilled_quantity' => Quantity::round((float) $fulfilInput[$item->id])]);
                }
            }
        }

        $action->execute($requisition);

        return redirect()->to($this->redirectTo($request))->with('status', 'Requisition fulfilled and stock transferred.');
    }

    public function cancel(Request $request, StockRequisition $requisition, ReserveRequisitionStockAction $reservation): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($requisition->tenant_id === $tenant->id, 403);
        abort_unless($requisition->status->isOpen(), 422);

        // Only an approved requisition is holding stock.
        if ($requisition->status === RequisitionStatus::Approved) {
            $reservation->release($requisition);
        }

        $requisition->update(['status' => RequisitionStatus::Cancelled->value]);

        return redirect()->to($this->redirectTo($request))->with('status', 'Requisition cancelled.');
    }

    /**
     * Resolve an id that must belong to the active tenant, or 404. Used for every id the
     * requisition form accepts, since cross-tenant ids would otherwise be written through.
     */
    private function tenantLocationId(string $tenantId, int $locationId): int
    {
        return (int) InventoryLocation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($locationId)
            ->id;
    }

    private function tenantVariantId(string $tenantId, int $variantId): int
    {
        return (int) ProductVariant::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($variantId)
            ->id;
    }

    private function tenantUnitId(string $tenantId, int $unitId): int
    {
        return (int) UnitOfMeasure::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($unitId)
            ->id;
    }

    private function nextNumber(string $tenantId): string
    {
        $prefix = 'REQ-'.now()->format('Ymd').'-';
        $seq = StockRequisition::query()->where('tenant_id', $tenantId)->where('requisition_number', 'like', $prefix.'%')->count() + 1;

        do {
            $candidate = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (StockRequisition::query()->where('tenant_id', $tenantId)->where('requisition_number', $candidate)->exists());

        return $candidate;
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

    private function redirectTo(Request $request): string
    {
        $tenantId = $request->string('tenant')->toString();

        return route('admin.inventory.requisitions.index', $tenantId !== '' ? ['tenant' => $tenantId] : []);
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
