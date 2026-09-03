<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Business\Support\PaystackDirectory;
use Tests\TestCase;

final class PaystackDirectoryTest extends TestCase
{
    public function test_resolved_account_names_are_returned_in_uppercase(): void
    {
        config([
            'services.paystack.secret_key' => 'sk_test_example',
            'services.paystack.base_url' => 'https://api.paystack.test',
        ]);

        Http::fake([
            'api.paystack.test/bank/resolve*' => Http::response([
                'status' => true,
                'data' => ['account_name' => 'Adaeze O\'Connor'],
            ]),
        ]);

        $result = app(PaystackDirectory::class)->resolveAccount('0123456789', '001');

        $this->assertTrue($result['ok']);
        $this->assertSame("ADAEZE O'CONNOR", $result['account_name']);
    }
}
