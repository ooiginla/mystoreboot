<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

final class ContactController extends Controller
{
    public function show(): View
    {
        return view('marketing.contact');
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Mail::to('support@storeboot.com')->send(new ContactMessageMail(
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
