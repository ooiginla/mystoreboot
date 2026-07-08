<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('partials.theme-head')
    <title>@yield('title', 'Storeboot — Run your whole business from one place')</title>
    <meta name="description" content="@yield('meta_description', 'Storeboot is the all-in-one platform for African SMEs — point of sale, inventory, sales, customers, procurement, finance and analytics in one beautifully simple system.')">
</head>
<body class="min-h-screen bg-white font-sans text-zinc-700 antialiased dark:bg-ink-950 dark:text-zinc-300">

    {{-- ===================== NAV ===================== --}}
    <header class="fixed inset-x-0 top-0 z-50">
        <div class="sb-container">
            <nav class="mt-3 flex items-center justify-between rounded-2xl border border-zinc-200/70 bg-white/80 px-4 py-2.5 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-ink-900/70">
                <a href="{{ url('/') }}" class="flex items-center" aria-label="Storeboot home">
                    <x-brand-logo />
                </a>

                <div class="hidden items-center gap-1 lg:flex">
                    <a href="#features" class="rounded-full px-4 py-2 text-sm font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5 dark:hover:text-white">Features</a>
                    <a href="#solutions" class="rounded-full px-4 py-2 text-sm font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5 dark:hover:text-white">Solutions</a>
                    <a href="#showcase" class="rounded-full px-4 py-2 text-sm font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5 dark:hover:text-white">Product</a>
                    <a href="#pricing" class="rounded-full px-4 py-2 text-sm font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5 dark:hover:text-white">Pricing</a>
                    <a href="#faq" class="rounded-full px-4 py-2 text-sm font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5 dark:hover:text-white">FAQ</a>
                    <a href="{{ route('about') }}" class="rounded-full px-4 py-2 text-sm font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5 dark:hover:text-white">About</a>
                    <a href="{{ route('contact') }}" class="rounded-full px-4 py-2 text-sm font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5 dark:hover:text-white">Contact</a>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="sbToggleTheme()" aria-label="Toggle dark mode"
                        class="grid h-10 w-10 place-items-center rounded-full border border-zinc-200 text-zinc-600 transition hover:bg-zinc-100 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5">
                        <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m15.36 6.36-1.06-1.06M6.7 6.7 5.64 5.64m12.72 0L17.3 6.7M6.7 17.3l-1.06 1.06M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg>
                        <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                    </button>

                    @auth
                        <a href="{{ route('admin.business.index') }}" class="sb-btn sb-btn-dark hidden sm:inline-flex">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden rounded-full px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:text-zinc-900 sm:inline-block dark:text-zinc-200 dark:hover:text-white">Sign in</a>
                        <a href="{{ route('register') }}" class="sb-btn sb-btn-primary hidden sm:inline-flex">Start free</a>
                    @endauth

                    <button type="button" onclick="sbToggleMenu()" aria-label="Open menu"
                        class="grid h-10 w-10 place-items-center rounded-full border border-zinc-200 text-zinc-700 lg:hidden dark:border-white/10 dark:text-zinc-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                </div>
            </nav>
        </div>

        {{-- Mobile menu --}}
        <div id="sb-mobile-menu" class="hidden lg:hidden">
            <div class="sb-container">
                <div class="mt-2 space-y-1 rounded-2xl border border-zinc-200/70 bg-white p-3 shadow-xl dark:border-white/10 dark:bg-ink-900">
                    <a href="#features" onclick="sbToggleMenu()" class="block rounded-xl px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">Features</a>
                    <a href="#solutions" onclick="sbToggleMenu()" class="block rounded-xl px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">Solutions</a>
                    <a href="#showcase" onclick="sbToggleMenu()" class="block rounded-xl px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">Product</a>
                    <a href="#pricing" onclick="sbToggleMenu()" class="block rounded-xl px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">Pricing</a>
                    <a href="#faq" onclick="sbToggleMenu()" class="block rounded-xl px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">FAQ</a>
                    <a href="{{ route('about') }}" class="block rounded-xl px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">About</a>
                    <a href="{{ route('contact') }}" class="block rounded-xl px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">Contact</a>
                    <div class="grid grid-cols-2 gap-2 pt-2">
                        <a href="{{ route('login') }}" class="sb-btn sb-btn-ghost">Sign in</a>
                        <a href="{{ route('register') }}" class="sb-btn sb-btn-primary">Start free</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    {{-- ===================== FOOTER ===================== --}}
    <footer class="border-t border-zinc-200/70 bg-zinc-50 dark:border-white/10 dark:bg-ink-900">
        <div class="sb-container py-16">
            <div class="grid gap-12 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div>
                    <x-brand-logo />
                    <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        The operating system for growing African businesses. Sell, stock, and see your numbers — all in one place.
                    </p>
                    <div class="mt-5 flex gap-2">
                        @foreach (['twitter' => 'M18.9 3H22l-7.1 8.1L23 21h-6.2l-4.9-6.4L6.2 21H3l7.6-8.7L2.6 3H9l4.4 5.9L18.9 3Z', 'instagram' => 'M12 8.6A3.4 3.4 0 1 0 12 15.4 3.4 3.4 0 0 0 12 8.6Zm5.1-.9a.9.9 0 1 1-1.8 0 .9.9 0 0 1 1.8 0ZM12 6.9c-1.7 0-1.9 0-2.6.04-.7.03-1 .1-1.3.2a2.6 2.6 0 0 0-1.5 1.5c-.1.3-.17.6-.2 1.3C6.3 10.1 6.3 10.3 6.3 12s0 1.9.04 2.6c.03.7.1 1 .2 1.3a2.6 2.6 0 0 0 1.5 1.5c.3.1.6.17 1.3.2.7.04.9.04 2.6.04s1.9 0 2.6-.04c.7-.03 1-.1 1.3-.2a2.6 2.6 0 0 0 1.5-1.5c.1-.3.17-.6.2-1.3.04-.7.04-.9.04-2.6s0-1.9-.04-2.6c-.03-.7-.1-1-.2-1.3a2.6 2.6 0 0 0-1.5-1.5c-.3-.1-.6-.17-1.3-.2C13.9 6.9 13.7 6.9 12 6.9Z', 'linkedin' => 'M6.9 8.4H4V20h2.9V8.4ZM5.4 4a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4ZM20 20h-2.9v-5.6c0-1.5-.6-2.2-1.7-2.2-1 0-1.6.7-1.6 2.2V20H11V8.4h2.8v1.3c.4-.7 1.3-1.6 3-1.6 2.1 0 3.2 1.4 3.2 4V20Z'] as $name => $d)
                            <a href="#" aria-label="{{ ucfirst($name) }}" class="grid h-9 w-9 place-items-center rounded-full border border-zinc-200 text-zinc-500 transition hover:border-brand-400 hover:text-brand-600 dark:border-white/10 dark:text-zinc-400 dark:hover:text-brand-400">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $d }}"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>

                @php $waGroup = 'https://chat.whatsapp.com/Iy1epwuwKIC2SAIEVMjAKa?mode=gi_t'; @endphp
                @foreach ([
                    'Product' => ['Features' => '#features', 'Point of Sale' => '#solutions', 'Product showcase' => '#showcase', 'Pricing' => '#pricing'],
                    'Company' => ['About us' => route('about'), 'Contact' => route('contact'), 'Privacy' => route('legal.privacy'), 'Terms' => route('legal.terms')],
                    'Resources' => ['Help center' => route('contact'), 'FAQ' => '#faq', 'Community' => $waGroup, 'Security' => route('legal.security')],
                ] as $heading => $links)
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ $heading }}</h4>
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($links as $label => $href)
                                <li><a href="{{ $href }}" @if(str_starts_with($href, 'http')) target="_blank" rel="noopener" @endif class="text-zinc-600 transition hover:text-brand-600 dark:text-zinc-400 dark:hover:text-brand-400">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <div class="mt-14 flex flex-col items-center justify-between gap-4 border-t border-zinc-200/70 pt-8 text-sm text-zinc-500 sm:flex-row dark:border-white/10">
                <p>© {{ date('Y') }} Storeboot — a product of The Bootup Limited, Nigeria.</p>
                <div class="flex gap-6">
                    <a href="{{ route('legal.privacy') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Privacy</a>
                    <a href="{{ route('legal.terms') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Terms</a>
                    <a href="{{ route('legal.security') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Security</a>
                </div>
            </div>
        </div>
    </footer>

    {{-- Floating WhatsApp contact — fixed, glowing like a ringing phone --}}
    <a href="https://wa.me/2347035361770?text=Hi%20Storeboot%2C%20I%27d%20like%20to%20know%20more"
       target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp"
       class="group fixed bottom-5 right-5 z-[70] flex items-center sm:bottom-7 sm:right-7">
        <span class="pointer-events-none absolute right-0 mr-16 hidden whitespace-nowrap rounded-full bg-ink-950 px-3.5 py-2 text-sm font-semibold text-white opacity-0 shadow-lg transition group-hover:opacity-100 sm:block dark:bg-white dark:text-ink-950">Chat with us</span>
        <span class="relative grid h-14 w-14 place-items-center">
            <span class="sb-wa-wave absolute inset-0 rounded-full bg-[#25D366]/60"></span>
            <span class="sb-wa-wave absolute inset-0 rounded-full bg-[#25D366]/60" style="animation-delay:.9s"></span>
            <span class="sb-wa-ring relative grid h-14 w-14 place-items-center rounded-full bg-[#25D366] text-white shadow-xl shadow-[#25D366]/40 transition group-hover:scale-105">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.9-.8-1.5-1.77-1.67-2.07-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.6-.92-2.2-.24-.58-.48-.5-.67-.5l-.57-.02c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.47 0 1.46 1.07 2.87 1.22 3.07.15.2 2.1 3.2 5.08 4.48.71.3 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.13-.27-.2-.57-.35Z"/><path d="M12 2a10 10 0 0 0-8.5 15.27L2 22l4.85-1.27A10 10 0 1 0 12 2Zm0 1.9a8.1 8.1 0 0 1 6.9 12.36l-.28.44.66 2.4-2.46-.65-.42.25A8.1 8.1 0 1 1 12 3.9Z"/></svg>
            </span>
        </span>
    </a>

    <script>
        function sbToggleTheme() {
            var isDark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('sb-theme', isDark ? 'dark' : 'light'); } catch (e) {}
        }
        function sbToggleMenu() {
            var m = document.getElementById('sb-mobile-menu');
            if (m) m.classList.toggle('hidden');
        }
        // Scroll reveal
        (function () {
            var els = document.querySelectorAll('.sb-reveal');
            if (!('IntersectionObserver' in window) || !els.length) {
                els.forEach(function (el) { el.classList.add('is-in'); });
                return;
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-in');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });
            els.forEach(function (el) { io.observe(el); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
