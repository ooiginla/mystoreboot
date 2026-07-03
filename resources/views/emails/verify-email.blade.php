<div style="font-family: Arial, sans-serif; color: #101828; line-height: 1.6;">
    <h1 style="margin-bottom: 12px;">Verify your Storeboot email</h1>
    <p>Hello {{ $user->name }},</p>
    <p>Please verify your email address so you can sign in to Storeboot.</p>
    <p style="margin: 24px 0;">
        <a href="{{ $verificationUrl }}" style="display: inline-block; background: #0f766e; color: #ffffff; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 700;">Verify Email</a>
    </p>
    <p>If the button is not clickable, copy and paste this link into your browser:</p>
    <p style="word-break: break-all;"><a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a></p>
    <p>This verification link expires in 24 hours.</p>
</div>
