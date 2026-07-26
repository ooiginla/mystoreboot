<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Welcome to Storeboot</title>
    <!--[if mso]><style>table,td,div,p,a{font-family:Arial,Helvetica,sans-serif !important;}</style><![endif]-->
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f4f7f5; -webkit-font-smoothing:antialiased; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">Your Storeboot workspace for {{ $tenant->name }} is ready — here's everything Storeboot does to help you run the business.</div>

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

                    {{-- ===== Hero ===== --}}
                    <tr>
                        <td style="padding:36px 32px 0;">
                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#027a45;">Welcome aboard</div>
                            <h1 style="margin:10px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:26px; line-height:1.25; font-weight:800; color:#0f1b16;">Welcome to Storeboot, {{ $user->name }} 👋</h1>
                            <p style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.65; color:#334155;">
                                Your workspace for <strong style="color:#0f1b16;">{{ $tenant->name }}</strong> is ready. Storeboot is the all-in-one operating system that brings every part of your business — sales, stock, money, and customers — into one clean, connected place.
                            </p>
                        </td>
                    </tr>

                    {{-- ===== CTA ===== --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="border-radius:10px; background-color:#009a53; box-shadow:0 6px 16px rgba(6,193,104,0.35);">
                                        <a href="{{ route('login') }}" target="_blank" style="display:inline-block; padding:14px 28px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">Verify your email &amp; sign in &rarr;</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#64748b;">
                                We've sent a separate email to confirm your address — verify it first, then sign in above.
                            </p>
                        </td>
                    </tr>

                    {{-- ===== Divider ===== --}}
                    <tr><td style="padding:24px 32px;"><div style="height:1px; background-color:#e4eae7; font-size:0; line-height:1px;">&nbsp;</div></td></tr>

                    {{-- ===== Features ===== --}}
                    <tr>
                        <td style="padding:0 32px;">
                            <h2 style="margin:0 0 4px; font-family:Arial,Helvetica,sans-serif; font-size:17px; font-weight:800; color:#0f1b16;">Everything you need to run the business</h2>
                            <p style="margin:0 0 8px; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.6; color:#64748b;">Your trial unlocks the full toolkit while you set things up:</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                @php
                                    $features = [
                                        ['🛒', 'Sales &amp; Point of Sale', 'Ring up sales fast, take payments, split tenders, and print receipts.'],
                                        ['📦', 'Products &amp; Inventory', 'Track stock and costs across every branch in real time.'],
                                        ['👥', 'Customers &amp; Credit', 'Keep a customer book, run credit accounts, and follow up on balances.'],
                                        ['🚚', 'Procurement', 'Raise purchase orders, manage suppliers, and record bills.'],
                                        ['💳', 'Finance &amp; Accounting', 'Automatic journals, expenses, receivables, and clear reports.'],
                                        ['🧾', 'Payroll', 'Run staff pay and keep your records tidy and compliant.'],
                                        ['🌐', 'Online Store', 'Launch a storefront that stays in sync with your catalog and stock.'],
                                        ['📊', 'Analytics', 'See revenue, profit, payments, and top products at a glance.'],
                                    ];
                                @endphp
                                @foreach ($features as [$icon, $title, $desc])
                                    <tr>
                                        <td style="padding:9px 0; {{ ! $loop->last ? 'border-bottom:1px solid #f0f3f1;' : '' }}">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td width="48" style="vertical-align:top;">
                                                        <div style="width:38px; height:38px; border-radius:10px; background-color:#ecfdf3; text-align:center; line-height:38px; font-size:18px;">{{ $icon }}</div>
                                                    </td>
                                                    <td style="vertical-align:top; padding-left:12px;">
                                                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:14.5px; font-weight:700; color:#0f1b16;">{!! $title !!}</div>
                                                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.55; color:#64748b; margin-top:2px;">{{ $desc }}</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Get started steps ===== --}}
                    <tr><td style="padding:24px 32px 0;"><div style="height:1px; background-color:#e4eae7; font-size:0; line-height:1px;">&nbsp;</div></td></tr>
                    <tr>
                        <td style="padding:22px 32px 0;">
                            <h2 style="margin:0 0 14px; font-family:Arial,Helvetica,sans-serif; font-size:17px; font-weight:800; color:#0f1b16;">Get started in 3 steps</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                @php
                                    $steps = [
                                        ['Verify your email', 'Confirm your address from the verification email we just sent.'],
                                        ['Set up your business', 'Add your business profile, branches, and payment accounts.'],
                                        ['Make your first sale', 'Add a few products and record a sale — it takes minutes.'],
                                    ];
                                @endphp
                                @foreach ($steps as $i => [$title, $desc])
                                    <tr>
                                        <td style="padding-bottom:{{ $loop->last ? '0' : '14px' }};">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td width="40" style="vertical-align:top;">
                                                        <div style="width:28px; height:28px; border-radius:50%; background-color:#009a53; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:800; text-align:center; line-height:28px;">{{ $i + 1 }}</div>
                                                    </td>
                                                    <td style="vertical-align:top; padding-left:10px;">
                                                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:14.5px; font-weight:700; color:#0f1b16;">{{ $title }}</div>
                                                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.55; color:#64748b; margin-top:2px;">{{ $desc }}</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Trial callout ===== --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ecfdf3; border:1px solid #d1fadf; border-radius:12px;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:800; color:#027a45;">You're on a free trial ✨</div>
                                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#3f6b57; margin-top:4px;">Full access to every module, no card required. Explore at your own pace and keep what fits your workflow.</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Footer ===== --}}
                    <tr>
                        <td style="padding:26px 32px 32px;">
                            <div style="height:1px; background-color:#e4eae7; font-size:0; line-height:1px;">&nbsp;</div>
                            <p style="margin:20px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#64748b;">
                                Need a hand getting set up? Just reply to this email — a real person will help.
                            </p>
                            <p style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:800; color:#0f1b16;">Welcome aboard,<br><span style="font-weight:700; color:#027a45;">The Storeboot Team</span></p>
                            <p style="margin:18px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:1.6; color:#98a29c;">
                                © {{ now()->year }} Storeboot. All rights reserved.<br>
                                You're receiving this because a workspace was created for {{ $user->email }}.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
