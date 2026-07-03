<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Storeboot Admin' }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            :root {
                color-scheme: light;
                --bg: #f7f8fb;
                --panel: #ffffff;
                --panel-soft: #f2f5f8;
                --ink: #18202f;
                --muted: #667085;
                --line: #d9e0ea;
                --brand: #0f766e;
                --brand-dark: #115e59;
                --accent: #2563eb;
                --accent-dark: #1d4ed8;
                --danger: #b42318;
                --radius: 8px;
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                background: var(--bg);
                color: var(--ink);
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                line-height: 1.5;
            }
            a { color: inherit; text-decoration: none; }
            button, input, select, textarea { font: inherit; }
            .shell { min-height: 100vh; display: grid; grid-template-columns: 260px 1fr; }
            .sidebar { background: #101828; color: #f8fafc; padding: 24px 18px; }
            .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; font-weight: 700; }
            .brand-mark { width: 34px; height: 34px; border-radius: 8px; display: grid; place-items: center; background: var(--brand); }
            .nav { display: grid; gap: 6px; }
            .nav a { color: #cbd5e1; padding: 10px 12px; border-radius: 8px; font-size: 14px; }
            .nav a.active, .nav a:hover { background: #1d2939; color: #ffffff; }
            .sidebar-footer { margin-top: 28px; border-top: 1px solid #344054; padding-top: 18px; display: grid; gap: 12px; }
            .sidebar-user { color: #e5e7eb; font-size: 13px; overflow-wrap: anywhere; }
            .logout { width: 100%; border: 1px solid #475467; border-radius: 8px; background: transparent; color: #f8fafc; padding: 9px 11px; cursor: pointer; font-weight: 700; }
            .logout:hover { background: #1d2939; }
            .main { padding: 28px; }
            .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; }
            .eyebrow { color: var(--brand-dark); font-weight: 700; font-size: 12px; text-transform: uppercase; }
            h1 { margin: 4px 0 0; font-size: 28px; line-height: 1.2; }
            .subtle { color: var(--muted); font-size: 14px; }
            .grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(340px, .75fr); gap: 18px; align-items: start; }
            .stack { display: grid; gap: 18px; }
            .panel { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: 0 1px 2px rgba(16,24,40,.04); }
            .panel-header { padding: 18px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; gap: 12px; align-items: start; }
            .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
            .panel-body { padding: 20px; }
            .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .field { display: grid; gap: 6px; }
            .field.full { grid-column: 1 / -1; }
            label { color: #344054; font-size: 13px; font-weight: 650; }
            input, select, textarea {
                width: 100%;
                border: 1px solid #cfd8e3;
                border-radius: 8px;
                background: #fff;
                color: var(--ink);
                padding: 10px 11px;
                outline: none;
            }
            textarea { min-height: 84px; resize: vertical; }
            input:focus, select:focus, textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(15,118,110,.14); }
            .button-row { margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px; }
            .btn { border: 0; border-radius: 8px; padding: 10px 14px; cursor: pointer; font-weight: 700; }
            .btn.primary { background: var(--brand); color: #fff; }
            .btn.primary:hover { background: var(--brand-dark); }
            .btn.accent { background: var(--accent); color: #fff; box-shadow: 0 8px 18px rgba(37, 99, 235, .18); }
            .btn.accent:hover { background: var(--accent-dark); }
            .btn.danger, .btn.destructive { background: #fef3f2; color: var(--danger); border: 1px solid #fecdca; }
            .btn.danger:hover, .btn.destructive:hover { background: #fee4e2; }
            .btn.secondary { background: var(--panel-soft); color: #344054; }
            .list { display: grid; gap: 10px; }
            .item { border: 1px solid var(--line); border-radius: 8px; padding: 12px; display: flex; justify-content: space-between; gap: 12px; }
            .item-title { font-weight: 700; }
            .badge { display: inline-flex; align-items: center; border-radius: 999px; background: #ecfdf3; color: #067647; padding: 3px 8px; font-size: 12px; font-weight: 700; }
            .badge.neutral { background: #eef2f6; color: #475467; }
            .alert { border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; border: 1px solid #abefc6; background: #ecfdf3; color: #067647; }
            .errors { border-color: #fecdca; background: #fef3f2; color: var(--danger); }
            .hours { display: grid; gap: 8px; }
            .hour-row { display: grid; grid-template-columns: 110px 80px 1fr 1fr; gap: 10px; align-items: center; }
            .empty { color: var(--muted); background: var(--panel-soft); border-radius: 8px; padding: 14px; }
            .mini-form { display: grid; gap: 12px; }
            .tenant-list { display: grid; gap: 8px; }
            .tenant-link { display: flex; justify-content: space-between; gap: 12px; border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; }
            .tenant-link.active { border-color: var(--brand); background: #f0fdfa; }
            .tab-layout { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 18px; align-items: start; }
            .pill-nav { position: sticky; top: 24px; display: grid; gap: 8px; }
            .pill-nav a { display: flex; justify-content: space-between; align-items: center; gap: 10px; border: 1px solid var(--line); border-radius: 999px; background: #fff; padding: 10px 14px; color: #344054; font-size: 14px; font-weight: 750; }
            .pill-nav a:hover, .pill-nav a:focus, .pill-nav a.active { border-color: var(--brand); color: var(--brand-dark); box-shadow: 0 0 0 3px rgba(15,118,110,.12); }
            .pill-nav a.active { background: #f0fdfa; }
            .content-stack { display: grid; gap: 18px; }
            .tab-panel[hidden] { display: none; }
            .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
            .summary-item { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 14px; min-width: 0; }
            .summary-item span { display: block; color: var(--muted); font-size: 12px; font-weight: 750; text-transform: uppercase; }
            .summary-item strong { display: block; margin-top: 4px; overflow-wrap: anywhere; }
            .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
            .stat { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 14px; }
            .stat strong { display: block; font-size: 22px; line-height: 1.1; }
            .table { width: 100%; border-collapse: collapse; }
            .table th, .table td { padding: 12px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
            .table th { color: #475467; font-size: 12px; text-transform: uppercase; }
            .dialog { width: min(760px, calc(100vw - 32px)); max-height: calc(100vh - 32px); margin: auto; border: 0; border-radius: 8px; padding: 0; box-shadow: 0 24px 60px rgba(16,24,40,.22); }
            .dialog::backdrop { background: rgba(16,24,40,.48); }
            .dialog-header { padding: 18px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; gap: 14px; align-items: start; }
            .dialog-body { padding: 20px; max-height: min(72vh, 760px); overflow: auto; }
            .icon-btn { width: 34px; height: 34px; display: inline-grid; place-items: center; border: 1px solid var(--line); border-radius: 8px; background: #fff; cursor: pointer; font-weight: 900; }
            .icon-btn:hover { background: var(--panel-soft); }

            @media (max-width: 960px) {
                .shell { grid-template-columns: 1fr; }
                .sidebar { position: static; }
                .grid { grid-template-columns: 1fr; }
                .tab-layout { grid-template-columns: 1fr; }
                .pill-nav { position: static; }
                .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .summary-grid { grid-template-columns: 1fr; }
                .form-grid { grid-template-columns: 1fr; }
                .hour-row { grid-template-columns: 1fr 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <aside class="sidebar">
                <div class="brand">
                    <span class="brand-mark">S</span>
                    <span>Storeboot</span>
                </div>
                <nav class="nav" aria-label="Admin navigation">
                    <a class="{{ request()->routeIs('admin.business.*') && ! request()->routeIs('admin.business.organizations.*') ? 'active' : '' }}" href="{{ route('admin.business.index') }}">Business setup</a>
                    <a class="{{ request()->routeIs('admin.catalog.*') ? 'active' : '' }}" href="{{ route('admin.catalog.index') }}">Product & Services</a>
                    <a class="{{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}" href="{{ route('admin.inventory.index') }}">Inventory & Stock</a>
                    <a class="{{ request()->routeIs('admin.procurement.*') ? 'active' : '' }}" href="{{ route('admin.procurement.index') }}">Purchasing & Suppliers</a>
                    <a class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}">Customers & Support</a>
                    <a class="{{ request()->routeIs('admin.sales.*') && ! request()->routeIs('admin.sales.settlements.*') && ! request()->routeIs('admin.sales.admin-settlements.*') ? 'active' : '' }}" href="{{ route('admin.sales.index') }}">Sales & POS</a>
                    <a class="{{ request()->routeIs('admin.sales.settlements.*') ? 'active' : '' }}" href="{{ route('admin.sales.settlements.index') }}">Business Settlements</a>
                    @if (auth()->user()?->is_platform_admin)
                        <a class="{{ request()->routeIs('admin.sales.admin-settlements.*') ? 'active' : '' }}" href="{{ route('admin.sales.admin-settlements.index') }}">Admin Settlements</a>
                    @endif
                    <a class="{{ request()->routeIs('admin.hr-payroll.*') ? 'active' : '' }}" href="{{ route('admin.hr-payroll.index') }}">HR & Payroll</a>
                    <a class="{{ request()->routeIs('admin.finance.expenses') || request()->routeIs('admin.finance.expense-categories.*') || request()->routeIs('admin.finance.expenses.*') || request()->routeIs('admin.finance.petty-cash.*') || request()->routeIs('admin.finance.journals.*') ? 'active' : '' }}" href="{{ route('admin.finance.expenses') }}">Expenses</a>
                    <a class="{{ request()->routeIs('admin.finance.chart-of-accounts') ? 'active' : '' }}" href="{{ route('admin.finance.chart-of-accounts') }}">Chart of Accounts</a>
                    <a class="{{ request()->routeIs('admin.finance.index') ? 'active' : '' }}" href="{{ route('admin.finance.index') }}">Report</a>
                    @if (auth()->user()?->is_platform_admin)
                        <a class="{{ request()->routeIs('admin.business.organizations.*') ? 'active' : '' }}" href="{{ route('admin.business.organizations.index') }}">Organizations</a>
                    @endif
                </nav>
                @auth
                    <div class="sidebar-footer">
                        <div class="sidebar-user">
                            <strong>{{ auth()->user()->name }}</strong><br>
                            {{ auth()->user()->email }}
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="logout" type="submit">Logout</button>
                        </form>
                    </div>
                @endauth
            </aside>

            <main class="main">
                {{ $slot }}
            </main>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));
                const tabs = Array.from(document.querySelectorAll('[data-tab-target]'));

                function activateTab(id) {
                    if (!panels.length) return;

                    const panelId = panels.some((panel) => panel.id === id) ? id : panels[0].id;

                    panels.forEach((panel) => {
                        panel.hidden = panel.id !== panelId;
                    });

                    tabs.forEach((tab) => {
                        const isActive = tab.dataset.tabTarget === panelId;
                        tab.classList.toggle('active', isActive);
                        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });
                }

                tabs.forEach((tab) => {
                    tab.addEventListener('click', (event) => {
                        event.preventDefault();
                        const id = tab.dataset.tabTarget;
                        history.replaceState(null, '', `#${id}`);
                        activateTab(id);
                    });
                });

                document.querySelectorAll('[data-dialog-open]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const currentDialog = button.closest('dialog');
                        const nextDialog = document.getElementById(button.dataset.dialogOpen);

                        if (currentDialog && currentDialog !== nextDialog) {
                            currentDialog.close();
                            window.setTimeout(() => nextDialog?.showModal(), 80);
                            return;
                        }

                        nextDialog?.showModal();
                    });
                });

                document.querySelectorAll('[data-dialog-close]').forEach((button) => {
                    button.addEventListener('click', () => {
                        button.closest('dialog')?.close();
                    });
                });

                document.addEventListener('input', (event) => {
                    const search = event.target.closest('[data-variant-search]');

                    if (!search) return;

                    const picker = search.closest('[data-variant-picker]');
                    const value = picker?.querySelector('[data-variant-value]');
                    const option = Array.from(search.list?.options || []).find((item) => item.value === search.value);

                    if (value) {
                        value.value = option?.dataset.variantId || '';
                    }

                    search.setCustomValidity(value?.value || !search.required ? '' : 'Choose an item from the search results.');
                });

                document.addEventListener('input', (event) => {
                    const search = event.target.closest('[data-customer-search]');

                    if (!search) return;

                    const picker = search.closest('[data-customer-picker]');
                    const value = picker?.querySelector('[data-customer-value]');
                    const option = Array.from(search.list?.options || []).find((item) => item.value === search.value);

                    if (value) {
                        value.value = option?.dataset.customerId || '';
                    }
                });

                document.addEventListener('submit', (event) => {
                    const form = event.target;

                    if (!(form instanceof HTMLFormElement)) return;

                    const invalid = Array.from(form.querySelectorAll('[data-variant-picker]')).find((picker) => {
                        const search = picker.querySelector('[data-variant-search]');
                        const value = picker.querySelector('[data-variant-value]');

                        return search?.required && !value?.value;
                    });

                    if (invalid) {
                        event.preventDefault();
                        const search = invalid.querySelector('[data-variant-search]');
                        search?.setCustomValidity('Choose an item from the search results.');
                        search?.reportValidity();
                    }

                    form.querySelectorAll('[data-money-input]').forEach((input) => {
                        input.value = input.value.replace(/,/g, '');
                    });
                });

                function formatMoney(value) {
                    const clean = value.replace(/,/g, '').replace(/[^\d.]/g, '');
                    if (!clean || clean === '.') return '';
                    const [whole, decimal = ''] = clean.split('.');
                    return `${Number(whole || 0).toLocaleString('en-US')}${decimal !== '' ? `.${decimal.slice(0, 2)}` : ''}`;
                }

                document.addEventListener('input', (event) => {
                    const input = event.target.closest('[data-money-input]');
                    if (!input) return;
                    input.value = input.value.replace(/[^\d.,]/g, '');
                });

                document.addEventListener('blur', (event) => {
                    const input = event.target.closest('[data-money-input]');
                    if (!input) return;
                    input.value = formatMoney(input.value);
                }, true);

                document.querySelectorAll('[data-money-input]').forEach((input) => {
                    input.value = formatMoney(input.value);
                });

                function syncPaymentSummary(select) {
                    const form = select.closest('form');
                    const option = select.selectedOptions[0];

                    if (!form || !option) return;

                    const total = form.querySelector('[data-payment-total]');
                    const paid = form.querySelector('[data-payment-paid]');
                    const balance = form.querySelector('[data-payment-balance]');
                    const amount = form.querySelector('input[name="amount"]');
                    const vendor = form.querySelector('select[name="vendor_id"]');

                    if (total) total.value = option.dataset.total || '';
                    if (paid) paid.value = option.dataset.paid || '';
                    if (balance) balance.value = option.dataset.balance || '';
                    if (amount && option.dataset.balance) amount.value = option.dataset.balance;
                    if (vendor && option.dataset.vendorId) vendor.value = option.dataset.vendorId;
                }

                document.querySelectorAll('[data-payment-po-select]').forEach(syncPaymentSummary);

                document.addEventListener('change', (event) => {
                    const select = event.target.closest('[data-payment-po-select]');
                    if (!select) return;
                    syncPaymentSummary(select);
                });

                document.addEventListener('click', (event) => {
                    const tab = event.target.closest('[data-local-tab-target]');

                    if (!tab) return;

                    event.preventDefault();

                    const dialog = tab.closest('dialog') || document;
                    const panel = dialog.querySelector(`#${tab.dataset.localTabTarget}`);

                    if (!panel) return;

                    dialog.querySelectorAll('[data-local-tab-panel]').forEach((item) => {
                        item.hidden = item !== panel;
                    });

                    dialog.querySelectorAll('[data-local-tab-target]').forEach((item) => {
                        item.classList.toggle('active', item === tab);
                    });
                });

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-print-dialog]');
                    if (!button) return;
                    window.print();
                });

                activateTab(window.location.hash.replace('#', ''));
            });
        </script>
    </body>
</html>
