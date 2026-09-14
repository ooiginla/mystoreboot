<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Business\Models\Branch;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Support\RecipeAvailability;
use Modules\Sales\Actions\AddCheckItemsAction;
use Modules\Sales\Actions\CancelCheckAction;
use Modules\Sales\Actions\FireCheckRoundAction;
use Modules\Sales\Actions\OpenCheckAction;
use Modules\Sales\Actions\RecalculateCheckTotalsAction;
use Modules\Sales\Enums\TableStatus;
use Modules\Sales\Models\RestaurantTable;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Sales\Models\SalesTillSession;
use Modules\Sales\Models\ServiceArea;
use Modules\Sales\Support\DineInSettings;
use Modules\Sales\Support\PendingVoids;
use Modules\Sales\Support\VoidReasons;
use Modules\Tenancy\Models\Tenant;

final class RestaurantController extends Controller
{
    /**
     * The floor: a map of every table with its live state. A manager should be able to
     * read the room from this one screen — who is seated, for how long, and how much they
     * are up to.
     */
    public function floor(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $areas = ServiceArea::query()
            ->with(['tables' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // One query for every open check, then matched to tables in memory — a floor map
        // must not fire a query per table. A split table has several; the original first.
        $openChecks = SalesOrder::query()
            ->with('server')
            // Count only — an empty check can be freed straight from the card, and the
            // floor must not load every line of every open bill to find that out.
            ->withCount('items')
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('restaurant_table_id')
            ->whereIn('check_status', ['open', 'bill_printed'])
            ->orderByRaw('parent_sales_order_id is null desc')
            ->orderBy('id')
            ->get()
            ->groupBy('restaurant_table_id');

        return view('sales::admin.restaurant.floor', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'areas' => $areas,
            'openChecks' => $openChecks,
            'serviceChargeRate' => DineInSettings::serviceChargeRate($tenant),
            'depletionTiming' => DineInSettings::depletionTiming($tenant),
        ]);
    }

    /**
     * Restaurant-wide settings: the service charge added to new bills, and whether
     * ingredients leave the store when the kitchen is sent an order or when it is paid.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $data = $request->validate([
            'service_charge_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'depletion' => ['required', Rule::in([DineInSettings::DEPLETE_AT_FIRE, DineInSettings::DEPLETE_AT_SETTLE])],
        ]);

        $tenant->update(['settings' => DineInSettings::merge($tenant, [
            'service_charge_rate' => round((float) $data['service_charge_rate'], 2),
            'depletion' => $data['depletion'],
        ])]);

        return redirect()->to($this->floorUrl($request))
            ->with('status', 'Restaurant settings saved. The service charge applies to tables seated from now on.');
    }

    /** The busser's button: a cleared table goes back to available in one tap. */
    public function markTableClean(Request $request, RestaurantTable $table): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($table->tenant_id === $tenant->id, 403);

        if ($table->openCheck() !== null) {
            return redirect()->to($this->floorUrl($request))
                ->withErrors(['status' => "Table {$table->name} still has an open bill."]);
        }

        $table->update(['status' => TableStatus::Available->value]);

        return redirect()->to($this->floorUrl($request))->with('status', "Table {$table->name} is ready for guests.");
    }

    public function check(Request $request, SalesOrder $order, RecipeAvailability $availability): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($order->tenant_id === $tenant->id, 403);
        abort_unless($order->isCheck(), 404);

        $order->load(['items.variant.product.prepStation', 'items.modifiers.componentUnit', 'table.serviceArea.sellableLocation', 'server', 'tickets.station', 'payments']);

