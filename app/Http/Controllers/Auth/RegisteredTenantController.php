<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TenantWelcomeMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Enums\BusinessType;
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        [$tenant, $user] = DB::transaction(function () use ($data): array {
            $tenant = Tenant::query()->create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueTenantSlug($data['business_name']),
                'status' => TenantStatus::Trialing,
                'business_type' => $data['business_category'],
                'country_code' => $data['country'],
                'timezone' => $this->timezoneFor($data['country']),
                'currency_code' => $this->currencyFor($data['country']),
                'settings' => [
                    'city' => $data['city'],
                    'signup_source' => 'self_service',
                ],
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'is_platform_admin' => false,
            ]);

            $role = Role::query()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Administrator',
                'slug' => 'administrator',
                'is_system' => true,
            ]);

            TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $this->allModulesTrialPlan()->id,
                'status' => SubscriptionStatus::Trialing,
                'billing_interval' => 'monthly',
                'trial_ends_at' => $tenant->trial_ends_at,
                'current_period_starts_at' => now(),
                'current_period_ends_at' => $tenant->trial_ends_at,
            ]);

            return [$tenant, $user];
        });

        Mail::to($user->email)->send(new TenantWelcomeMail($tenant, $user));
        Mail::to($user->email)->send(new VerifyEmailMail($user, $this->verificationUrl($user)));

        return redirect()
            ->route('login')
            ->with('status', 'Your Storeboot account has been created. We sent a welcome email and a verification email. Please verify your email before signing in.');
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless(hash_equals(sha1($user->email), (string) $request->query('hash')), 403);

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()
            ->route('login')
            ->with('status', 'Email verified. You can now sign in.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $user = User::query()->where('email', Str::lower($data['email']))->first();

        if ($user && ! $user->email_verified_at) {
            Mail::to($user->email)->send(new VerifyEmailMail($user, $this->verificationUrl($user)));
        }

        return back()->with('status', 'If that email is registered and unverified, we have resent the verification email. Please check your inbox and spam messages.');
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            [
                'user' => $user->id,
                'hash' => sha1($user->email),
            ],
        );
    }

    private function allModulesTrialPlan(): Plan
    {
        $this->ensureBillableModules();

        $plan = Plan::query()->where('slug', 'all-modules-trial')->first();

        if (! $plan) {
            $plan = Plan::query()->create([
                'name' => 'All Modules Trial',
                'slug' => 'all-modules-trial',
                'sort_order' => 1,
                'monthly_price_minor' => 0,
                'yearly_price_minor' => 0,
                'currency_code' => 'NGN',
                'limits' => ['trial' => true],
                'is_active' => true,
            ]);
        }

        $modules = Module::query()->where('is_active', true)->get();

        foreach ($modules as $module) {
            $plan->modules()->syncWithoutDetaching([
                $module->id => [
                    'is_enabled' => true,
                    'limits' => null,
                ],
            ]);
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
