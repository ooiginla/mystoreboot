@extends('storefront::layout', ['title' => 'Contact | '.$store->store_name])

@php
    $contactHeroPath = collect($store->slides ?? [])
        ->pluck('image_path')
        ->filter()
        ->first() ?: $store->hero_image_path;
    $contactHeroUrl = $contactHeroPath ? '/storage/'.ltrim($contactHeroPath, '/') : null;
    $storeAddress = collect([$store->address, $store->city, $store->state, $store->country])
        ->filter()
        ->join(', ');
@endphp

@push('styles')
    <style>
        .contact-hero {
            position: relative;
            min-height: 150px;
            display: flex;
            align-items: flex-end;
            overflow: hidden;
            background: linear-gradient(115deg, var(--store-primary), var(--store-secondary));
        }
        .contact-hero-image,
        .contact-hero-overlay {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }
        .contact-hero-image { object-fit: cover; }
        .contact-hero-overlay {
            background: linear-gradient(90deg, color-mix(in srgb, var(--store-primary) 76%, black), rgba(10, 18, 22, .44));
        }
        .contact-hero-content {
            position: relative;
            z-index: 1;
            width: 100%;
            padding-top: 32px;
            padding-bottom: 32px;
            color: #fff;
        }
        .contact-layout {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            background: #fff;
        }
        .contact-details,
        .contact-form-panel { padding: 72px; }
        .contact-form-panel { background: var(--store-soft); }
        .contact-detail-list {
            display: grid;
            gap: 24px;
            margin-top: 36px;
        }
        .contact-detail {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            min-width: 0;
        }
        .contact-detail-icon {
            width: 48px;
            height: 48px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 6px;
            background: color-mix(in srgb, var(--store-primary) 8%, white);
            color: var(--store-primary);
        }
        .contact-detail-copy { min-width: 0; }
        .contact-detail-copy a,
        .contact-detail-copy p { overflow-wrap: anywhere; }
        .contact-form-panel .store-input {
            border-color: color-mix(in srgb, var(--store-muted) 50%, white);
            border-radius: 5px;
            background: #fff;
        }
        .contact-form-panel textarea.store-input { min-height: 160px; resize: vertical; }
        .contact-submit-icon { transition: transform .16s ease; }
        .contact-form-panel button:hover .contact-submit-icon { transform: translateX(3px); }
        main:has(.contact-page) + footer { margin-top: 0; }
        @media (max-width: 900px) {
            .contact-layout { grid-template-columns: 1fr; }
            .contact-details,
            .contact-form-panel { padding: 48px; }
        }
        @media (max-width: 640px) {
            .contact-hero { min-height: 120px; }
            .contact-hero-content { padding-top: 24px; padding-bottom: 24px; }
            .contact-details,
            .contact-form-panel { padding: 36px 20px; }
            .contact-detail-list { gap: 20px; margin-top: 28px; }
            .contact-detail-icon { width: 44px; height: 44px; }
        }
    </style>
@endpush

