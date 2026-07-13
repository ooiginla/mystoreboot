@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $currencySymbols = ['NGN' => '₦', 'USD' => '$', 'GHS' => '₵', 'KES' => 'KSh', 'ZAR' => 'R', 'GBP' => '£', 'EUR' => '€', 'GHc' => '₵'];
    $currency = $currencySymbols[$tenant->currency_code] ?? $tenant->currency_code;
    $signedMoney = fn (int $minor): string => ($minor < 0 ? '-' : '').$currency.' '.number_format(abs($minor) / 100, 2);
    $posLocations = $activeTill
        ? $locations->filter(fn ($location) => $location->branch_id === null || $location->branch_id === $activeTill->branch_id)
        : collect();
    $tileName = fn ($v): string => $v->product?->name.($v->variant_name && $v->variant_name !== 'Default' ? ' · '.$v->variant_name : '');
    $tileImage = function ($v): ?string {
        $path = $v->image_path ?: $v->product?->image_path;
        return $path ? asset('storage/'.ltrim($path, '/')) : null;
    };
    $movementTypes = [
        'cash_in' => 'Cash In',
        'cash_out' => 'Cash Out',
        'petty_cash_withdrawal' => 'Petty Cash',
        'cash_deposit' => 'Cash Deposit',
    ];
    $statusClass = fn (string $status): string => match ($status) {
        'completed', 'paid', 'delivered' => 'success',
        'partially_paid', 'partially_returned', 'partially_refunded', 'pending', 'processing', 'out_for_delivery', 'customer_credit' => 'warning',
        'cancelled', 'returned', 'refunded', 'unpaid', 'failed' => 'danger',
        default => 'neutral',
    };
    $deliveryStatusLabel = fn (?string $status): string => match ($status) {
        'pending' => 'Pending',
        'processing' => 'Processing',
        'out_for_delivery' => 'Out for delivery',
        'failed' => 'Failed delivery',
        'returned' => 'Returned',
        default => 'Delivered',
    };
@endphp

