@extends('marketing.layout')

@section('title', 'Storeboot — Run your whole business from one place')

@section('content')

    {{-- ============================================================= HERO --}}
    <section class="relative overflow-hidden pt-32 sm:pt-40">
        {{-- decorative background --}}
        <div class="pointer-events-none absolute inset-0 -z-10 text-brand-600/70 dark:text-brand-400/30">
            <div class="sb-grid-bg absolute inset-0"></div>
        </div>
        <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-brand-400/20 blur-3xl dark:bg-brand-500/15"></div>
        <div class="pointer-events-none absolute right-0 top-40 -z-10 h-72 w-72 rounded-full bg-accent-400/20 blur-3xl"></div>

        <div class="sb-container">
            <div class="mx-auto max-w-3xl text-center">
                <span class="sb-eyebrow sb-reveal">
                    <span class="relative flex h-2 w-2">
                        <span class="sb-pulse-ring absolute inline-flex h-full w-full rounded-full bg-brand-500"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-brand-500"></span>
                    </span>
                    Built for African SMEs
                </span>

                <h1 class="sb-reveal mt-6 font-display text-4xl font-bold leading-[1.05] tracking-tight text-zinc-900 sm:text-6xl md:text-[68px] dark:text-white">
                    Run your <span class="sb-text-gradient">whole business</span><br class="hidden sm:block">
                    from <span class="font-serif italic font-normal text-brand-600 dark:text-brand-400">one</span> beautiful place
                </h1>

                <p class="sb-reveal mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-zinc-600 sm:text-xl dark:text-zinc-400">
                    Storeboot replaces your notebooks, spreadsheets and scattered apps with one simple platform —
                    point of sale, inventory, sales, customers, expenses and clear reports that finally make sense.
                </p>

                <div class="sb-reveal mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="sb-btn sb-btn-primary w-full px-7 py-3.5 text-base sm:w-auto">
                        Start free — 14 days
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
                    </a>
                    <a href="#showcase" class="sb-btn sb-btn-ghost w-full px-7 py-3.5 text-base sm:w-auto">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7L8 5Z"/></svg>
                        Watch it work
                    </a>
                </div>

                <p class="sb-reveal mt-5 text-sm text-zinc-500 dark:text-zinc-500">
                    No card required · Set up in minutes · Cancel anytime
                </p>
            </div>

            {{-- Hero product mockup --}}
            <div class="sb-reveal relative mx-auto mt-16 max-w-5xl">
                <div class="overflow-hidden rounded-[26px] border border-zinc-200/80 bg-white shadow-2xl shadow-brand-950/10 dark:border-white/10 dark:bg-ink-900 dark:shadow-black/40">
                    {{-- browser chrome --}}
                    <div class="flex items-center gap-2 border-b border-zinc-100 bg-zinc-50/80 px-4 py-3 dark:border-white/5 dark:bg-white/[0.03]">
                        <span class="h-3 w-3 rounded-full bg-red-400/80"></span>
                        <span class="h-3 w-3 rounded-full bg-amber-400/80"></span>
                        <span class="h-3 w-3 rounded-full bg-green-400/80"></span>
                        <span class="mx-auto flex items-center gap-2 rounded-md bg-white px-3 py-1 text-xs font-medium text-zinc-400 shadow-sm dark:bg-white/5 dark:text-zinc-500">
                            <svg class="h-3 w-3 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 0 0-8 0v4h8Z"/></svg>
                            app.storeboot.com/dashboard
                        </span>
                    </div>
                    @include('marketing.partials.dashboard-mock')
                </div>

                {{-- floating cards --}}
                <div class="sb-float absolute -left-4 top-24 hidden w-52 rounded-2xl border border-zinc-200/80 bg-white/90 p-4 shadow-xl backdrop-blur md:block dark:border-white/10 dark:bg-ink-800/90">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500/15 text-brand-600 dark:text-brand-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-zinc-500">Today's sales</p>
                            <p class="font-display text-lg font-bold text-zinc-900 dark:text-white">₦482,900</p>
                        </div>
                    </div>
                </div>
                <div class="sb-float-slow absolute -right-4 bottom-16 hidden w-52 rounded-2xl border border-zinc-200/80 bg-white/90 p-4 shadow-xl backdrop-blur md:block dark:border-white/10 dark:bg-ink-800/90">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-accent-500/20 text-accent-600 dark:text-accent-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4 12 5 5L20 6"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-zinc-500">Low stock alert</p>
                            <p class="font-display text-sm font-bold text-zinc-900 dark:text-white">3 items to reorder</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================= TRUST MARQUEE --}}
    <section class="py-14">
        <div class="sb-container">
            <p class="text-center text-sm font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                One platform for every kind of business
            </p>
        </div>
        <div class="relative mt-8 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_12%,black_88%,transparent)]">
            <div class="sb-marquee flex w-max gap-4">
                @foreach (array_merge($businessTypes, $businessTypes) as $type)
                    <span class="sb-chip whitespace-nowrap px-4 py-2 text-sm">
                        <span class="text-brand-500">{!! $type['icon'] !!}</span>
                        {{ $type['label'] }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================= STATS --}}
    <section class="sb-section pt-4">
        <div class="sb-container">
            <div class="sb-reveal grid gap-6 rounded-3xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 to-white p-8 sm:grid-cols-2 lg:grid-cols-4 dark:border-white/10 dark:from-white/[0.04] dark:to-transparent">
                @foreach ([
                    ['12+', 'Modules in one system'],
                    ['3 min', 'Average setup time'],
                    ['Offline', 'POS that keeps selling'],
                    ['1 login', 'For all your branches'],
                ] as [$stat, $label])
                    <div class="text-center sm:text-left">
                        <p class="font-display text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl dark:text-white">{{ $stat }}</p>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================= PROBLEM → SOLUTION --}}
    <section class="sb-section pt-0">
        <div class="sb-container">
            <div class="grid items-center gap-14 lg:grid-cols-2">
                <div class="sb-reveal">
                    <span class="sb-eyebrow">The Storeboot difference</span>
                    <h2 class="sb-h2 mt-5">Stop running your business from a notebook.</h2>
                    <p class="sb-lead mt-5">
                        Sales in one app, stock in a book, expenses on WhatsApp, receipts in a drawer.
                        When your data lives everywhere, you can never really see how your business is doing.
                        Storeboot brings it all together — so every sale, item and naira is accounted for.
                    </p>
                    <ul class="mt-8 space-y-4">
                        @foreach ([
                            'Know your real profit — not just what is in the till',
                            'See what is selling and what is gathering dust',
                            'Never run out of your best-selling items again',
                            'Give staff access without giving away control',
                        ] as $point)
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-400">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                </span>
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- real business owner + floating overlays --}}
                <div class="sb-reveal relative">
                    <div class="pointer-events-none absolute -inset-4 -z-10 rounded-[36px] bg-gradient-to-br from-brand-400/25 via-transparent to-accent-400/20 blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-[28px] border border-zinc-200/80 shadow-2xl shadow-brand-950/10 dark:border-white/10">
                        <img src="{{ asset('media/photos/shop-owner.jpg') }}" alt="A Nigerian shop owner in his provisions store" loading="lazy"
                            class="h-[440px] w-full object-cover sm:h-[520px]">
                        <div class="absolute inset-0 bg-gradient-to-t from-ink-950/70 via-ink-950/10 to-transparent"></div>

                        {{-- top-left: before chip --}}
                        <div class="absolute left-4 top-4 rounded-2xl border border-white/15 bg-ink-950/60 px-4 py-3 backdrop-blur-md">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-white/50">Before</p>
                            <p class="mt-0.5 text-sm font-medium text-white/80 line-through decoration-red-400/70">Notebook · WhatsApp · Calculator</p>
                        </div>

                        {{-- bottom: after card --}}
                        <div class="sb-float-slow absolute bottom-5 left-5 right-5 flex items-center gap-3 rounded-2xl border border-white/15 bg-white/95 p-4 shadow-xl backdrop-blur dark:bg-ink-800/90">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-600 text-white"><x-brand-logo mark-only class="scale-[0.72]" /></span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-brand-600 dark:text-brand-400">After</p>
                                <p class="truncate font-display text-base font-bold text-zinc-900 dark:text-white">Everything, in one Storeboot</p>
                            </div>
                        </div>

                        {{-- floating profit stat --}}
                        <div class="sb-float absolute right-4 top-24 hidden rounded-2xl border border-white/15 bg-white/95 px-4 py-3 shadow-xl backdrop-blur sm:block dark:bg-ink-800/90">
                            <p class="text-[10px] text-zinc-500">Profit today</p>
                            <p class="font-display text-lg font-bold text-zinc-900 dark:text-white">₦486K <span class="text-xs font-semibold text-brand-600 dark:text-brand-400">+22%</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================= FEATURES --}}
    <section id="features" class="sb-section scroll-mt-24 bg-zinc-50/70 dark:bg-ink-900/40">
        <div class="sb-container">
            <div class="sb-reveal mx-auto max-w-2xl text-center">
                <span class="sb-eyebrow">Everything you need</span>
                <h2 class="sb-h2 mt-5">One platform. Every part of your business.</h2>
                <p class="sb-lead mt-5">Turn modules on as you grow. Start with sales and inventory, add finance, procurement and payroll whenever you're ready.</p>
            </div>

            <div class="mt-16 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <div class="sb-reveal sb-card group p-6 transition duration-300 hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg dark:hover:border-brand-500/40">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-500/12 text-brand-600 transition group-hover:bg-brand-500 group-hover:text-white dark:text-brand-400">
                            {!! $feature['icon'] !!}
                        </span>
                        <h3 class="mt-5 font-display text-lg font-bold text-zinc-900 dark:text-white">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $feature['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================= PRODUCT SHOWCASE / VIDEO --}}
    <section id="showcase" class="sb-section scroll-mt-24">
        <div class="sb-container">
            <div class="sb-reveal mx-auto max-w-2xl text-center">
                <span class="sb-eyebrow">See it in action</span>
                <h2 class="sb-h2 mt-5">Beautifully simple, seriously powerful.</h2>
                <p class="sb-lead mt-5">A workspace your whole team will actually enjoy using — fast on any device, clear on every screen.</p>
            </div>

            {{-- Video / animated demo --}}
            <div class="sb-reveal mt-14">
                @include('marketing.partials.video-demo')
            </div>

            {{-- Bento grid of product moments --}}
            <div class="mt-8 grid gap-5 lg:grid-cols-3">
                <div class="sb-reveal sb-card lg:col-span-2">
                    <div class="p-6 pb-0">
                        <span class="sb-chip"><span class="text-brand-500">●</span> Point of Sale</span>
                        <h3 class="mt-4 font-display text-2xl font-bold text-zinc-900 dark:text-white">Sell in seconds — even offline</h3>
                        <p class="mt-2 max-w-lg text-sm text-zinc-600 dark:text-zinc-400">A lightning-fast till for your counter. Keep ringing up sales when the internet drops; everything syncs to the cloud automatically.</p>
                    </div>
                    @include('marketing.partials.pos-mock')
                </div>

                <div class="sb-reveal sb-card overflow-hidden bg-gradient-to-br from-brand-600 to-brand-800 p-7 text-white">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">Analytics</span>
                    <h3 class="mt-4 font-display text-2xl font-bold">Numbers you can finally read</h3>
                    <p class="mt-2 text-sm text-white/80">Profit, best-sellers and cash flow — updated with every sale.</p>
                    @include('marketing.partials.analytics-mock')
                </div>

                @foreach ([
                    ['Inventory', 'Every item counted', 'Track stock across branches, get low-stock alerts before you run out.', 'M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                    ['Invoicing', 'Get paid, properly', 'Send clean invoices and receipts, record payments and track who owes you.', 'M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.5L19 8.5V19a2 2 0 0 1-2 2Z'],
                    ['Customers', 'Keep them coming back', 'Know your regulars, their purchases and follow-ups — your own simple CRM.', 'M15 19v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m14-6h6m-3-3v6M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
                ] as [$tag, $title, $body, $icon])
                    <div class="sb-reveal sb-card p-6">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-brand-500/12 text-brand-600 dark:text-brand-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        </span>
                        <span class="mt-4 block text-xs font-bold uppercase tracking-widest text-brand-600 dark:text-brand-400">{{ $tag }}</span>
                        <h3 class="mt-1 font-display text-lg font-bold text-zinc-900 dark:text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================= SOLUTIONS / OFFLINE --}}
    <section id="solutions" class="sb-section scroll-mt-24">
        <div class="sb-container">
            <div class="sb-reveal overflow-hidden rounded-[32px] border border-zinc-200/80 bg-ink-950 text-white dark:border-white/10">
                <div class="grid items-center gap-10 p-8 sm:p-12 lg:grid-cols-2 lg:p-16">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-brand-300">Works how you work</span>
                        <h2 class="mt-5 font-display text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
                            Multiple branches?<br>One clear picture.
                        </h2>
                        <p class="mt-5 text-lg leading-relaxed text-zinc-400">
                            Run every shop, store or outlet under one roof. Move stock between branches,
                            set roles for each team member, and roll up every branch's performance into a single view —
                            with an offline-ready till that never stops selling.
                        </p>
                        <div class="mt-8 grid gap-4 sm:grid-cols-2">
                            @foreach ([
                                ['Offline-first POS', 'Sell without internet, sync at day\'s end'],
                                ['Roles & permissions', 'Right access for every staff member'],
                                ['Branch transfers', 'Move stock where it\'s needed'],
                                ['Cloud dashboard', 'Owner view from anywhere'],
                            ] as [$t, $d])
                                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                                    <p class="font-semibold text-white">{{ $t }}</p>
                                    <p class="mt-1 text-sm text-zinc-400">{{ $d }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="relative">
                        @include('marketing.partials.branches-mock')
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================= TESTIMONIALS --}}
    <section class="sb-section pt-0">
        <div class="sb-container">
            <div class="sb-reveal mx-auto max-w-2xl text-center">
                <span class="sb-eyebrow">Loved by business owners</span>
                <h2 class="sb-h2 mt-5">Built with real shops, for real growth.</h2>
            </div>
            <div class="mt-14 grid gap-5 lg:grid-cols-3">
                @foreach ($testimonials as $i => $t)
                    <figure class="sb-reveal sb-card flex flex-col p-7 {{ $i === 1 ? 'lg:-mt-6 ring-1 ring-brand-500/30' : '' }}">
                        <div class="flex gap-0.5 text-accent-500">
                            @for ($s = 0; $s < 5; $s++)
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01L12 2Z"/></svg>
                            @endfor
                        </div>
                        <blockquote class="mt-4 flex-1 text-[15px] leading-relaxed text-zinc-700 dark:text-zinc-300">“{{ $t['quote'] }}”</blockquote>
                        <figcaption class="mt-6 flex items-center gap-3">
                            <img src="{{ asset($t['photo']) }}" alt="{{ $t['name'] }}, {{ $t['role'] }}" loading="lazy" width="44" height="44" class="h-11 w-11 rounded-full object-cover object-[50%_32%] ring-2 ring-brand-500/30">
                            <span>
                                <span class="block text-sm font-bold text-zinc-900 dark:text-white">{{ $t['name'] }}</span>
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $t['role'] }}</span>
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================= PRICING --}}
    <section id="pricing" class="sb-section scroll-mt-24 bg-zinc-50/70 dark:bg-ink-900/40">
        <div class="sb-container">
            <div class="sb-reveal mx-auto max-w-2xl text-center">
                <span class="sb-eyebrow">Simple pricing</span>
                <h2 class="sb-h2 mt-5">Plans that grow with you.</h2>
                <p class="sb-lead mt-5">Start free for 14 days. No card required. Only pay for the modules you switch on.</p>

                <div class="mt-8 inline-flex items-center gap-1 rounded-full border border-zinc-200 bg-white p-1 dark:border-white/10 dark:bg-white/5">
                    <button type="button" data-billing="monthly" class="sb-bill-btn rounded-full px-5 py-2 text-sm font-semibold transition">Monthly</button>
                    <button type="button" data-billing="yearly" class="sb-bill-btn rounded-full px-5 py-2 text-sm font-semibold transition">
                        Yearly <span class="ml-1 rounded-full bg-brand-500/15 px-2 py-0.5 text-xs text-brand-700 dark:text-brand-300">–20%</span>
                    </button>
                </div>
            </div>

            <div class="mt-14 grid items-stretch gap-6 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="sb-reveal relative flex flex-col rounded-3xl border p-7 {{ $plan['featured'] ? 'border-brand-500 bg-white shadow-2xl shadow-brand-600/10 dark:bg-ink-800 lg:-mt-4 lg:mb-4' : 'border-zinc-200/80 bg-white dark:border-white/10 dark:bg-white/[0.03]' }}">
                        @if ($plan['featured'])
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-3 py-1 text-xs font-bold text-white shadow">Most popular</span>
                        @endif
                        <h3 class="font-display text-lg font-bold text-zinc-900 dark:text-white">{{ $plan['name'] }}</h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan['tagline'] }}</p>
                        <div class="mt-6 flex items-end gap-1">
                            <span class="font-display text-4xl font-bold text-zinc-900 dark:text-white" data-price-monthly="{{ $plan['monthly'] }}" data-price-yearly="{{ $plan['yearly'] }}">{{ $plan['monthly'] }}</span>
                            <span class="pb-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan['unit'] }}</span>
                        </div>
                        <a href="{{ route('register') }}" class="sb-btn {{ $plan['featured'] ? 'sb-btn-primary' : 'sb-btn-ghost' }} mt-6 w-full">{{ $plan['cta'] }}</a>
                        <ul class="mt-7 space-y-3 border-t border-zinc-100 pt-6 text-sm dark:border-white/5">
                            @foreach ($plan['features'] as $f)
                                <li class="flex items-start gap-3 text-zinc-600 dark:text-zinc-300">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                    {{ $f }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="sb-reveal mt-8 text-center text-sm text-zinc-500 dark:text-zinc-400">Prices in Naira. Other currencies supported at signup. Need a custom plan for many branches? <a href="#" class="font-semibold text-brand-600 dark:text-brand-400">Talk to us →</a></p>
        </div>
    </section>

    {{-- ============================================================= FAQ --}}
    <section id="faq" class="sb-section scroll-mt-24">
        <div class="sb-container">
            <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
                <div class="sb-reveal">
                    <span class="sb-eyebrow">Questions</span>
                    <h2 class="sb-h2 mt-5">Everything you want to know.</h2>
                    <p class="sb-lead mt-5">Still curious? Our team is a message away and happy to help you get set up.</p>
                    <a href="#" class="sb-btn sb-btn-dark mt-6">Contact support</a>
                </div>
                <div class="sb-reveal divide-y divide-zinc-200 rounded-3xl border border-zinc-200/80 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-white/[0.03]">
                    @foreach ($faqs as $i => $faq)
                        <details class="group px-6" @if($i === 0) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between py-5 font-semibold text-zinc-900 marker:hidden dark:text-white">
                                {{ $faq['q'] }}
                                <span class="ml-4 grid h-7 w-7 shrink-0 place-items-center rounded-full border border-zinc-200 text-zinc-500 transition group-open:rotate-45 group-open:border-brand-500 group-open:text-brand-600 dark:border-white/10 dark:text-zinc-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                </span>
                            </summary>
                            <p class="pb-5 pr-10 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $faq['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================= FINAL CTA --}}
    <section class="sb-section">
        <div class="sb-container">
            <div class="sb-reveal relative overflow-hidden rounded-[32px] bg-gradient-to-br from-brand-600 via-brand-700 to-ink-950 px-6 py-16 text-center shadow-2xl shadow-brand-600/25 sm:px-12 sm:py-20">
                <div class="pointer-events-none absolute inset-0 sb-grid-bg text-white/40"></div>
                <div class="pointer-events-none absolute -right-10 -top-10 h-64 w-64 rounded-full bg-accent-400/25 blur-3xl"></div>
                <div class="relative mx-auto max-w-2xl">
                    <h2 class="font-display text-3xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
                        Your business deserves to be <span class="font-serif italic font-normal">seen clearly.</span>
                    </h2>
                    <p class="mx-auto mt-5 max-w-xl text-lg text-white/85">
                        Join the businesses moving from guesswork to clarity. Set up Storeboot in minutes — free for 14 days.
                    </p>
                    <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="sb-btn w-full bg-white px-7 py-3.5 text-base text-ink-950 hover:-translate-y-0.5 hover:bg-zinc-100 sm:w-auto">
                            Start your free trial
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
                        </a>
                        <a href="{{ route('login') }}" class="sb-btn w-full border border-white/25 px-7 py-3.5 text-base text-white hover:bg-white/10 sm:w-auto">Sign in</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Lightbox video modal --}}
    <div id="sb-video-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-ink-950/80 p-4 backdrop-blur-sm">
        <div class="relative w-full max-w-4xl">
            <button type="button" onclick="sbCloseVideo()" aria-label="Close" class="absolute -top-11 right-0 grid h-9 w-9 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-black shadow-2xl">
                {{-- Swap the <source> for your own product walkthrough (public/media/storeboot-demo.mp4). --}}
                <video id="sb-video-el" class="aspect-video w-full" controls playsinline preload="none"
                       poster="{{ asset('media/storeboot-poster.svg') }}">
                    <source src="{{ asset('media/storeboot-demo.mp4') }}" type="video/mp4">
                </video>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    // Pricing billing toggle
    (function () {
        var buttons = document.querySelectorAll('.sb-bill-btn');
        var prices = document.querySelectorAll('[data-price-monthly]');
        function setBilling(mode) {
            buttons.forEach(function (b) {
                var active = b.dataset.billing === mode;
                b.classList.toggle('bg-brand-600', active);
                b.classList.toggle('text-white', active);
                b.classList.toggle('shadow', active);
                b.classList.toggle('text-zinc-600', !active);
                b.classList.toggle('dark:text-zinc-300', !active);
            });
            prices.forEach(function (p) {
                p.textContent = mode === 'yearly' ? p.dataset.priceYearly : p.dataset.priceMonthly;
            });
        }
        buttons.forEach(function (b) { b.addEventListener('click', function () { setBilling(b.dataset.billing); }); });
        setBilling('monthly');
    })();

    // Video lightbox
    function sbOpenVideo() {
        var m = document.getElementById('sb-video-modal');
        m.classList.remove('hidden'); m.classList.add('flex');
        var v = document.getElementById('sb-video-el');
        if (v) { try { v.play(); } catch (e) {} }
    }
    function sbCloseVideo() {
        var m = document.getElementById('sb-video-modal');
        m.classList.add('hidden'); m.classList.remove('flex');
        var v = document.getElementById('sb-video-el');
        if (v) { try { v.pause(); } catch (e) {} }
    }
    document.getElementById('sb-video-modal').addEventListener('click', function (e) {
        if (e.target === this) sbCloseVideo();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') sbCloseVideo(); });
</script>
@endpush
