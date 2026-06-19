@php
    use Modules\Catalog\Enums\ProductStatus;
    use Modules\Catalog\Enums\ProductType;
    use Modules\Catalog\Models\Product;

    $imageUrl = fn (?string $path): ?string => $path ? '/storage/'.ltrim($path, '/') : null;
    $money = fn (int|float|null $minor): string => number_format(((int) $minor) / 100, 2);
    $currency = $store->tenant?->currency_code ?? 'NGN';
    $currencySymbol = [
        'NGN' => '₦',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'GHS' => '₵',
        'KES' => 'KSh',
        'ZAR' => 'R',
        'CAD' => '$',
        'AUD' => '$',
    ][strtoupper($currency)] ?? strtoupper($currency);
    $logoUrl = $imageUrl($store->logo_path);
    $whatsapp = preg_replace('/\D+/', '', (string) ($store->store_whatsapp ?: data_get($store->social_accounts, 'whatsapp')));
    $socialUrl = function (string $network, mixed $handle): string {
        $handle = trim((string) $handle);

        if (str_starts_with($handle, 'http')) {
            return $handle;
        }

        return match ($network) {
            'facebook' => 'https://facebook.com/'.ltrim($handle, '@/'),
            'instagram' => 'https://instagram.com/'.ltrim($handle, '@/'),
            'tiktok' => 'https://tiktok.com/@'.ltrim($handle, '@/'),
            'whatsapp' => 'https://wa.me/'.preg_replace('/\D+/', '', $handle),
            default => '#',
        };
    };
    $hasServices = Product::query()
        ->where('tenant_id', $store->tenant_id)
        ->where('status', ProductStatus::Active->value)
        ->where('product_type', ProductType::Service->value)
        ->exists();
    $menuCategories = $store->categories->filter(fn ($category) => ($category->category_type?->value ?? (string) $category->category_type) === 'product');
    $navLinks = [
        ['label' => 'Products', 'href' => route('storefront.storefront.store.home', $store)],
        ...($hasServices ? [['label' => 'Services', 'href' => route('storefront.storefront.store.services', $store)]] : []),
        ['label' => 'About', 'href' => route('storefront.storefront.store.about', $store)],
        ['label' => 'FAQ', 'href' => route('storefront.storefront.store.faq', $store)],
        ['label' => 'Contact', 'href' => route('storefront.storefront.store.contact', $store)],
    ];
    $footerPages = [
        ['label' => 'Terms of Service', 'href' => route('storefront.storefront.store.terms', $store)],
        ['label' => 'Refunds', 'href' => route('storefront.storefront.store.refunds', $store)],
        ['label' => 'Privacy Policy', 'href' => route('storefront.storefront.store.privacy', $store)],
        ['label' => 'Shipping Info', 'href' => route('storefront.storefront.store.shipping', $store)],
    ];
    $paymentLabels = [
        'pay_on_delivery' => 'Pay on delivery',
        'storeboot_paystack' => 'Pay online with Paystack',
        'self_hosted_paystack' => 'Pay online with card',
        'bank_account' => 'Bank transfer',
        'place_order' => 'Place order now, pay later',
    ];
