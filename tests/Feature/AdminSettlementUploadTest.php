<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Sales\Models\OnlineCollectedPayment;
use Modules\Sales\Models\OnlinePaymentSettlement;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class AdminSettlementUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_preview_and_post_uploaded_settlement_csv(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $firstTenant = $this->tenant('First Store');
        $secondTenant = $this->tenant('Second Store');
        $firstPayment = $this->onlinePayment($firstTenant, 'GW-FIRST', 100000, 1000);
        $secondPayment = $this->onlinePayment($secondTenant, 'GW-SECOND', 250000, 2500);
        $csv = implode("\n", [
            'online_collected_payment_id,tenant_id,gateway_reference',
            "{$firstPayment->id},{$firstTenant->id},GW-FIRST",
            "{$secondPayment->id},{$secondTenant->id},GW-SECOND",
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sales.admin-settlements.store'), [
                'settlement_file' => UploadedFile::fake()->createWithContent('settlements.csv', $csv),
            ])
            ->assertRedirectContains('#create')
            ->assertSessionHas('admin_settlement_preview');

        $this->actingAs($admin)
            ->get(route('admin.sales.admin-settlements.index'))
            ->assertOk()
            ->assertSee('Settlement preview')
            ->assertSee('First Store')
            ->assertSee('Second Store')
            ->assertSee('NGN 35.00')
            ->assertSee('NGN 3,500.00');

        $this->actingAs($admin)
            ->post(route('admin.sales.admin-settlements.post'))
            ->assertRedirectContains('#create')
            ->assertSessionMissing('admin_settlement_preview');

        $this->assertSame(2, OnlinePaymentSettlement::query()->count());
        $this->assertDatabaseHas('online_payment_settlements', [
            'tenant_id' => $firstTenant->id,
            'payment_count' => 1,
            'total_gateway_charge_minor' => 1000,
            'total_net_amount_minor' => 100000,
        ]);
        $this->assertDatabaseHas('online_payment_settlements', [
            'tenant_id' => $secondTenant->id,
            'payment_count' => 1,
            'total_gateway_charge_minor' => 2500,
            'total_net_amount_minor' => 250000,
        ]);
        $this->assertDatabaseHas('online_collected_payments', [
            'id' => $firstPayment->id,
            'is_settled' => true,
        ]);
        $this->assertDatabaseHas('online_collected_payments', [
            'id' => $secondPayment->id,
            'is_settled' => true,
        ]);
    }

    public function test_platform_admin_can_cancel_uploaded_settlement_preview(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->withSession(['admin_settlement_preview' => ['payment_ids' => [1]]])
            ->post(route('admin.sales.admin-settlements.cancel-preview'))
            ->assertRedirectContains('#create')
            ->assertSessionMissing('admin_settlement_preview')
            ->assertSessionHas('status', 'Settlement upload preview cleared.');
    }

    private function tenant(string $name): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }

    private function onlinePayment(Tenant $tenant, string $gatewayReference, int $netAmountMinor, int $gatewayChargeMinor): OnlineCollectedPayment
    {
        $productAmountMinor = max(0, $netAmountMinor - $gatewayChargeMinor);

        $order = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'source' => 'online',
            'order_number' => 'SO-'.$gatewayReference,
            'invoice_number' => 'INV-'.$gatewayReference,
            'receipt_number' => 'RCT-'.$gatewayReference,
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'order_date' => now()->toDateString(),
            'total_minor' => $netAmountMinor,
            'paid_minor' => $netAmountMinor,
        ]);

        return OnlineCollectedPayment::query()->create([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'provider' => 'paystack',
            'payment_method' => 'storeboot_paystack',
            'provider_reference' => 'PSK-'.$gatewayReference,
            'gateway_reference' => $gatewayReference,
            'currency' => 'NGN',
            'product_amount_minor' => $productAmountMinor,
            'shipping_amount_minor' => 0,
            'gateway_charge_minor' => $gatewayChargeMinor,
            'amount_minor' => $netAmountMinor,
            'net_amount_minor' => $netAmountMinor,
            'status' => 'successful',
            'is_settled' => false,
            'collected_at' => now(),
            'verified_at' => now(),
        ]);
    }
}
