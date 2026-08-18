<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TenantWelcomeMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Access\Actions\EnsureSystemRolesAction;
use Modules\Access\Actions\SyncPermissionCatalogueAction;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Actions\CreateBranchAction;
use Modules\Business\Enums\BusinessType;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\TenantSubscription;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;

final class RegisteredTenantController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'businessCategories' => BusinessType::options(),
            'countries' => $this->countries(),
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'recaptchaEnabled' => $this->recaptchaEnabled(),
            'localSignupBypassEnabled' => $this->localSignupBypassEnabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'business_category' => ['required', Rule::in(array_column(BusinessType::cases(), 'value'))],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', Rule::in(array_keys($this->countries()))],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'g-recaptcha-response' => [
                Rule::requiredIf($this->recaptchaEnabled()),
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($this->recaptchaEnabled() && ! $this->verifyRecaptcha($request, (string) $value)) {
                        $fail('Please complete the captcha verification.');
                    }
                },
            ],
        ]);

        [$tenant, $user] = DB::transaction(function () use ($data): array {
            $tenant = Tenant::query()->create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueTenantSlug($data['business_name']),
                'status' => TenantStatus::Trialing,
                'business_type' => $data['business_category'],
                'phone' => $data['phone'],
                'country_code' => $data['country'],
                'timezone' => $this->timezoneFor($data['country']),
                'currency_code' => $this->currencyFor($data['country']),
                'settings' => [
                    'city' => $data['city'],
                    'signup_source' => 'self_service',
                    'rbac_enforced' => true,
                    'approvals' => ['enabled' => false, 'actions' => []],
                ],
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'is_platform_admin' => false,
            ]);

            // Seed the atomic permission catalogue and every system role template,
            // then make the founding user a protected Business Owner.
            app(SyncPermissionCatalogueAction::class)->execute();
            app(EnsureSystemRolesAction::class)->execute($tenant->id, $tenant->currency_code);

            $role = Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'business-owner')
                ->firstOrFail();

            TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            app(CreateBranchAction::class)->execute([
                'tenant_id' => $tenant->id,
                'name' => 'Head Office',
                'code' => 'HO',
                'timezone' => $tenant->timezone,
                'currency_code' => $tenant->currency_code,
                'is_primary' => true,
                'status' => 'active',
            ]);

            TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $this->starterPlan()->id,
                'status' => SubscriptionStatus::Trialing,
                'billing_interval' => 'monthly',
                'trial_ends_at' => $tenant->trial_ends_at,
                'current_period_starts_at' => now(),
                'current_period_ends_at' => $tenant->trial_ends_at,
            ]);

            app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);

            // Every business gets a zero-balance wallet from day one, regardless of payout
            // mode, so they can always see and move any balance held for them.
            app(\Modules\Sales\Support\Wallet\WalletService::class)->walletFor($tenant);

            return [$tenant, $user];
        });

        Mail::to($user->email)->send(new TenantWelcomeMail($tenant, $user));
        $this->sendVerificationCode($user);

        return redirect()
            ->route('verification.notice')
            ->with('verification_email', $user->email)
            ->with('status', 'Your account has been created. Enter the six-digit code sent to your email.');
    }

    public function verificationNotice(Request $request): View
    {
        return view('auth.verify-email', [
            'email' => old('email', session('verification_email', $request->query('email', ''))),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::query()->where('email', Str::lower($data['email']))->first();

        if (! $user || $user->email_verified_at || ! $user->email_verification_code
            || ! hash_equals($user->email_verification_code, $data['code'])) {
            return back()
                ->withInput(['email' => $data['email']])
                ->withErrors(['code' => 'The verification code is invalid.']);
        }

        if (! $user->email_verification_code_expires_at || $user->email_verification_code_expires_at->isPast()) {
            return back()
                ->withInput(['email' => $data['email']])
                ->withErrors(['code' => 'This verification code has expired. Please request a new code.']);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('onboarding.index')
            ->with('status', 'Email verified. Welcome to Storeboot.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $user = User::query()->where('email', Str::lower($data['email']))->first();

        if ($user && ! $user->email_verified_at) {
            $this->sendVerificationCode($user);
        }

        return redirect()
            ->route('verification.notice')
            ->with('verification_email', Str::lower($data['email']))
            ->with('status', 'If that email is registered and unverified, a new code has been sent.');
    }

    private function sendVerificationCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'email_verification_code' => $code,
            'email_verification_code_expires_at' => now()->addMinutes(15),
        ])->save();

        Mail::to($user->email)->send(new VerifyEmailMail($user, $code));
    }

    private function recaptchaEnabled(): bool
    {
        return ! $this->localSignupBypassEnabled()
            && filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    private function localSignupBypassEnabled(): bool
    {
        return app()->environment('local');
    }

    private function verifyRecaptcha(Request $request, string $token): bool
    {
        if (trim($token) === '') {
            return false;
        }

        $response = Http::asForm()
            ->timeout(5)
            ->post((string) config('services.recaptcha.verify_url'), [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

        return $response->ok() && (bool) $response->json('success');
    }

    private function starterPlan(): Plan
    {
        $this->ensureBillableModules();

        $plan = Plan::query()->where('slug', 'starter')->first();

        if (! $plan) {
            $plan = Plan::query()->create([
                'name' => 'Starter',
                'slug' => 'starter',
                'sort_order' => 10,
                'monthly_price_minor' => 0,
                'yearly_price_minor' => 0,
                'currency_code' => 'NGN',
                'limits' => [
                    'branches' => 1,
                    'users' => 2,
                    'products' => 100,
                    'invoices_per_month' => 100,
                ],
                'is_active' => true,
            ]);

            $moduleIds = Module::query()
                ->whereIn('slug', [
                    'business',
                    'access',
                    'subscriptions',
                    'catalog',
                    'inventory',
                    'sales',
                    'retail-pos',
                    'finance',
                    'storefront',
                    'customers',
                    'analytics',
                ])
                ->pluck('id');

            $plan->modules()->sync($moduleIds->mapWithKeys(
                fn (int $moduleId): array => [$moduleId => [
                    'is_enabled' => true,
                    'limits' => null,
                ]],
            )->all());
        }

        return $plan->refresh();
    }

    private function ensureBillableModules(): void
    {
        $labels = [
            'business' => 'Business Setup',
            'access' => 'Users & Access Control',
            'subscriptions' => 'Subscriptions',
            'catalog' => 'Products & Services',
            'inventory' => 'Inventory Management',
            'sales' => 'Sales & Invoicing',
            'retail-pos' => 'Retail POS',
            'customers' => 'Customers & CRM',
            'procurement' => 'Vendors & Procurement',
            'finance' => 'Expenses & Accounting',
            'hrpayroll' => 'HR & Payroll',
            'analytics' => 'Analytics Dashboard',
            'storefront' => 'Customer-Facing Storefront',
        ];

        foreach ((array) config('modules.registry', []) as $moduleName => $definition) {
            if (! (bool) ($definition['enabled'] ?? false)) {
                continue;
            }

            $slug = Str::lower($moduleName);

            if (! array_key_exists($slug, $labels)) {
                continue;
            }

            Module::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $labels[$slug],
                    'description' => null,
                    'is_core' => in_array($slug, ['business', 'access', 'subscriptions'], true),
                    'is_active' => true,
                ],
            );
        }
    }

    private function uniqueTenantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $counter = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    private function countries(): array
    {
        return [
            'NG' => 'Nigeria',
            'GH' => 'Ghana',
            'KE' => 'Kenya',
            'ZA' => 'South Africa',
            'GB' => 'United Kingdom',
            'US' => 'United States',
        ];
    }

    private function timezoneFor(string $country): string
    {
        return match ($country) {
            'GH', 'GB' => 'UTC',
            'KE' => 'Africa/Nairobi',
            'ZA' => 'Africa/Johannesburg',
            'US' => 'America/New_York',
            default => 'Africa/Lagos',
        };
    }

    private function currencyFor(string $country): string
    {
        return match ($country) {
            'GH' => 'GHS',
            'KE' => 'KES',
            'ZA' => 'ZAR',
            'GB' => 'GBP',
            'US' => 'USD',
            default => 'NGN',
        };
    }
}