@endphp
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $store->store_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries,line-clamp"></script>
    @endif
    <style>
        :root {
            --store-primary: {{ $store->theme_primary_color ?: '#00236f' }};
            --store-secondary: {{ $store->theme_secondary_color ?: '#006a61' }};
            --store-ink: #17181d;
            --store-muted: #565965;
            --store-line: #dddfe7;
            --store-soft: #f5f6fa;
            --store-surface: #ffffff;
        }
        body { font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif; color: var(--store-ink); background: #fafafa; }
        h1, h2, h3, .store-display { font-family: "Hanken Grotesk", ui-sans-serif, system-ui, sans-serif; letter-spacing: 0; }
        .material-symbols-outlined { font-variation-settings: "FILL" 0, "wght" 500, "GRAD" 0, "opsz" 24; }
        .store-shell { max-width: 1280px; margin: 0 auto; padding-left: 16px; padding-right: 16px; }
        @media (min-width: 768px) { .store-shell { padding-left: 48px; padding-right: 48px; } }
        .store-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; border-radius: 8px; padding: 10px 18px; font-weight: 800; transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease; }
        .store-btn:active { transform: scale(.98); }
        .store-btn-primary { background: var(--store-primary); color: white; }
        .store-btn-secondary { background: var(--store-secondary); color: white; }
        .store-card { background: var(--store-surface); border: 1px solid var(--store-line); border-radius: 8px; box-shadow: 0 16px 40px rgba(15, 23, 42, .05); }
        .store-input { width: 100%; border: 1px solid var(--store-line); border-radius: 8px; background: #fff; padding: 12px 14px; outline: none; transition: border-color .16s ease, box-shadow .16s ease; }
        .store-input:focus { border-color: var(--store-primary); box-shadow: 0 0 0 4px color-mix(in srgb, var(--store-primary) 15%, transparent); }
        .drawer-open { overflow: hidden; }
        .store-announcement { animation: storeBlink 1.1s ease-in-out infinite; }
        .store-whatsapp-float::before,
        .store-whatsapp-float::after { content: ""; position: absolute; inset: -8px; border-radius: 9999px; border: 1px solid rgba(37, 211, 102, .48); animation: whatsappRipple 2.4s ease-out infinite; }
        .store-whatsapp-float::after { animation-delay: 1.2s; }
        @keyframes storeBlink { 0%, 100% { opacity: 1; } 50% { opacity: .28; } }
        @keyframes whatsappRipple { 0% { transform: scale(.85); opacity: .65; } 100% { transform: scale(1.9); opacity: 0; } }
    </style>
</head>
<body data-storefront data-cart-key="storefront-cart-{{ $store->username }}">
    @if ($store->announcement)
        <div class="bg-black px-4 py-2 text-center text-xs font-bold uppercase tracking-[.2em] text-white">
            <span class="store-announcement inline-block">{{ $store->announcement }}</span>
        </div>
    @endif

    <header class="sticky top-0 z-50 border-b border-[var(--store-line)] bg-white/95 backdrop-blur">
        <div class="store-shell flex min-h-20 items-center justify-between gap-4">
            <div class="flex items-center gap-4 md:gap-8">
                <a href="{{ route('storefront.storefront.store.home', $store) }}" class="flex min-w-0 items-center gap-3">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $store->store_name }} logo" class="h-10 w-10 rounded-lg object-cover">
                    @else
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-black text-white" style="background: var(--store-primary);">{{ Str::of($store->store_name)->substr(0, 2)->upper() }}</span>
                    @endif
                    <span class="store-display truncate text-xl font-black text-[var(--store-primary)] md:text-2xl">{{ $store->store_name }}</span>
                </a>
                <nav class="hidden items-center gap-5 lg:flex">
                    <div class="relative">
                        <button type="button" class="flex items-center gap-2 text-sm font-bold text-[var(--store-secondary)] hover:text-[var(--store-primary)]" data-categories-toggle aria-expanded="false">
                            <span class="material-symbols-outlined text-[20px] text-[var(--store-secondary)]">menu</span>
                            All Categories
                        </button>
                        <div class="store-card invisible absolute left-0 top-9 z-50 w-72 translate-y-2 p-2 opacity-0 transition" data-categories-menu>
                            @forelse ($menuCategories as $category)
                                <a href="{{ route('storefront.storefront.store.home', [$store, 'category' => $category->slug]) }}" class="flex items-center justify-between rounded-md px-3 py-2 text-sm font-semibold hover:bg-[var(--store-soft)]">
                                    {{ $category->name }}
                                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                                </a>
                            @empty
                                <span class="block px-3 py-2 text-sm text-[var(--store-muted)]">No categories yet</span>
                            @endforelse
                        </div>
                    </div>
                    @foreach ($navLinks as $link)
                        <a href="{{ $link['href'] }}" class="text-sm font-bold text-[var(--store-muted)] hover:text-[var(--store-primary)]">{{ $link['label'] }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('storefront.storefront.store.contact', $store) }}" class="hidden rounded-md border border-[var(--store-line)] px-3 py-2 text-sm font-bold text-[var(--store-muted)] hover:text-[var(--store-primary)] md:inline-flex">Contact</a>
                <button type="button" class="relative rounded-full p-3 hover:bg-[var(--store-soft)]" data-cart-open aria-label="Open cart">
                    <span class="material-symbols-outlined text-[var(--store-primary)]">shopping_cart</span>
                    <span class="absolute right-1 top-1 hidden min-w-5 rounded-full bg-black px-1 text-center text-[10px] font-bold leading-5 text-white" data-cart-count>0</span>
                </button>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="mt-16 bg-black text-white">
        <div class="store-shell grid gap-8 py-12 md:grid-cols-4">
            <div>
                <h2 class="store-display text-xl font-black text-white">{{ $store->store_name }}</h2>
                <p class="mt-3 text-sm leading-6 text-zinc-300">{{ $store->description ?: 'Shop curated products from our online store.' }}</p>
            </div>
            <div>
                <h3 class="font-bold text-white">Shop</h3>
                <div class="mt-3 grid gap-2 text-sm text-zinc-300">
                    <a class="hover:text-[var(--store-secondary)]" href="{{ route('storefront.storefront.store.home', $store) }}">Products</a>
                    @if ($hasServices)
                        <a class="hover:text-[var(--store-secondary)]" href="{{ route('storefront.storefront.store.services', $store) }}">Services</a>
                    @endif
                    <a class="hover:text-[var(--store-secondary)]" href="{{ route('storefront.storefront.store.faq', $store) }}">FAQ</a>
                    <a class="hover:text-[var(--store-secondary)]" href="{{ route('storefront.storefront.store.contact', $store) }}">Contact</a>
                </div>
            </div>
            <div>
                <h3 class="font-bold text-white">Info</h3>
                <div class="mt-3 grid gap-2 text-sm text-zinc-300">
                    @foreach ($footerPages as $link)
                        <a class="hover:text-[var(--store-secondary)]" href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
            <div>
                <h3 class="font-bold text-white">Connect</h3>
                @if ($store->address || $store->store_phone || $store->site_email)
                    <div class="mt-3 grid gap-2 text-sm text-zinc-300">
                        @if ($store->address)
                            <p class="flex items-start gap-2"><span class="material-symbols-outlined mt-0.5 text-[18px] text-[var(--store-secondary)]">location_on</span><span>{{ $store->address }}</span></p>
                        @endif
                        @if ($store->store_phone)
                            <p class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-[var(--store-secondary)]">call</span>{{ $store->store_phone }}</p>
                        @endif
                        @if ($store->site_email)
                            <p class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-[var(--store-secondary)]">mail</span>{{ $store->site_email }}</p>
                        @endif
                    </div>
                @endif
                <div class="mt-3 flex flex-wrap gap-3">
                    @foreach (['facebook', 'instagram', 'tiktok', 'whatsapp'] as $network)
                        @php $handle = data_get($store->social_accounts, $network); @endphp
                        @if ($handle)
                            <a href="{{ $socialUrl($network, $handle) }}" class="flex h-10 w-10 items-center justify-center rounded-full border border-zinc-700 text-zinc-200 transition hover:border-[var(--store-secondary)] hover:bg-[var(--store-secondary)] hover:text-black" aria-label="{{ ucfirst($network) }}">
                                @include('storefront::partials.social-icon', ['network' => $network, 'class' => 'h-5 w-5'])
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </footer>

    @if ($whatsapp)
        <a href="https://wa.me/{{ $whatsapp }}" class="store-whatsapp-float fixed bottom-8 right-7 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-[0_18px_40px_rgba(37,211,102,.42)]" aria-label="Chat on WhatsApp">
            <span class="relative z-10">@include('storefront::partials.social-icon', ['network' => 'whatsapp', 'class' => 'h-8 w-8'])</span>
        </a>
    @endif

    @include('storefront::partials.cart-drawer', ['paymentLabels' => $paymentLabels, 'currency' => $currency, 'currencySymbol' => $currencySymbol])

    <script>
        (() => {
            const body = document.body;
            const cartKey = body.dataset.cartKey;
            const drawer = document.querySelector('[data-cart-drawer]');
            const backdrop = document.querySelector('[data-cart-backdrop]');
            const categoriesButton = document.querySelector('[data-categories-toggle]');
            const categoriesMenu = document.querySelector('[data-categories-menu]');
            const count = document.querySelector('[data-cart-count]');
            const formatter = new Intl.NumberFormat('en-NG', { style: 'currency', currency: @json($currency), currencyDisplay: 'narrowSymbol' });
            let cart = JSON.parse(localStorage.getItem(cartKey) || '[]');
            let step = 'cart';

            const money = (minor) => formatter.format((Number(minor) || 0) / 100);
            const save = () => localStorage.setItem(cartKey, JSON.stringify(cart));
            const subtotal = () => cart.reduce((sum, item) => sum + (item.priceMinor * item.quantity), 0);
            const selectedShipping = () => document.querySelector('input[name="shipping_option"]:checked');
            const shippingMinor = () => selectedShipping() ? Number(selectedShipping().dataset.priceMinor || 0) : 0;

            const showStep = (nextStep) => {
                step = nextStep;
                document.querySelectorAll('[data-checkout-step]').forEach((panel) => panel.hidden = panel.dataset.checkoutStep !== step);
                document.querySelectorAll('[data-progress-step]').forEach((item) => {
                    item.dataset.active = item.dataset.progressStep === step ? 'true' : 'false';
                });
                render();
            };

            const openDrawer = () => {
                drawer.classList.remove('translate-x-full');
                backdrop.classList.remove('hidden');
                body.classList.add('drawer-open');
                drawer.querySelector('button, input, select, textarea')?.focus();
            };
            const closeDrawer = () => {
                drawer.classList.add('translate-x-full');
                backdrop.classList.add('hidden');
                body.classList.remove('drawer-open');
            };

            const render = () => {
                const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
                count.textContent = totalItems;
                count.classList.toggle('hidden', totalItems === 0);

                const list = document.querySelector('[data-cart-items]');
                list.innerHTML = cart.length ? cart.map((item) => `
                    <div class="flex gap-3 rounded-lg border border-[var(--store-line)] p-3">
                        <div class="h-20 w-20 flex-none overflow-hidden rounded-md bg-[var(--store-soft)]">${item.image ? `<img src="${item.image}" alt="" class="h-full w-full object-cover">` : ''}</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold">${item.name}</p>
                            <p class="text-sm text-[var(--store-muted)]">${money(item.priceMinor)}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <button class="rounded border px-2" data-cart-qty="${item.id}" data-delta="-1" type="button">-</button>
                                <span class="min-w-6 text-center text-sm font-bold">${item.quantity}</span>
                                <button class="rounded border px-2" data-cart-qty="${item.id}" data-delta="1" type="button">+</button>
                                <button class="ml-auto text-xs font-bold text-red-600" data-cart-remove="${item.id}" type="button">Remove</button>
                            </div>
                        </div>
                    </div>
                `).join('') : '<div class="rounded-lg border border-dashed border-[var(--store-line)] p-6 text-center text-sm text-[var(--store-muted)]">Your cart is empty.</div>';

                document.querySelectorAll('[data-subtotal]').forEach((node) => node.textContent = money(subtotal()));
                document.querySelectorAll('[data-shipping-total]').forEach((node) => node.textContent = money(shippingMinor()));
                document.querySelectorAll('[data-grand-total]').forEach((node) => node.textContent = money(subtotal() + shippingMinor()));
                document.querySelectorAll('[data-requires-cart]').forEach((button) => button.disabled = cart.length === 0);
            };

            document.addEventListener('click', (event) => {
                const add = event.target.closest('[data-add-to-cart]');
                if (add) {
                    const product = JSON.parse(add.dataset.product);
                    const requestedQuantity = add.dataset.useDetailQuantity === 'true'
                        ? Number(document.querySelector('[data-detail-quantity]')?.textContent || 1)
                        : 1;
                    const existing = cart.find((item) => item.id === product.id);
                    existing ? existing.quantity += requestedQuantity : cart.push({ ...product, quantity: requestedQuantity });
                    save();
                    render();
                    openDrawer();
                }

                if (event.target.closest('[data-cart-open]')) openDrawer();
                if (event.target.closest('[data-cart-close]') || event.target === backdrop) closeDrawer();

                const qty = event.target.closest('[data-cart-qty]');
                if (qty) {
                    const item = cart.find((row) => row.id === qty.dataset.cartQty);
                    if (item) item.quantity = Math.max(1, item.quantity + Number(qty.dataset.delta));
                    save();
                    render();
                }

                const remove = event.target.closest('[data-cart-remove]');
                if (remove) {
                    cart = cart.filter((item) => item.id !== remove.dataset.cartRemove);
                    save();
                    render();
                }

                const stepButton = event.target.closest('[data-go-step]');
                if (stepButton) {
                    if (stepButton.dataset.goStep === 'payment' && !selectedShipping()) {
                        document.querySelector('[data-drawer-alert]').textContent = 'Select a shipping option to continue.';
                        return;
                    }
                    document.querySelector('[data-drawer-alert]').textContent = '';
                    showStep(stepButton.dataset.goStep);
                }

                if (event.target.closest('[data-confirm-order]')) {
                    cart = [];
                    save();
                    showStep('confirm');
                }

                if (categoriesButton && event.target.closest('[data-categories-toggle]')) {
                    const expanded = categoriesButton.getAttribute('aria-expanded') === 'true';
                    categoriesButton.setAttribute('aria-expanded', String(!expanded));
                    categoriesMenu.classList.toggle('invisible', expanded);
                    categoriesMenu.classList.toggle('opacity-0', expanded);
                    categoriesMenu.classList.toggle('translate-y-2', expanded);
                } else if (categoriesMenu && !event.target.closest('[data-categories-menu]')) {
                    categoriesButton?.setAttribute('aria-expanded', 'false');
                    categoriesMenu.classList.add('invisible', 'opacity-0', 'translate-y-2');
                }

                const qtyControl = event.target.closest('[data-detail-qty]');
                if (qtyControl) {
                    const target = document.querySelector('[data-detail-quantity]');
                    target.textContent = Math.max(1, Number(target.textContent || 1) + Number(qtyControl.dataset.detailQty));
                }

                const tab = event.target.closest('[data-tab-button]');
                if (tab) {
                    document.querySelectorAll('[data-tab-button]').forEach((button) => {
                        const active = button.dataset.tabButton === tab.dataset.tabButton;
                        button.classList.toggle('border-[var(--store-primary)]', active);
                        button.classList.toggle('border-transparent', !active);
                        button.classList.toggle('text-[var(--store-primary)]', active);
                        button.classList.toggle('text-[var(--store-muted)]', !active);
                    });
                    document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                        panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab.dataset.tabButton);
                    });
                }
            });

            document.addEventListener('change', (event) => {
                if (event.target.matches('input[name="shipping_option"]')) render();
            });

            const galleryImages = Array.from(document.querySelectorAll('[data-gallery-image]')).map((button) => button.dataset.galleryImage);
            let galleryIndex = 0;
            const setGalleryImage = (index) => {
                if (!galleryImages.length) return;
                galleryIndex = (index + galleryImages.length) % galleryImages.length;
                const main = document.querySelector('[data-product-main-image]');
                if (main) main.src = galleryImages[galleryIndex];
                document.querySelectorAll('[data-gallery-image]').forEach((button, buttonIndex) => {
                    button.classList.toggle('ring-2', buttonIndex === galleryIndex);
                });
            };

            document.querySelectorAll('[data-gallery-image]').forEach((button, index) => {
                button.addEventListener('click', () => setGalleryImage(index));
            });
            document.querySelector('[data-gallery-prev]')?.addEventListener('click', () => setGalleryImage(galleryIndex - 1));
            document.querySelector('[data-gallery-next]')?.addEventListener('click', () => setGalleryImage(galleryIndex + 1));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeDrawer();
            });

            document.querySelectorAll('form[data-disable-on-submit]').forEach((form) => {
                form.addEventListener('submit', () => {
                    form.querySelectorAll('button[type="submit"]').forEach((button) => {
                        button.disabled = true;
                        button.dataset.originalText = button.textContent;
                        button.textContent = 'Sending...';
                    });
                });
            });

            showStep('cart');
        })();
    </script>
</body>
</html>
