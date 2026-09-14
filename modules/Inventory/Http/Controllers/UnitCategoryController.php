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
use Modules\Access\Enums\MembershipStatus;
use Modules\Inventory\Models\UnitCategory;
use Modules\Tenancy\Models\Tenant;

final class UnitCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('unit_categories', 'name')->where('tenant_id', $tenant->id)],
        ]);

        UnitCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => trim((string) $request->string('name')),
            'is_default' => false,
        ]);

        return redirect()->to($this->redirectTo($request))->with('status', 'Measurement category added.');
    }

    public function update(Request $request, UnitCategory $unitCategory): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($unitCategory->tenant_id === $tenant->id, 403);
        $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('unit_categories', 'name')->where('tenant_id', $tenant->id)->ignore($unitCategory->id)],
        ]);

        $unitCategory->update(['name' => trim((string) $request->string('name'))]);

        return redirect()->to($this->redirectTo($request))->with('status', 'Category renamed.');
    }

    public function destroy(Request $request, UnitCategory $unitCategory): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($unitCategory->tenant_id === $tenant->id, 403);

        if ($unitCategory->is_default) {
            throw ValidationException::withMessages(['category' => 'The General category can’t be removed.']);
        }

        $hasUnits = DB::table('units_of_measure')->where('unit_category_id', $unitCategory->id)->exists();
        $hasProducts = DB::table('products')->where('unit_category_id', $unitCategory->id)->exists();

        if ($hasUnits || $hasProducts) {
            throw ValidationException::withMessages([
                'category' => 'Remove or reassign this category’s units and products before deleting it.',
            ]);
        }

        $unitCategory->delete();

        return redirect()->to($this->redirectTo($request))->with('status', 'Category removed.');
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
