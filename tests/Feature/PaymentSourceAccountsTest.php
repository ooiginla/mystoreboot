<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Support\PaymentSourceAccounts;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorPayment;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class PaymentSourceAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_sources_include_business_accounts_and_exclude_non_cash_current_assets(): void
    {
        $tenant = $this->tenant();
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);
        [$businessAccount, $legacyBankAccount] = $this->addBusinessPaymentAccounts($tenant);

        $codes = PaymentSourceAccounts::query($tenant->id)->pluck('code');

        $this->assertEqualsCanonicalizing(
            ['1000', '1010', '1030', $businessAccount->code, $legacyBankAccount->code],
            $codes->all(),
        );
        $this->assertTrue(PaymentSourceAccounts::allows($businessAccount, $tenant->id));
        $this->assertTrue(PaymentSourceAccounts::allows($legacyBankAccount, $tenant->id));
        $this->assertFalse(PaymentSourceAccounts::allows(
            FinanceAccount::query()->where('tenant_id', $tenant->id)->where('code', '1200')->firstOrFail(),
            $tenant->id,
        ));
    }

    public function test_operational_funding_dropdowns_use_the_same_payment_sources(): void
    {
        $tenant = $this->tenant();
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);
        [$businessAccount] = $this->addBusinessPaymentAccounts($tenant);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $procurement = $this->actingAs($user)
            ->get(route('admin.procurement.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->getContent();
        $expenses = $this->actingAs($user)
            ->get(route('admin.finance.expenses', ['tenant' => $tenant->id]))
            ->assertOk()
            ->getContent();
        $payroll = $this->actingAs($user)
            ->get(route('admin.hr-payroll.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->getContent();

        $this->assertFundingSelect($procurement, 'payment_account_code', $businessAccount->name);
        $this->assertFundingSelect($expenses, 'payment_account_code', $businessAccount->name);
        $this->assertFundingSelect($payroll, 'funding_account_code', $businessAccount->name);
    }

    public function test_vendor_payment_accepts_a_business_payment_account_and_rejects_inventory(): void
    {
        $tenant = $this->tenant();
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);
        [$businessAccount] = $this->addBusinessPaymentAccounts($tenant);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Funding Test Supplier',
            'lead_time_days' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('admin.procurement.payments.store'), [
                'tenant_id' => $tenant->id,
                'vendor_id' => $vendor->id,
                'payment_date' => '2026-07-25',
                'amount' => '100',
                'payment_method' => 'Bank transfer',
                'payment_account_code' => $businessAccount->code,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vendor_payments', [
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'amount_minor' => 10000,
        ]);

        $this->actingAs($user)
            ->from(route('admin.procurement.index', ['tenant' => $tenant->id]))
            ->post(route('admin.procurement.payments.store'), [
                'tenant_id' => $tenant->id,
                'vendor_id' => $vendor->id,
                'payment_date' => '2026-07-25',
                'amount' => '50',
                'payment_method' => 'Bank transfer',
                'payment_account_code' => '1200',
            ])
            ->assertSessionHasErrors('payment_account_code');

        $this->assertSame(1, VendorPayment::query()->where('tenant_id', $tenant->id)->count());
    }

    /**
     * @return array{FinanceAccount, FinanceAccount}
     */
    private function addBusinessPaymentAccounts(Tenant $tenant): array
    {
        $businessAccount = FinanceAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'PMT-1001',
            'name' => 'Main Business Bank',
            'type' => 'asset',
            'category' => 'Bank & Payment Accounts',
            'description' => 'Primary business bank account.',
            'normal_balance' => 'debit',
            'is_system' => false,
            'is_active' => true,
        ]);
        BusinessPaymentAccount::query()->create([
            'tenant_id' => $tenant->id,
            'finance_account_id' => $businessAccount->id,
            'identifier' => 'Main bank',
            'account_name' => 'Funding Test Shop',
            'provider_name' => 'Example Bank',
            'account_number' => '0123456789',
            'account_type' => 'normal',
            'supported_payment_methods' => ['transfer'],
            'status' => 'active',
        ]);
        $legacyBankAccount = FinanceAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'BANK-1001',
            'name' => 'Legacy Business Bank',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Legacy business bank account.',
            'normal_balance' => 'debit',
            'is_system' => false,
            'is_active' => true,
        ]);

        return [$businessAccount, $legacyBankAccount];
    }

    private function assertFundingSelect(string $html, string $name, string $includedAccountName): void
    {
        $matched = preg_match(
            '/<select name="'.preg_quote($name, '/').'".*?<\\/select>/s',
            $html,
            $matches,
        );

        $this->assertSame(1, $matched, "The {$name} funding selector was not rendered.");
        $this->assertStringContainsString($includedAccountName, $matches[0]);
        $this->assertStringNotContainsString('Inventory', $matches[0]);
        $this->assertStringNotContainsString('Accounts Receivable', $matches[0]);
        $this->assertStringNotContainsString('Vendor Advances', $matches[0]);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Funding Test Shop',
            'slug' => 'funding-test-shop-'.str()->random(6),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
