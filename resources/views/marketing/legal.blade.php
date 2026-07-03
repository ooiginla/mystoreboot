@extends('marketing.layout')

@section('title', $pageTitle . ' · Storeboot')
@section('meta_description', 'Storeboot ' . $pageTitle . ' — a product of The Bootup Limited, Nigeria.')

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden pt-32 pb-14 sm:pt-40">
        <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 h-[420px] w-[720px] -translate-x-1/2 rounded-full bg-brand-400/15 blur-3xl dark:bg-brand-500/10"></div>
        <div class="sb-container">
            <div class="mx-auto max-w-3xl">
                <span class="sb-eyebrow">{{ $eyebrow }}</span>
                <h1 class="mt-5 font-display text-4xl font-bold leading-tight tracking-tight text-zinc-900 sm:text-5xl dark:text-white">{{ $pageTitle }}</h1>
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Last updated {{ $updated }}</p>
                <p class="mt-6 text-lg leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $intro }}</p>
            </div>
        </div>
    </section>

    {{-- Body --}}
    <section class="pb-24">
        <div class="sb-container">
            <div class="mx-auto max-w-3xl">
                <div class="space-y-10 rounded-3xl border border-zinc-200/80 bg-white p-8 sm:p-12 dark:border-white/10 dark:bg-white/[0.02]">
                    @foreach ($sections as $i => $section)
                        <div>
                            <h2 class="flex items-baseline gap-3 font-display text-xl font-bold text-zinc-900 dark:text-white">
                                <span class="text-sm font-semibold text-brand-500">{{ sprintf('%02d', $i + 1) }}</span>
                                {{ $section['heading'] }}
                            </h2>
                            <div class="mt-3 space-y-3 pl-8 text-[15px] leading-relaxed text-zinc-600 dark:text-zinc-300">
                                @foreach ($section['blocks'] as $block)
                                    @if (is_array($block))
                                        <ul class="space-y-2">
                                            @foreach ($block['list'] as $item)
                                                <li class="flex items-start gap-2.5">
                                                    <svg class="mt-1.5 h-3.5 w-3.5 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                                    <span>{{ $item }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p>{{ $block }}</p>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 rounded-2xl border border-brand-200 bg-brand-50 p-6 text-sm text-brand-900 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-200">
                    Storeboot is a product of <strong>The Bootup Limited</strong>, a company registered in Nigeria.
                    Questions? Email <a href="mailto:support@storeboot.com" class="font-semibold underline decoration-brand-400 underline-offset-2">support@storeboot.com</a>
                    or visit our <a href="{{ route('contact') }}" class="font-semibold underline decoration-brand-400 underline-offset-2">Contact page</a>.
                </div>
            </div>
        </div>
    </section>
@endsection