        $variants = ProductVariant::query()
            ->with(['product.category', 'product.modifierGroups' => fn ($q) => $q->where('status', 'active')->with('options')])
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($q) => $q->whereIn(
                'product_type',
                array_map(fn (ProductType $t): string => $t->value, ProductType::sellable()),
            ))
            ->orderBy('sku')
            ->get();

        $locations = InventoryLocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // A line can be poured from a different bar than the one the guest is sitting in,
        // so the pad needs to know what every store holds, not just this area's.
        $stock = [];
        foreach (InventoryStockLevel::query()->where('tenant_id', $tenant->id)->get() as $level) {
            $stock[(int) $level->inventory_location_id][(int) $level->product_variant_id] = round($level->quantity_available, 4);
        }

        // Only physical stock-tracked goods have a source worth choosing. A recipe item
        // draws its ingredients from its prep station; a service draws nothing.
        $tracked = $variants->filter(fn (ProductVariant $v): bool => $this->isStockTracked($v))
            ->pluck('id')
            ->all();

        // What each made-to-order line would be short of, so the pad can warn before the
        // round is sent rather than failing at the point of firing.
        $shortfalls = [];
        foreach ($order->items as $line) {
            $lineVariant = $line->variant;

            if ($line->fired_at !== null || $line->voided_at !== null) {
                continue;
            }

            // "Extra chicken" can make a line short even when the plain dish is not.
            $adjustments = $line->ingredientAdjustments();

            if (! $lineVariant || (! ($lineVariant->product?->usesRecipeDepletion() ?? false) && $adjustments === [])) {
                continue;
            }

            $missing = $availability->shortfalls(
                $tenant->id,
                (int) ($line->inventory_location_id ?: $order->inventory_location_id),
                $lineVariant,
                (float) $line->quantity,
                $adjustments,
            );

            if ($missing !== []) {
                $shortfalls[(int) $line->id] = $missing;
            }
        }

        // Made-to-order items have no stock of their own, but they still come out of a
        // kitchen — the server needs to see which one, and so does anyone asking where a
        // shortage will land.
        $recipeVariants = $variants->filter(
            fn (ProductVariant $v): bool => (bool) ($v->product?->usesRecipeDepletion() ?? false),
        )->pluck('id')->all();

        $pendingRequests = PendingVoids::forCheck($order);

        return view('sales::admin.restaurant.check', [
            // 6e: voids waiting on a manager, the other bills at this table, and the
            // open checks anywhere that could be merged into this one.
            'pendingVoids' => PendingVoids::itemQuantities($pendingRequests),
            'checkVoidPending' => PendingVoids::wholeCheck($pendingRequests),
            'voidReasons' => VoidReasons::all(),
            'tableChecks' => $order->restaurant_table_id
                ? SalesOrder::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('restaurant_table_id', $order->restaurant_table_id)
                    ->whereIn('check_status', ['open', 'bill_printed'])
                    ->orderByRaw('parent_sales_order_id is null desc')
                    ->orderBy('id')
                    ->get(['id', 'order_number', 'total_minor', 'check_status', 'parent_sales_order_id'])
                : collect(),
            'mergeable' => SalesOrder::query()
                ->with('table')
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('service_area_id')
                ->whereIn('check_status', ['open', 'bill_printed'])
                ->where('id', '!=', $order->id)
                ->where('paid_minor', 0)
                ->orderBy('id')
                ->get(),
            // 6d: what is needed to take payment at the table.
            'till' => SalesTillSession::query()
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $order->branch_id)
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->latest('id')
                ->first(),
            'paymentMethods' => array_values((array) ($tenant->settings['payment_methods'] ?? ['Cash', 'Bank transfer', 'POS/Card', 'Cheque'])),
            'paymentAccounts' => BusinessPaymentAccount::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $order->branch_id))
                ->orderBy('sort_order')
                ->orderBy('identifier')
                ->get(),
            'standardServiceChargeRate' => DineInSettings::serviceChargeRate($tenant),
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'check' => $order,
            'floorUrl' => $this->floorUrl($request),
            'locations' => $locations,
            'stock' => $stock,
            'trackedVariantIds' => $tracked,
            'recipeVariantIds' => $recipeVariants,
            'shortfalls' => $shortfalls,
            'jsMenu' => $variants->map(fn (ProductVariant $v): array => [
                'id' => $v->id,
                'name' => $v->product?->name ?? 'Item',
                'variant' => $v->variant_name,
                'category' => $v->product?->category?->name ?? 'Other',
                'price' => (int) ($v->discount_price_minor ?: $v->selling_price_minor ?: 0),
                // Tapping an item with choices opens the choice sheet instead of adding it.
                'modifiers' => ($v->product?->modifierGroups ?? collect())->map(fn ($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'rule' => $group->ruleLabel(),
                    'min' => $group->minimumChoices(),
                    'max' => $group->max_select,
                    'options' => $group->options->map(fn ($option): array => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price' => (int) $option->price_delta_minor,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function openCheck(Request $request, RestaurantTable $table, OpenCheckAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($table->tenant_id === $tenant->id, 403);

        $check = $action->execute(
            $tenant->id,
            $table,
            (int) $request->integer('cover_count', 2),
            $user,
        );

        return redirect()->to($this->checkUrl($request, $check));
    }

    public function storeItems(Request $request, SalesOrder $order, AddCheckItemsAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($order->tenant_id === $tenant->id, 403);
        abort_unless($order->isCheck(), 404);

        $action->execute($order, (array) $request->input('items', []));

        return redirect()->to($this->checkUrl($request, $order))->with('status', 'Added to the check.');
    }

    /**
     * Take a line back off the check. Only possible while it is still on the pad —
     * once the kitchen has been sent it, the food is being cooked and removing it is a
     * void that has to be recorded as waste (6e), not a quiet delete.
     */
    public function destroyItem(
        Request $request,
        SalesOrder $order,
        SalesOrderItem $item,
        RecalculateCheckTotalsAction $totals,
    ): RedirectResponse {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($order->tenant_id === $tenant->id, 403);
        abort_unless($order->isCheck(), 404);
        abort_unless($item->sales_order_id === $order->id, 404);

        if (! $order->check_status?->isOpen()) {
            return redirect()->to($this->checkUrl($request, $order))
                ->withErrors(['items' => 'This check is no longer open.']);
        }

        if ($item->isFired()) {
            return redirect()->to($this->checkUrl($request, $order))
                ->withErrors(['items' => "{$item->item_name} has already gone to the kitchen. It has to be voided with a reason, not removed."]);
        }

        $name = $item->item_name;
        $item->delete();
        $totals->execute($order);

        return redirect()->to($this->checkUrl($request, $order))->with('status', "{$name} removed.");
    }

    /**
     * Pour this line from a different store.
     *
     * The pool bar runs out of wine, the barman fetches a bottle from the lounge bar and
     * serves it. Recording a transfer would be a small lie — that bottle never lived at
     * the pool, it was drunk — so the line simply records which shelf it actually came
     * off. One guest, one bill; only the stock fans out.
     */
    public function updateItemSource(Request $request, SalesOrder $order, SalesOrderItem $item): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($order->tenant_id === $tenant->id, 403);
        abort_unless($order->isCheck(), 404);
        abort_unless($item->sales_order_id === $order->id, 404);

        if ($item->isFired()) {
            return redirect()->to($this->checkUrl($request, $order))
                ->withErrors(['items' => "{$item->item_name} has already gone to the kitchen — its source cannot be changed now."]);
        }

        $location = InventoryLocation::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail((int) $request->integer('inventory_location_id'));

        $item->update(['inventory_location_id' => $location->id]);

        return redirect()->to($this->checkUrl($request, $order))
            ->with('status', "{$item->item_name} will come from {$location->name}.");
    }

    /**
     * Physical goods counted in their own right — not recipe items, which consume
     * ingredients instead, and not services.
     */
    private function isStockTracked(ProductVariant $variant): bool
    {
        $product = $variant->product;

        return $product?->product_type === ProductType::Product
            && (bool) ($product->track_inventory ?? true)
            && ! $product->usesRecipeDepletion();
    }

    public function fire(Request $request, SalesOrder $order, FireCheckRoundAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($order->tenant_id === $tenant->id, 403);
        abort_unless($order->isCheck(), 404);

        // The server is asked to confirm when the pad already showed a shortfall; that
        // consent is what lets the ingredients go negative rather than blocking the round.
        $result = $action->execute($order, $tenant, $request->boolean('allow_short'));
        $tickets = $result['tickets'];
        $failures = $result['failures'];

        $stations = $tickets->map(fn ($t) => $t->station?->name ?? 'Kitchen')->unique()->implode(', ');
        $message = "Sent to {$stations}. {$tickets->count()} ticket(s) printed.";

        $redirect = redirect()->to($this->checkUrl($request, $order))->with('status', $message);

        // Whatever could not go stays on the pad, and the server is told why.
        return $failures === [] ? $redirect : $redirect->withErrors(['items' => array_values($failures)]);
    }

    public function cancelCheck(Request $request, SalesOrder $order, CancelCheckAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($order->tenant_id === $tenant->id, 403);
        abort_unless($order->isCheck(), 404);

        $tableName = $order->table?->name;
        $action->execute($order, $user, $request->string('reason')->toString() ?: null);

        return redirect()
            ->to($this->floorUrl($request))
            ->with('status', $tableName
                ? "Check cancelled. Table {$tableName} is available again."
                : 'Check cancelled.');
    }

    public function areas(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $areas = ServiceArea::query()
            ->with(['branch', 'sellableLocation'])
            ->withCount('tables')
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        // One area is shown at a time — a floor plan is edited section by section, and a
        // single long stack of every table in the building is harder to work with.
        $requested = $request->integer('area');
        $selected = $areas->firstWhere('id', $requested) ?? $areas->first();

        $selected?->load(['tables' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')]);

        // Which tables are holding guests — a table cannot be freed underneath a live check.
        $busyTableIds = $selected
            ? SalesOrder::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('check_status', ['open', 'bill_printed'])
                ->whereIn('restaurant_table_id', $selected->tables->pluck('id'))
                ->pluck('restaurant_table_id')
                ->all()
            : [];

        return view('sales::admin.restaurant.areas', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'areas' => $areas,
            'area' => $selected,
            'busyTableIds' => $busyTableIds,
            'branches' => Branch::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            // Only somewhere you can actually sell from: ticking "Can sell from here" on
            // a location is now the single thing that puts it in this list.
            'sellableLocations' => InventoryLocation::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_sellable_point', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateArea(Request $request, ServiceArea $area): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($area->tenant_id === $tenant->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sellable_location_id' => ['nullable', 'integer'],
        ]);

        $area->update([
            'name' => $data['name'],
            'sellable_location_id' => $this->sellableLocationId($tenant->id, $data['sellable_location_id'] ?? null),
        ]);

        return redirect()->to($this->areasUrl($request, $area->id))->with('status', 'Service area updated.');
    }

    /**
     * Change a table's status straight from the list — one click, no form to save. The
     * only move that is refused is freeing a table that still has guests on it.
     */
    public function updateTableStatus(Request $request, RestaurantTable $table): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($table->tenant_id === $tenant->id, 403);

        $status = TableStatus::tryFrom((string) $request->string('status'));

        if ($status === null) {
            return back()->withErrors(['status' => 'That is not a valid table status.']);
        }

        if ($status !== TableStatus::Occupied && $table->openCheck() !== null) {
            return redirect()->to($this->areasUrl($request, $table->service_area_id))
                ->withErrors(['status' => "Table {$table->name} still has an open check. Open the table from the floor and cancel its check to free it."]);
        }

        $table->update(['status' => $status->value]);

        return redirect()->to($this->areasUrl($request, $table->service_area_id))
            ->with('status', "Table {$table->name} is now {$status->label()}.");
    }

    public function storeArea(Request $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['required', 'integer'],
            'sellable_location_id' => ['nullable', 'integer'],
        ]);

        $branch = Branch::query()->where('tenant_id', $tenant->id)->findOrFail((int) $data['branch_id']);

        $area = ServiceArea::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'sellable_location_id' => $this->sellableLocationId($tenant->id, $data['sellable_location_id'] ?? null),
            'name' => $data['name'],
            'status' => 'active',
        ]);

        return redirect()->to($this->areasUrl($request, $area->id))->with('status', 'Service area created.');
    }

    public function storeTable(Request $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $data = $request->validate([
            'service_area_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:60'],
            'seats' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $area = ServiceArea::query()->where('tenant_id', $tenant->id)->findOrFail((int) $data['service_area_id']);

        RestaurantTable::query()->create([
            'tenant_id' => $tenant->id,
            'service_area_id' => $area->id,
            'name' => $data['name'],
            'seats' => (int) $data['seats'],
            'status' => TableStatus::Available->value,
        ]);

        return redirect()->to($this->areasUrl($request, $area->id))->with('status', 'Table added.');
    }

    public function updateTable(Request $request, RestaurantTable $table): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($table->tenant_id === $tenant->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'seats' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', 'string'],
        ]);

        $status = TableStatus::tryFrom($data['status']) ?? TableStatus::Available;

        // A table with guests on it cannot be quietly marked free — clear the check first.
        if ($status !== TableStatus::Occupied && $table->openCheck() !== null) {
            return redirect()->to($this->areasUrl($request, $table->service_area_id))
                ->withErrors(['status' => "Table {$table->name} still has an open check."]);
        }

        $table->update([
            'name' => $data['name'],
            'seats' => (int) $data['seats'],
            'status' => $status->value,
        ]);

        return redirect()->to($this->areasUrl($request, $table->service_area_id))->with('status', 'Table updated.');
    }

    /**
     * A service area can only sell from a location marked "Can sell from here" — the
     * guard that was missing when areas pointed at sales points instead.
     */
    private function sellableLocationId(string $tenantId, mixed $locationId): ?int
    {
        if (empty($locationId)) {
            return null;
        }

        return (int) InventoryLocation::query()
            ->where('tenant_id', $tenantId)
            ->where('is_sellable_point', true)
            ->findOrFail((int) $locationId)
            ->id;
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

    private function floorUrl(Request $request): string
    {
        $tenantId = $request->string('tenant')->toString();

        return route('admin.sales.restaurant.floor', $tenantId !== '' ? ['tenant' => $tenantId] : []);
    }

    /**
     * Keeps the chosen area selected across a save, so editing several tables in a row
     * does not bounce back to the first area every time.
     */
    private function areasUrl(Request $request, ?int $areaId = null): string
    {
        $params = [];
        $tenantId = $request->string('tenant')->toString();

        if ($tenantId !== '') {
            $params['tenant'] = $tenantId;
        }

        $areaId ??= $request->integer('area') ?: null;

        if ($areaId) {
            $params['area'] = $areaId;
        }

        return route('admin.sales.restaurant.areas.index', $params);
    }

    private function checkUrl(Request $request, SalesOrder $check): string
    {
        $tenantId = $request->string('tenant')->toString();
        $params = ['order' => $check->id];

        if ($tenantId !== '') {
            $params['tenant'] = $tenantId;
        }

        return route('admin.sales.restaurant.check', $params);
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
