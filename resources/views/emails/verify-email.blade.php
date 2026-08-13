<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Verify your Storeboot email</title>
    <!--[if mso]><style>table,td,div,p,a{font-family:Arial,Helvetica,sans-serif !important;}</style><![endif]-->
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f4f7f5; -webkit-font-smoothing:antialiased; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">Use your six-digit code to verify your Storeboot account. It expires in 15 minutes.</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f7f5;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e4eae7;">

                    {{-- ===== Header ===== --}}
                    <tr>
                        <td style="background-color:#0a1712; background-image:linear-gradient(100deg,#0a1712,#0f2a1e); padding:26px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <div style="width:40px; height:40px; border-radius:11px; background-color:#009a53; background-image:linear-gradient(140deg,#22dd85,#009a53); color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:21px; font-weight:800; text-align:center; line-height:40px;">S</div>
                                    </td>
                                    <td style="vertical-align:middle; padding-left:12px;">
                                        <span style="font-family:Arial,Helvetica,sans-serif; font-size:20px; font-weight:800; color:#ffffff; letter-spacing:-0.02em;">Storeboot</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Body ===== --}}
                    <tr>
                        <td style="padding:36px 32px 0;">
                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#027a45;">Confirm your email</div>
                            <h1 style="margin:10px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:25px; line-height:1.25; font-weight:800; color:#0f1b16;">Verify your email address</h1>
                            <p style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.65; color:#334155;">
                                Hi {{ $user->name }}, enter this code on Storeboot to confirm your email and continue to your dashboard.
                            </p>
                        </td>
                    </tr>

                    {{-- ===== Verification code ===== --}}
                    <tr>
                        <td align="center" style="padding:28px 32px 8px;">
                            <div style="display:inline-block; border:1px solid #a6f4c5; border-radius:12px; background:#ecfdf3; padding:16px 24px; font-family:Arial,Helvetica,sans-serif; font-size:32px; line-height:1; font-weight:800; letter-spacing:0.24em; color:#027a45;">{{ $verificationCode }}</div>
                        </td>
                    </tr>

                    {{-- ===== Notes ===== --}}
                    <tr>
                        <td style="padding:20px 32px 0;">
                            <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#64748b;">
                                ⏱ This verification code expires in <strong style="color:#0f1b16;">15 minutes</strong>. If you request another code, this one will stop working.
                            </p>
                        </td>
                    </tr>

                    {{-- ===== Footer ===== --}}
                    <tr>
                        <td style="padding:24px 32px 32px;">
                            <div style="height:1px; background-color:#e4eae7; font-size:0; line-height:1px;">&nbsp;</div>
                            <p style="margin:18px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12.5px; line-height:1.6; color:#98a29c;">
                                If you didn't create a Storeboot account, you can safely ignore this email — no action is needed.
                            </p>
                            <p style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:1.6; color:#98a29c;">
                                © {{ now()->year }} Storeboot. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
