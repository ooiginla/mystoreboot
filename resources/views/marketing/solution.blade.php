@extends('marketing.layout')

@section('title', $page['title'])
@section('meta_description', $page['meta_description'])
@section('canonical', route('solutions.'.$page['slug']))
@section('og_title', $page['h1'].' | Storeboot')
@section('og_description', $page['meta_description'])
@section('og_image', asset($page['image']))

@php
    $softwareSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'Storeboot',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web browser',
        'description' => $page['meta_description'],
        'url' => route('solutions.'.$page['slug']),
        'image' => asset($page['image']),
        'areaServed' => ['@type' => 'Country', 'name' => 'Nigeria'],
        'offers' => [
            ['@type' => 'Offer', 'name' => 'Basic', 'price' => '0', 'priceCurrency' => 'NGN'],
            ['@type' => 'Offer', 'name' => 'Enterprise', 'price' => '5000', 'priceCurrency' => 'NGN'],
        ],
    ];
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($page['faqs'])->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
        ])->values()->all(),
    ];
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page['h1'], 'item' => route('solutions.'.$page['slug'])],
        ],
    ];
    $screens = [
        'online-store' => ['Storefront', '12 new orders', '₦284,500', 'Products published', '148'],
        'inventory-management' => ['Inventory overview', '18 low-stock items', '4 locations', 'Units on hand', '8,426'],
        'pos' => ['Today at the till', '126 completed sales', '₦742,300', 'Average basket', '₦5,891'],
        'restaurant-management' => ['Restaurant floor', '9 occupied tables', '24 active covers', 'Open checks', '₦318,400'],
        'bakery-management' => ['Production overview', '6 batches completed', '42 ingredients', 'Output today', '684 units'],
        'lounge-management' => ['Tonight’s service', '14 open checks', '3 service areas', 'Current tabs', '₦492,800'],
        'retail-management' => ['Retail performance', '4 branches online', '₦2.8m this week', 'Sell-through', '68%'],
        'small-business-software' => ['Business overview', 'All systems active', '4 branches', 'Revenue this month', '₦8.4m'],
    ];
    $screen = $screens[$page['slug']];
@endphp

