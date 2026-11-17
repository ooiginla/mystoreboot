<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Support\RecaptchaVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ContactController extends Controller
{
    public function show(RecaptchaVerifier $recaptcha): View
    {
        return view('marketing.contact', [
            'recaptchaEnabled' => $recaptcha->enabled(),
            'recaptchaSiteKey' => $recaptcha->siteKey(),
        ]);
    }

    public function submit(Request $request, RecaptchaVerifier $recaptcha): RedirectResponse
    {
        if ($request->filled('company_website')) {
            return redirect()
                ->route('contact')
                ->with('status', 'Thanks for reaching out! Our team will get back to you shortly.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'max:5000'],
            'company_website' => ['nullable', 'string', 'max:0'],
            'g-recaptcha-response' => [
                Rule::requiredIf($recaptcha->enabled()),
                'nullable',
                'string',
                'max:4096',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $recaptcha): void {
                    if ($recaptcha->enabled() && ! $recaptcha->verify($request, (string) $value)) {
                        $fail('Please complete the captcha verification.');
                    }
                },
            ],
        ]);

        Mail::to((string) config('mail.identities.support.address'))->send(new ContactMessageMail(
            senderName: $data['name'],
            senderEmail: $data['email'],
            subjectLine: $data['subject'] ?? 'General enquiry',
            body: $data['message'],
        ));

        return redirect()
            ->route('contact')
            ->with('status', 'Thanks for reaching out! Our team will get back to you shortly.');
    }
}
