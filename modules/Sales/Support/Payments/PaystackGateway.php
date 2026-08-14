<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Paystack implementation of the payment + payout contracts. This is the ONLY place that
 * knows Paystack's signature scheme, event names, payload shape, and transfer-fee tiers.
 */
final class PaystackGateway implements PaymentGateway, PayoutGateway
{
    public function provider(): string
    {
        return 'paystack';
    }

    public function verifyWebhookSignature(Request $request, string $secretKey): bool
    {
        $signature = (string) $request->header('x-paystack-signature', '');

        if ($signature === '' || $secretKey === '') {
            return false;
        }

        // Paystack signs the raw request body with HMAC-SHA512 using the secret key.
        $expected = hash_hmac('sha512', (string) $request->getContent(), $secretKey);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookEvent(array $payload): ?GatewayWebhookEvent
    {
        $event = (string) ($payload['event'] ?? '');
        $data = (array) ($payload['data'] ?? []);

        if ($event === 'settlement.success') {
            $settlementId = (string) ($data['id'] ?? '');

            if ($settlementId === '') {
                return null;
            }

            return new GatewayWebhookEvent(
                type: GatewayWebhookEvent::SETTLEMENT_SUCCEEDED,
                settlementId: $settlementId,
            );
        }

        $transferType = match ($event) {
            'transfer.success' => GatewayWebhookEvent::TRANSFER_SUCCEEDED,
            'transfer.failed' => GatewayWebhookEvent::TRANSFER_FAILED,
            'transfer.reversed' => GatewayWebhookEvent::TRANSFER_REVERSED,
            default => null,
        };

        if ($transferType !== null) {
            return new GatewayWebhookEvent(
                type: $transferType,
                reference: (string) ($data['reference'] ?? ''),
                transferCode: (string) ($data['transfer_code'] ?? '') ?: null,
            );
        }

        $reference = (string) ($data['reference'] ?? '');

        if ($reference === '') {
            return null;
        }

        return new GatewayWebhookEvent(
            type: $event === 'charge.success'
                ? GatewayWebhookEvent::PAYMENT_SUCCEEDED
                : GatewayWebhookEvent::IGNORED,
            reference: $reference,
            orderId: $this->orderIdFrom($data, $reference),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public function fetchSettlementReferences(string $settlementId, string $secretKey): array
    {
        $references = [];
        $page = 1;
        $baseUrl = rtrim((string) config('services.paystack.base_url'), '/');

        do {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->get($baseUrl.'/settlement/'.rawurlencode($settlementId).'/transactions', [
                    'perPage' => 200,
                    'page' => $page,
                ]);

            if (! $response->successful() || ! (bool) data_get($response->json(), 'status')) {
                break;
            }

            $rows = (array) data_get($response->json(), 'data', []);

            foreach ($rows as $row) {
                $reference = (string) data_get($row, 'reference', '');

                if ($reference !== '') {
                    $references[] = $reference;
                }
            }

            $pageCount = (int) data_get($response->json(), 'meta.pageCount', 1);
            $page++;
        } while ($page <= $pageCount && $rows !== []);

        return $references;
    }

    public function fetchTransaction(string $reference, string $secretKey): ?GatewayPayment
    {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->get(rtrim((string) config('services.paystack.base_url'), '/').'/transaction/verify/'.rawurlencode($reference));

        $payload = $response->json();

        if (! $response->successful() || ! (bool) data_get($payload, 'status')) {
            return null;
        }

        $data = (array) data_get($payload, 'data', []);

        return new GatewayPayment(
            provider: $this->provider(),
            reference: (string) ($data['reference'] ?? $reference),
            successful: ($data['status'] ?? null) === 'success',
            amountMinor: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            feesMinor: (int) ($data['fees'] ?? 0),
            gatewayReference: isset($data['id']) ? (string) $data['id'] : null,
            customerEmail: data_get($data, 'customer.email'),
            settledDirectly: filled($data['subaccount'] ?? null),
            paidAt: isset($data['paid_at']) ? (string) $data['paid_at'] : null,
            raw: is_array($payload) ? $payload : [],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function orderIdFrom(array $data, string $reference): ?int
    {
        $metaId = data_get($data, 'metadata.sales_order_id');

        if (is_numeric($metaId)) {
            return (int) $metaId;
        }

        // Our references are minted as "PSK-{orderId}-{random}" — fall back to that.
        if (preg_match('/^PSK-(\d+)-/i', $reference, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    // ---- PayoutGateway -----------------------------------------------------

    public function transferFeeMinor(int $amountMinor, string $currency): int
    {
        if ($amountMinor <= 0 || strtoupper($currency) !== 'NGN') {
            return 0;
        }

        $config = $this->transferFeeConfig();

        $fee = 0;
        foreach ((array) ($config['tiers'] ?? []) as $tier) {
            $max = $tier['max_minor'] ?? null;

            if ($max === null || $amountMinor <= (int) $max) {
                $fee = (int) ($tier['fee_minor'] ?? 0);
                break;
            }
        }

        $stamp = $config['stamp_duty'] ?? null;
        if (is_array($stamp) && $amountMinor >= (int) ($stamp['threshold_minor'] ?? PHP_INT_MAX)) {
            $fee += (int) ($stamp['fee_minor'] ?? 0);
        }

        return $fee;
    }

    /**
     * The gateway transfer-fee structure for this provider, read from the
     * GATEWAY_TRANSFER_FEE config (so it's editable without a deploy), falling back to
     * Paystack's published NGN tiers + stamp duty.
     *
     * @return array<string, mixed>
     */
    private function transferFeeConfig(): array
    {
        $defaults = [
            'tiers' => [
                ['max_minor' => 500000, 'fee_minor' => 1000],
                ['max_minor' => 5000000, 'fee_minor' => 2500],
                ['max_minor' => null, 'fee_minor' => 5000],
            ],
            'stamp_duty' => ['threshold_minor' => 1000000, 'fee_minor' => 5000],
        ];

        $raw = DB::table('global_configs')
            ->whereNull('tenant_id')
            ->where('key', 'GATEWAY_TRANSFER_FEE')
            ->value('value');

        $all = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        $config = is_array($all) ? ($all[$this->provider()] ?? null) : null;

        return is_array($config) && ! empty($config['tiers']) ? $config : $defaults;
    }

    public function createTransferRecipient(
        string $bankCode,
        string $accountNumber,
        string $accountName,
        string $currency,
        string $secretKey,
    ): ?string {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->asJson()
            ->post(rtrim((string) config('services.paystack.base_url'), '/').'/transferrecipient', [
                'type' => 'nuban',
                'name' => $accountName,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'currency' => strtoupper($currency),
            ]);

        if (! $response->successful() || ! (bool) data_get($response->json(), 'status')) {
            return null;
        }

        $code = (string) data_get($response->json(), 'data.recipient_code', '');

        return $code !== '' ? $code : null;
    }

    public function initiateTransfer(
        string $recipientCode,
        int $amountMinor,
        string $reference,
        string $reason,
        string $secretKey,
    ): TransferResult {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->asJson()
            ->post(rtrim((string) config('services.paystack.base_url'), '/').'/transfer', [
                'source' => 'balance',
                'amount' => $amountMinor,
                'recipient' => $recipientCode,
                'reference' => $reference,
                'reason' => $reason,
            ]);

        $payload = $response->json();

        if (! $response->successful() || ! (bool) data_get($payload, 'status')) {
            return new TransferResult(
                ok: false,
                status: TransferResult::STATUS_FAILED,
                message: (string) data_get($payload, 'message', 'The transfer could not be initiated.'),
                raw: is_array($payload) ? $payload : [],
            );
        }

        $status = (string) data_get($payload, 'data.status', 'pending');

        return new TransferResult(
            ok: true,
            status: $status === 'success' ? TransferResult::STATUS_SUCCESS : TransferResult::STATUS_PENDING,
            transferCode: (string) data_get($payload, 'data.transfer_code', '') ?: null,
            message: (string) data_get($payload, 'message', ''),
            raw: is_array($payload) ? $payload : [],
        );
    }
}