@push('head')
    <script type="application/ld+json">{!! json_encode($softwareSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <article>
        <header class="relative overflow-hidden bg-ink-950 pb-20 pt-32 text-white sm:pb-28 sm:pt-40">
            <div class="absolute inset-0 opacity-20 sb-grid-bg"></div>
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl"></div>
            <div class="sb-container relative">
                <nav aria-label="Breadcrumb" class="mb-8 flex items-center gap-2 text-xs font-semibold text-zinc-400">
                    <a href="{{ route('home') }}" class="transition hover:text-white">Home</a>
                    <span aria-hidden="true">/</span>
                    <span class="text-brand-300">{{ $page['h1'] }}</span>
                </nav>

                <div class="grid items-center gap-12 lg:grid-cols-[1.02fr_.98fr]">
                    <div>
                        <span class="sb-eyebrow border-brand-400/20 bg-brand-400/10 text-brand-300">{{ $page['eyebrow'] }}</span>
                        <h1 class="mt-6 max-w-3xl font-display text-4xl font-bold leading-[1.04] tracking-tight sm:text-5xl lg:text-6xl">{{ $page['h1'] }}</h1>
                        <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-300">{{ $page['hero'] }}</p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('register') }}" class="sb-btn sb-btn-primary">Start free — no card required
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                            </a>
                            <a href="{{ route('contact') }}" class="sb-btn border border-white/15 bg-white/5 text-white hover:bg-white/10">Talk to our team</a>
                        </div>
                        <ul class="mt-8 flex flex-wrap gap-x-5 gap-y-3 text-sm text-zinc-300">
                            @foreach ($page['proof'] as $proof)
                                <li class="flex items-center gap-2"><svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>{{ $proof }}</li>
                            @endforeach
                        </ul>
                    </div>

                    <figure class="relative">
                        <div class="absolute -inset-3 rounded-[2rem] bg-gradient-to-br from-brand-400/25 to-transparent blur-xl"></div>
                        <img src="{{ asset($page['image']) }}" alt="{{ $page['image_alt'] }}" width="1600" height="900" fetchpriority="high" class="relative aspect-[16/10] w-full rounded-[2rem] border border-white/10 object-cover shadow-2xl shadow-black/40">
                        <figcaption class="sr-only">{{ $page['image_alt'] }}</figcaption>
                    </figure>
                </div>
            </div>
        </header>

        <nav aria-label="On this page" class="sticky top-[76px] z-30 hidden border-b border-zinc-200/80 bg-white/90 backdrop-blur lg:block dark:border-white/10 dark:bg-ink-950/90">
            <div class="sb-container flex items-center justify-between gap-4 py-3 text-xs font-bold text-zinc-500">
                @foreach (['problem' => 'The problem', 'solution' => 'How it works', 'features' => 'Features', 'examples' => 'Examples', 'pricing' => 'Pricing', 'compare' => 'Compare', 'faq' => 'FAQs'] as $anchor => $label)
                    <a href="#{{ $anchor }}" class="rounded-full px-3 py-2 transition hover:bg-brand-50 hover:text-brand-700 dark:hover:bg-white/5 dark:hover:text-brand-300">{{ $label }}</a>
                @endforeach
                <a href="{{ route('register') }}" class="sb-btn sb-btn-primary !px-4 !py-2">Start free</a>
            </div>
        </nav>

        <section id="problem" class="sb-section scroll-mt-32">
            <div class="sb-container grid gap-10 lg:grid-cols-[.75fr_1.25fr]">
                <div>
                    <span class="sb-eyebrow">The operating problem</span>
                    <h2 class="sb-h2 mt-5">{{ $page['problem_title'] }}</h2>
                </div>
                <div class="space-y-6 text-[17px] leading-8 text-zinc-600 dark:text-zinc-300">
                    @foreach ($page['problems'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
                </div>
            </div>
        </section>

        <section id="solution" class="scroll-mt-32 bg-zinc-50 py-20 sm:py-28 dark:bg-ink-900">
            <div class="sb-container">
                <div class="grid gap-12 lg:grid-cols-[.9fr_1.1fr] lg:items-center">
                    <div class="space-y-6 text-[17px] leading-8 text-zinc-600 dark:text-zinc-300">
                        <span class="sb-eyebrow">The Storeboot approach</span>
                        <h2 class="sb-h2 !mb-8">{{ $page['solution_title'] }}</h2>
                        @foreach ($page['solutions'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
                    </div>

                    <figure class="overflow-hidden rounded-[2rem] border border-zinc-200 bg-white shadow-2xl shadow-zinc-900/10 dark:border-white/10 dark:bg-ink-950">
                        <figcaption class="flex items-center gap-2 border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span><span class="h-2.5 w-2.5 rounded-full bg-brand-400"></span>
                            <span class="ml-2 text-xs font-semibold text-zinc-500">Storeboot · {{ $screen[0] }}</span>
                        </figcaption>
                        <div class="grid gap-4 p-5 sm:grid-cols-3 sm:p-7">
                            <div class="rounded-2xl bg-ink-950 p-5 text-white sm:col-span-2">
                                <p class="text-xs font-bold uppercase tracking-widest text-brand-300">{{ $screen[0] }}</p>
                                <p class="mt-3 font-display text-3xl font-bold">{{ $screen[2] }}</p>
                                <p class="mt-1 text-sm text-zinc-400">{{ $screen[1] }}</p>
                                <div class="mt-8 flex h-28 items-end gap-2" aria-hidden="true">
                                    @foreach ([36, 52, 44, 72, 63, 88, 78, 96, 84] as $height)
                                        <span class="flex-1 rounded-t-md bg-brand-500/80" style="height: {{ $height }}%"></span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="rounded-2xl border border-zinc-200 p-5 dark:border-white/10">
                                <p class="text-xs font-bold uppercase tracking-widest text-zinc-500">{{ $screen[3] }}</p>
                                <p class="mt-3 font-display text-3xl font-bold text-zinc-900 dark:text-white">{{ $screen[4] }}</p>
                                <p class="mt-2 text-sm text-brand-600 dark:text-brand-400">↑ Updated live</p>
                            </div>
                            @foreach (['Sales', 'Stock', 'Customers'] as $label)
                                <div class="rounded-2xl border border-zinc-200 p-4 dark:border-white/10">
                                    <div class="h-2 w-16 rounded bg-zinc-200 dark:bg-white/10"></div>
                                    <p class="mt-5 text-sm font-bold text-zinc-900 dark:text-white">{{ $label }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">View current activity</p>
                                </div>
                            @endforeach
                        </div>
                    </figure>
                </div>
                <p class="mt-5 text-center text-xs text-zinc-500">Illustrative Storeboot product view. Available information depends on your plan, modules and business setup.</p>
            </div>
        </section>

        <section id="features" class="sb-section scroll-mt-32">
            <div class="sb-container">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="sb-eyebrow">Capabilities that work together</span>
                    <h2 class="sb-h2 mt-5">The features behind a more controlled operation</h2>
                    <p class="sb-lead mx-auto mt-5">Each feature is useful alone. The bigger advantage comes when the same sale, item, location, customer and payment keep their context across the platform.</p>
                </div>
                <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($page['features'] as $index => $feature)
                        <section class="sb-card p-7">
                            <div class="grid h-11 w-11 place-items-center rounded-2xl bg-brand-50 font-display text-sm font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">0{{ $index + 1 }}</div>
                            <h3 class="mt-6 font-display text-xl font-bold text-zinc-900 dark:text-white">{{ $feature['title'] }}</h3>
                            <p class="mt-3 leading-7 text-zinc-600 dark:text-zinc-400">{{ $feature['body'] }}</p>
                        </section>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-ink-950 py-20 text-white sm:py-28">
            <div class="sb-container">
                <div class="max-w-3xl">
                    <span class="sb-eyebrow border-brand-400/20 bg-brand-400/10 text-brand-300">A practical rollout</span>
                    <h2 class="mt-5 font-display text-3xl font-bold tracking-tight sm:text-5xl">From setup to useful daily records</h2>
                    <p class="mt-5 text-lg leading-8 text-zinc-400">Good software does not repair a process by magic. It gives the team a clear sequence, fewer places to record the same event, and better evidence for the next decision.</p>
                </div>
                <ol class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ($page['workflow'] as $index => $step)
                        <li class="rounded-3xl border border-white/10 bg-white/5 p-6">
                            <span class="font-display text-4xl font-bold text-brand-400">{{ $index + 1 }}</span>
                            <h3 class="mt-5 font-display text-lg font-bold">{{ $step['title'] }}</h3>
                            <p class="mt-3 text-sm leading-6 text-zinc-400">{{ $step['body'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="examples" class="sb-section scroll-mt-32">
            <div class="sb-container">
                <div class="grid gap-12 lg:grid-cols-[.7fr_1.3fr]">
                    <div>
                        <span class="sb-eyebrow">Industry examples</span>
                        <h2 class="sb-h2 mt-5">What this looks like in a real business</h2>
                        <p class="mt-5 leading-7 text-zinc-600 dark:text-zinc-400">Storeboot is configurable because two businesses in the same industry may sell, fulfil and account differently. These examples show common patterns—not rigid templates.</p>
                    </div>
                    <div class="space-y-4">
                        @foreach ($page['examples'] as $example)
                            <section class="rounded-3xl border border-zinc-200 p-7 dark:border-white/10">
                                <h3 class="font-display text-xl font-bold text-zinc-900 dark:text-white">{{ $example['title'] }}</h3>
                                <p class="mt-2 leading-7 text-zinc-600 dark:text-zinc-400">{{ $example['body'] }}</p>
                            </section>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="pricing" class="scroll-mt-32 bg-brand-50 py-20 sm:py-28 dark:bg-brand-950/20">
            <div class="sb-container">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="sb-eyebrow">Straightforward pricing</span>
                    <h2 class="sb-h2 mt-5">Start free. Upgrade when operations demand more.</h2>
                    <p class="sb-lead mx-auto mt-5">Basic is built for getting online and organised. Enterprise adds the branch, till, people, procurement and finance controls required by more complex teams.</p>
                </div>
                <div class="mx-auto mt-12 grid max-w-5xl gap-6 lg:grid-cols-2">
                    @foreach ($plans as $plan)
                        <section class="rounded-[2rem] border {{ $plan['featured'] ? 'border-brand-300 bg-white shadow-xl shadow-brand-900/10' : 'border-zinc-200 bg-white' }} p-8 dark:border-white/10 dark:bg-ink-900">
                            <div class="flex items-start justify-between gap-4">
                                <div><h3 class="font-display text-2xl font-bold text-zinc-900 dark:text-white">{{ $plan['name'] }}</h3><p class="mt-2 text-sm leading-6 text-zinc-500">{{ $plan['tagline'] }}</p></div>
                                @if ($plan['featured'])<span class="sb-chip border-brand-200 text-brand-700">Start here</span>@endif
                            </div>
                            <p class="mt-7"><span class="font-display text-4xl font-bold text-zinc-900 dark:text-white">{{ $plan['monthly'] }}</span> <span class="text-sm text-zinc-500">{{ $plan['unit'] }}</span></p>
                            <a href="{{ route('register') }}" class="sb-btn {{ $plan['featured'] ? 'sb-btn-primary' : 'sb-btn-dark' }} mt-7 w-full">{{ $plan['cta'] }}</a>
                            <ul class="mt-7 grid gap-3 text-sm sm:grid-cols-2">
                                @foreach (array_slice($plan['features'], 0, 10) as $feature)
                                    <li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
                <p class="mx-auto mt-6 max-w-3xl text-center text-sm leading-6 text-zinc-500">Yearly Enterprise billing is listed at ₦4,000 per month. Prices shown are in Nigerian Naira and may change; taxes, payment processing and any third-party services are separate where applicable.</p>
            </div>
        </section>

        <section id="compare" class="sb-section scroll-mt-32">
            <div class="sb-container">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="sb-eyebrow">A useful comparison</span>
                    <h2 class="sb-h2 mt-5">Storeboot versus a disconnected setup</h2>
                    <p class="mt-5 text-left text-[17px] leading-8 text-zinc-600 dark:text-zinc-300">{{ $page['comparison_intro'] }}</p>
                </div>
                <div class="mt-10 overflow-hidden rounded-3xl border border-zinc-200 dark:border-white/10">
                    <div class="grid grid-cols-[.7fr_1fr_1fr] bg-ink-950 px-5 py-4 text-xs font-bold uppercase tracking-wider text-white sm:px-7">
                        <span>Need</span><span class="text-brand-300">With Storeboot</span><span>Typical manual setup</span>
                    </div>
                    @foreach ($page['comparison'] as $row)
                        <div class="grid grid-cols-[.7fr_1fr_1fr] gap-3 border-t border-zinc-200 px-5 py-5 text-sm leading-6 first:border-0 sm:px-7 dark:border-white/10">
                            <strong class="text-zinc-900 dark:text-white">{{ $row['need'] }}</strong>
                            <span class="text-zinc-700 dark:text-zinc-300">{{ $row['storeboot'] }}</span>
                            <span class="text-zinc-500">{{ $row['alternative'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="faq" class="scroll-mt-32 bg-zinc-50 py-20 sm:py-28 dark:bg-ink-900">
            <div class="sb-container grid gap-12 lg:grid-cols-[.7fr_1.3fr]">
                <div>
                    <span class="sb-eyebrow">Frequently asked questions</span>
                    <h2 class="sb-h2 mt-5">Questions business owners ask before switching</h2>
                    <p class="mt-5 leading-7 text-zinc-600 dark:text-zinc-400">Need an answer specific to your process? Tell us how your team works and we will help you map it honestly.</p>
                    <a href="{{ route('contact') }}" class="sb-btn sb-btn-ghost mt-7">Ask a question</a>
                </div>
                <div class="space-y-3">
                    @foreach ($page['faqs'] as $faq)
                        <details class="group rounded-2xl border border-zinc-200 bg-white p-6 open:shadow-sm dark:border-white/10 dark:bg-white/[.03]">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-5 font-display font-bold text-zinc-900 dark:text-white">{{ $faq['q'] }}<span class="text-2xl font-normal text-brand-600 transition group-open:rotate-45">+</span></summary>
                            <p class="mt-4 pr-8 leading-7 text-zinc-600 dark:text-zinc-400">{{ $faq['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="py-20 sm:py-28">
            <div class="sb-container">
                <div class="rounded-[2.5rem] bg-ink-950 px-7 py-14 text-center text-white sm:px-12 sm:py-20">
                    <span class="sb-eyebrow border-brand-400/20 bg-brand-400/10 text-brand-300">Your next working day can be clearer</span>
                    <h2 class="mx-auto mt-6 max-w-4xl font-display text-3xl font-bold tracking-tight sm:text-5xl">Put sales, stock and the rest of the operation in one place.</h2>
                    <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-zinc-400">Create your Storeboot account without a card. Start with the workflow that matters most, bring the team in, and add more control as the business grows.</p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="sb-btn sb-btn-primary">Start free</a>
                        <a href="{{ route('contact') }}" class="sb-btn border border-white/15 bg-white/5 text-white hover:bg-white/10">Discuss your setup</a>
                    </div>
                </div>
            </div>
        </section>

        <aside aria-labelledby="related-heading" class="border-t border-zinc-200 py-16 dark:border-white/10">
            <div class="sb-container">
                <h2 id="related-heading" class="font-display text-2xl font-bold text-zinc-900 dark:text-white">Explore related Storeboot solutions</h2>
                <div class="mt-7 grid gap-4 md:grid-cols-3">
                    @foreach ($page['related'] as $relatedSlug)
                        @php $related = $pages[$relatedSlug]; @endphp
                        <a href="{{ route('solutions.'.$relatedSlug) }}" class="group rounded-3xl border border-zinc-200 p-6 transition hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg dark:border-white/10">
                            <span class="text-xs font-bold uppercase tracking-widest text-brand-600 dark:text-brand-400">Storeboot solution</span>
                            <h3 class="mt-3 font-display text-lg font-bold text-zinc-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">{{ $related['h1'] }}</h3>
                            <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold">Learn more <span aria-hidden="true">→</span></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </aside>
    </article>
@endsection
