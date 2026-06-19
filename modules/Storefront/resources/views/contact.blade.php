@extends('storefront::layout', ['title' => 'Contact | '.$store->store_name])

@section('content')
    <section class="store-shell py-14">
        <div class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <p class="text-sm font-black uppercase tracking-[.18em] text-[var(--store-secondary)]">{{ $store->store_name }}</p>
                <h1 class="store-display mt-3 text-4xl font-black text-[var(--store-primary)]">Contact Us</h1>
                <p class="mt-3 text-[var(--store-muted)]">Send a message to the store team. We will create a customer support ticket and follow up with you.</p>

                @if (session('status'))
                    <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('storefront.storefront.store.contact.submit', $store) }}" class="store-card mt-6 grid gap-4 p-5 md:p-6" data-disable-on-submit>
                    @csrf
                    <div>
                        <label for="name" class="text-sm font-bold">Full name</label>
                        <input id="name" name="name" value="{{ old('name') }}" class="store-input mt-2" required autofocus autocomplete="name">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="phone" class="text-sm font-bold">Phone number</label>
                            <input id="phone" name="phone" value="{{ old('phone') }}" class="store-input mt-2" required autocomplete="tel">
                            @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email" class="text-sm font-bold">Email address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" class="store-input mt-2" autocomplete="email">
                            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="subject" class="text-sm font-bold">Subject</label>
                        <input id="subject" name="subject" value="{{ old('subject') }}" class="store-input mt-2" required>
                        @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="message" class="text-sm font-bold">Message</label>
                        <textarea id="message" name="message" class="store-input mt-2 min-h-36" required>{{ old('message') }}</textarea>
                        @error('message') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="store-btn store-btn-primary justify-self-start disabled:cursor-not-allowed disabled:opacity-60">Submit ticket</button>
                </form>
            </div>

            <aside class="lg:col-span-5">
                <div class="store-card sticky top-28 p-6">
                    <h2 class="store-display text-2xl font-black">Store details</h2>
                    <div class="mt-5 grid gap-4 text-sm text-[var(--store-muted)]">
                        @if ($store->address)
                            <p><span class="block font-bold text-[var(--store-ink)]">Address</span>{{ $store->address }}</p>
                        @endif
                        @if ($store->store_phone)
                            <p><span class="block font-bold text-[var(--store-ink)]">Phone</span>{{ $store->store_phone }}</p>
                        @endif
                        @if ($store->store_whatsapp)
                            <p><span class="block font-bold text-[var(--store-ink)]">WhatsApp</span>{{ $store->store_whatsapp }}</p>
                        @endif
                        @if ($store->site_email)
                            <p><span class="block font-bold text-[var(--store-ink)]">Email</span>{{ $store->site_email }}</p>
                        @endif
                    </div>
                    @if (filled($store->social_accounts))
                        <div class="mt-6 flex flex-wrap gap-2">
                            @foreach ((array) $store->social_accounts as $network => $handle)
                                @if ($handle)
                                    <span class="rounded-full bg-[var(--store-soft)] px-3 py-2 text-xs font-black uppercase text-[var(--store-muted)]">{{ $network }}: {{ $handle }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </section>
@endsection
