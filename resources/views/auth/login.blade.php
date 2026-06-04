<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Sign in · Storeboot</title>
        <style>
            :root { --bg: #f7f8fb; --ink: #18202f; --muted: #667085; --line: #d9e0ea; --brand: #0f766e; --brand-dark: #115e59; --danger: #b42318; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
            main { width: min(420px, calc(100vw - 32px)); }
            .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 22px; font-weight: 800; font-size: 20px; }
            .mark { width: 38px; height: 38px; border-radius: 8px; display: grid; place-items: center; background: var(--brand); color: white; }
            .panel { background: white; border: 1px solid var(--line); border-radius: 8px; padding: 24px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
            h1 { margin: 0 0 6px; font-size: 24px; line-height: 1.2; }
            p { margin: 0 0 20px; color: var(--muted); }
            form { display: grid; gap: 14px; }
            label { display: grid; gap: 6px; color: #344054; font-size: 13px; font-weight: 650; }
            input { width: 100%; border: 1px solid #cfd8e3; border-radius: 8px; padding: 10px 11px; outline: none; font: inherit; }
            input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(15,118,110,.14); }
            .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .check { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); font-size: 14px; }
            .check input { width: auto; }
            button { border: 0; border-radius: 8px; background: var(--brand); color: white; padding: 11px 14px; cursor: pointer; font-weight: 800; }
            button:hover { background: var(--brand-dark); }
            .error { margin-bottom: 14px; border-radius: 8px; border: 1px solid #fecdca; background: #fef3f2; color: var(--danger); padding: 10px 12px; }
        </style>
    </head>
    <body>
        <main>
            <div class="brand"><span class="mark">S</span><span>Storeboot</span></div>
            <section class="panel">
                <h1>Sign in</h1>
                <p>Access the Storeboot administration workspace.</p>

                @if ($errors->any())
                    <div class="error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <label>
                        Email
                        <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                    </label>
                    <label>
                        Password
                        <input name="password" type="password" autocomplete="current-password" required>
                    </label>
                    <div class="row">
                        <label class="check"><input name="remember" type="checkbox" value="1"> Remember me</label>
                    </div>
                    <button type="submit">Sign in</button>
                </form>
            </section>
        </main>
    </body>
</html>