<x-layouts.admin title="Retail POS">
    <style>
        body:has([data-rpos]) .main { padding: 0; max-width: none; }
        body:has([data-rpos]) .admin-context-bar { display: none; }
        body.rpos-full .sidebar { display: none; }
        body.rpos-full .shell { grid-template-columns: 1fr; }

        .rpos { display: flex; flex-direction: column; height: 100vh; background: #eef2f0; color: var(--ink); overflow: hidden; }
        .rpos * { box-sizing: border-box; }
        .rpos-topbar { display: flex; align-items: center; gap: 14px; padding: 10px 16px; background: linear-gradient(100deg, #0a1712, #0f2a1e); color: #eef2f0; flex: 0 0 auto; }
        .rpos-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; }
        .rpos-brand .mark { width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center; background: linear-gradient(140deg, #22dd85, #009a53); }
        .rpos-brand .mark svg { width: 18px; height: 18px; color: #fff; }
        .rpos-menu-toggle { display: none; width: 34px; height: 34px; border: 1px solid rgba(255,255,255,.14); border-radius: 9px; background: rgba(255,255,255,.06); color: #eef2f0; place-items: center; cursor: pointer; }
        .rpos-menu-toggle svg { width: 19px; height: 19px; }
        .rpos-context { display: flex; flex-direction: column; line-height: 1.15; }
        .rpos-context strong { font-size: 14px; }
        .rpos-context span { font-size: 11.5px; color: #9db3a8; }
        .rpos-sync { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #9db3a8; }
        .rpos-sync .dot { width: 8px; height: 8px; border-radius: 50%; background: #22dd85; box-shadow: 0 0 0 3px rgba(34,221,133,.2); }
        .rpos-top-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
        .rpos-tbtn { display: inline-flex; align-items: center; gap: 7px; border: 1px solid rgba(255,255,255,.14); background: rgba(255,255,255,.06); color: #eef2f0; border-radius: 9px; padding: 8px 12px; font-weight: 650; font-size: 13px; cursor: pointer; }
        .rpos-tbtn:hover { background: rgba(255,255,255,.14); }
        .rpos-tbtn svg { width: 16px; height: 16px; }
        .rpos-tbtn.ghost { background: transparent; }
        .rpos-tbtn .count { background: #22dd85; color: #06231a; border-radius: 999px; padding: 0 6px; font-size: 11px; font-weight: 800; }

        .rpos-body { flex: 1 1 auto; display: grid; grid-template-columns: minmax(380px, 34%) 1fr; min-height: 0; }

        /* ---- Cart panel ---- */
        .rpos-cart { background: #fff; border-right: 1px solid #d9e2dd; display: flex; flex-direction: column; min-height: 0; }
        .rpos-customer { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid #eef2f0; cursor: pointer; }
        .rpos-customer:hover { background: #f6faf8; }
        .rpos-customer .avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--brand-050); color: var(--brand-strong); display: grid; place-items: center; font-weight: 800; flex: 0 0 auto; }
        .rpos-customer .who { flex: 1 1 auto; min-width: 0; }
        .rpos-customer .who strong { display: block; font-size: 14px; }
        .rpos-customer .who span { font-size: 12px; color: var(--muted); }
        .rpos-customer .chg { font-size: 12px; font-weight: 700; color: var(--brand-strong); }
        .rpos-lines { flex: 1 1 auto; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; min-height: 180px; }
        .rpos-empty-cart { margin: auto; text-align: center; color: var(--muted); padding: 30px; }
        .rpos-empty-cart svg { width: 46px; height: 46px; color: #cbd5cf; margin-bottom: 8px; }
        .rpos-line { border: 1px solid #e4efe9; border-left: 4px solid var(--brand); border-radius: 12px; background: #f7fdfb; padding: 10px 12px; display: grid; grid-template-columns: minmax(0, 1fr) auto auto auto; gap: 12px; align-items: center; }
        .rpos-line .nm { font-weight: 700; font-size: 14px; min-width: 0; }
        .rpos-line .nm small { display: block; color: var(--muted); font-weight: 500; font-size: 11.5px; }
        .rpos-line .lt { font-weight: 800; font-size: 15px; font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; min-width: 74px; }
        .rpos-qty { display: inline-flex; align-items: center; gap: 0; border: 1px solid #cfe0d8; border-radius: 9px; overflow: hidden; background: #fff; }
        .rpos-qty button { width: 30px; height: 30px; border: 0; background: #fff; color: var(--brand-strong); font-size: 17px; font-weight: 800; cursor: pointer; display: grid; place-items: center; }
        .rpos-qty button:hover { background: var(--brand-050); }
        .rpos-qty input { width: 38px; height: 30px; border: 0; border-left: 1px solid #eef2f0; border-right: 1px solid #eef2f0; text-align: center; font-weight: 800; font-variant-numeric: tabular-nums; padding: 0; }
        .rpos-line .rm { width: 26px; height: 26px; border: 0; background: transparent; color: #98a29c; border-radius: 7px; cursor: pointer; font-size: 15px; font-weight: 700; display: grid; place-items: center; }
        .rpos-line .rm:hover { background: #fef2f2; color: #dc2626; }

        .rpos-summary { border-top: 1px solid #eef2f0; padding: 12px 14px; background: #fff; flex: 0 0 auto; }
        .rpos-sline { display: flex; justify-content: space-between; padding: 3px 0; font-size: 14px; color: #526177; font-variant-numeric: tabular-nums; }
        .rpos-sline.disc { color: #b42318; }
        .rpos-total { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding: 13px 16px; border-radius: 12px; background: #fff; border: 1.5px solid var(--line); color: var(--ink); }
        .rpos-total span { font-size: 15px; font-weight: 700; color: var(--ink-soft); }
        .rpos-total strong { font-size: 25px; font-weight: 850; font-variant-numeric: tabular-nums; color: var(--brand-strong); }
        .rpos-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 7px; margin-top: 10px; }
        .rpos-act { display: flex; flex-direction: column; align-items: center; gap: 4px; border: 1px solid transparent; border-radius: 11px; padding: 10px 4px; cursor: pointer; font-size: 11.5px; font-weight: 700; transition: filter .12s, transform .05s; }
        .rpos-act:hover { filter: brightness(.97); }
        .rpos-act:active { transform: translateY(1px); }
        .rpos-act svg { width: 19px; height: 19px; }
        .rpos-act.c-coupon { background: #f5f3ff; color: #6d28d9; border-color: #e6e0ff; }
        .rpos-act.c-discount { background: #fff7ed; color: #c2410c; border-color: #ffe6d0; }
        .rpos-act.c-delivery { background: #eff6ff; color: #1d4ed8; border-color: #dbeafe; }
        .rpos-act.c-note { background: #ecfeff; color: #0e7490; border-color: #cffafe; }
        .rpos-act.c-hold { background: #fefce8; color: #a16207; border-color: #fef08a; }
        .rpos-act.void { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .rpos-act .tag { font-size: 10px; color: var(--brand-strong); font-weight: 800; }
        .rpos-pay { margin-top: 10px; width: 100%; border: 0; border-radius: 12px; background: #009a53; color: #fff; padding: 13px 16px; font-size: 16px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 10px 20px -8px rgba(6,193,104,.5); }
        .rpos-pay:hover { background: #027a45; }
        .rpos-pay:disabled { background: #cbd5cf; box-shadow: none; cursor: not-allowed; }
        .rpos-pay svg { width: 20px; height: 20px; flex: 0 0 auto; }
        .rpos-pay .amt { font-variant-numeric: tabular-nums; font-size: 17px; }

        /* Mobile-only cart drawer bits (hidden on desktop / tablet split view) */
        .rpos-cartbar { display: none; }
        .rpos-cart-handle { display: none; }

        /* ---- Products panel ---- */
        .rpos-products { display: flex; flex-direction: column; min-height: 0; padding: 12px; }
        .rpos-search { position: relative; flex: 0 0 auto; }
        .rpos-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--muted); }
        .rpos-search input { width: 100%; border: 1px solid #d4ddd8; border-radius: 12px; background: #fff; padding: 13px 14px 13px 42px; font-size: 15px; outline: none; }
        .rpos-search input:focus { border-color: var(--brand); box-shadow: 0 0 0 3.5px var(--brand-ring); }
        .rpos-cats { display: flex; gap: 8px; overflow-x: auto; padding: 12px 2px; flex: 0 0 auto; scrollbar-width: thin; }
        .rpos-cats::-webkit-scrollbar { height: 6px; }
        .rpos-cats::-webkit-scrollbar-thumb { background: #cbd5cf; border-radius: 3px; }
        .rpos-cat { flex: 0 0 auto; border: 1px solid #d4ddd8; background: #fff; border-radius: 999px; padding: 9px 16px; font-weight: 700; font-size: 13px; color: var(--ink-soft); cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
        .rpos-cat:hover { border-color: var(--brand); color: var(--brand-strong); }
        .rpos-cat.active { background: var(--brand); border-color: var(--brand); color: #fff; }
        .rpos-cat svg { width: 14px; height: 14px; }
        .rpos-grid { flex: 1 1 auto; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; align-content: start; padding: 4px 2px 2px; min-height: 0; }
        .rpos-tile { position: relative; border: 1px solid #e0e8e4; border-radius: 16px; background: #fff; padding: 14px 13px 13px; cursor: pointer; text-align: left; display: flex; flex-direction: column; gap: 11px; transition: transform .05s, box-shadow .15s, border-color .15s; min-height: 200px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
        .rpos-tile:hover { border-color: var(--brand); box-shadow: 0 10px 22px -8px rgba(16,24,40,.2); transform: translateY(-2px); }
        .rpos-tile:active { transform: scale(.98); }
        .rpos-tile-img { position: relative; height: 108px; border-radius: 12px; display: grid; place-items: center; font-weight: 800; font-size: 34px; color: #fff; overflow: hidden; }
        .rpos-tile-img img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .rpos-tile-name { font-size: 14.5px; font-weight: 700; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .rpos-tile-foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 6px; }
        .rpos-tile-price { font-weight: 800; font-size: 16px; color: var(--brand-strong); font-variant-numeric: tabular-nums; }
        .rpos-tile-sku { font-size: 11px; color: var(--muted); }
        .rpos-pin { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; border-radius: 8px; border: 0; background: rgba(255,255,255,.85); color: #cbd5cf; display: grid; place-items: center; cursor: pointer; }
        .rpos-pin:hover { color: var(--brand); background: #fff; }
        .rpos-pin.pinned { color: #f59e0b; }
        .rpos-pin svg { width: 15px; height: 15px; }
        .rpos-grid-empty { grid-column: 1/-1; text-align: center; color: var(--muted); padding: 50px 20px; }

        /* keypad */
        .rpos-keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .rpos-keypad button { border: 1px solid #d4ddd8; background: #fff; border-radius: 11px; padding: 16px; font-size: 20px; font-weight: 800; cursor: pointer; }
        .rpos-keypad button:hover { background: var(--panel-soft); }
        .rpos-keypad button.wide { grid-column: span 2; }
        .rpos-keypad button.clear { color: #b42318; border-color: #f6c7c2; background: #fff1f0; }
        .pay-amount-box { border: 1px solid #d4ddd8; border-radius: 12px; padding: 14px; text-align: center; background: var(--brand-050); }
        .pay-amount-box span { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .pay-amount-box strong { display: block; font-size: 30px; font-weight: 850; color: var(--brand-strong); font-variant-numeric: tabular-nums; }
        .pay-methods { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .pay-method { border: 1.5px solid #d4ddd8; background: #fff; border-radius: 12px; padding: 14px 10px; font-weight: 800; cursor: pointer; text-align: center; font-size: 14px; transition: transform .1s, box-shadow .1s; }
        .pay-methods .pay-method:nth-child(1) { border-color: #a6f4c5; color: #027a45; background: #f0fdf4; }
        .pay-methods .pay-method:nth-child(2) { border-color: #bfdbfe; color: #1d4ed8; background: #eff6ff; }
        .pay-methods .pay-method:nth-child(3) { border-color: #e6e0ff; color: #6d28d9; background: #f5f3ff; }
        .pay-methods .pay-method:nth-child(4) { border-color: #fde68a; color: #b45309; background: #fffbeb; }
        .pay-methods .pay-method:nth-child(5) { border-color: #fbcfe8; color: #be185d; background: #fdf2f8; }
        .pay-method.active { color: #fff !important; border-color: transparent !important; box-shadow: 0 8px 16px -6px rgba(16,24,40,.4); transform: translateY(-1px); }
        .pay-methods .pay-method:nth-child(5n+1).active { background: #059669 !important; }
        .pay-methods .pay-method:nth-child(5n+2).active { background: #2563eb !important; }
        .pay-methods .pay-method:nth-child(5n+3).active { background: #7c3aed !important; }
        .pay-methods .pay-method:nth-child(5n+4).active { background: #d97706 !important; }
        .pay-methods .pay-method:nth-child(5n+5).active { background: #db2777 !important; }
        .rpos-cancel { border: 1.5px solid #fecaca; background: #fef2f2; color: #dc2626; border-radius: 12px; padding: 14px 24px; font-weight: 800; cursor: pointer; font-size: 14px; }
        .rpos-cancel:hover { background: #dc2626; color: #fff; border-color: #dc2626; }
        .pay-quick { display: flex; gap: 8px; flex-wrap: wrap; }
        .pay-quick button { flex: 1 1 auto; border: 1px solid #d4ddd8; background: #fff; border-radius: 9px; padding: 10px; font-weight: 700; cursor: pointer; }
        .pay-quick button:hover { border-color: var(--brand); }
        .pay-status { display: flex; justify-content: space-between; align-items: center; padding: 13px 16px; border-radius: 12px; font-weight: 700; border: 1.5px solid transparent; transition: background .15s, border-color .15s; }
        .pay-status strong { font-size: 22px; font-variant-numeric: tabular-nums; }
        .pay-status.exact { background: var(--panel-soft); color: var(--ink-soft); }
        .pay-status.exact strong { color: var(--brand-strong); }
        .pay-status.over { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        .pay-status.over strong { color: #1d4ed8; }
        .pay-status.short { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
        .pay-status.short strong { color: #dc2626; }
        .pay-error { margin-top: 8px; padding: 12px 14px; border-radius: 11px; background: #fef2f2; border: 1.5px solid #fecaca; color: #dc2626; font-weight: 700; text-align: center; }
        .rpos-success .dialog-body { padding: 34px 30px 26px; text-align: center; }
        .rpos-success .succ-check { width: 92px; height: 92px; margin: 0 auto 18px; border-radius: 50%; background: var(--brand-050); display: grid; place-items: center; box-shadow: 0 0 0 8px rgba(6,193,104,.08); }
        .rpos-success .succ-check svg { width: 46px; height: 46px; color: var(--brand); }
        .rpos-success h2 { font-size: 26px; margin: 0 0 6px; font-weight: 850; letter-spacing: -.01em; }
        .rpos-success .succ-sub { color: var(--muted); font-size: 16px; margin: 0 0 22px; }
        .rpos-success .succ-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .rpos-success .succ-actions button { justify-content: center; padding: 14px 10px; font-size: 14px; }
        .rpos-success .succ-actions .succ-newsale { background: #4f46e5; color: #fff; border: 0; }
        .rpos-success .succ-actions .succ-newsale:hover { background: #4338ca; }
        .held-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid var(--line); border-radius: 11px; padding: 12px 14px; background: #fff; }
        .held-item:hover { border-color: var(--brand); }
        .rpos-toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(20px); background: #0a1712; color: #fff; padding: 12px 20px; border-radius: 999px; font-weight: 700; box-shadow: 0 12px 30px rgba(16,24,40,.3); opacity: 0; transition: .25s; z-index: 200; pointer-events: none; }
        .rpos-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        @media (max-width: 1100px) {
            .rpos-body { grid-template-columns: minmax(320px, 40%) 1fr; }
            .rpos-grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px; }
            .rpos-tile { min-height: 172px; padding: 11px; border-radius: 13px; }
            .rpos-tile-img { height: 82px; }
            .rpos-topbar { gap: 10px; }
            .rpos-tbtn { padding: 8px 10px; }
        }

        @media (max-width: 900px) {
            body:has([data-rpos]) .shell { grid-template-columns: 1fr; }
            body:has([data-rpos]) .sidebar { position: fixed; inset: 0 auto 0 0; z-index: 400; width: min(82vw, 300px); height: 100dvh; display: flex; transform: translateX(-105%); transition: transform .2s ease; box-shadow: 18px 0 36px rgba(16,24,40,.28); }
            body.rpos-menu-open:has([data-rpos]) .sidebar { transform: translateX(0); }
            body.rpos-menu-open:has([data-rpos])::after { content: ''; position: fixed; inset: 0; z-index: 390; background: rgba(9,20,15,.45); }
            .rpos { height: 100dvh; min-height: 100dvh; }
            .rpos-topbar { flex-wrap: wrap; padding: 9px 10px; gap: 8px; }
            .rpos-menu-toggle { display: grid; }
            .rpos-brand .mark { width: 30px; height: 30px; border-radius: 8px; }
            .rpos-context { min-width: 0; flex: 1 1 170px; }
            .rpos-context strong, .rpos-context span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .rpos-sync { display: none; }
            .rpos-top-actions { order: 3; width: 100%; margin-left: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
            .rpos-tbtn { justify-content: center; min-height: 42px; padding: 8px 6px; font-size: 12px; border-radius: 8px; }
            .rpos-tbtn svg { width: 15px; height: 15px; }

            /* Products fill the screen; the cart lives in a slide-up sheet */
            .rpos-body { display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
            .rpos-products { flex: 1 1 auto; min-height: 0; padding: 10px; }
            .rpos-search input { padding-top: 12px; padding-bottom: 12px; font-size: 16px; }
            .rpos-cats { padding: 9px 2px; gap: 7px; }
            .rpos-cat { padding: 8px 13px; font-size: 12.5px; }
            .rpos-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 9px; padding-bottom: 84px; }
            .rpos-tile { min-height: 150px; gap: 8px; padding: 9px; border-radius: 12px; }
            .rpos-tile-img { height: 70px; border-radius: 9px; font-size: 25px; }
            .rpos-tile-name { font-size: 13px; line-height: 1.25; }
            .rpos-tile-price { font-size: 14px; }

            /* Fixed cart bar — the drawer trigger */
            .rpos-cartbar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 260;
                display: flex; align-items: center; gap: 12px; width: 100%; border: 0; cursor: pointer;
                padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
                background: linear-gradient(100deg, #0a1712, #0f2a1e); color: #eef2f0;
                box-shadow: 0 -12px 30px -18px rgba(9,20,15,.7); }
            .rpos-cartbar .cb-cart { position: relative; display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,.1); flex: 0 0 auto; }
            .rpos-cartbar .cb-cart svg { width: 20px; height: 20px; }
            .rpos-cartbar .cb-count { position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px; background: #22dd85; color: #06231a; font-size: 12px; font-weight: 850; display: grid; place-items: center; }
            .rpos-cartbar .cb-text { display: flex; flex-direction: column; line-height: 1.15; text-align: left; }
            .rpos-cartbar .cb-text strong { font-size: 15px; font-weight: 800; }
            .rpos-cartbar .cb-text span { font-size: 11.5px; color: #9db3a8; }
            .rpos-cartbar .cb-total { margin-left: auto; font-weight: 850; font-size: 18px; font-variant-numeric: tabular-nums; }
            .rpos-cartbar .cb-chev { width: 20px; height: 20px; flex: 0 0 auto; opacity: .85; }
            .rpos-cartbar.is-empty { opacity: .5; pointer-events: none; }

            /* Cart as a bottom sheet */
            .rpos-cart { position: fixed; left: 0; right: 0; bottom: 0; z-index: 320;
                width: 100%; height: auto; max-height: 92dvh; border-right: 0; border-top: 0;
                border-radius: 20px 20px 0 0; box-shadow: 0 -24px 50px -18px rgba(9,20,15,.55);
                transform: translateY(102%); transition: transform .28s cubic-bezier(.32,.72,0,1); will-change: transform; }
            body.rpos-cart-open .rpos-cart { transform: translateY(0); }
            body.rpos-cart-open::before { content: ''; position: fixed; inset: 0; z-index: 300; background: rgba(9,20,15,.5); }

            .rpos-cart-handle { display: flex; align-items: center; gap: 12px; position: relative; padding: 12px 14px 8px; border-bottom: 1px solid #eef2f0; }
            .rpos-cart-handle .grip { position: absolute; top: 6px; left: 50%; transform: translateX(-50%); width: 40px; height: 4px; border-radius: 999px; background: #cfe0d8; }
            .rpos-cart-handle strong { font-size: 15px; margin-top: 3px; }
            .rpos-cart-handle button { margin-left: auto; margin-top: 3px; border: 0; background: var(--brand-050); color: var(--brand-strong); font-weight: 800; font-size: 13px; border-radius: 999px; padding: 7px 16px; cursor: pointer; }

            .rpos-customer { padding: 10px 14px; }
            .rpos-lines { flex: 1 1 auto; min-height: 90px; max-height: none; padding: 10px 12px; gap: 8px; }
            .rpos-empty-cart { padding: 22px; font-size: 13px; }
            .rpos-empty-cart svg { width: 34px; height: 34px; }
            .rpos-line { grid-template-columns: minmax(0, 1fr) auto auto; gap: 10px; padding: 9px 11px; border-radius: 11px; }
            .rpos-line .nm { grid-column: 1 / -1; font-size: 13.5px; }
            .rpos-line .lt { min-width: 70px; font-size: 14px; }
            .rpos-qty button { width: 34px; height: 34px; }
            .rpos-qty input { width: 42px; height: 34px; }
            .rpos-summary { flex: 0 0 auto; padding: 10px 14px calc(12px + env(safe-area-inset-bottom, 0px)); }
            .rpos-sline { font-size: 13px; padding: 2px 0; }
            .rpos-total { margin-top: 8px; padding: 11px 14px; border-radius: 11px; }
            .rpos-total span { font-size: 13px; }
            .rpos-total strong { font-size: 22px; }
            .rpos-actions { grid-template-columns: repeat(6, 1fr); gap: 6px; margin-top: 9px; }
            .rpos-act { min-height: 44px; padding: 6px 2px; border-radius: 10px; font-size: 10px; }
            .rpos-act svg { width: 17px; height: 17px; }
            .rpos-pay { margin-top: 9px; min-height: 50px; padding: 13px 16px; border-radius: 12px; font-size: 16px; }

            #rpos-pay-dialog { width: min(560px, calc(100vw - 16px)) !important; max-height: calc(100dvh - 16px); }
            #rpos-pay-dialog .dialog-body > div:first-child { grid-template-columns: 1fr !important; gap: 12px !important; }
            .pay-amount-box { padding: 11px; }
            .pay-amount-box strong { font-size: 25px; }
            .pay-methods { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .pay-method { padding: 12px 8px; font-size: 13px; }
            .rpos-keypad { gap: 7px; }
            .rpos-keypad button { padding: 13px; font-size: 18px; border-radius: 10px; }
            .pay-status { padding: 11px 13px; }
            .pay-status strong { font-size: 19px; }
            .rpos-success .succ-actions { grid-template-columns: 1fr; }
        }

        @media (max-width: 520px) {
            .rpos-top-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .rpos-tbtn.ghost { display: none; }
            .rpos-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .rpos-tile { min-height: 138px; }
            .rpos-tile-img { height: 58px; }
            .rpos-act { min-height: 42px; font-size: 0; gap: 0; }
            .rpos-act svg { width: 18px; height: 18px; }
            .pay-methods { grid-template-columns: 1fr; }
            .pay-quick button { flex-basis: calc(50% - 4px); }
        }
    </style>

    @if (! $activeTill)
        {{-- ============ Open till first ============ --}}
        <div style="min-height: calc(100vh - 90px); display: grid; place-items: center; padding: 24px;">
            <div class="panel" style="max-width: 460px; width: 100%;">
                <div class="panel-header"><div><h2 class="panel-title">Open your till to start selling</h2><p class="subtle">The Retail POS needs an open till session for this branch.</p></div></div>
                <div class="panel-body">
                    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.tills.open') }}">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <div class="field"><label>Branch</label><select name="branch_id" required>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                        <div class="field"><label>Opening cash float</label><input name="opening_float" type="text" inputmode="decimal" data-money-input value="0.00"></div>
                        <div class="field"><label>Opening note</label><textarea name="opening_note" rows="2" placeholder="Optional"></textarea></div>
                        <div class="button-row"><button class="btn primary" type="submit">Open till &amp; start</button></div>
                    </form>
                </div>
            </div>
        </div>
    @else
    <div class="rpos" data-rpos
         data-tenant="{{ $tenant->id }}"
         data-currency="{{ $currency }}"
         data-walkin-id="{{ $walkInCustomer->id }}"
         data-walkin-name="{{ $walkInCustomer->name }}"
         data-quick-customer-url="{{ route('admin.sales.customers.quick') }}">

        @if ($errors->any())
            <div style="background:#fef2f2; color:#b42318; border-bottom:1px solid #fecaca; padding:9px 16px; font-weight:650; font-size:13px; flex:0 0 auto;">
                ⚠ {{ $errors->first() }}
            </div>
        @endif

        {{-- ======================= TOP BAR ======================= --}}
        <header class="rpos-topbar">
            <button class="rpos-menu-toggle" type="button" data-rpos-menu-toggle aria-label="Open menu">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <div class="rpos-brand"><span class="mark"><svg viewBox="0 0 24 24"><use href="#i-spark"/></svg></span></div>
            <div class="rpos-context">
                <strong>{{ $activeTill->branch?->name ?? 'Retail POS' }}</strong>
                <span>{{ $activeTill->session_number }} · Cashier: {{ $cashier->name }}</span>
            </div>
            <span class="rpos-sync"><span class="dot"></span> Online · synced</span>
            <div class="rpos-top-actions">
                <button class="rpos-tbtn" type="button" data-dialog-open="rpos-till-dialog">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v10H3zM3 11h18M7 15h3"/></svg>
                    Till &amp; Cash
                </button>
                <button class="rpos-tbtn" type="button" data-dialog-open="rpos-held-dialog">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6v12M16 6v12M4 6h16"/></svg>
                    Held <span class="count" data-held-count>0</span>
                </button>
                <button class="rpos-tbtn" type="button" data-dialog-open="rpos-orders-dialog">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                    Orders <span class="count">{{ $sessionOrders->count() }}</span>
                </button>
                <button class="rpos-tbtn ghost" type="button" data-fullscreen title="Toggle full screen">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 9V5a1 1 0 0 1 1-1h4M20 9V5a1 1 0 0 0-1-1h-4M4 15v4a1 1 0 0 0 1 1h4M20 15v4a1 1 0 0 1-1 1h-4"/></svg>
                </button>
            </div>
        </header>

        <div class="rpos-body">
            {{-- ======================= CART ======================= --}}
            <aside class="rpos-cart">
                <div class="rpos-cart-handle">
                    <span class="grip"></span>
                    <strong>Current order</strong>
                    <button type="button" data-cart-close>Done</button>
                </div>
                <div class="rpos-customer" data-dialog-open="rpos-customer-dialog">
                    <span class="avatar" data-customer-initial>W</span>
                    <span class="who"><strong data-customer-name>{{ $walkInCustomer->name }}</strong><span data-customer-sub>Walk-in customer</span></span>
                    <span class="chg">Change ›</span>
                </div>

                <div class="rpos-lines" data-cart-lines>
                    <div class="rpos-empty-cart" data-empty-cart>
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 3H5l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/></svg>
                        <div>Tap a product to add it to the cart</div>
                    </div>
                </div>

                <div class="rpos-summary">
                    <div class="rpos-sline"><span>Subtotal</span><span data-sum-subtotal>{{ $currency }} 0.00</span></div>
                    <div class="rpos-sline"><span>Tax</span><span data-sum-tax>{{ $currency }} 0.00</span></div>
                    <div class="rpos-sline disc" data-coupon-line hidden><span>Coupon <em data-coupon-tag></em></span><span data-sum-coupon>-{{ $currency }} 0.00</span></div>
                    <div class="rpos-sline disc" data-discount-line hidden><span>Discount</span><span data-sum-discount>-{{ $currency }} 0.00</span></div>
                    <div class="rpos-sline" data-shipping-line hidden><span>Delivery</span><span data-sum-shipping>{{ $currency }} 0.00</span></div>
                    <div class="rpos-total"><span>Total</span><strong data-sum-total>{{ $currency }} 0.00</strong></div>

                    <div class="rpos-actions">
                        <button class="rpos-act c-coupon" type="button" data-dialog-open="rpos-coupon-dialog"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H4v5l10 10 5-5L9 5ZM7.5 7.5h.01"/></svg>Coupon</button>
                        <button class="rpos-act c-discount" type="button" data-dialog-open="rpos-discount-dialog"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 15 9M9.5 9.5h.01M14.5 14.5h.01M4 8v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2Z"/></svg>Discount</button>
                        <button class="rpos-act c-delivery" type="button" data-dialog-open="rpos-delivery-dialog"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h11v10H3zM14 9h4l3 3v4h-7M7.5 20a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Zm10 0a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Z"/></svg>Delivery</button>
                        <button class="rpos-act c-note" type="button" data-dialog-open="rpos-note-dialog"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 10h16M4 15h10"/></svg>Note</button>
                        <button class="rpos-act c-hold" type="button" data-hold><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6v12M15 6v12"/></svg>Hold</button>
                        <button class="rpos-act void" type="button" data-void><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>Void</button>
                    </div>

                    <button class="rpos-pay" type="button" data-open-pay disabled>
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v10H3zM3 11h18M7 15h2"/></svg>
                        Pay
                    </button>
                </div>
            </aside>

            {{-- ======================= PRODUCTS ======================= --}}
            <section class="rpos-products">
                <div class="rpos-search">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.5-4.5M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z"/></svg>
                    <input type="search" data-product-search placeholder="Search products by name or SKU..." autocomplete="off">
                </div>

                <div class="rpos-cats" data-cats>
                    <button class="rpos-cat active" type="button" data-cat="pinned"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 2.5 5.5L20 9l-4 4 1 6-5-3-5 3 1-6-4-4 5.5-.5L12 3Z"/></svg>Pinned</button>
                    <button class="rpos-cat" type="button" data-cat="all">All items</button>
                    @foreach ($categories as $category)
                        <button class="rpos-cat" type="button" data-cat="{{ $category->id }}">{{ $category->name }}</button>
                    @endforeach
                </div>

                <div class="rpos-grid" data-grid>
                    @forelse ($variants as $variant)
                        @php
                            $selectedTaxRate = $variant->product?->taxes?->sum(fn ($tax) => (float) $tax->rate) ?? 0;
                            $taxRate = $variant->tax_behavior->value === 'taxable' ? (float) ($selectedTaxRate > 0 ? $selectedTaxRate : ($variant->tax_rate ?? $variant->product?->tax_rate ?? 0)) : 0;
                            $catId = $variant->product?->category?->id ?? 0;
                            $hue = crc32((string) ($variant->product?->name ?? $variant->sku)) % 360;
                        @endphp
                        <div class="rpos-tile" role="button" tabindex="0"
                            data-tile
                            data-variant-id="{{ $variant->id }}"
                            data-category-id="{{ $catId }}"
                            data-name="{{ $tileName($variant) }}"
                            data-price="{{ $variant->selling_price_minor / 100 }}"
                            data-tax-rate="{{ $taxRate }}"
                            data-sku="{{ $variant->sku }}">
                            <button class="rpos-pin" type="button" data-pin aria-label="Pin product"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M14 3l7 7-4 1-4 4-1 5-2-2-4 4-1-1 4-4-2-2 5-1 4-4 1-4Z"/></svg></button>
                            @php $tileImg = $tileImage($variant); @endphp
                            <span class="rpos-tile-img" style="background: hsl({{ $hue }} 55% 42%);">{{ strtoupper(mb_substr($variant->product?->name ?? 'P', 0, 1)) }}@if ($tileImg)<img src="{{ $tileImg }}" alt="" loading="lazy" onerror="this.remove()">@endif</span>
                            <span class="rpos-tile-name">{{ $tileName($variant) }}</span>
                            <span class="rpos-tile-foot">
                                <span class="rpos-tile-price">{{ $currency }} {{ $money($variant->selling_price_minor) }}</span>
                            </span>
                        </div>
                        {{-- SKU kept in data-sku for search --}}
                    @empty
                        <div class="rpos-grid-empty">No products yet. Add products in <a href="{{ route('admin.catalog.index', ['tenant' => $tenant->id]) }}">Product &amp; Services</a>.</div>
                    @endforelse
                    <div class="rpos-grid-empty" data-grid-empty hidden>No products match this view.</div>
                </div>
            </section>
        </div>

        {{-- Mobile cart bar — opens the cart sheet --}}
        <button class="rpos-cartbar is-empty" type="button" data-cart-open aria-label="View order">
            <span class="cb-cart">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 3H5l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/><circle cx="9" cy="20" r="1.4" fill="currentColor" stroke="none"/><circle cx="17" cy="20" r="1.4" fill="currentColor" stroke="none"/></svg>
                <span class="cb-count" data-cartbar-count>0</span>
            </span>
            <span class="cb-text"><strong>View order</strong><span data-cartbar-items>0 items</span></span>
            <span class="cb-total" data-cartbar-total>{{ $currency }} 0.00</span>
            <svg class="cb-chev" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 15 6-6 6 6"/></svg>
        </button>
    </div>

    {{-- ======================= HIDDEN ORDER FORM ======================= --}}
    <form method="POST" action="{{ route('admin.sales.orders.store') }}" data-pos-form style="display:none;">
        @csrf
        <input type="hidden" name="source" value="retail_pos">
        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
        <input type="hidden" name="sales_till_session_id" value="{{ $activeTill->id }}">
        <input type="hidden" name="branch_id" value="{{ $activeTill->branch_id }}">
        <input type="hidden" name="inventory_location_id" value="{{ $posLocations->first()?->id }}">
        <input type="hidden" name="order_date" value="{{ now()->toDateString() }}">
        <input type="hidden" name="customer_id" data-f-customer value="{{ $walkInCustomer->id }}">
        <input type="hidden" name="payment_method" data-f-method value="Cash">
        <input type="hidden" name="business_payment_account_id" data-f-payment-account value="">
        <input type="hidden" name="amount_paid" data-f-paid value="0">
        <input type="hidden" name="coupon_code" data-f-coupon value="">
        <input type="hidden" name="admin_discount_type" data-f-disc-type value="amount">
        <input type="hidden" name="admin_discount_value" data-f-disc-value value="0">
        <input type="hidden" name="delivery_method" data-f-delivery value="">
        <input type="hidden" name="shipping" data-f-shipping value="0">
        <input type="hidden" name="delivery_status" data-f-delivery-status value="delivered">
        <input type="hidden" name="delivery_address" data-f-delivery-address value="">
        <input type="hidden" name="notes" data-f-notes value="">
        <input type="hidden" name="is_credit_sale" data-f-credit value="0">
        <div data-f-items></div>
    </form>

    {{-- coupon reference data --}}
    <div hidden data-coupon-bank>
        @foreach ($coupons as $coupon)
            <span data-coupon data-code="{{ $coupon->code }}" data-active="{{ $coupon->is_active ? 1 : 0 }}" data-type="{{ $coupon->discount_type->value }}" data-amount="{{ $coupon->discount_value_minor / 100 }}" data-percent="{{ $coupon->discount_percent }}"></span>
        @endforeach
    </div>

    @include('sales::admin.partials.retail-pos-dialogs')
    @include('sales::admin.partials.retail-pos-till')

    @foreach ($recentOrders as $order)
        @include('sales::admin.partials.thermal-receipt-dialog', ['order' => $order])
    @endforeach

    @php $justPaidOrder = session('receipt_order_id') ? $recentOrders->firstWhere('id', session('receipt_order_id')) : null; @endphp
    @if ($justPaidOrder)
        <dialog class="dialog rpos-success" id="rpos-success-dialog" style="width:min(460px,calc(100vw - 24px));" data-success-order="{{ $justPaidOrder->id }}">
            <div class="dialog-body">
                <div class="succ-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg></div>
                <h2>Payment Confirmed!</h2>
                <p class="succ-sub">{{ $currency }} {{ $money($justPaidOrder->total_minor) }} charged · {{ $justPaidOrder->order_number }}</p>
                <div class="succ-actions">
                    <button class="btn secondary" type="button" data-success-print>
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18H5a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1M6 14h12v6H6z"/></svg>
                        Print
                    </button>
                    <button class="btn secondary" type="button" data-success-email data-customer-email="{{ $justPaidOrder->customer?->email }}">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                        Email
                    </button>
                    <button class="succ-newsale" type="button" data-success-newsale>New Sale</button>
                </div>
            </div>
        </dialog>
    @endif

    <div class="rpos-toast" data-toast></div>

    <script src="{{ asset('js/retail-pos.js') }}?v=8"></script>
    <script>
        window.storebootReceiptOrderId = @json(session('receipt_order_id'));
    </script>
    @endif
</x-layouts.admin>
