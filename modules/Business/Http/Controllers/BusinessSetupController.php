<?php

declare(strict_types=1);

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Business\Models\Department;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Models\ProductCategory;
use Modules\Finance\Models\FinanceAccount;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\TenantSubscription;
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
            'plans' => Plan::query()->with('modules')->where('is_active', true)->orderBy('sort_order')->get(),
            'subscriptionStatuses' => SubscriptionStatus::cases(),
            'tenantSubscriptions' => $tenant
                ? TenantSubscription::query()
                    ->with('plan')
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->get()
                : collect(),
            'businessTypes' => BusinessType::options(),
            'branches' => $tenant
                ? Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get()
                : collect(),
            'departments' => $tenant
                ? Department::query()->with('branch')->where('tenant_id', $tenant->id)->orderBy('name')->get()
                : collect(),
            'paymentAccounts' => $tenant
                ? $this->businessPaymentAccounts($tenant)
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

    public function savePaymentMethods(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', Rule::in(['Cash', 'Transfer', 'Card', 'Cheque'])],
        ]);
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);
        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        $methods = collect(['Cash'])
            ->merge($data['payment_methods'] ?? [])
            ->map(fn (string $method): string => $this->canonicalPaymentMethod($method))
            ->unique()
            ->values()
            ->all();

        $tenant->settings = array_merge($tenant->settings ?? [], [
            'payment_methods' => $methods,
        ]);
        $tenant->save();

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $tenant->id]).'#payment-accounts')
            ->with('status', 'Payment methods updated.');
    }

    public function storePaymentAccount(Request $request): RedirectResponse
    {
        $data = $this->paymentAccountData($request);
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $paymentAccount = $this->savePaymentAccount($data);
        $this->syncPaymentAccountSettings($paymentAccount->tenant_id);

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $paymentAccount->tenant_id]).'#payment-accounts')
            ->with('status', "Payment account {$paymentAccount->identifier} saved.");
    }

    public function updatePaymentAccount(Request $request, BusinessPaymentAccount $paymentAccount): RedirectResponse
    {
        $data = $this->paymentAccountData($request);
        $this->authorizeTenantIdAccess($request->user(), $paymentAccount->tenant_id);
        abort_unless($data['tenant_id'] === $paymentAccount->tenant_id, 403);

        $paymentAccount = $this->savePaymentAccount($data, $paymentAccount);
        $this->syncPaymentAccountSettings($paymentAccount->tenant_id);

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $paymentAccount->tenant_id]).'#payment-accounts')
            ->with('status', "Payment account {$paymentAccount->identifier} updated.");
    }

    public function destroyPaymentAccount(Request $request, BusinessPaymentAccount $paymentAccount): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $paymentAccount->tenant_id);
        $tenantId = $paymentAccount->tenant_id;
        $paymentAccount->update(['status' => 'inactive']);
        $paymentAccount->financeAccount?->update(['is_active' => false]);
        $this->syncPaymentAccountSettings($tenantId);

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $tenantId]).'#payment-accounts')
            ->with('status', 'Payment account deactivated.');
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

        $slides = $this->onlineStoreSlides($request, $data['tenant_id'], $data['slides'] ?? []);
        $firstSlide = $slides[0] ?? null;

        if ($firstSlide) {
            $heroImagePath = $firstSlide['image_path'] ?: $heroImagePath;
            $data['hero_image_text'] = $firstSlide['hero_image_text'];
            $data['hero_image_description'] = $firstSlide['hero_image_description'];
            $data['hero_image_tag'] = $firstSlide['hero_image_tag'];
        }

        $selectedBankAccountKey = in_array('bank_account', $data['payment_methods'] ?? [], true)
            ? ($data['bank_account_key'] ?? null)
            : null;

        $store->fill([
            'fulfilment_branch_id' => $data['fulfilment_branch_id'] ?? null,
            'username' => $data['username'],
            'store_name' => $data['store_name'],
            'description' => $data['description'] ?? null,
            'logo_path' => $logoPath,
            'hero_image_path' => $heroImagePath,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? null,
            'site_email' => $data['site_email'] ?? null,
            'store_phone' => $data['store_phone'] ?? null,
            'store_whatsapp' => $data['store_whatsapp'] ?? null,
            'hero_image_text' => $data['hero_image_text'] ?? null,
            'hero_image_description' => $data['hero_image_description'] ?? null,
            'hero_image_tag' => $data['hero_image_tag'] ?? null,
            'slides' => $slides,
            'announcement' => $data['announcement'] ?? null,
            'theme_primary_color' => $data['theme_primary_color'],
            'theme_secondary_color' => $data['theme_secondary_color'],
            'payment_methods' => $data['payment_methods'] ?? [],
            'payment_settings' => [
                'paystack' => [
                    'public_key' => $data['paystack']['public_key'] ?? null,
                    'private_key' => $data['paystack']['private_key'] ?? null,
                ],
                'settlement_bank_account' => [
                    'bank_name' => $data['settlement_bank_account']['bank_name'] ?? null,
                    'account_number' => $data['settlement_bank_account']['account_number'] ?? null,
                    'account_name' => $data['settlement_bank_account']['account_name'] ?? null,
                ],
                'bank_account_key' => $selectedBankAccountKey,
            ],
            'bank_accounts' => $this->selectedBusinessBankAccount($data['tenant_id'], $selectedBankAccountKey),
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
            ->to(route('admin.business.index', [
                'tenant' => $store->tenant_id,
                'online_store_section' => $data['online_store_section'] ?? 'online-store-basics',
            ]).'#online-store')
            ->with('status', 'Online store setup saved.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $slides
     * @return list<array{image_path: string|null, hero_image_tag: string|null, hero_image_text: string|null, hero_image_description: string|null}>
     */
    private function onlineStoreSlides(OnlineStoreRequest $request, string $tenantId, array $slides): array
    {
        return collect($slides)
            ->map(function (array $slide, int $index) use ($request, $tenantId): array {
                $imagePath = trim((string) ($slide['existing_image_path'] ?? '')) ?: null;
                $uploadedImage = $request->file("slides.{$index}.image");

                if ($uploadedImage) {
                    $imagePath = $uploadedImage->store("tenants/{$tenantId}/online-store/heroes", 'public');
                }

                return [
                    'image_path' => $imagePath,
                    'hero_image_tag' => trim((string) ($slide['hero_image_tag'] ?? '')) ?: null,
                    'hero_image_text' => trim((string) ($slide['hero_image_text'] ?? '')) ?: null,
                    'hero_image_description' => trim((string) ($slide['hero_image_description'] ?? '')) ?: null,
                ];
            })
            ->filter(fn (array $slide): bool => $slide['image_path'] || $slide['hero_image_tag'] || $slide['hero_image_text'] || $slide['hero_image_description'])
            ->values()
            ->all();
    }

    public function storeSubscription(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $data = $this->validatedSubscriptionData($request);

        TenantSubscription::query()->create($data);

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $data['tenant_id']]).'#subscriptions')
            ->with('status', 'Tenant subscription created.');
    }

    public function updateSubscription(Request $request, TenantSubscription $subscription): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $data = $this->validatedSubscriptionData($request);
        abort_unless($data['tenant_id'] === $subscription->tenant_id, 403);

        $subscription->update($data);

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $subscription->tenant_id]).'#subscriptions')
            ->with('status', 'Tenant subscription updated.');
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
     * @return array{
     *     tenant_id: string,
     *     plan_id: int,
     *     status: string,
     *     billing_interval: string,
     *     trial_ends_at?: string|null,
     *     current_period_starts_at?: string|null,
     *     current_period_ends_at?: string|null,
     *     cancelled_at?: string|null
     * }
     */
    private function validatedSubscriptionData(Request $request): array
    {
        return $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'status' => ['required', 'string', Rule::in(SubscriptionStatus::values())],
            'billing_interval' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_starts_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date', 'after_or_equal:current_period_starts_at'],
            'cancelled_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return list<array{bank_name: string, account_name: string, account_number: string}>
     */
    private function selectedBusinessBankAccount(string $tenantId, ?string $selectedKey): array
    {
        if (! $selectedKey) {
            return [];
        }

        $tenant = Tenant::query()->find($tenantId);
        $account = collect($tenant?->settings['bank_details'] ?? [])
            ->filter(fn ($account): bool => is_array($account))
            ->filter(fn (array $account): bool => ($account['status'] ?? 'active') === 'active')
            ->map(fn (array $account): array => [
                'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                'account_name' => trim((string) ($account['account_name'] ?? '')),
                'account_number' => trim((string) ($account['account_number'] ?? '')),
            ])
            ->filter(fn (array $account): bool => $account['bank_name'] !== '' && $account['account_number'] !== '')
            ->first(fn (array $account): bool => $this->bankAccountKey($account) === $selectedKey);

        return $account ? [$account] : [];
    }

    /**
     * @param  array{bank_name: string, account_name: string, account_number: string}  $account
     */
    private function bankAccountKey(array $account): string
    {
        return sha1(implode('|', [$account['bank_name'], $account['account_name'], $account['account_number']]));
    }

    private function businessPaymentAccounts(Tenant $tenant): EloquentCollection
    {
        $this->migrateLegacyBankDetailsToPaymentAccounts($tenant);

        return BusinessPaymentAccount::query()
            ->with(['branch', 'financeAccount'])
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('identifier')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentAccountData(Request $request): array
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'identifier' => ['required', 'string', 'max:140'],
            'account_name' => ['nullable', 'string', 'max:160'],
            'provider_name' => ['required', 'string', 'max:140'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_type' => ['required', Rule::in(['normal', 'virtual'])],
            'supported_payment_methods' => ['required', 'array', 'min:1'],
            'supported_payment_methods.*' => ['string', Rule::in(['Transfer', 'Card', 'Cheque'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if (($data['branch_id'] ?? null) !== null) {
            abort_unless(Branch::query()->where('tenant_id', $data['tenant_id'])->whereKey($data['branch_id'])->exists(), 403);
        }

        $data['supported_payment_methods'] = collect($data['supported_payment_methods'])
            ->map(fn (string $method): string => $this->canonicalPaymentMethod($method))
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function savePaymentAccount(array $data, ?BusinessPaymentAccount $paymentAccount = null): BusinessPaymentAccount
    {
        $paymentAccount ??= new BusinessPaymentAccount(['tenant_id' => $data['tenant_id']]);
        $financeAccount = $paymentAccount->financeAccount ?: FinanceAccount::query()->create([
            'tenant_id' => $data['tenant_id'],
            'code' => $this->nextPaymentFinanceAccountCode($data['tenant_id']),
            'name' => (string) $data['identifier'],
            'type' => 'asset',
            'category' => $data['account_type'] === 'virtual' ? 'Payment Wallets / Virtual Accounts' : 'Bank & Payment Accounts',
            'description' => 'Payment receiving account managed from Business Profile.',
            'normal_balance' => 'debit',
            'is_system' => false,
            'is_active' => $data['status'] === 'active',
        ]);

        $financeAccount->fill([
            'name' => (string) $data['identifier'],
            'type' => 'asset',
            'category' => $data['account_type'] === 'virtual' ? 'Payment Wallets / Virtual Accounts' : 'Bank & Payment Accounts',
            'description' => 'Payment receiving account managed from Business Profile.',
            'normal_balance' => 'debit',
            'is_active' => $data['status'] === 'active',
        ])->save();

        $paymentAccount->fill([
            'tenant_id' => $data['tenant_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'finance_account_id' => $financeAccount->id,
            'identifier' => trim((string) $data['identifier']),
            'account_name' => trim((string) ($data['account_name'] ?? '')) ?: null,
            'provider_name' => trim((string) $data['provider_name']),
            'account_number' => trim((string) ($data['account_number'] ?? '')) ?: null,
            'account_type' => $data['account_type'],
            'supported_payment_methods' => $data['supported_payment_methods'],
            'status' => $data['status'],
        ])->save();

        return $paymentAccount->refresh();
    }

    private function nextPaymentFinanceAccountCode(string $tenantId): string
    {
        $numbers = FinanceAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', 'PMT-%')
            ->pluck('code')
            ->map(fn (string $code): int => (int) preg_replace('/\D+/', '', $code))
            ->filter(fn (int $number): bool => $number > 0);

        $next = max(1000, (int) ($numbers->max() ?? 1000)) + 1;

        do {
            $code = 'PMT-'.$next;
            $next++;
        } while (FinanceAccount::query()->where('tenant_id', $tenantId)->where('code', $code)->exists());

        return $code;
    }

    private function canonicalPaymentMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match (true) {
            str_contains($method, 'card'), str_contains($method, 'pos') => 'Card',
            str_contains($method, 'cheque'), str_contains($method, 'check') => 'Cheque',
            str_contains($method, 'transfer'), str_contains($method, 'bank') => 'Transfer',
            default => 'Cash',
        };
    }

    private function syncPaymentAccountSettings(string $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            return;
        }

        $accounts = BusinessPaymentAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('identifier')
            ->get();
        $methods = collect(['Cash'])
            ->merge($tenant->settings['payment_methods'] ?? [])
            ->merge($accounts->flatMap(fn (BusinessPaymentAccount $account): array => $account->supported_payment_methods ?? []))
            ->map(fn (string $method): string => $this->canonicalPaymentMethod($method))
            ->unique()
            ->values()
            ->all();
        $bankDetails = $accounts
            ->filter(fn (BusinessPaymentAccount $account): bool => $account->supports('Transfer'))
            ->map(fn (BusinessPaymentAccount $account): array => [
                'bank_name' => $account->provider_name,
                'account_name' => $account->account_name ?: $account->identifier,
                'account_number' => $account->account_number ?: '',
                'status' => $account->status,
                'asset_account_code' => $account->financeAccount?->code,
            ])
            ->values()
            ->all();

        $tenant->settings = array_merge($tenant->settings ?? [], [
            'payment_methods' => $methods,
            'bank_details' => $bankDetails,
        ]);
        $tenant->save();
    }

    private function migrateLegacyBankDetailsToPaymentAccounts(Tenant $tenant): void
    {
        if (BusinessPaymentAccount::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach ((array) ($tenant->settings['bank_details'] ?? []) as $account) {
            if (! is_array($account)) {
                continue;
            }

            $bankName = trim((string) ($account['bank_name'] ?? ''));
            $accountNumber = trim((string) ($account['account_number'] ?? ''));

            if ($bankName === '' && $accountNumber === '') {
                continue;
            }

            $this->savePaymentAccount([
                'tenant_id' => $tenant->id,
                'branch_id' => null,
                'identifier' => trim($bankName.' '.$accountNumber) ?: 'Payment account',
                'account_name' => trim((string) ($account['account_name'] ?? '')),
                'provider_name' => $bankName ?: 'Bank',
                'account_number' => $accountNumber,
                'account_type' => 'normal',
                'supported_payment_methods' => ['Transfer'],
                'status' => in_array(($account['status'] ?? 'active'), ['active', 'inactive'], true) ? (string) ($account['status'] ?? 'active') : 'active',
            ]);
        }

        $this->syncPaymentAccountSettings($tenant->id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return list<array{location: string, description: string, price: float}>
     */
    private function onlineStoreShippingOptions(array $options): array
    {
        return collect($options)
            ->filter(fn (array $option): bool => trim((string) ($option['location'] ?? '')) !== '')
            ->map(fn (array $option): array => [
                'location' => trim((string) $option['location']),
                'description' => trim((string) ($option['description'] ?? '')),
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
