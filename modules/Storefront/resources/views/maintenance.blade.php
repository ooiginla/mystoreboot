<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>We will be back soon | {{ $store->store_name }}</title>
    <style>
        :root {
            color-scheme: light;
            --primary: {{ $store->theme_primary_color ?: '#006554' }};
            --secondary: {{ $store->theme_secondary_color ?: '#f59e0b' }};
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f7faf9;
            color: #111827;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        main { width: min(640px, 100%); text-align: center; }
        .mark {
            display: inline-grid;
            place-items: center;
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            font-size: 32px;
            font-weight: 800;
        }
        h1 { margin: 28px 0 12px; color: var(--primary); font-size: clamp(2rem, 8vw, 3.5rem); line-height: 1.05; }
        p { margin: 0 auto; max-width: 540px; color: #4b5563; font-size: 1.125rem; line-height: 1.7; }
        .store-name { margin-top: 24px; color: var(--primary); font-weight: 750; }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">{{ strtoupper(mb_substr($store->store_name, 0, 1)) }}</div>
        <h1>We will be back soon</h1>
        <p>We are refreshing our online store experience. Please check back shortly.</p>
        <div class="store-name">{{ $store->store_name }}</div>
    </main>
</body>
</html>
