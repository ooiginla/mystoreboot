<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Inventory\Enums\UnitDimension;
use Modules\Inventory\Models\UnitCategory;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Tenancy\Models\Tenant;

final class UnitOfMeasureController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request);

        return view('inventory::admin.units.index', [
            'tenant' => $tenant,
            'tenants' => $this->visibleTenantsFor($request->user()),
            'isPlatformAdmin' => $request->user()->is_platform_admin,
            'unitCategories' => UnitCategory::query()
                ->with('units')
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'unitDimensions' => UnitDimension::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        $data = $this->validated($request, $tenant->id, null);

        UnitOfMeasure::query()->create([
            'tenant_id' => $tenant->id,
            'unit_category_id' => $data['unit_category_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'dimension' => $data['dimension'],
            'to_base_factor' => $data['to_base_factor'] ?? null,
            'is_base_for_dimension' => (bool) ($data['is_base_for_dimension'] ?? false),
            'status' => 'active',
        ]);

        return redirect()->to($this->redirectTo($request))->with('status', 'Unit added.');
    }

    public function update(Request $request, UnitOfMeasure $unit): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($unit->tenant_id === $tenant->id, 403);
        $data = $this->validated($request, $tenant->id, $unit->id);

        $unit->update([
            'unit_category_id' => $data['unit_category_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'dimension' => $data['dimension'],
            'to_base_factor' => $data['to_base_factor'] ?? null,
            'is_base_for_dimension' => (bool) ($data['is_base_for_dimension'] ?? false),
        ]);

        return redirect()->to($this->redirectTo($request))->with('status', 'Unit updated.');
    }

    public function destroy(Request $request, UnitOfMeasure $unit): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($unit->tenant_id === $tenant->id, 403);

        if ($this->isInUse($tenant->id, $unit->id)) {
            throw ValidationException::withMessages([
                'unit' => 'This unit can’t be removed while products, recipes, production or requisitions still use it.',
            ]);
        }

        $unit->delete();

        return redirect()->to($this->redirectTo($request))->with('status', 'Unit removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $tenantId, ?int $ignoreId): array
    {
        return $request->validate([
            'unit_category_id' => [
                'required', 'integer',
                Rule::exists('unit_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('units_of_measure', 'code')->where('tenant_id', $tenantId)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:80'],
            'dimension' => ['required', Rule::in(array_column(UnitDimension::cases(), 'value'))],
            'to_base_factor' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'is_base_for_dimension' => ['nullable', 'boolean'],
        ]);
    }

    private function isInUse(string $tenantId, int $unitId): bool
    {
        return DB::table('product_variants')->where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->where('base_unit_id', $unitId)->orWhere('purchase_unit_id', $unitId))->exists()
            || DB::table('recipes')->where('tenant_id', $tenantId)->where('yield_unit_id', $unitId)->exists()
            || DB::table('recipe_items')->where('tenant_id', $tenantId)->where('unit_id', $unitId)->exists()
            || DB::table('production_order_items')->where('tenant_id', $tenantId)->where('unit_id', $unitId)->exists()
            || DB::table('stock_requisition_items')->where('tenant_id', $tenantId)->where('unit_id', $unitId)->exists();
    }

    private function tenantFromRequest(Request $request): Tenant
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

        return route('admin.inventory.units.index', $tenantId !== '' ? ['tenant' => $tenantId] : []);
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
