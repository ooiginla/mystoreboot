@extends('marketing.layout')

@section('title', 'About Storeboot · A product of The Bootup Limited')
@section('meta_description', 'Storeboot is building the operating system for African small businesses — a product of The Bootup Limited, Nigeria.')

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden pt-32 pb-16 sm:pt-40">
        <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 h-[460px] w-[780px] -translate-x-1/2 rounded-full bg-brand-400/15 blur-3xl dark:bg-brand-500/10"></div>
        <div class="pointer-events-none absolute inset-0 -z-10 text-brand-600/60 dark:text-brand-400/20"><div class="sb-grid-bg absolute inset-0"></div></div>

        <div class="sb-container">
            <div class="mx-auto max-w-3xl text-center">
                <span class="sb-eyebrow sb-reveal">About Storeboot</span>
                <h1 class="sb-reveal mt-6 font-display text-4xl font-bold leading-[1.08] tracking-tight text-zinc-900 sm:text-6xl dark:text-white">
                    Building the <span class="sb-text-gradient">operating system</span> for African business
                </h1>
                <p class="sb-reveal mx-auto mt-6 max-w-2xl sb-lead">
                    Millions of small businesses power our economies — yet most still run on notebooks, calculators and memory.
                    Storeboot exists to give every one of them the clarity that bigger companies take for granted.
                </p>
                <div class="sb-reveal mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="sb-btn sb-btn-primary px-7 py-3.5 text-base">Start free</a>
                    <a href="{{ route('contact') }}" class="sb-btn sb-btn-ghost px-7 py-3.5 text-base">Talk to us</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Story --}}
    <section class="sb-section pt-4">
        <div class="sb-container">
            <div class="grid items-center gap-14 lg:grid-cols-2">
                <div class="sb-reveal relative order-2 lg:order-1">
                    <div class="pointer-events-none absolute -inset-4 -z-10 rounded-[36px] bg-gradient-to-br from-brand-400/25 via-transparent to-accent-400/20 blur-2xl"></div>
                    <div class="overflow-hidden rounded-[28px] border border-zinc-200/80 shadow-2xl shadow-brand-950/10 dark:border-white/10">
                        <img src="{{ asset('media/photos/market-vendor.jpg') }}" alt="A Nigerian market vendor at her stall" loading="lazy" class="h-[440px] w-full object-cover sm:h-[500px]">
                    </div>
                </div>
                <div class="sb-reveal order-1 lg:order-2">
                    <span class="sb-eyebrow">Our story</span>
                    <h2 class="sb-h2 mt-5">Made by The Bootup Limited, for businesses like yours.</h2>
                    <div class="mt-5 space-y-4 sb-lead">
                        <p>Storeboot is a product of <strong class="text-zinc-800 dark:text-zinc-200">The Bootup Limited</strong>, a technology company registered in Nigeria. We build practical software for the businesses around us — the shops, supermarkets, pharmacies and traders that keep our streets moving.</p>
                        <p>We kept meeting owners who worked incredibly hard but couldn't answer simple questions: <em>Am I actually making a profit? What's selling? What's running out?</em> The tools that could answer them were built for big companies in other markets — expensive, complicated, and offline-unfriendly.</p>
                        <p>So we set out to build one platform that feels as easy as the apps people already use, works even when the network doesn't, and grows one module at a time — from a single till to a multi-branch business.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Values --}}
    <section class="sb-section pt-0">
        <div class="sb-container">
            <div class="sb-reveal mx-auto max-w-2xl text-center">
                <span class="sb-eyebrow">What we believe</span>
                <h2 class="sb-h2 mt-5">The principles behind Storeboot.</h2>
            </div>
            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Simple by default', 'Powerful software should still feel effortless. If a shop owner can\'t use it in minutes, we haven\'t finished the job.', 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z'],
                    ['Built for Africa', 'Offline-first, Naira-first, and designed around how businesses here actually operate — not adapted from somewhere else.', 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 0c2.5 2.5 3.5 6 3.5 9s-1 6.5-3.5 9m0-18c-2.5 2.5-3.5 6-3.5 9s1 6.5 3.5 9M3.5 9h17M3.5 15h17'],
                    ['Your data, your business', 'The records you enter belong to you. We keep them safe, private, and always exportable — never for sale.', 'M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3Zm-1 9 4-4M9 12l2 2'],
                    ['Grow with you', 'Turn on modules as you\'re ready — from a single till to inventory, finance, payroll and more. Never pay for what you don\'t use.', 'M3 3v18h18M7 14l3-3 3 3 5-6'],
                    ['Reliable, always', 'Cloud backups, offline sync and a platform you can trust to be there when the shop is busy and the day is long.', 'm4 12 5 5L20 6'],
                    ['Close to our customers', 'We build with real businesses, listen on WhatsApp, and ship the things that genuinely make their days easier.', 'M15 19v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m14-6h6m-3-3v6M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
                ] as [$title, $body, $icon])
                    <div class="sb-reveal sb-card p-6 transition duration-300 hover:-translate-y-1 hover:border-brand-300 dark:hover:border-brand-500/40">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-500/12 text-brand-600 dark:text-brand-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        </span>
                        <h3 class="mt-5 font-display text-lg font-bold text-zinc-900 dark:text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Mission band --}}
    <section class="sb-section pt-0">
        <div class="sb-container">
            <div class="sb-reveal grid gap-6 rounded-3xl border border-zinc-200/80 bg-gradient-to-br from-zinc-50 to-white p-8 text-center sm:grid-cols-3 dark:border-white/10 dark:from-white/[0.04] dark:to-transparent">
                @foreach ([
                    ['1 platform', 'For your entire business'],
                    ['Offline-first', 'Sell even without internet'],
                    ['Nigeria-built', 'By The Bootup Limited'],
                ] as [$stat, $label])
                    <div>
                        <p class="font-display text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ $stat }}</p>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="sb-section pt-0">
        <div class="sb-container">
            <div class="sb-reveal relative overflow-hidden rounded-[32px] bg-gradient-to-br from-brand-600 via-brand-700 to-ink-950 px-6 py-16 text-center shadow-2xl shadow-brand-600/25 sm:px-12 sm:py-20">
                <div class="pointer-events-none absolute inset-0 sb-grid-bg text-white/40"></div>
                <div class="pointer-events-none absolute -right-10 -top-10 h-64 w-64 rounded-full bg-accent-400/25 blur-3xl"></div>
                <div class="relative mx-auto max-w-2xl">
                    <h2 class="font-display text-3xl font-bold leading-tight tracking-tight text-white sm:text-5xl">Let's grow your business together.</h2>
                    <p class="mx-auto mt-5 max-w-xl text-lg text-white/85">Join the businesses running on Storeboot. Free for 14 days — no card required.</p>
                    <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="sb-btn w-full bg-white px-7 py-3.5 text-base text-ink-950 hover:-translate-y-0.5 hover:bg-zinc-100 sm:w-auto">Start your free trial</a>
                        <a href="{{ route('contact') }}" class="sb-btn w-full border border-white/25 px-7 py-3.5 text-base text-white hover:bg-white/10 sm:w-auto">Contact us</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
