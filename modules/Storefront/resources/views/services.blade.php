@extends('storefront::layout', ['title' => 'Services | '.$store->store_name, 'metaDescription' => $metaDescription ?? null, 'canonical' => $canonical ?? null])

@push('styles')
    <style>
        .store-services-layout { display: grid; gap: 32px; align-items: start; }
        .store-service-category-chevron { transform: rotate(90deg); transition: transform .18s ease; }
        .store-service-category[open] .store-service-category-chevron { transform: rotate(-90deg); }
        .store-service-action { min-width: 0; padding-left: 12px; padding-right: 12px; font-size: 12px; line-height: 16px; letter-spacing: .02em; white-space: nowrap; }
        .store-service-profile-logo { width: 112px; height: 112px; aspect-ratio: 1 / 1; border-radius: 50%; }
        @media (min-width: 1024px) {
            .store-services-layout { grid-template-columns: minmax(0, 1fr) 340px; }
            .store-service-profile { position: sticky; top: 164px; }
        }
    </style>
@endpush

@php
    $profileLogoUrl = $store->logo_path ? '/storage/'.ltrim($store->logo_path, '/') : null;
    $profileAddress = collect([$store->address, $store->city, $store->state, $store->country])->filter()->join(', ');
    $profileWhatsapp = preg_replace('/\D+/', '', (string) ($store->store_whatsapp ?: data_get($store->social_accounts, 'whatsapp')));
    $todayKey = now($store->tenant?->timezone ?: 'Africa/Lagos')->format('l');
    $todayHours = data_get($store->tenant?->opening_hours, $todayKey)
        ?? data_get($store->tenant?->opening_hours, strtolower($todayKey))
        ?? [];
    $isScheduledOpen = (bool) ($todayHours['is_open'] ?? false);
    $opensAt = $todayHours['opens_at'] ?? null;
    $closesAt = $todayHours['closes_at'] ?? null;
    $hoursLabel = null;
    $openStatus = null;

    if ($isScheduledOpen && $opensAt && $closesAt) {
        $timezone = $store->tenant?->timezone ?: 'Africa/Lagos';
        $currentTime = now($timezone);
        $openingTime = $currentTime->copy()->setTimeFromTimeString($opensAt);
        $closingTime = $currentTime->copy()->setTimeFromTimeString($closesAt);
        if ($closingTime->lessThanOrEqualTo($openingTime)) {
            $closingTime->addDay();
        }
        $openStatus = $currentTime->between($openingTime, $closingTime) ? 'Open now' : 'Closed now';
        $hoursLabel = $openingTime->format('g:i A').' – '.$closingTime->format('g:i A');
    } elseif ($store->tenant?->opening_hours) {
        $openStatus = 'Closed today';
    }
@endphp

