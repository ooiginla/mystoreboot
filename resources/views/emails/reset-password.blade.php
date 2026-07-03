<div style="font-family: Arial, sans-serif; color: #101828; line-height: 1.6;">
    <h1 style="margin-bottom: 12px;">Reset your Storeboot password</h1>
    <p>Hello {{ $user->name }},</p>
    <p>We received a request to reset the password for your Storeboot account. Click the button below to choose a new password.</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}" style="display: inline-block; background: #009a53; color: #ffffff; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 700;">Reset Password</a>
    </p>
    <p>If the button is not clickable, copy and paste this link into your browser:</p>
    <p style="word-break: break-all;"><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>
    <p>This link expires in 60 minutes. If you did not request a password reset, you can safely ignore this email.</p>
</div>
