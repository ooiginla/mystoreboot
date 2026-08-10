<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.google-analytics')

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#009a53">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <title>{{ $title ?? 'Storeboot Admin' }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;600;700;800&display=swap" rel="stylesheet">

        <style>
            :root {
                color-scheme: light;
                --bg: #f4f7f5;
                --panel: #ffffff;
                --panel-soft: #f1f5f3;
                --ink: #0f1b16;
                --ink-soft: #334155;
                --muted: #64748b;
                --line: #e4eae7;
                --line-soft: #eef2f0;
                --brand: #009a53;
                --brand-strong: #027a45;
                --brand-050: #ecfdf3;
                --brand-100: #d1fadf;
                --brand-ring: rgba(6, 193, 104, .18);
                --accent: #2563eb;
                --accent-dark: #1d4ed8;
                --danger: #dc2626;
                --danger-strong: #b91c1c;
                --danger-bg: #fef2f2;
                --danger-border: #fecaca;
                --warn: #b54708;
                --warn-bg: #fffaeb;
                --sb-bg: #0a1712;
                --sb-panel: #0f1f18;
                --sb-hover: #16281f;
                --sb-text: #b7c5bd;
                --radius: 12px;
                --radius-sm: 9px;
                --shadow-sm: 0 1px 2px rgba(16, 24, 40, .05);
                --shadow: 0 6px 20px -6px rgba(16, 24, 40, .12);
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                background: var(--bg);
                color: var(--ink);
                font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                font-feature-settings: "cv02", "cv03", "cv04", "cv11";
                line-height: 1.5;
                -webkit-font-smoothing: antialiased;
            }
            a { color: inherit; text-decoration: none; }
            button, input, select, textarea { font: inherit; }

            /* ---------- Shell + sidebar ---------- */
            .shell { min-height: 100vh; display: grid; grid-template-columns: 264px 1fr; }
            .sidebar { background: var(--sb-bg); color: #eef2f0; padding: 20px 14px; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
            .brand { display: flex; align-items: center; gap: 11px; margin: 6px 8px 22px; font-weight: 800; font-size: 18px; letter-spacing: -.01em; }
            .brand-mark { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: linear-gradient(140deg, #22dd85, #009a53); color: #fff; box-shadow: 0 4px 12px rgba(6,193,104,.3); }
            .brand-mark svg { width: 20px; height: 20px; }
            .nav { display: grid; gap: 3px; }
            .nav-group { margin: 16px 10px 6px; font-size: 11px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: #5c7268; }
            .nav a { display: flex; align-items: center; gap: 11px; color: var(--sb-text); padding: 9px 11px; border-radius: 9px; font-size: 13.5px; font-weight: 500; transition: background .15s, color .15s; }
            .nav a svg { width: 18px; height: 18px; flex: 0 0 auto; opacity: .85; }
            .nav a:hover { background: var(--sb-hover); color: #fff; }
            .nav a.active { background: linear-gradient(100deg, rgba(6,193,104,.20), rgba(6,193,104,.08)); color: #fff; box-shadow: inset 2px 0 0 var(--brand); font-weight: 600; }
            .nav a.active svg { opacity: 1; color: #4ade80; }
            .sidebar-footer { margin-top: auto; padding-top: 16px; border-top: 1px solid rgba(255,255,255,.08); display: grid; gap: 12px; }
            .sidebar-user { display: flex; align-items: center; gap: 10px; color: #dbe4df; font-size: 13px; overflow-wrap: anywhere; }
            .sidebar-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(140deg, #22dd85, #009a53); color: #06231a; display: grid; place-items: center; font-weight: 800; flex: 0 0 auto; font-size: 13px; }
            .sidebar-user strong { display: block; color: #fff; font-weight: 650; }
            .logout { width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid rgba(255,255,255,.14); border-radius: 9px; background: transparent; color: #eef2f0; padding: 9px 11px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background .15s; }
            .logout:hover { background: var(--sb-hover); }
            .logout svg { width: 16px; height: 16px; }
            .admin-mobile-header, .admin-sidebar-backdrop, .admin-sidebar-close { display: none; }

            /* ---------- Main + headings ---------- */
            .main { padding: 26px 30px 60px; max-width: 1560px; }
            .admin-context-bar { display: flex; justify-content: space-between; align-items: center; gap: 18px; margin-bottom: 16px; }
            .business-context { display: inline-flex; align-items: center; gap: 10px; min-width: 0; }
            .business-context-icon { width: 38px; height: 38px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 10px; background: var(--brand-050); color: var(--brand-strong); }
            .business-context-icon svg { width: 20px; height: 20px; }
            .business-context-label { display: grid; gap: 1px; min-width: 0; }
            .business-context-label span { color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
            .business-context-label strong { color: var(--ink); font-size: 17px; font-weight: 800; line-height: 1.2; overflow-wrap: anywhere; }
            .branch-switcher { display: inline-flex; align-items: center; gap: 10px; border: 1px solid var(--line); border-radius: 10px; background: #fff; padding: 8px 10px; box-shadow: var(--shadow-sm); min-width: min(100%, 330px); }
            .branch-switcher svg { width: 18px; height: 18px; color: var(--brand-strong); flex: 0 0 auto; }
            .branch-switcher-label { display: grid; gap: 1px; min-width: 88px; }
            .branch-switcher-label span { color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
            .branch-switcher-label strong { color: var(--ink); font-size: 13px; font-weight: 750; line-height: 1.2; }
            .branch-switcher select { min-width: 150px; padding-top: 8px; padding-bottom: 8px; border-radius: 8px; }
            .branch-choice-grid { display: grid; gap: 10px; }
            .branch-choice-grid .btn { width: 100%; justify-content: flex-start; padding: 12px 14px; }
            .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; }
            .eyebrow { color: var(--brand-strong); font-weight: 700; font-size: 11.5px; text-transform: uppercase; letter-spacing: .06em; }
            h1 { margin: 5px 0 0; font-size: 25px; line-height: 1.15; font-weight: 750; letter-spacing: -.02em; }
            .subtle { color: var(--muted); font-size: 13.5px; }
            .grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(340px, .75fr); gap: 18px; align-items: start; }
            .stack { display: grid; gap: 18px; }

            /* ---------- Panels / cards ---------- */
            .panel { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow-sm); }
            .panel-header { padding: 18px 22px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
            .panel-title { margin: 0; font-size: 16px; font-weight: 700; letter-spacing: -.01em; }
            .panel-body { padding: 22px; }

            /* ---------- Forms ---------- */
            .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
            .field { display: grid; align-content: start; gap: 7px; min-width: 0; }
            .field.full { grid-column: 1 / -1; }
            label { color: var(--ink-soft); font-size: 13px; font-weight: 600; }
            input, select, textarea {
                width: 100%;
                border: 1px solid #d4ddd8;
                border-radius: var(--radius-sm);
                background: #fff;
                color: var(--ink);
                padding: 10px 12px;
                outline: none;
                transition: border-color .15s, box-shadow .15s;
            }
            input::placeholder, textarea::placeholder { color: #9aa7a1; }
            textarea { min-height: 88px; resize: vertical; }
            select:not([multiple]) {
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4 6 4 4 4-4'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 12px center;
                padding-right: 34px;
            }
            input:focus, select:focus, textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3.5px var(--brand-ring); }
            input:disabled, select:disabled, textarea:disabled { background: var(--panel-soft); color: var(--muted); cursor: not-allowed; }

            /* ---------- Searchable product variant picker ---------- */
            .variant-search-picker { position: relative; }
            .variant-search-control { display: grid; grid-template-columns: minmax(0, 1fr) 48px; border: 1px solid #d4ddd8; border-radius: var(--radius-sm); background: #fff; overflow: hidden; transition: border-color .15s, box-shadow .15s; }
            .variant-search-control:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3.5px var(--brand-ring); }
            .variant-search-control input { min-width: 0; border: 0; border-radius: 0; box-shadow: none; }
            .variant-search-control input:focus { border: 0; box-shadow: none; }
            .variant-search-button { display: grid; place-items: center; border: 0; border-left: 1px solid var(--brand-strong); background: var(--brand); color: #fff; cursor: pointer; }
            .variant-search-button:hover { background: var(--brand-strong); }
            .variant-search-button svg { width: 22px; height: 22px; }
            .variant-search-options { position: absolute; z-index: 60; top: calc(100% + 6px); left: 0; right: 0; max-height: 280px; overflow-y: auto; border: 1px solid #d4ddd8; border-radius: var(--radius-sm); background: #fff; padding: 6px; box-shadow: 0 14px 30px -10px rgba(16,24,40,.28); }
            .variant-search-options[hidden] { display: none; }
            .variant-search-option { width: 100%; display: block; border: 0; border-radius: 7px; background: #fff; color: var(--ink); padding: 11px 12px; cursor: pointer; text-align: left; font-size: 14px; font-weight: 700; line-height: 1.35; }
            .variant-search-option:hover, .variant-search-option.active { background: var(--brand-050); color: var(--brand-strong); }
            .variant-search-empty { padding: 12px; color: var(--muted); font-size: 13.5px; text-align: center; }

            /* Switch toggle (added to boolean checkboxes via JS).
               High specificity (.main input.switch-input) so page-local
               `.inline-check input { width:auto }` rules cannot shrink it. */
            .main input.switch-input {
                appearance: none; -webkit-appearance: none;
                width: 42px; height: 24px; min-width: 42px; max-width: 42px; border-radius: 999px;
                background: #cbd5cf; border: 0; position: relative; cursor: pointer;
                transition: background .18s ease; flex: 0 0 auto; padding: 0; margin: 0; accent-color: auto;
                display: inline-block; vertical-align: middle;
            }
            .main input.switch-input::after {
                content: ''; position: absolute; top: 3px; left: 3px;
                width: 18px; height: 18px; border-radius: 50%; background: #fff;
                box-shadow: 0 1px 3px rgba(16,24,40,.28); transition: transform .18s ease;
            }
            .main input.switch-input:checked { background: var(--brand); }
            .main input.switch-input:checked::after { transform: translateX(18px); }
            .main input.switch-input:focus-visible { box-shadow: 0 0 0 3.5px var(--brand-ring); }

            .button-row { margin-top: 18px; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
            .btn {
                display: inline-flex; align-items: center; justify-content: center; gap: 7px;
                border: 1px solid transparent; border-radius: var(--radius-sm); padding: 9px 15px;
                cursor: pointer; font-weight: 600; font-size: 13.5px; transition: background .15s, box-shadow .15s, border-color .15s, transform .05s;
            }
            .btn:active { transform: translateY(.5px); }
            .btn svg { width: 16px; height: 16px; }
            .btn.primary { background: var(--brand); color: #fff; box-shadow: 0 4px 12px -2px rgba(6,193,104,.35); }
            .btn.primary:hover { background: var(--brand-strong); }
            .btn.accent { background: var(--brand); color: #fff; box-shadow: 0 4px 12px -2px rgba(6,193,104,.35); }
            .btn.accent:hover { background: var(--brand-strong); }
            .btn.danger, .btn.destructive { background: #fff; color: var(--danger); border-color: var(--danger-border); }
            .btn.danger:hover, .btn.destructive:hover { background: var(--danger); color: #fff; border-color: var(--danger); box-shadow: 0 4px 12px -2px rgba(220,38,38,.35); }
            .btn.secondary { background: #fff; color: var(--ink-soft); border-color: var(--line); box-shadow: var(--shadow-sm); }
            .btn.secondary:hover { background: var(--panel-soft); border-color: #d4ddd8; }

            /* ---------- Lists / items ---------- */
            .list { display: grid; gap: 10px; }
            .item { border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 14px; display: flex; justify-content: space-between; gap: 12px; background: #fff; transition: border-color .15s, box-shadow .15s; }
            .item:hover { border-color: #d4ddd8; box-shadow: var(--shadow-sm); }
            .item-title { font-weight: 700; }

            /* ---------- Badges / tags ---------- */
            .badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; background: var(--brand-050); color: #067647; padding: 3px 9px; font-size: 12px; font-weight: 650; }
            .badge.neutral { background: #eef2f6; color: #475467; }

            /* ---------- Alerts ---------- */
            .alert { border-radius: var(--radius-sm); padding: 13px 15px; margin-bottom: 18px; border: 1px solid #a6f4c5; background: var(--brand-050); color: #05603a; font-weight: 500; display: flex; gap: 10px; align-items: flex-start; }
            .alert::before { content: ''; flex: 0 0 auto; width: 18px; height: 18px; margin-top: 1px; background: currentColor; -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m5 13 4 4L19 7'/%3E%3C/svg%3E") center/contain no-repeat; mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m5 13 4 4L19 7'/%3E%3C/svg%3E") center/contain no-repeat; }
            .alert.errors { border-color: var(--danger-border); background: var(--danger-bg); color: var(--danger-strong); }
            .alert.errors::before { -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 8v5M12 17h.01'/%3E%3Ccircle cx='12' cy='12' r='9'/%3E%3C/svg%3E") center/contain no-repeat; mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 8v5M12 17h.01'/%3E%3Ccircle cx='12' cy='12' r='9'/%3E%3C/svg%3E") center/contain no-repeat; }
            .alert ul { margin: 4px 0 0; padding-left: 18px; }

            .hours { display: grid; gap: 8px; }
            .hour-row { display: grid; grid-template-columns: 110px 80px 1fr 1fr; gap: 10px; align-items: center; }
            .empty { color: var(--muted); background: var(--panel-soft); border-radius: var(--radius-sm); padding: 16px; text-align: center; font-size: 13.5px; }
            .mini-form { display: grid; gap: 14px; }
            .tenant-list { display: grid; gap: 8px; }
            .tenant-link { display: flex; justify-content: space-between; gap: 12px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 12px 14px; background: #fff; transition: border-color .15s; }
            .tenant-link:hover { border-color: #d4ddd8; }
            .tenant-link.active { border-color: var(--brand); background: var(--brand-050); }

            /* ---------- Tabs — horizontal top bar (full-width content) ---------- */
            .tab-layout { display: block; }
            .pill-nav {
                position: sticky; top: 0; z-index: 20;
                display: flex; flex-wrap: wrap; gap: 6px;
                margin-bottom: 20px; padding: 6px;
                background: #fff; border: 1px solid var(--line); border-radius: 999px;
                box-shadow: var(--shadow-sm);
            }
            .pill-nav a {
                display: inline-flex; align-items: center; gap: 8px;
                border-radius: 999px; padding: 9px 18px; color: var(--ink-soft);
                font-size: 13.5px; font-weight: 600; transition: background .15s, color .15s;
            }
            .pill-nav a svg { width: 16px; height: 16px; }
            .pill-nav a:hover { background: var(--panel-soft); color: var(--ink); }
            .pill-nav a.active { background: var(--brand); color: #fff; box-shadow: 0 4px 12px -3px rgba(6,193,104,.5); }
            .pill-nav a.active .badge { background: rgba(255,255,255,.25); color: #fff; }
            .content-stack { display: grid; gap: 18px; }
            .tab-panel[hidden] { display: none; }

            /* ---------- Summary / stats ---------- */
            .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
            .summary-item { border: 1px solid var(--line); border-radius: var(--radius-sm); background: #fff; padding: 16px; min-width: 0; }
            .summary-item span { display: block; color: var(--muted); font-size: 11.5px; font-weight: 650; text-transform: uppercase; letter-spacing: .04em; }
            .summary-item strong { display: block; margin-top: 5px; font-size: 17px; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
            .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
            .stat { border: 1px solid var(--line); border-radius: var(--radius); background: #fff; padding: 18px; box-shadow: var(--shadow-sm); }
            .stat strong { display: block; font-size: 24px; line-height: 1.1; font-weight: 750; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }

            /* ---------- Tables ---------- */
            .table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
            .table th, .table td { padding: 12px 14px; border-bottom: 1px solid var(--line-soft); text-align: left; vertical-align: middle; }
            .table th { color: #526177; font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; font-weight: 650; background: var(--panel-soft); }
            .table th:first-child { border-top-left-radius: 8px; }
            .table th:last-child { border-top-right-radius: 8px; }
            .table tbody tr { transition: background .12s; }
            .table tbody tr:hover { background: #fafcfb; }
            .table tbody tr:last-child td { border-bottom: 0; }

            /* ---------- Dialogs ---------- */
            .dialog { width: min(760px, calc(100vw - 32px)); max-height: calc(100vh - 32px); margin: auto; border: 0; border-radius: 16px; padding: 0; box-shadow: 0 32px 80px rgba(16,24,40,.28); }
            .dialog:not([open]) { display: none; }
            .dialog::backdrop { background: rgba(9, 20, 15, .5); backdrop-filter: blur(2px); }
            body.dialog-fallback-active { overflow: hidden; }
            body.dialog-fallback-active::before { content: ''; position: fixed; inset: 0; z-index: 999; background: rgba(9, 20, 15, .62); }
            .dialog.dialog-fallback-open { display: block; position: fixed; inset: 16px; z-index: 1000; overflow: auto; }
            .dialog-header { padding: 18px 22px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; gap: 14px; align-items: start; }
            .dialog-body { padding: 22px; max-height: min(72vh, 760px); overflow: auto; }
            .icon-btn { width: 36px; height: 36px; display: inline-grid; place-items: center; border: 1px solid var(--line); border-radius: 9px; background: #fff; cursor: pointer; font-weight: 700; color: var(--ink-soft); transition: background .15s, border-color .15s; line-height: 0; }
            .icon-btn:hover { background: var(--panel-soft); border-color: #d4ddd8; }

            @media (max-width: 960px) {
                .shell { grid-template-columns: 1fr; }
                body.admin-nav-open { overflow: hidden; }
                .admin-mobile-header {
                    position: sticky; top: 0; z-index: 50; min-height: 64px; padding: 10px 16px;
                    display: flex; align-items: center; justify-content: space-between; gap: 14px;
                    background: rgba(255,255,255,.96); border-bottom: 1px solid var(--line);
                    box-shadow: var(--shadow-sm); backdrop-filter: blur(10px);
                }
                .admin-mobile-brand { display: flex; align-items: center; gap: 10px; color: var(--ink); font-weight: 800; font-size: 17px; }
                .admin-mobile-header .brand-mark { width: 34px; height: 34px; }
                .admin-menu-toggle, .admin-sidebar-close {
                    width: 42px; height: 42px; border: 1px solid var(--line); border-radius: 10px;
                    background: #fff; color: var(--ink); cursor: pointer; place-items: center;
                }
                .admin-menu-toggle { display: grid; }
                .admin-menu-toggle:hover, .admin-sidebar-close:hover { background: var(--brand-050); border-color: var(--brand-100); color: var(--brand-strong); }
                .admin-menu-toggle svg, .admin-sidebar-close svg { width: 23px; height: 23px; }
                .admin-sidebar-close { display: grid; margin-left: auto; border-color: rgba(255,255,255,.16); background: transparent; color: #fff; }
                .admin-sidebar-close:hover { background: var(--sb-hover); border-color: rgba(255,255,255,.25); color: #fff; }
                .sidebar {
                    position: fixed; inset: 0 auto 0 0; z-index: 70; width: min(300px, 86vw); height: 100dvh;
                    flex-direction: column; transform: translateX(-105%); transition: transform .22s ease;
                    box-shadow: 20px 0 45px rgba(6, 23, 16, .26);
                }
                .sidebar.is-open { transform: translateX(0); }
                .admin-sidebar-backdrop {
                    position: fixed; inset: 0; z-index: 60; width: 100%; height: 100%; padding: 0;
                    display: block; border: 0; background: rgba(4, 15, 10, .56); opacity: 0;
                    pointer-events: none; transition: opacity .22s ease;
                }
                .admin-sidebar-backdrop.is-open { opacity: 1; pointer-events: auto; }
                .main { padding: 20px 16px 48px; }
                .admin-context-bar { align-items: stretch; flex-direction: column; }
                .branch-switcher { width: 100%; }
                .branch-switcher form { flex: 1; }
                .grid { grid-template-columns: 1fr; }
                .tab-layout { grid-template-columns: 1fr; }
                .pill-nav { position: static; border-radius: 14px; }
                .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .summary-grid { grid-template-columns: 1fr; }
                .form-grid { grid-template-columns: 1fr; }
                .hour-row { grid-template-columns: 1fr 1fr; }
            }
            @media (prefers-reduced-motion: reduce) {
                .sidebar, .admin-sidebar-backdrop { transition: none; }
            }
        </style>
    </head>
    <body>
        @php
            $activeBranchState = app(\App\Support\ActiveBranchManager::class)->stateForRequest(request(), auth()->user());
            $activeBranchTenant = $activeBranchState['tenant'];
            $activeBranchOptions = $activeBranchState['branches'];
            $activeBranch = $activeBranchState['activeBranch'];
            $shouldPromptForBranch = $activeBranchState['shouldPrompt'];
            $activeTenantRouteParams = $activeBranchTenant ? ['tenant' => $activeBranchTenant->id] : [];
            $tenantModuleStates = $activeBranchTenant
                ? app(\Modules\Subscriptions\Support\TenantModuleAccess::class)->states($activeBranchTenant)
                : collect();
            $knownTenantModules = $tenantModuleStates->map(fn (array $state): string => $state['module']->slug);
            $enabledTenantModules = $tenantModuleStates
                ->filter(fn (array $state): bool => $state['enabled'])
                ->map(fn (array $state): string => $state['module']->slug);
            $hasTenantModule = fn (string $slug): bool => ! $activeBranchTenant
                || ! $knownTenantModules->contains($slug)
                || $enabledTenantModules->contains($slug);

            $approvalTypes = ($activeBranchTenant && auth()->user())
                ? app(\Modules\Access\Support\ApprovalService::class)->typesApprovableBy(auth()->user(), $activeBranchTenant)
                : [];
            $canApproveAny = $approvalTypes !== [];
            $pendingApprovalCount = $canApproveAny
                ? app(\Modules\Access\Support\ApprovalService::class)->pendingForApprover($activeBranchTenant, auth()->user())->count()
                : 0;
        @endphp
        <svg width="0" height="0" style="position:absolute" aria-hidden="true">
            <defs>
                <g id="i-store"><path d="M3 9l1.5-5h15L21 9M4 9v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9M4 9h16M9 20v-6h6v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-package"><path d="M12 2 3 7v10l9 5 9-5V7l-9-5ZM3 7l9 5 9-5M12 12v10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-layers"><path d="M12 3 2 8l10 5 10-5-10-5ZM2 13l10 5 10-5M2 18l10 5 10-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-truck"><path d="M3 6h11v10H3zM14 9h4l3 3v4h-7M7.5 20a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Zm10 0a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-users"><path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 20v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-cart"><path d="M2.5 3H5l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.2L21 7H6M9.5 20a1.2 1.2 0 1 0 0-2.4 1.2 1.2 0 0 0 0 2.4Zm8 0a1.2 1.2 0 1 0 0-2.4 1.2 1.2 0 0 0 0 2.4Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-wallet"><path d="M3 7a2 2 0 0 1 2-2h13v3M3 7v10a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3M3 7h16a1 1 0 0 1 1 1v3M17 13.5h.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-shield"><path d="M12 2 4 6v6c0 5 3.4 8 8 10 4.6-2 8-5 8-10V6l-8-4ZM9 12l2 2 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-badge"><path d="M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1ZM9 9.5a2 2 0 1 0 0-.01M6.5 16a3 3 0 0 1 6 0M15 9h3M15 13h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-receipt"><path d="M5 3v18l2-1 2 1 2-1 2 1 2-1 2 1V3l-2 1-2-1-2 1-2-1-2 1-2-1ZM9 8h6M9 12h6M9 16h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-book"><path d="M6 3h13a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2ZM9 3v18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-chart"><path d="M3 3v18h18M7 15v3M12 9v9M17 5v13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-building"><path d="M4 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16M15 21V9h3a2 2 0 0 1 2 2v10M2 21h20M8 7h3M8 11h3M8 15h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-logout"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-spark"><path d="M13 2 4.5 13H11l-1 9 8.5-11H12l1-9Z" fill="currentColor"/></g>
                <g id="i-grid"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <g id="i-pos"><path d="M4 5h16a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1ZM7 9h7M7 12h4M8 19h8M12 15v4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g>
            </defs>
        </svg>

        <header class="admin-mobile-header">
            <div class="admin-mobile-brand">
                <span class="brand-mark"><svg viewBox="0 0 24 24"><use href="#i-spark"/></svg></span>
                <span>Storeboot</span>
            </div>
            <button class="admin-menu-toggle" type="button" data-admin-menu-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open admin menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </header>
        <button class="admin-sidebar-backdrop" type="button" data-admin-sidebar-backdrop aria-label="Close admin menu" tabindex="-1"></button>
        <div class="shell">
            <aside class="sidebar" id="admin-sidebar" data-admin-sidebar>
                <div class="brand">
                    <span class="brand-mark"><svg viewBox="0 0 24 24"><use href="#i-spark"/></svg></span>
                    <span>Storeboot</span>
                    <button class="admin-sidebar-close" type="button" data-admin-menu-close aria-label="Close admin menu">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <nav class="nav" aria-label="Admin navigation">
                    @if ($hasTenantModule('analytics'))
                        @permission('dashboard.view')
                        <a class="{{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}" href="{{ route('admin.analytics.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-grid"/></svg><span>Dashboard</span></a>
                        @endpermission
                    @endif
                    @if (\Illuminate\Support\Facades\Gate::any(['business.settings.manage', 'branches.manage', 'users.manage', 'roles.manage', 'subscriptions.manage']))
                        <a class="{{ request()->routeIs('admin.business.*') && ! request()->routeIs('admin.business.organizations.*') && ! request()->routeIs('admin.business.online-store.*') ? 'active' : '' }}" href="{{ route('admin.business.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-store"/></svg><span>Business setup</span></a>
                    @endif
                    @if ($canApproveAny)
                        <a class="{{ request()->routeIs('admin.access.approvals.*') ? 'active' : '' }}" href="{{ route('admin.access.approvals.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-shield"/></svg><span>Approvals</span>@if ($pendingApprovalCount > 0)<span class="badge" style="margin-left:auto;">{{ $pendingApprovalCount }}</span>@endif</a>
                    @endif
                    @if (\Illuminate\Support\Facades\Gate::any(['roles.manage', 'users.manage']))
                        <a class="{{ request()->routeIs('admin.access.review.*') ? 'active' : '' }}" href="{{ route('admin.access.review.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-badge"/></svg><span>Access review</span></a>
                    @endif

                    @if (\Illuminate\Support\Facades\Gate::any(['catalog.view', 'storefront.manage', 'inventory.view', 'procurement.view', 'customers.view', 'sales.create', 'sales.view']))
                        <div class="nav-group">Operations</div>
                    @endif
                    @if ($hasTenantModule('catalog'))
                        @permission('catalog.view')
                        <a class="{{ request()->routeIs('admin.catalog.*') ? 'active' : '' }}" href="{{ route('admin.catalog.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-package"/></svg><span>Product &amp; Services</span></a>
                        @endpermission
                    @endif
                    @if ($hasTenantModule('storefront'))
                        @permission('storefront.manage')
                        <a class="{{ request()->routeIs('admin.business.online-store.*') ? 'active' : '' }}" href="{{ route('admin.business.online-store.index', $activeTenantRouteParams) }}#online-store"><svg viewBox="0 0 24 24"><use href="#i-store"/></svg><span>Online Store</span></a>
                        @endpermission
                    @endif
                    @if ($hasTenantModule('inventory'))
                        @permission('inventory.view')
                        <a class="{{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}" href="{{ route('admin.inventory.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-layers"/></svg><span>Inventory &amp; Stock</span></a>
                        @endpermission
                    @endif
                    @if ($hasTenantModule('procurement'))
                        @permission('procurement.view')
                        <a class="{{ request()->routeIs('admin.procurement.*') ? 'active' : '' }}" href="{{ route('admin.procurement.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-truck"/></svg><span>Purchasing &amp; Suppliers</span></a>
                        @endpermission
                    @endif
                    @if ($hasTenantModule('customers'))
                        @permission('customers.view')
                        <a class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-users"/></svg><span>Customers &amp; Support</span></a>
                        @endpermission
                    @endif
                    @if ($hasTenantModule('retail-pos'))
                        @permission('sales.create')
                        <a class="{{ request()->routeIs('admin.sales.retail-pos') ? 'active' : '' }}" href="{{ route('admin.sales.retail-pos', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-pos"/></svg><span>Retail POS</span></a>
                        @endpermission
                    @endif
                    @if ($hasTenantModule('sales'))
                        @permission('sales.create')
                        <a class="{{ request()->routeIs('admin.sales.index') ? 'active' : '' }}" href="{{ route('admin.sales.index', $activeTenantRouteParams) }}#pos"><svg viewBox="0 0 24 24"><use href="#i-cart"/></svg><span>Record Sale</span></a>
                        @endpermission
                        @permission('sales.view')
                        <a class="{{ request()->routeIs('admin.sales.orders.index') ? 'active' : '' }}" href="{{ route('admin.sales.orders.index', $activeTenantRouteParams) }}#orders"><svg viewBox="0 0 24 24"><use href="#i-receipt"/></svg><span>Orders</span></a>
                        @endpermission
                    @endif

                    @if (\Illuminate\Support\Facades\Gate::any(['settlements.view', 'hr.staff.view', 'finance.view']))
                        <div class="nav-group">Finance</div>
                    @endif
                    @if ($hasTenantModule('sales'))
                        @permission('settlements.view')
                        <a class="{{ request()->routeIs('admin.sales.settlements.*') ? 'active' : '' }}" href="{{ route('admin.sales.settlements.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-wallet"/></svg><span>Business Settlements</span></a>
                        @endpermission
                        @if (auth()->user()?->is_platform_admin)
                            <a class="{{ request()->routeIs('admin.sales.admin-settlements.*') ? 'active' : '' }}" href="{{ route('admin.sales.admin-settlements.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-shield"/></svg><span>Admin Settlements</span></a>
                        @endif
                    @endif
                    @if ($hasTenantModule('hrpayroll'))
                        @permission('hr.staff.view')
                        <a class="{{ request()->routeIs('admin.hr-payroll.*') ? 'active' : '' }}" href="{{ route('admin.hr-payroll.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-badge"/></svg><span>HR &amp; Payroll</span></a>
                        @endpermission
                    @endif
                    @if ($hasTenantModule('finance'))
                        @permission('finance.view')
                        <a class="{{ request()->routeIs('admin.finance.expenses') || request()->routeIs('admin.finance.expenses.*') || request()->routeIs('admin.finance.petty-cash.*') ? 'active' : '' }}" href="{{ route('admin.finance.expenses', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-receipt"/></svg><span>Expenses</span></a>
                        <a class="{{ request()->routeIs('admin.finance.journals') || request()->routeIs('admin.finance.journals.*') || request()->routeIs('admin.finance.expense-categories.*') || request()->routeIs('admin.finance.chart-of-accounts') ? 'active' : '' }}" href="{{ route('admin.finance.journals', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-book"/></svg><span>Journals</span></a>
                        @endpermission
                        @permission('finance.reports.view')
                        <a class="{{ request()->routeIs('admin.finance.index') ? 'active' : '' }}" href="{{ route('admin.finance.index', $activeTenantRouteParams) }}"><svg viewBox="0 0 24 24"><use href="#i-chart"/></svg><span>Report</span></a>
                        @endpermission
                    @endif

                    @if (auth()->user()?->is_platform_admin)
                        <div class="nav-group">Platform</div>
                        <a class="{{ request()->routeIs('admin.business.organizations.*') ? 'active' : '' }}" href="{{ route('admin.business.organizations.index') }}"><svg viewBox="0 0 24 24"><use href="#i-building"/></svg><span>Organizations</span></a>
                    @endif
                </nav>
                @auth
                    <div class="sidebar-footer">
                        <div class="sidebar-user">
                            <span class="sidebar-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <span><strong>{{ auth()->user()->name }}</strong>{{ auth()->user()->email }}</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="logout" type="submit"><svg viewBox="0 0 24 24"><use href="#i-logout"/></svg> Logout</button>
                        </form>
                    </div>
                @endauth
            </aside>

            <main class="main">
                @if ($activeBranchTenant)
                    <div class="admin-context-bar">
                        <div class="business-context" data-business-context>
                            <span class="business-context-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#i-store"/></svg>
                            </span>
                            <div class="business-context-label">
                                <span>Business</span>
                                <strong>{{ $activeBranchTenant->name }}</strong>
                            </div>
                        </div>
                        @if ($activeBranchOptions->isNotEmpty())
                            <div class="branch-switcher">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#i-building"/></svg>
                                <div class="branch-switcher-label">
                                    <span>Active branch</span>
                                    <strong>{{ $activeBranch?->name ?? 'Choose branch' }}</strong>
                                </div>
                                @if ($activeBranchOptions->count() > 1)
                                    <form method="POST" action="{{ route('admin.active-branch.update') }}">
                                        @csrf
                                        <input type="hidden" name="tenant_id" value="{{ $activeBranchTenant->id }}">
                                        <select name="branch_id" onchange="this.form.submit()" aria-label="Active branch">
                                            <option value="">Choose branch</option>
                                            @foreach ($activeBranchOptions as $branchOption)
                                                <option value="{{ $branchOption->id }}" @selected($activeBranch?->id === $branchOption->id)>{{ $branchOption->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{ $slot }}

                @if ($activeBranchTenant && $shouldPromptForBranch)
                    <dialog class="dialog" data-active-branch-dialog>
                        <div class="dialog-header">
                            <div>
                                <h2 class="panel-title">Choose active branch</h2>
                                <p class="subtle">{{ $activeBranchTenant->name }}</p>
                            </div>
                        </div>
                        <div class="dialog-body">
                            <div class="branch-choice-grid">
                                @foreach ($activeBranchOptions as $branchOption)
                                    <form method="POST" action="{{ route('admin.active-branch.update') }}">
                                        @csrf
                                        <input type="hidden" name="tenant_id" value="{{ $activeBranchTenant->id }}">
                                        <input type="hidden" name="branch_id" value="{{ $branchOption->id }}">
                                        <button class="btn secondary" type="submit">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#i-building"/></svg>
                                            {{ $branchOption->name }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    </dialog>
                @endif
            </main>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const adminSidebar = document.querySelector('[data-admin-sidebar]');
                const adminSidebarBackdrop = document.querySelector('[data-admin-sidebar-backdrop]');
                const adminMenuToggle = document.querySelector('[data-admin-menu-toggle]');

                const setAdminMenu = (open) => {
                    if (!adminSidebar || !adminSidebarBackdrop || !adminMenuToggle) return;

                    adminSidebar.classList.toggle('is-open', open);
                    adminSidebarBackdrop.classList.toggle('is-open', open);
                    document.body.classList.toggle('admin-nav-open', open);
                    adminMenuToggle.setAttribute('aria-expanded', String(open));
                    adminMenuToggle.setAttribute('aria-label', open ? 'Close admin menu' : 'Open admin menu');

                    if (open) {
                        adminSidebar.querySelector('a, button')?.focus();
                    } else if (window.innerWidth <= 960) {
                        adminMenuToggle.focus();
                    }
                };

                adminMenuToggle?.addEventListener('click', () => {
                    setAdminMenu(adminMenuToggle.getAttribute('aria-expanded') !== 'true');
                });
                document.querySelector('[data-admin-menu-close]')?.addEventListener('click', () => setAdminMenu(false));
                adminSidebarBackdrop?.addEventListener('click', () => setAdminMenu(false));
                adminSidebar?.querySelectorAll('nav a').forEach((link) => {
                    link.addEventListener('click', () => setAdminMenu(false));
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && adminSidebar?.classList.contains('is-open')) {
                        setAdminMenu(false);
                    }
                });
                window.addEventListener('resize', () => {
                    if (window.innerWidth > 960 && adminSidebar?.classList.contains('is-open')) {
                        setAdminMenu(false);
                    }
                });

                const supportsNativeDialog = typeof window.HTMLDialogElement !== 'undefined'
                    && typeof window.HTMLDialogElement.prototype.showModal === 'function';
                const openDialog = (dialog) => {
                    if (!dialog) return;

                    if (supportsNativeDialog) {
                        if (!dialog.open) dialog.showModal();
                        return;
                    }

                    dialog.setAttribute('open', '');
                    dialog.classList.add('dialog-fallback-open');
                    document.body.classList.add('dialog-fallback-active');
                };
                const closeDialog = (dialog) => {
                    if (!dialog) return;

                    if (supportsNativeDialog && dialog.open) {
                        dialog.close();
                        return;
                    }

                    dialog.removeAttribute('open');
                    dialog.classList.remove('dialog-fallback-open');
                    if (!document.querySelector('dialog.dialog-fallback-open')) {
                        document.body.classList.remove('dialog-fallback-active');
                    }
                };
                window.sbOpenDialog = openDialog;
                window.sbCloseDialog = closeDialog;

                const activeBranchDialog = document.querySelector('[data-active-branch-dialog]');
                if (activeBranchDialog) {
                    openDialog(activeBranchDialog);
                    activeBranchDialog.addEventListener('cancel', (event) => event.preventDefault());
                }

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
                            closeDialog(currentDialog);
                            window.setTimeout(() => openDialog(nextDialog), 80);
                            return;
                        }

                        openDialog(nextDialog);
                    });
                });

                document.querySelectorAll('[data-dialog-close]').forEach((button) => {
                    button.addEventListener('click', () => {
                        closeDialog(button.closest('dialog'));
                    });
                });

                const variantOptionsFor = (search) => {
                    const optionsId = search?.dataset.variantOptionsId || search?.getAttribute('list') || '';
                    return Array.from(document.getElementById(optionsId)?.options || []);
                };
                const selectedVariantOption = (search) => variantOptionsFor(search)
                    .find((option) => option.value === search?.value);

                document.querySelectorAll('[data-variant-search-picker]').forEach((searchPicker, pickerIndex) => {
                    const search = searchPicker.querySelector('[data-variant-search]');
                    const panel = searchPicker.querySelector('[data-variant-search-options]');
                    const toggle = searchPicker.querySelector('[data-variant-search-toggle]');
                    if (!search || !panel || !toggle) return;

                    const panelId = `variant-search-options-${pickerIndex}`;
                    let activeIndex = -1;
                    panel.id = panelId;
                    search.setAttribute('aria-controls', panelId);

                    const close = () => {
                        panel.hidden = true;
                        search.setAttribute('aria-expanded', 'false');
                        activeIndex = -1;
                    };
                    const activate = (index) => {
                        const rows = Array.from(panel.querySelectorAll('[data-variant-search-option]'));
                        if (!rows.length) return;
                        activeIndex = Math.max(0, Math.min(index, rows.length - 1));
                        rows.forEach((row, rowIndex) => row.classList.toggle('active', rowIndex === activeIndex));
                        rows[activeIndex].scrollIntoView({ block: 'nearest' });
                    };
                    const select = (option) => {
                        if (!option) return;
                        search.value = option.value;
                        search.dispatchEvent(new Event('input', { bubbles: true }));
                        search.dispatchEvent(new Event('change', { bubbles: true }));
                        close();
                        search.focus();
                    };
                    const render = (showAll = false) => {
                        const query = showAll ? '' : search.value.trim().toLowerCase();
                        const matches = variantOptionsFor(search)
                            .filter((option) => !query
                                || option.value.toLowerCase().includes(query)
                                || (option.dataset.sku || '').toLowerCase().includes(query)
                                || (option.dataset.barcode || '').toLowerCase().includes(query))
                            .slice(0, 60);

                        panel.innerHTML = '';
                        if (!matches.length) {
                            const empty = document.createElement('div');
                            empty.className = 'variant-search-empty';
                            empty.textContent = 'No matching product variants';
                            panel.append(empty);
                        } else {
                            matches.forEach((option, index) => {
                                const row = document.createElement('button');
                                row.type = 'button';
                                row.setAttribute('role', 'option');
                                row.className = 'variant-search-option';
                                row.dataset.variantSearchOption = String(index);
                                row.textContent = option.value;
                                row.addEventListener('mousedown', (event) => event.preventDefault());
                                row.addEventListener('click', () => select(option));
                                panel.append(row);
                            });
                        }

                        panel.hidden = false;
                        search.setAttribute('aria-expanded', 'true');
                        activeIndex = -1;
                    };

                    search.addEventListener('focus', () => render(Boolean(selectedVariantOption(search))));
                    search.addEventListener('input', () => render(false));
                    search.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            close();
                            return;
                        }

                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            if (panel.hidden) render(false);
                            activate(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
                            return;
                        }

                        if (event.key === 'Enter' && !panel.hidden && activeIndex >= 0) {
                            event.preventDefault();
                            panel.querySelectorAll('[data-variant-search-option]')[activeIndex]?.click();
                        }
                    });
                    toggle.addEventListener('click', () => {
                        if (panel.hidden) {
                            render(true);
                            search.focus();
                        } else {
                            close();
                        }
                    });
                    searchPicker.addEventListener('focusout', (event) => {
                        if (!searchPicker.contains(event.relatedTarget)) close();
                    });
                });

                document.addEventListener('click', (event) => {
                    document.querySelectorAll('[data-variant-search-picker]').forEach((searchPicker) => {
                        if (searchPicker.contains(event.target)) return;
                        const search = searchPicker.querySelector('[data-variant-search]');
                        const panel = searchPicker.querySelector('[data-variant-search-options]');
                        if (panel) panel.hidden = true;
                        if (search) search.setAttribute('aria-expanded', 'false');
                    });
                });

                document.addEventListener('input', (event) => {
                    const search = event.target.closest('[data-variant-search]');

                    if (!search) return;

                    const picker = search.closest('[data-variant-picker]');
                    const value = picker?.querySelector('[data-variant-value]');
                    const option = selectedVariantOption(search);

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

                activateTab(window.location.hash.replace('#', '') || document.querySelector('[data-default-tab]')?.dataset.defaultTab || '');
            });
        </script>
    </body>
</html>