@section('content')
    @if ($store->maintenance_mode)
        <section class="store-shell py-20">
            <div class="mx-auto max-w-2xl text-center">
                @include('storefront::partials.icon', ['name' => 'construction', 'class' => 'mx-auto h-16 w-16 text-[var(--store-secondary)]'])
                <h1 class="sf-headline-lg mt-5 text-[var(--store-primary)]">We will be back soon</h1>
                <p class="sf-body-lg mt-4 text-[var(--store-muted)]">{{ $store->store_name }} is refreshing the online store experience. Please check back shortly.</p>
            </div>
        </section>
    @else
        <section id="services" class="store-shell pb-14 pt-8">
            <div data-services-heading>
                <h1 class="sf-headline-lg text-[var(--store-primary)]">Our Services</h1>
                <p class="sf-body-md mt-2 text-[var(--store-muted)]">Browse services available from {{ $store->store_name }}.</p>
            </div>

            <div class="store-services-layout mt-8">
                <div id="service-list" class="min-w-0">
                    <div class="space-y-8" data-service-groups>
                        @forelse ($serviceGroups as $group)
                            <details class="store-service-category" data-service-category open>
                                <summary class="sf-headline-md flex cursor-pointer list-none items-center gap-2 text-[var(--store-ink)]">
                                    {{ $group['category']?->name ?? 'Other services' }}
                                    @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'store-service-category-chevron h-5 w-5 text-[var(--store-muted)]'])
                                </summary>
                                <div class="mt-4 grid gap-4">
                                    @foreach ($group['services'] as $service)
                                        @include('storefront::partials.service-row', ['service' => $service])
                                    @endforeach
                                </div>
                            </details>
                        @empty
                            <div class="store-card p-10 text-center">
                                <h3 class="sf-headline-lg-mobile">No services available yet</h3>
                                <p class="sf-body-md mt-2 text-[var(--store-muted)]">Please check back soon.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <aside class="store-card store-service-profile overflow-hidden text-center" data-service-profile aria-label="{{ $store->store_name }} business information">
                    <div class="p-6">
                        @if ($profileLogoUrl)
                            <img src="{{ $profileLogoUrl }}" alt="{{ $store->store_name }} logo" class="store-service-profile-logo mx-auto object-cover">
                        @else
                            <div class="sf-headline-lg store-service-profile-logo mx-auto flex items-center justify-center text-white" style="background: var(--store-primary);">
                                {{ Str::of($store->store_name)->substr(0, 2)->upper() }}
                            </div>
                        @endif
                        <h2 class="sf-headline-lg-mobile mt-5 text-[var(--store-ink)]">{{ $store->store_name }}</h2>
                        @if ($store->description)
                            <p class="sf-body-md mt-2 text-[var(--store-muted)]">{{ Str::limit(strip_tags($store->description), 120) }}</p>
                        @endif
                        <a href="#service-list" class="store-btn store-btn-secondary mt-5">Book</a>

                        @if ($openStatus)
                            <div class="sf-body-md mt-6 flex items-center justify-center gap-2 text-[var(--store-muted)]" data-service-hours>
                                @include('storefront::partials.icon', ['name' => 'schedule', 'class' => 'h-5 w-5 shrink-0'])
                                <strong class="text-[var(--store-ink)]">{{ $openStatus }}</strong>
                                @if ($hoursLabel)
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $hoursLabel }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($profileAddress || $store->store_phone || $store->site_email || $profileWhatsapp)
                        <div class="border-t border-[var(--store-line)] p-6">
                            @if ($profileAddress)
                                <p class="sf-body-md flex items-start justify-center gap-2 text-[var(--store-muted)]">
                                    @include('storefront::partials.icon', ['name' => 'location_on', 'class' => 'mt-0.5 h-5 w-5 shrink-0'])
                                    <span>{{ $profileAddress }}</span>
                                </p>
                            @endif
                            <details class="mt-5" data-service-contact>
                                <summary class="sf-body-md inline-flex cursor-pointer list-none items-center gap-1 font-bold text-[var(--store-ink)]">
                                    Contact us
                                    @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-4 w-4 rotate-90'])
                                </summary>
                                <div class="mt-4 grid gap-3 text-left">
                                    @if ($store->store_phone)
                                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $store->store_phone) }}" class="sf-body-md flex items-center gap-2 text-[var(--store-muted)] hover:text-[var(--store-primary)]">
                                            @include('storefront::partials.icon', ['name' => 'call', 'class' => 'h-5 w-5 shrink-0'])
                                            {{ $store->store_phone }}
                                        </a>
                                    @endif
                                    @if ($store->site_email)
                                        <a href="mailto:{{ $store->site_email }}" class="sf-body-md flex items-center gap-2 text-[var(--store-muted)] hover:text-[var(--store-primary)]">
                                            @include('storefront::partials.icon', ['name' => 'mail', 'class' => 'h-5 w-5 shrink-0'])
                                            {{ $store->site_email }}
                                        </a>
                                    @endif
                                    @if ($profileWhatsapp)
                                        <a href="https://wa.me/{{ $profileWhatsapp }}" class="sf-body-md flex items-center gap-2 text-[var(--store-muted)] hover:text-[var(--store-primary)]">
                                            @include('storefront::partials.social-icon', ['network' => 'whatsapp', 'class' => 'h-5 w-5 shrink-0'])
                                            WhatsApp
                                        </a>
                                    @endif
                                </div>
                            </details>
                        </div>
                    @endif
                </aside>
            </div>
        </section>
    @endif
@endsection