@section('content')
    <div class="contact-page">
        <section class="contact-hero" aria-labelledby="contact-page-title">
            @if ($contactHeroUrl)
                <img src="{{ $contactHeroUrl }}" alt="" class="contact-hero-image">
            @endif
            <div class="contact-hero-overlay"></div>
            <div class="contact-hero-content store-shell">
                <p class="sf-body-lg font-semibold">
                    <a href="{{ route('storefront.storefront.store.home', $store) }}" class="hover:underline">Home</a>
                    <span aria-hidden="true"> / </span>
                    <span id="contact-page-title">Contact Us</span>
                </p>
            </div>
        </section>

        <section class="contact-layout" aria-label="Contact information and message form">
            <div class="contact-details">
                <p class="sf-label-md uppercase text-[var(--store-secondary)]">{{ $store->store_name }}</p>
                <h1 class="sf-headline-lg mt-3">Get in touch with us</h1>
                <p class="sf-body-md mt-5 max-w-xl text-[var(--store-muted)]">
                    Have a question about a product, delivery, or an existing order? Send us a message and our store team will get back to you shortly.
                </p>

                <div class="contact-detail-list">
                    @if ($store->store_phone)
                        <div class="contact-detail">
                            <span class="contact-detail-icon">
                                @include('storefront::partials.icon', ['name' => 'call', 'class' => 'h-6 w-6'])
                            </span>
                            <div class="contact-detail-copy">
                                <h2 class="sf-headline-md">Phone Number</h2>
                                <a href="tel:{{ preg_replace('/\s+/', '', $store->store_phone) }}" class="sf-body-md mt-1 block text-[var(--store-muted)] hover:text-[var(--store-primary)]">{{ $store->store_phone }}</a>
                            </div>
                        </div>
                    @endif

                    @if ($store->store_whatsapp)
                        <div class="contact-detail">
                            <span class="contact-detail-icon">
                                @include('storefront::partials.social-icon', ['network' => 'whatsapp', 'class' => 'h-6 w-6'])
                            </span>
                            <div class="contact-detail-copy">
                                <h2 class="sf-headline-md">WhatsApp</h2>
                                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $store->store_whatsapp) }}" class="sf-body-md mt-1 block text-[var(--store-muted)] hover:text-[var(--store-primary)]">{{ $store->store_whatsapp }}</a>
                            </div>
                        </div>
                    @endif

                    @if ($store->site_email)
                        <div class="contact-detail">
                            <span class="contact-detail-icon">
                                @include('storefront::partials.icon', ['name' => 'mail', 'class' => 'h-6 w-6'])
                            </span>
                            <div class="contact-detail-copy">
                                <h2 class="sf-headline-md">E-Mail address</h2>
                                <a href="mailto:{{ $store->site_email }}" class="sf-body-md mt-1 block text-[var(--store-muted)] hover:text-[var(--store-primary)]">{{ $store->site_email }}</a>
                            </div>
                        </div>
                    @endif

                    @if ($storeAddress)
                        <div class="contact-detail">
                            <span class="contact-detail-icon">
                                @include('storefront::partials.icon', ['name' => 'location_on', 'class' => 'h-6 w-6'])
                            </span>
                            <div class="contact-detail-copy">
                                <h2 class="sf-headline-md">Location</h2>
                                <p class="sf-body-md mt-1 text-[var(--store-muted)]">{{ $storeAddress }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="contact-form-panel">
                <h2 class="sf-headline-lg">Send us a message</h2>
                <p class="sf-body-md mt-2 text-[var(--store-muted)]">Complete the form below and we’ll follow up with you.</p>

                @if (session('status'))
                    <div class="sf-body-md mt-6 rounded-lg border border-green-200 bg-green-50 p-4 font-semibold text-green-800">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('storefront.storefront.store.contact.submit', $store) }}" class="mt-7 grid gap-5" data-disable-on-submit>
                    @csrf
                    <div>
                        <label for="name" class="sf-body-md font-semibold">Name</label>
                        <input id="name" name="name" value="{{ old('name') }}" class="store-input mt-2" placeholder="Your name here" required autofocus autocomplete="name">
                        @error('name') <p class="sf-body-md mt-1 text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="email" class="sf-body-md font-semibold">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" class="store-input mt-2" placeholder="Your email here" autocomplete="email">
                            @error('email') <p class="sf-body-md mt-1 text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="phone" class="sf-body-md font-semibold">Phone Number</label>
                            <input id="phone" name="phone" value="{{ old('phone') }}" class="store-input mt-2" placeholder="Your phone number" required autocomplete="tel">
                            @error('phone') <p class="sf-body-md mt-1 text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="subject" class="sf-body-md font-semibold">Subject</label>
                        <input id="subject" name="subject" value="{{ old('subject') }}" class="store-input mt-2" placeholder="How can we help?" required>
                        @error('subject') <p class="sf-body-md mt-1 text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="message" class="sf-body-md font-semibold">Message</label>
                        <textarea id="message" name="message" class="store-input mt-2" placeholder="Your message here..." required>{{ old('message') }}</textarea>
                        @error('message') <p class="sf-body-md mt-1 text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="store-btn store-btn-primary justify-self-start disabled:cursor-not-allowed disabled:opacity-60">
                        Submit
                        @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'contact-submit-icon h-5 w-5'])
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection
