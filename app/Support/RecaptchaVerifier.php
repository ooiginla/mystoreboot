<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

final class RecaptchaVerifier
{
    public function enabled(): bool
    {
        return ! app()->environment('local')
            && filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    public function siteKey(): string
    {
        return (string) config('services.recaptcha.site_key');
    }

    public function verify(Request $request, string $token): bool
    {
        if (! $this->enabled() || trim($token) === '') {
            return ! $this->enabled();
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.recaptcha.verify_url'), [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (Throwable) {
            return false;
        }

        return $response->ok() && (bool) $response->json('success');
    }
}
