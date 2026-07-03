<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-head')
    <title>@yield('title', 'Storeboot')</title>
</head>
<body class="min-h-screen bg-white font-sans text-zinc-700 antialiased dark:bg-ink-950 dark:text-zinc-300">
    <div class="grid min-h-screen lg:grid-cols-2">

        {{-- ============ SHOWCASE PANEL (desktop) ============ --}}
        <aside class="relative hidden overflow-hidden bg-ink-950 p-12 text-white lg:flex lg:flex-col">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-700 via-ink-900 to-ink-950"></div>
            <div class="pointer-events-none absolute -left-20 top-10 h-72 w-72 rounded-full bg-brand-500/25 blur-3xl"></div>
            <div class="pointer-events-none absolute bottom-0 right-0 h-72 w-72 rounded-full bg-accent-400/15 blur-3xl"></div>
            <div class="sb-grid-bg pointer-events-none absolute inset-0 text-white/40"></div>

            <div class="relative flex h-full flex-col">
                <a href="{{ url('/') }}" class="flex items-center" aria-label="Storeboot home">
                    <x-brand-logo word-class="text-white" />
                </a>

                <div class="my-auto max-w-md">
                    <h2 class="font-display text-4xl font-bold leading-[1.1] tracking-tight">
                        Run your whole business from <span class="font-serif italic font-normal text-accent-300">one</span> place.
                    </h2>
                    <p class="mt-4 text-lg text-white/70">
                        Sales, inventory, customers and clear reports — the simple platform African businesses grow on.
                    </p>

                    <ul class="mt-8 space-y-3">
                        @foreach (['Point of sale that works offline', 'One dashboard for every branch', 'Know your real profit, in real time'] as $point)
                            <li class="flex items-center gap-3 text-white/85">
                                <span class="grid h-6 w-6 place-items-center rounded-full bg-white/15">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                </span>
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>

                    <figure class="mt-10 rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur">
                        <blockquote class="text-sm leading-relaxed text-white/90">“Storeboot changed how I run my shop. Everything is finally in one place.”</blockquote>
                        <figcaption class="mt-3 flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-xs font-bold">AO</span>
                            <span class="text-xs text-white/70">Amaka Obi · FreshMart Grocery</span>
                        </figcaption>
                    </figure>
                </div>

                <p class="relative text-xs text-white/50">© {{ date('Y') }} Storeboot. Built for businesses that are going places.</p>
            </div>
        </aside>

        {{-- ============ FORM PANEL ============ --}}
        <main class="relative flex flex-col px-5 py-8 sm:px-8">
            <div class="flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center lg:hidden" aria-label="Storeboot home">
                    <x-brand-logo />
                </a>
                <a href="{{ url('/') }}" class="hidden items-center gap-1.5 text-sm font-semibold text-zinc-500 transition hover:text-zinc-800 lg:inline-flex dark:text-zinc-400 dark:hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0 6 6m-6-6 6-6"/></svg>
                    Back to home
                </a>
                <button type="button" onclick="sbToggleTheme()" aria-label="Toggle dark mode"
                    class="grid h-10 w-10 place-items-center rounded-full border border-zinc-200 text-zinc-600 transition hover:bg-zinc-100 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5">
                    <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m15.36 6.36-1.06-1.06M6.7 6.7 5.64 5.64m12.72 0L17.3 6.7M6.7 17.3l-1.06 1.06M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                </button>
            </div>

            <div class="mx-auto flex w-full @yield('formWidth', 'max-w-md') flex-1 flex-col justify-center py-10">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        function sbToggleTheme() {
            var isDark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('sb-theme', isDark ? 'dark' : 'light'); } catch (e) {}
        }
    </script>
    @stack('scripts')
</body>
</html>
