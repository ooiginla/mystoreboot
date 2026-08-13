@php
    $tenantSettings = $tenant->settings ?? [];
    $stepMeta = [
        1 => ['title' => 'Your address', 'short' => 'Address', 'sub' => 'Where is your business located?'],
        2 => ['title' => 'Your look', 'short' => 'Branding', 'sub' => 'Add your logo and brand colours.'],
        3 => ['title' => 'Get paid', 'short' => 'Payments', 'sub' => 'Set up your settlement account.'],
        4 => ['title' => 'First product', 'short' => 'Product', 'sub' => 'Add something to sell.'],
        5 => ['title' => 'All set!', 'short' => 'Finish', 'sub' => 'Your store is live.'],
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set up your store · Storeboot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand:#009a53; --brand-strong:#027a45; --brand-050:#ecfdf3; --brand-100:#d1fadf; --brand-ring:rgba(6,193,104,.18); --ink:#0f1b16; --ink-soft:#334155; --muted:#64748b; --line:#e4eae7; --panel:#fff; --soft:#f4f7f5; --danger:#dc2626; --radius:14px; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--soft); color:var(--ink); font-family:'Inter',ui-sans-serif,system-ui,sans-serif; -webkit-font-smoothing:antialiased; line-height:1.5; }
        .wrap { min-height:100vh; display:flex; flex-direction:column; }
        .topbar { background:#fff; border-bottom:1px solid var(--line); padding:22px 20px 4px; }
        .brand { display:flex; align-items:center; justify-content:center; gap:10px; font-weight:800; font-size:19px; color:var(--ink); margin-bottom:22px; }
        .brand-mark { width:32px; height:32px; border-radius:9px; display:grid; place-items:center; background:linear-gradient(140deg,#22dd85,#009a53); }
        .brand-mark svg { width:18px; height:18px; }
        /* top horizontal stepper */
        .stepper { display:flex; max-width:640px; margin:0 auto; padding-bottom:26px; }
        .stepper-item { flex:1; position:relative; display:flex; flex-direction:column; align-items:center; gap:9px; }
        .stepper-item::before { content:""; position:absolute; top:16px; left:-50%; width:100%; height:3px; background:var(--line); z-index:0; }
        .stepper-item:first-child::before { display:none; }
        .stepper-item.done::before, .stepper-item.active::before { background:var(--brand); }
        .stepper-dot { position:relative; z-index:1; width:34px; height:34px; border-radius:50%; display:grid; place-items:center; font-weight:700; font-size:14px; background:#fff; border:2px solid var(--line); color:var(--muted); transition:background .2s,border-color .2s; }
        .stepper-item.done .stepper-dot { background:var(--brand); border-color:var(--brand); color:#fff; }
        .stepper-item.active .stepper-dot { background:var(--brand); border-color:var(--brand); color:#fff; box-shadow:0 0 0 4px var(--brand-ring); }
        .stepper-label { font-size:12.5px; font-weight:600; color:var(--muted); text-align:center; line-height:1.25; }
        .stepper-item.done .stepper-label, .stepper-item.active .stepper-label { color:var(--brand-strong); }
        @media (max-width:520px){ .stepper-dot{ width:30px; height:30px; font-size:13px; } .stepper-item::before{ top:14px; } .stepper-label{ font-size:11px; } }
        @media (max-width:380px){ .stepper-label{ display:none; } }
        .main { flex:1; display:flex; align-items:flex-start; justify-content:center; padding:40px 20px; overflow-y:auto; }
        .card { width:min(560px,100%); background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); box-shadow:0 8px 30px -12px rgba(16,24,40,.14); padding:32px; }
        .eyebrow { color:var(--brand-strong); font-weight:700; font-size:11.5px; text-transform:uppercase; letter-spacing:.06em; }
        h1 { margin:6px 0 4px; font-size:24px; font-weight:800; letter-spacing:-.02em; }
        .lead { color:var(--muted); font-size:14.5px; margin:0 0 22px; }
        .field { display:grid; gap:6px; margin-bottom:15px; position:relative; }
        label { font-size:13px; font-weight:650; color:var(--ink-soft); }
        input[type=text], input[type=number], textarea, select { width:100%; padding:11px 13px; border:1px solid #d4ddd8; border-radius:10px; font:inherit; color:var(--ink); background:#fff; }
        input:focus, textarea:focus, select:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3.5px var(--brand-ring); }
        textarea { min-height:88px; resize:vertical; }
        .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        @media (max-width:520px){ .grid2{ grid-template-columns:1fr; } }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid transparent; border-radius:10px; padding:12px 20px; font-weight:700; font-size:14.5px; cursor:pointer; text-decoration:none; }
        .btn.primary { background:var(--brand); color:#fff; box-shadow:0 4px 12px -2px rgba(6,193,104,.4); width:100%; }
        .btn.primary:hover { background:var(--brand-strong); }
        .btn.ghost { background:transparent; color:var(--muted); }
        .actions { margin-top:8px; }
        .skip { display:block; text-align:center; margin-top:14px; color:var(--muted); font-size:13px; }
        .alert { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:10px; padding:11px 13px; font-size:13.5px; margin-bottom:16px; }
        .hint { color:var(--muted); font-size:12px; }
        /* username + suffix */
        .suffix-input { display:flex; align-items:stretch; border:1px solid #d4ddd8; border-radius:10px; overflow:hidden; background:#fff; }
        .suffix-input:focus-within { border-color:var(--brand); box-shadow:0 0 0 3.5px var(--brand-ring); }
        .suffix-input input[type=text] { border:0; border-radius:0; box-shadow:none; flex:1; min-width:0; }
        .suffix-input input[type=text]:focus { box-shadow:none; }
        .suffix-input .suffix { display:flex; align-items:center; padding:0 12px; background:var(--soft); color:var(--muted); font-size:13.5px; font-weight:600; white-space:nowrap; border-left:1px solid #e3ebe6; }
        /* color picker */
        .color-row { display:flex; align-items:center; gap:10px; border:1px solid #d4ddd8; border-radius:10px; padding:6px 10px; }
        .color-row input[type=color] { width:38px; height:38px; border:0; background:none; padding:0; cursor:pointer; }
        .color-row input[type=text] { border:0; padding:6px 4px; box-shadow:none; }
        .color-row input[type=text]:focus { box-shadow:none; }
        /* uploader */
        .uploader { border:1.5px dashed #c6d2cb; border-radius:12px; padding:18px; text-align:center; cursor:pointer; background:var(--soft); }
        .uploader:hover { border-color:var(--brand); background:var(--brand-050); }
        .uploader img { max-height:120px; border-radius:8px; margin:0 auto 8px; display:block; }
        .uploader input { display:none; }
        /* bank dropdown */
        .bank-options { position:absolute; z-index:40; top:100%; left:0; right:0; margin-top:6px; max-height:240px; overflow-y:auto; background:#fff; border:1px solid #d4ddd8; border-radius:10px; box-shadow:0 14px 30px -10px rgba(16,24,40,.28); padding:6px; }
        .bank-options button { display:block; width:100%; text-align:left; border:0; background:#fff; padding:10px 12px; border-radius:7px; cursor:pointer; font:inherit; font-weight:600; }
        .bank-options button:hover { background:var(--brand-050); color:var(--brand-strong); }
        .verified { background:var(--soft); }
        .status { font-size:12.5px; margin-top:6px; }
        .check-card { display:flex; align-items:center; gap:11px; border:1.5px solid var(--line); border-radius:11px; padding:12px 14px; cursor:pointer; font-weight:650; font-size:13.5px; color:var(--ink-soft); margin-bottom:10px; }
        .check-card:has(input:checked) { border-color:var(--brand); background:var(--brand-050); color:var(--brand-strong); box-shadow:inset 0 0 0 1px var(--brand); }
        .check-card input { width:19px; height:19px; accent-color:var(--brand); }
        .check-card small { display:block; font-weight:500; color:var(--muted); font-size:12px; }
        /* congrats */
        .congrats { text-align:center; padding:8px 0 4px; }
        .congrats .big { font-size:54px; line-height:1; margin-bottom:10px; }
        .store-link { display:flex; align-items:center; justify-content:space-between; gap:12px; background:var(--brand-050); border:1px solid var(--brand-100); border-radius:12px; padding:14px 16px; margin:20px 0; font-weight:700; color:var(--brand-strong); word-break:break-all; }
        .confetti { position:fixed; top:-40px; font-size:26px; pointer-events:none; z-index:50; animation:fall linear forwards; }
        @keyframes fall { to { transform:translateY(105vh) rotate(360deg); opacity:.9; } }
    </style>
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <div class="brand">
            <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none"><path d="M13 2 4.5 13H11l-1 9 8.5-11H12l1-9Z" fill="#fff"/></svg></span>
            Storeboot
        </div>
        <div class="stepper">
            @foreach ($stepMeta as $n => $meta)
                @if ($n <= $lastStep)
                    <div class="stepper-item {{ $n === $step ? 'active' : ($n < $step ? 'done' : '') }}">
                        <span class="stepper-dot">{{ $n < $step ? '✓' : $n }}</span>
                        <span class="stepper-label">{{ $meta['short'] }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    </header>

    <main class="main">
        <div class="card">
            <div class="eyebrow">Step {{ min($step, 4) }} of 4</div>
            <h1>{{ $stepMeta[$step]['title'] }}</h1>
            <p class="lead">{{ $stepMeta[$step]['sub'] }}</p>

            @if ($errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif

            {{-- STEP 1: ADDRESS --}}
            @if ($step === 1)
                <form method="POST" action="{{ route('onboarding.address') }}" data-address-form data-username-check="{{ route('onboarding.username.check') }}">
                    @csrf
                    <div class="field">
                        <label>Store address <span class="hint">(your online store link)</span></label>
                        <div class="suffix-input">
                            <input type="text" name="username" id="store-username" required autocomplete="off" spellcheck="false"
                                   value="{{ old('username', $store->username) }}" placeholder="your-store">
                            <span class="suffix">.{{ $storeDomainSuffix }}</span>
                        </div>
                        <div class="status" data-username-status></div>
                    </div>
                    <div class="field">
                        <label>Business address</label>
                        <input type="text" name="business_address" required value="{{ old('business_address', $store->address ?? ($tenantSettings['business_address'] ?? '')) }}" placeholder="12 Adeola Odeku Street">
                    </div>
                    <div class="grid2">
                        <div class="field"><label>City</label><input type="text" name="city" required value="{{ old('city', $store->city ?? ($tenantSettings['city'] ?? '')) }}" placeholder="Lagos"></div>
                        <div class="field"><label>State</label><input type="text" name="state" required value="{{ old('state', $store->state ?? ($tenantSettings['state'] ?? '')) }}" placeholder="Lagos"></div>
                    </div>
                    <div class="actions"><button class="btn primary" type="submit">Continue →</button></div>
                </form>

            {{-- STEP 2: THEME --}}
            @elseif ($step === 2)
                <form method="POST" action="{{ route('onboarding.theme') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="field">
                        <label>Logo <span class="hint">(optional)</span></label>
                        <label class="uploader" id="logo-drop">
                            <img id="logo-preview" src="{{ $store->logo_path ? '/storage/'.ltrim($store->logo_path,'/') : '' }}" @if (! $store->logo_path) style="display:none" @endif alt="">
                            <div id="logo-text">Click to upload your logo (PNG or JPG)</div>
                            <input type="file" name="logo" accept="image/*" id="logo-input">
                        </label>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label>Primary colour</label>
                            <div class="color-row"><input type="color" value="{{ old('theme_primary_color', $store->theme_primary_color ?: '#009a53') }}" data-color-for="primary"><input type="text" name="theme_primary_color" value="{{ old('theme_primary_color', $store->theme_primary_color ?: '#009a53') }}" data-color-text="primary"></div>
                        </div>
                        <div class="field">
                            <label>Secondary colour</label>
                            <div class="color-row"><input type="color" value="{{ old('theme_secondary_color', $store->theme_secondary_color ?: '#f59e0b') }}" data-color-for="secondary"><input type="text" name="theme_secondary_color" value="{{ old('theme_secondary_color', $store->theme_secondary_color ?: '#f59e0b') }}" data-color-text="secondary"></div>
                        </div>
                    </div>
                    <div class="actions"><button class="btn primary" type="submit">Continue →</button></div>
                </form>

            {{-- STEP 3: BANK --}}
            @elseif ($step === 3)
                <form method="POST" action="{{ route('onboarding.bank') }}"
                      data-bank data-tenant="{{ $tenant->id }}"
                      data-banks-url="{{ route('admin.business.banks.index', ['tenant' => $tenant->id]) }}"
                      data-resolve-url="{{ route('admin.business.resolve-account') }}">
                    @csrf
                    <div class="field">
                        <label>Your bank</label>
                        <input type="text" autocomplete="off" placeholder="Search your bank…" data-bank-search>
                        <input type="hidden" name="bank_code" data-bank-code>
                        <div class="bank-options" data-bank-options hidden></div>
                    </div>
                    <div class="field">
                        <label>Account number</label>
                        <input type="text" name="account_number" inputmode="numeric" maxlength="10" placeholder="10-digit NUBAN" data-account-number>
                    </div>
                    <div class="field">
                        <label>Account name</label>
                        <input type="text" class="verified" readonly placeholder="Verified automatically" data-account-name>
                        <div class="status" data-bank-status></div>
                    </div>
                    <div class="field">
                        <label>How customers pay you online</label>
                        <label class="check-card"><input type="checkbox" name="store_payment_methods[]" value="storeboot_paystack" checked> <span>Card & bank transfer <small>Secure online payments via Paystack, settled to the account above.</small></span></label>
                        <label class="check-card"><input type="checkbox" name="store_payment_methods[]" value="pay_on_delivery"> <span>Pay on delivery <small>Customer pays when the order arrives.</small></span></label>
                    </div>
                    <div class="actions"><button class="btn primary" type="submit">Continue →</button></div>
                    @unless ($paystackConfigured)<p class="hint" style="margin-top:10px;">Bank verification isn't configured on this environment yet.</p>@endunless
                </form>

            {{-- STEP 4: PRODUCT --}}
            @elseif ($step === 4)
                <form method="POST" action="{{ route('onboarding.product') }}" enctype="multipart/form-data" data-product data-photo-url="{{ route('onboarding.product.photo') }}">
                    @csrf
                    <div class="field">
                        <label>Product photo</label>
                        <label class="uploader" id="prod-drop">
                            <img id="prod-preview" style="display:none" alt="">
                            <div id="prod-text">Click to upload a product photo</div>
                            <input type="file" name="image" accept="image/*" id="prod-input">
                        </label>
                        <button type="button" class="btn ghost" id="autofill-btn" style="margin-top:8px; border:1px solid var(--line);">✨ Autofill details from photo</button>
                        <div class="status" id="autofill-status"></div>
                    </div>
                    <div class="field"><label>Title</label><input type="text" name="name" required value="{{ old('name') }}" placeholder="e.g. Vintage Leather Bag"></div>
                    <div class="field">
                        <label>Category</label>
                        <input type="text" name="category" list="cat-list" value="{{ old('category') }}" placeholder="e.g. Bags">
                        <datalist id="cat-list">@foreach ($categories as $c)<option value="{{ $c->name }}">@endforeach</datalist>
                    </div>
                    <div class="grid2">
                        <div class="field"><label>Selling price ({{ $currency }})</label><input type="text" inputmode="decimal" data-money name="base_price" required value="{{ old('base_price') }}" placeholder="0.00"></div>
                        <div class="field"><label>Cost price ({{ $currency }}) <span class="hint">optional</span></label><input type="text" inputmode="decimal" data-money name="base_cost_price" value="{{ old('base_cost_price') }}" placeholder="0.00"></div>
                    </div>
                    <div class="field"><label>Description</label><textarea name="description" placeholder="Tell customers about this product…">{{ old('description') }}</textarea></div>
                    <div class="field"><label>Tags <span class="hint">comma separated</span></label><input type="text" name="tags" value="{{ old('tags') }}" placeholder="leather, handmade, gift"></div>
                    <div class="actions"><button class="btn primary" type="submit">Finish setup →</button></div>
                </form>

            {{-- STEP 5: CONGRATS --}}
            @elseif ($step === 5)
                <div class="congrats">
                    <div class="big">🎉</div>
                    <h1 style="font-size:26px;">Your store is live!</h1>
                    <p class="lead">Congratulations — {{ $store->store_name ?: $tenant->name }} is ready to take orders.</p>
                    <div class="store-link">
                        <span>{{ $storeUrl }}</span>
                        <a class="btn primary" style="width:auto; padding:9px 16px;" href="{{ $storeUrl }}" target="_blank" rel="noopener">Visit store ↗</a>
                    </div>
                    <form method="POST" action="{{ route('onboarding.complete') }}">
                        @csrf
                        <button class="btn primary" type="submit">Go to my dashboard →</button>
                    </form>
                </div>
            @endif
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

    // Store username (step 1): sanitise input + live availability
    const addrForm = document.querySelector('[data-address-form]');
    if (addrForm) {
        const uInput = document.getElementById('store-username');
        const uStatus = addrForm.querySelector('[data-username-status]');
        const sanitize = (v) => v.toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-{2,}/g,'-').replace(/^-+/,'').slice(0,63);
        let timer;
        const check = async () => {
            const v = uInput.value.trim();
            if (!v) { uStatus.textContent=''; return; }
            uStatus.textContent = 'Checking availability…'; uStatus.style.color = 'var(--muted)';
            try {
                const b = new URLSearchParams(); b.append('_token',csrf); b.append('username',v);
                const res = await fetch(addrForm.dataset.usernameCheck,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded',Accept:'application/json'},body:b});
                const d = await res.json();
                uStatus.textContent = (d.available ? '✓ ' : '✕ ') + d.message;
                uStatus.style.color = d.available ? 'var(--brand-strong)' : 'var(--danger)';
            } catch(e) { uStatus.textContent=''; }
        };
        uInput.addEventListener('input', () => {
            const clean = sanitize(uInput.value);
            if (clean !== uInput.value) uInput.value = clean;
            clearTimeout(timer); timer = setTimeout(check, 400);
        });
        uInput.addEventListener('blur', check);
        check();
    }

    // Money inputs (step 4): live thousand separators, stripped before submit
    const formatMoney = (el) => {
        let v = el.value.replace(/[^0-9.]/g, '');
        const dot = v.indexOf('.');
        if (dot !== -1) v = v.slice(0, dot + 1) + v.slice(dot + 1).replace(/\./g, '');
        let [int, dec] = v.split('.');
        int = (int || '').replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        el.value = dec !== undefined ? int + '.' + dec.slice(0, 2) : int;
    };
    document.querySelectorAll('[data-money]').forEach((el) => {
        if (el.value) formatMoney(el);
        el.addEventListener('input', () => formatMoney(el));
        el.closest('form')?.addEventListener('submit', () => { el.value = el.value.replace(/,/g, ''); });
    });

    // Color pickers
    document.querySelectorAll('[data-color-for]').forEach((picker) => {
        const text = document.querySelector('[data-color-text="'+picker.dataset.colorFor+'"]');
        picker.addEventListener('input', () => { text.value = picker.value; });
        text.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value; });
    });

    // Image previews
    const bindPreview = (inputId, imgId, textId) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('change', () => {
            const file = input.files[0]; if (!file) return;
            const url = URL.createObjectURL(file);
            const img = document.getElementById(imgId); img.src = url; img.style.display = 'block';
            const t = document.getElementById(textId); if (t) t.textContent = file.name;
        });
    };
    bindPreview('logo-input','logo-preview','logo-text');
    bindPreview('prod-input','prod-preview','prod-text');

    // Bank picker
    const bankForm = document.querySelector('[data-bank]');
    if (bankForm) {
        const search = bankForm.querySelector('[data-bank-search]');
        const code = bankForm.querySelector('[data-bank-code]');
        const panel = bankForm.querySelector('[data-bank-options]');
        const acct = bankForm.querySelector('[data-account-number]');
        const name = bankForm.querySelector('[data-account-name]');
        const status = bankForm.querySelector('[data-bank-status]');
        let banks = [], loaded = false;
        const load = async () => {
            if (loaded) return; loaded = true;
            try { const d = await (await fetch(bankForm.dataset.banksUrl, {credentials:'same-origin',headers:{Accept:'application/json'}})).json(); banks = d.banks || []; } catch(e){}
        };
        const render = (q) => {
            const t = (q||'').toLowerCase().trim();
            const m = banks.filter(b => b.name.toLowerCase().includes(t)).slice(0,40);
            panel.innerHTML = m.length ? m.map(b=>`<button type="button" data-code="${b.code}">${b.name}</button>`).join('') : '<div style="padding:10px;color:#94a3b8">No banks found</div>';
            panel.hidden = false;
        };
        const resolve = async () => {
            const n = (acct.value||'').trim();
            if (!code.value || !/^[0-9]{10}$/.test(n)) return;
            status.textContent = 'Verifying account…'; status.style.color = 'var(--muted)';
            try {
                const b = new URLSearchParams(); b.append('_token',csrf); b.append('tenant_id',bankForm.dataset.tenant); b.append('account_number',n); b.append('bank_code',code.value);
                const res = await fetch(bankForm.dataset.resolveUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded',Accept:'application/json'},body:b});
                const d = await res.json();
                if (res.ok && d.account_name) { name.value = d.account_name; status.textContent = '✓ Verified'; status.style.color = 'var(--brand-strong)'; }
                else { name.value=''; status.textContent = d.message || 'Could not verify.'; status.style.color = 'var(--danger)'; }
            } catch(e){ status.textContent='Verification failed.'; status.style.color='var(--danger)'; }
        };
        search.addEventListener('focus', async () => { await load(); render(search.value); });
        search.addEventListener('input', async () => { code.value=''; name.value=''; status.textContent=''; await load(); render(search.value); });
        panel.addEventListener('click', (e) => { const btn = e.target.closest('[data-code]'); if(!btn) return; search.value=btn.textContent; code.value=btn.dataset.code; panel.hidden=true; resolve(); });
        document.addEventListener('click', (e) => { if(!bankForm.querySelector('.field').contains(e.target)) panel.hidden=true; });
        acct.addEventListener('blur', resolve);
        acct.addEventListener('input', () => { if(name.value){ name.value=''; status.textContent=''; } });
    }

    // Product photo autofill
    const btn = document.getElementById('autofill-btn');
    if (btn) {
        const prodForm = document.querySelector('[data-product]');
        const input = document.getElementById('prod-input');
        const st = document.getElementById('autofill-status');
        btn.addEventListener('click', async () => {
            const file = input.files[0];
            if (!file) { st.textContent = 'Upload a photo first.'; st.style.color='var(--danger)'; return; }
            st.textContent = 'Reading your photo…'; st.style.color='var(--muted)'; btn.disabled = true;
            try {
                const fd = new FormData(); fd.append('_token',csrf); fd.append('photo',file);
                const res = await fetch(prodForm.dataset.photoUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:fd});
                const d = await res.json();
                if (res.ok) {
                    if (d.name) prodForm.querySelector('[name=name]').value = d.name;
                    if (d.category) prodForm.querySelector('[name=category]').value = d.category;
                    if (d.description) prodForm.querySelector('[name=description]').value = d.description;
                    if (d.tags) prodForm.querySelector('[name=tags]').value = d.tags;
                    st.textContent = '✓ Details filled in — add your price.'; st.style.color='var(--brand-strong)';
                } else { st.textContent = d.message || 'Could not read the photo.'; st.style.color='var(--danger)'; }
            } catch(e){ st.textContent='Autofill failed. Fill the details manually.'; st.style.color='var(--danger)'; }
            btn.disabled = false;
        });
    }

    // Confetti (step 5)
    if (document.querySelector('.congrats')) {
        const emojis = ['🎉','🎊','✨','🥳','🎈','⭐'];
        for (let i=0;i<40;i++){
            const el = document.createElement('div');
            el.className='confetti'; el.textContent = emojis[i % emojis.length];
            el.style.left = (Math.random()*100)+'vw';
            el.style.animationDuration = (3+Math.random()*3)+'s';
            el.style.animationDelay = (Math.random()*2)+'s';
            document.body.appendChild(el);
            setTimeout(()=>el.remove(), 9000);
        }
    }
});
</script>
</body>
</html>
