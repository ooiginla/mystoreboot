<?php

declare(strict_types=1);

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Actions\CreateBranchAction;
use Modules\Business\Actions\CreateDepartmentAction;
use Modules\Business\Actions\SaveBusinessProfileAction;
use Modules\Business\Enums\BusinessType;
use Modules\Business\Http\Requests\BranchRequest;
use Modules\Business\Http\Requests\BusinessProfileRequest;
use Modules\Business\Http\Requests\DepartmentRequest;
use Modules\Business\Http\Requests\OnlineStoreRequest;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Department;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Models\ProductCategory;
use Modules\Subscriptions\Models\Plan;
use Modules\Tenancy\Models\Tenant;

final class BusinessSetupController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $isPlatformAdmin = $user->is_platform_admin;
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $user, $tenants);

        abort_if(! $isPlatformAdmin && ! $tenant, 403);

        return view('business::admin.setup', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $isPlatformAdmin,
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'businessTypes' => BusinessType::options(),
            'branches' => $tenant
                ? Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get()
                : collect(),
            'departments' => $tenant
                ? Department::query()->with('branch')->where('tenant_id', $tenant->id)->orderBy('name')->get()
                : collect(),
            'roles' => $tenant
                ? Role::query()->where('tenant_id', $tenant->id)->orderByDesc('is_system')->orderBy('name')->get()
                : collect(),
            'memberships' => $tenant
                ? TenantMembership::query()
                    ->with(['user', 'role', 'branch'])
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->get()
                : collect(),
            'selectedPlanId' => $tenant
                ? app('db')->table('tenant_subscriptions')->where('tenant_id', $tenant->id)->latest('id')->value('plan_id')
                : null,
            'onlineStore' => $tenant
                ? OnlineStore::query()->with(['categories', 'fulfilmentBranch'])->where('tenant_id', $tenant->id)->first()
                : null,
            'productCategories' => $tenant
                ? ProductCategory::query()->where('tenant_id', $tenant->id)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function saveProfile(BusinessProfileRequest $request, SaveBusinessProfileAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if(! $user->is_platform_admin && ! $request->filled('tenant_id'), 403);

        $tenant = $request->filled('tenant_id')
            ? Tenant::query()->find($request->string('tenant_id')->toString())
            : null;

        $this->authorizeTenantAccess($user, $tenant);

        $savedTenant = $action->execute($request->validated(), $tenant);

        return redirect()
            ->route('admin.business.index', ['tenant' => $savedTenant->id])
            ->with('status', "Business profile saved for {$savedTenant->name}.");
    }

    public function storeBranch(BranchRequest $request, CreateBranchAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $branch = $action->execute($request->validated());

        return redirect()
            ->route('admin.business.index', ['tenant' => $branch->tenant_id])
            ->with('status', "Branch {$branch->name} created.");
    }

    public function updateBranch(BranchRequest $request, Branch $branch, CreateBranchAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $branch->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $branch->tenant_id, 403);

        $updatedBranch = $action->execute($data, $branch);

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $updatedBranch->tenant_id]).'#branches')
            ->with('status', "Branch {$updatedBranch->name} updated.");
    }

    public function storeDepartment(DepartmentRequest $request, CreateDepartmentAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $department = $action->execute($request->validated());

        return redirect()
            ->route('admin.business.index', ['tenant' => $department->tenant_id])
            ->with('status', "Department {$department->name} created.");
    }

    public function saveOnlineStore(OnlineStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $store = OnlineStore::query()->firstOrNew(['tenant_id' => $data['tenant_id']]);
        $logoPath = $store->logo_path;
        $heroImagePath = $store->hero_image_path;

        if ($request->file('logo')) {
            $logoPath = $request->file('logo')->store("tenants/{$data['tenant_id']}/online-store/logos", 'public');
        }

        if ($request->file('hero_image')) {
            $heroImagePath = $request->file('hero_image')->store("tenants/{$data['tenant_id']}/online-store/heroes", 'public');
        }

        $store->fill([
            'fulfilment_branch_id' => $data['fulfilment_branch_id'] ?? null,
            'username' => $data['username'],
            'store_name' => $data['store_name'],
            'description' => $data['description'] ?? null,
            'logo_path' => $logoPath,
            'hero_image_path' => $heroImagePath,
            'address' => $data['address'] ?? null,
            'site_email' => $data['site_email'] ?? null,
            'store_phone' => $data['store_phone'] ?? null,
            'store_whatsapp' => $data['store_whatsapp'] ?? null,
            'hero_image_text' => $data['hero_image_text'] ?? null,
            'hero_image_description' => $data['hero_image_description'] ?? null,
            'hero_image_tag' => $data['hero_image_tag'] ?? null,
            'announcement' => $data['announcement'] ?? null,
            'theme_primary_color' => $data['theme_primary_color'],
            'theme_secondary_color' => $data['theme_secondary_color'],
            'payment_methods' => $data['payment_methods'] ?? [],
            'payment_settings' => [
                'paystack' => [
                    'public_key' => $data['paystack']['public_key'] ?? null,
                    'private_key' => $data['paystack']['private_key'] ?? null,
                ],
            ],
            'bank_accounts' => $this->onlineStoreBankAccounts($data['bank_accounts'] ?? []),
            'shipping_options' => $this->onlineStoreShippingOptions($data['shipping_options'] ?? []),
            'social_accounts' => $data['socials'] ?? [],
            'pages' => $data['pages'] ?? [],
            'faqs' => $this->onlineStoreFaqs($data['faqs'] ?? []),
            'is_active' => true,
            'maintenance_mode' => (bool) ($data['maintenance_mode'] ?? false),
        ]);
        $store->save();
        $store->categories()->sync($data['category_ids'] ?? []);

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $store->tenant_id]).'#online-store')
            ->with('status', 'Online store setup saved.');
    }

    public function organizations(Request $request): View
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        return view('business::admin.organizations.index', [
            'tenants' => Tenant::query()
                ->withCount(['branches', 'roles'])
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function organizationDetails(Request $request, Tenant $tenant): View
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $tenant->load(['branches.departments', 'roles']);

        return view('business::admin.organizations.show', [
            'tenant' => $tenant,
            'businessTypes' => BusinessType::options(),
            'memberships' => TenantMembership::query()
                ->with(['user', 'role', 'branch'])
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->get(),
            'selectedPlan' => app('db')->table('tenant_subscriptions')
                ->join('plans', 'plans.id', '=', 'tenant_subscriptions.plan_id')
                ->where('tenant_subscriptions.tenant_id', $tenant->id)
                ->latest('tenant_subscriptions.id')
                ->value('plans.name'),
        ]);
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

    /**
     * @param  EloquentCollection<int, Tenant>  $visibleTenants
     */
    private function resolveTenant(Request $request, User $user, EloquentCollection $visibleTenants): ?Tenant
    {
        if ($user->is_platform_admin && $request->boolean('new')) {
            return null;
        }

        $tenantId = $request->string('tenant')->toString();

        if ($tenantId !== '') {
            abort_unless($visibleTenants->contains('id', $tenantId), 403);

            return Tenant::query()
                ->with(['branches.departments', 'departments.branch', 'roles'])
                ->find($tenantId);
        }

        $firstTenant = $visibleTenants->first();

        return $firstTenant
            ? Tenant::query()->with(['branches.departments', 'departments.branch', 'roles'])->find($firstTenant->id)
            : null;
    }

    private function authorizeTenantIdAccess(?User $user, string $tenantId): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->is_platform_admin) {
            return;
        }

        abort_unless(
            TenantMembership::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('status', MembershipStatus::Active->value)
                ->exists(),
            403,
        );
    }

    private function authorizeTenantAccess(User $user, ?Tenant $tenant): void
    {
        if (! $tenant) {
            abort_unless($user->is_platform_admin, 403);

            return;
        }

        $this->authorizeTenantIdAccess($user, $tenant->id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     * @return list<array{bank_name: string, account_name: string, account_number: string}>
     */
    private function onlineStoreBankAccounts(array $accounts): array
    {
        return collect($accounts)
            ->filter(fn (array $account): bool => trim((string) ($account['bank_name'] ?? '')) !== '' || trim((string) ($account['account_number'] ?? '')) !== '')
            ->map(fn (array $account): array => [
                'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                'account_name' => trim((string) ($account['account_name'] ?? '')),
                'account_number' => trim((string) ($account['account_number'] ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return list<array{location: string, price: float}>
     */
    private function onlineStoreShippingOptions(array $options): array
    {
        return collect($options)
            ->filter(fn (array $option): bool => trim((string) ($option['location'] ?? '')) !== '')
            ->map(fn (array $option): array => [
                'location' => trim((string) $option['location']),
                'price' => (float) ($option['price'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $faqs
     * @return list<array{question: string, answer: string}>
     */
    private function onlineStoreFaqs(array $faqs): array
    {
        return collect($faqs)
            ->filter(fn (array $faq): bool => trim((string) ($faq['question'] ?? '')) !== '' || trim((string) ($faq['answer'] ?? '')) !== '')
            ->map(fn (array $faq): array => [
                'question' => trim((string) ($faq['question'] ?? '')),
                'answer' => trim((string) ($faq['answer'] ?? '')),
            ])
            ->values()
            ->all();
    }
}
