<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Business\Actions\SavePaymentAccountAction;
use Modules\Business\Models\OnlineStore;
use Modules\Business\Support\PaystackDirectory;
use Modules\Catalog\Actions\CreateCategoryAction;
use Modules\Catalog\Actions\DraftProductFromImageAction;
use Modules\Catalog\Actions\EnsureDefaultProductCategoryAction;
use Modules\Catalog\Actions\SaveProductAction;
use Modules\Catalog\Models\ProductCategory;
use Modules\Storefront\Support\StorefrontUrl;
use Modules\Tenancy\Models\Tenant;

final class OnboardingController extends Controller
{
    private const LAST_STEP = 5;

    public function index(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);

        if ($this->state($tenant)['completed'] ?? false) {
            return redirect()->route('admin.home');
        }

        return redirect()->route('onboarding.step', ['step' => $this->state($tenant)['step'] ?? 1]);
    }

    public function show(Request $request, int $step): View|RedirectResponse
    {
        $tenant = $this->tenant($request);
        $state = $this->state($tenant);

        if ($state['completed'] ?? false) {
            return redirect()->route('admin.home');
        }

        // Don't let people jump ahead of the furthest step they've reached.
        $step = max(1, min(self::LAST_STEP, $step));
        if ($step > ($state['step'] ?? 1)) {
            return redirect()->route('onboarding.step', ['step' => $state['step'] ?? 1]);
        }

        $store = $this->store($tenant);

        return view('onboarding.wizard', [
            'tenant' => $tenant,
            'store' => $store,
            'step' => $step,
            'lastStep' => self::LAST_STEP,
            'categories' => ProductCategory::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'paystackConfigured' => app(PaystackDirectory::class)->configured(),
            'currency' => $tenant->currency_code ?: 'NGN',
            'storeUrl' => StorefrontUrl::route($store),
            'storeDomainSuffix' => $this->usernameSuffix(),
        ]);
    }

    public function saveAddress(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $store = $this->store($tenant);

        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'username' => [
                'required', 'string', 'max:63',
                'regex:/\A(?!-)[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::notIn((array) config('storefront.reserved_subdomains', [])),
                Rule::unique('online_stores', 'username')->ignore($store->id),
                Rule::unique('online_stores', 'subdomain')->ignore($store->id),
            ],
            'business_address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
        ], [
            'username.regex' => 'Your store address may use only lowercase letters, numbers, and single hyphens.',
            'username.not_in' => 'That store address is reserved by Storeboot. Please choose another.',
            'username.unique' => 'Sorry, that store address has already been taken. Please pick another.',
        ]);

        $tenant->settings = array_merge($tenant->settings ?? [], [
            'business_address' => $data['business_address'],
            'city' => $data['city'],
            'state' => $data['state'],
        ]);
        $tenant->save();

        $store->forceFill([
            'username' => $data['username'],
            'subdomain' => $data['username'],
            'address' => $data['business_address'],
            'city' => $data['city'],
            'state' => $data['state'],
        ])->save();

        return $this->advance($tenant, 2);
    }

    public function checkUsername(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $store = $this->store($tenant);

        $username = Str::lower(trim((string) $request->input('username')));
        $address = $username.'.'.$this->usernameSuffix();

        if (strlen($username) > 63 || ! preg_match('/\A(?!-)[a-z0-9]+(?:-[a-z0-9]+)*\z/', $username)) {
            return response()->json(['available' => false, 'username' => $username, 'message' => 'Use lowercase letters, numbers, and single hyphens only.']);
        }

        if (in_array($username, (array) config('storefront.reserved_subdomains', []), true)) {
            return response()->json(['available' => false, 'username' => $username, 'message' => "{$address} is reserved by Storeboot."]);
        }

        $taken = OnlineStore::query()
            ->where('id', '!=', $store->id)
            ->where(fn ($query) => $query->where('username', $username)->orWhere('subdomain', $username))
            ->exists();

        return response()->json([
            'available' => ! $taken,
            'username' => $username,
            'message' => $taken ? "{$address} is already taken." : "{$address} is available.",
        ]);
    }

    public function saveTheme(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validate([
            'theme_primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $store = $this->store($tenant);
        $store->theme_primary_color = $data['theme_primary_color'];
        $store->theme_secondary_color = $data['theme_secondary_color'];

        if ($request->file('logo')) {
            $store->logo_path = $request->file('logo')->store("tenants/{$tenant->id}/online-store/logos", 'public');
        }

        $store->save();

        return $this->advance($tenant, 3);
    }

    public function saveBank(Request $request, SavePaymentAccountAction $savePaymentAccount, PaystackDirectory $paystack): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validate([
            'bank_code' => ['required', 'string', 'max:20'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'store_payment_methods' => ['nullable', 'array'],
            'store_payment_methods.*' => ['string', Rule::in(['storeboot_paystack', 'pay_on_delivery', 'place_order'])],
        ]);

        $currency = $tenant->currency_code ?: 'NGN';

        if (! $paystack->isValidBankCode($data['bank_code'], $currency)) {
            return back()->withErrors(['bank_code' => 'Select a valid bank.'])->withInput();
        }

        $resolved = $paystack->resolveAccount($data['account_number'], $data['bank_code']);
        if (! $resolved['ok']) {
            return back()->withErrors(['account_number' => $resolved['message'] ?? 'We could not verify this account.'])->withInput();
        }

        $bankName = $paystack->bankName($data['bank_code'], $currency);
        $accountName = $resolved['account_name'];
        $store = $this->store($tenant);

        // 1. A receiving payment account (internal ledger + transfers).
        $savePaymentAccount->execute([
            'tenant_id' => $tenant->id,
            'identifier' => $bankName.' — '.$accountName,
            'provider_name' => $bankName,
            'bank_code' => $data['bank_code'],
            'account_number' => $data['account_number'],
            'account_type' => 'normal',
            'supported_payment_methods' => ['Transfer'],
            'status' => 'active',
        ]);

        // 2. The Paystack settlement subaccount (direct settlement for online sales).
        $existingCode = $store->payment_settings['settlement_bank_account']['subaccount_code'] ?? null;
        $subaccount = $paystack->createOrUpdateSubaccount([
            'business_name' => $store->store_name ?: $tenant->name,
            'bank_code' => $data['bank_code'],
            'account_number' => $data['account_number'],
            'subaccount_code' => $existingCode,
        ]);

        $paymentSettings = $store->payment_settings ?? [];
        $paymentSettings['settlement_bank_account'] = [
            'bank_name' => $bankName,
            'bank_code' => $data['bank_code'],
            'account_number' => $data['account_number'],
            'account_name' => $accountName,
            'subaccount_code' => $subaccount['ok'] ? $subaccount['subaccount_code'] : $existingCode,
        ];

        $methods = collect($data['store_payment_methods'] ?? ['storeboot_paystack'])->unique()->values()->all();

        $store->forceFill([
            'payment_methods' => $methods,
            'payment_settings' => $paymentSettings,
        ])->save();

        return $this->advance($tenant, 4);
    }

    public function productFromPhoto(Request $request, DraftProductFromImageAction $draft): JsonResponse
    {
        $tenant = $this->tenant($request);
        $request->validate(['photo' => ['required', 'image', 'max:8192']]);

        $file = $request->file('photo');
        $result = $draft->execute(
            (string) file_get_contents($file->getRealPath()),
            (string) $file->getMimeType(),
            null,
            $tenant->currency_code ?: 'NGN',
        );

        return response()->json([
            'name' => $result['name'] ?? '',
            'description' => $result['description'] ?? '',
            'category' => $result['category'] ?? '',
            'tags' => implode(', ', $result['tags'] ?? []),
        ]);
    }

    public function saveProduct(Request $request, SaveProductAction $saveProduct, EnsureDefaultProductCategoryAction $ensureCategory): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:120'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'base_cost_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:8192'],
        ]);

        $categoryId = $this->resolveCategory($tenant, $data['category'] ?? null, $ensureCategory);

        // Make the product visible on the storefront: it is created "active" (Live) and its
        // category must be attached to the online store, which is how the storefront scopes
        // the products it lists.
        $this->store($tenant)->categories()->syncWithoutDetaching([$categoryId]);

        $saveProduct->execute([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'slug' => $this->uniqueProductSlug($tenant->id, $data['name']),
            'product_type' => 'product',
            'category_id' => $categoryId,
            'base_price' => $data['base_price'],
            'base_cost_price' => $data['base_cost_price'] ?? null,
            'sku' => null,
            'tax_behavior' => 'exempt',
            'status' => 'active',
            'description' => $data['description'] ?? null,
            'new_tags' => $data['tags'] ?? null,
            'image' => $request->file('image'),
        ]);

        return $this->advance($tenant, 5);
    }

    public function complete(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $tenant->settings = array_merge($tenant->settings ?? [], [
            'onboarding' => ['completed' => true, 'step' => self::LAST_STEP],
        ]);
        $tenant->save();

        return redirect()->route('admin.home')->with('status', 'Your store is ready. Welcome to Storeboot!');
    }

    // ---- helpers ------------------------------------------------------------

    private function tenant(Request $request): Tenant
    {
        /** @var User $user */
        $user = $request->user();

        $tenant = $user->tenantMemberships()
            ->where('status', MembershipStatus::Active->value)
            ->with('tenant')
            ->latest('id')
            ->first()?->tenant;

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    /**
     * @return array{completed?: bool, step?: int}
     */
    private function state(Tenant $tenant): array
    {
        return (array) ($tenant->settings['onboarding'] ?? ['completed' => false, 'step' => 1]);
    }

    private function advance(Tenant $tenant, int $nextStep): RedirectResponse
    {
        $current = (int) ($this->state($tenant)['step'] ?? 1);
        $tenant->settings = array_merge($tenant->settings ?? [], [
            'onboarding' => ['completed' => false, 'step' => max($current, $nextStep)],
        ]);
        $tenant->save();

        return redirect()->route('onboarding.step', ['step' => $nextStep]);
    }

    private function store(Tenant $tenant): OnlineStore
    {
        return OnlineStore::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'store_name' => $tenant->name,
                'username' => $this->uniqueUsername($tenant->name),
                'description' => $tenant->name.' online store.',
                'is_active' => true,
            ],
        );
    }

    /**
     * A hyphenated slug of the business name, kept globally unique across store
     * usernames/subdomains. If the slug is taken (or reserved), a short random
     * suffix is appended.
     */
    private function uniqueUsername(string $base, ?int $ignoreId = null): string
    {
        $slug = substr(Str::slug($base) ?: 'store', 0, 63);
        $reserved = (array) config('storefront.reserved_subdomains', []);

        $candidate = $slug;
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $clash = in_array($candidate, $reserved, true)
                || OnlineStore::query()
                    ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                    ->where(fn ($query) => $query->where('username', $candidate)->orWhere('subdomain', $candidate))
                    ->exists();

            if (! $clash) {
                return $candidate;
            }

            $suffix = '-'.Str::lower(Str::random(4));
            $candidate = substr($slug, 0, 63 - strlen($suffix)).$suffix;
        }

        return 'store-'.Str::lower(Str::random(6));
    }

    private function usernameSuffix(): string
    {
        return trim((string) config('storefront.base_domain')) ?: 'storeboot.com';
    }

    private function resolveCategory(Tenant $tenant, ?string $name, EnsureDefaultProductCategoryAction $ensureCategory): int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return $ensureCategory->execute($tenant->id)->id;
        }

        $existing = ProductCategory::query()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return app(CreateCategoryAction::class)->execute([
            'tenant_id' => $tenant->id,
            'category_type' => 'product',
            'name' => $name,
            'slug' => $this->uniqueCategorySlug($tenant->id, $name),
        ])->id;
    }

    private function uniqueProductSlug(string $tenantId, string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 2;
        while (\Modules\Catalog\Models\Product::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $tenantId, string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (ProductCategory::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
