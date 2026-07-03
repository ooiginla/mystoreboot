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
    $storeAddress = collect([$store->address, $store->city, $store->state, $store->country])->filter()->join(', ');
    $socialUrl = function (string $network, mixed $handle): string {
        $handle = trim((string) $handle);

        if (str_starts_with($handle, 'http')) {
            return $handle;
        }

        return match ($network) {
            'facebook' => 'https://facebook.com/'.ltrim($handle, '@/'),
            'instagram' => 'https://instagram.com/'.ltrim($handle, '@/'),
            'tiktok' => 'https://tiktok.com/@'.ltrim($handle, '@/'),
            'twitter' => 'https://x.com/'.ltrim($handle, '@/'),
            'youtube' => str_starts_with(ltrim($handle, '@/'), '@')
                ? 'https://youtube.com/'.ltrim($handle, '/')
                : 'https://youtube.com/@'.ltrim($handle, '@/'),
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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $store->store_name }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <script src="https://js.paystack.co/v2/inline.js"></script>
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
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--store-ink); background: #fafafa; font-size: 16px; line-height: 24px; font-weight: 400; letter-spacing: 0; }
        h1, h2, h3, .store-display, .sf-display-xl, .sf-headline-lg, .sf-headline-md, .sf-label-md { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; letter-spacing: 0; }
        .sf-display-xl { font-size: 48px; line-height: 56px; letter-spacing: -0.02em; font-weight: 700; }
        .sf-headline-lg { font-size: 32px; line-height: 40px; letter-spacing: -0.01em; font-weight: 600; }
        .sf-headline-lg-mobile { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 24px; line-height: 32px; font-weight: 600; letter-spacing: 0; }
        .sf-headline-md { font-size: 20px; line-height: 28px; font-weight: 600; }
        .sf-body-lg { font-size: 18px; line-height: 28px; font-weight: 400; }
        .sf-body-md { font-size: 16px; line-height: 24px; font-weight: 400; }
        .sf-label-md { font-size: 14px; line-height: 20px; letter-spacing: 0.05em; font-weight: 600; }
        .sf-caption { font-size: 12px; line-height: 16px; font-weight: 400; }
        @media (max-width: 767px) {
            .sf-display-xl { font-size: 24px; line-height: 32px; letter-spacing: 0; font-weight: 600; }
            .sf-headline-lg { font-size: 24px; line-height: 32px; letter-spacing: 0; }
        }
        .store-shell { max-width: 1280px; margin: 0 auto; padding-left: 16px; padding-right: 16px; }
        @media (min-width: 768px) { .store-shell { padding-left: 48px; padding-right: 48px; } }
        .store-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; border-radius: 8px; padding: 10px 18px; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 14px; line-height: 20px; letter-spacing: 0.05em; font-weight: 600; text-transform: uppercase; transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease; }
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
    @stack('styles')
</head>
<body data-storefront data-cart-key="storefront-cart-{{ $store->username }}">
    @if ($store->announcement)
        <div class="sf-label-md bg-black px-4 py-2 text-center uppercase text-white">
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
                        <span class="sf-label-md flex h-10 w-10 items-center justify-center rounded-lg text-white" style="background: var(--store-primary);">{{ Str::of($store->store_name)->substr(0, 2)->upper() }}</span>
                    @endif
                    <span class="sf-headline-md truncate text-[var(--store-primary)]">{{ $store->store_name }}</span>
                </a>
                <nav class="hidden items-center gap-5 lg:flex">
                    <div class="relative">
                        <button type="button" class="sf-body-md flex items-center gap-2 font-bold text-[var(--store-secondary)] hover:text-[var(--store-primary)]" data-categories-toggle aria-expanded="false">
                            @include('storefront::partials.icon', ['name' => 'menu', 'class' => 'h-5 w-5 text-[var(--store-secondary)]'])
                            All Categories
                        </button>
                        <div class="store-card invisible absolute left-0 top-9 z-50 w-72 translate-y-2 p-2 opacity-0 transition" data-categories-menu>
                            @forelse ($menuCategories as $category)
                                <a href="{{ route('storefront.storefront.store.home', [$store, 'category' => $category->slug]) }}" class="sf-body-md flex items-center justify-between rounded-md px-3 py-2 font-semibold hover:bg-[var(--store-soft)]">
                                    {{ $category->name }}
                                    @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5'])
                                </a>
                            @empty
                                <span class="sf-body-md block px-3 py-2 text-[var(--store-muted)]">No categories yet</span>
                            @endforelse
                        </div>
                    </div>
                    @foreach ($navLinks as $link)
                        <a href="{{ $link['href'] }}" class="sf-body-md font-bold text-[var(--store-muted)] hover:text-[var(--store-primary)]">{{ $link['label'] }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('storefront.storefront.store.contact', $store) }}" class="sf-body-md hidden rounded-md border border-[var(--store-line)] px-3 py-2 font-bold text-[var(--store-muted)] hover:text-[var(--store-primary)] md:inline-flex">Contact</a>
                <button type="button" class="relative rounded-full p-3 hover:bg-[var(--store-soft)]" data-cart-open aria-label="Open cart">
                    @include('storefront::partials.icon', ['name' => 'shopping_cart', 'class' => 'h-5 w-5 text-[var(--store-primary)]'])
                    <span class="sf-caption absolute right-1 top-1 hidden min-w-5 rounded-full bg-black px-1 text-center font-bold text-white" data-cart-count>0</span>
                </button>
            </div>
        </div>
    </header>

    @if (session('status') || session('payment_error'))
        <div class="store-shell pt-4">
            <div class="sf-body-md rounded-lg border p-4 font-semibold {{ session('payment_error') ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-800' }}">
                {{ session('payment_error') ?: session('status') }}
            </div>
        </div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer class="mt-16 bg-black text-white">
        <div class="store-shell grid gap-8 py-12 md:grid-cols-4">
            <div>
                <h2 class="sf-headline-md text-white">{{ $store->store_name }}</h2>
                <p class="sf-body-md mt-3 text-zinc-300">{{ $store->description ?: 'Shop curated products from our online store.' }}</p>
            </div>
            <div>
                <h3 class="sf-headline-md text-white">Shop</h3>
                <div class="sf-body-md mt-3 grid gap-2 text-zinc-300">
                    <a class="hover:text-[var(--store-secondary)]" href="{{ route('storefront.storefront.store.home', $store) }}">Products</a>
                    @if ($hasServices)
                        <a class="hover:text-[var(--store-secondary)]" href="{{ route('storefront.storefront.store.services', $store) }}">Services</a>
                    @endif
                    <a class="hover:text-[var(--store-secondary)]" href="{{ route('storefront.storefront.store.faq', $store) }}">FAQ</a>
                    <a class="hover:text-[var(--store-secondary)]" href="{{ route('storefront.storefront.store.contact', $store) }}">Contact</a>
                </div>
            </div>
            <div>
                <h3 class="sf-headline-md text-white">Info</h3>
                <div class="sf-body-md mt-3 grid gap-2 text-zinc-300">
                    @foreach ($footerPages as $link)
                        <a class="hover:text-[var(--store-secondary)]" href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
            <div>
                <h3 class="sf-headline-md text-white">Connect</h3>
                @if ($storeAddress || $store->store_phone || $store->site_email)
                    <div class="sf-body-md mt-3 grid gap-2 text-zinc-300">
                        @if ($storeAddress)
                            <p class="flex items-start gap-2">@include('storefront::partials.icon', ['name' => 'location_on', 'class' => 'mt-0.5 h-5 w-5 shrink-0 text-[var(--store-secondary)]'])<span>{{ $storeAddress }}</span></p>
                        @endif
                        @if ($store->store_phone)
                            <p class="flex items-center gap-2">@include('storefront::partials.icon', ['name' => 'call', 'class' => 'h-5 w-5 shrink-0 text-[var(--store-secondary)]']){{ $store->store_phone }}</p>
                        @endif
                        @if ($store->site_email)
                            <p class="flex items-center gap-2">@include('storefront::partials.icon', ['name' => 'mail', 'class' => 'h-5 w-5 shrink-0 text-[var(--store-secondary)]']){{ $store->site_email }}</p>
                        @endif
                    </div>
                @endif
                <div class="mt-3 flex flex-wrap gap-3">
                    @foreach (['facebook', 'instagram', 'tiktok', 'twitter', 'youtube', 'whatsapp'] as $network)
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
            let checkoutOrder = null;
            let gatewayChargeMinor = 0;
            const paystackMethods = ['storeboot_paystack', 'self_hosted_paystack'];
            const checkoutSteps = ['cart', 'shipping', 'payment', 'confirm'];
            const paystackInitializeUrl = @json(route('storefront.storefront.store.checkout.paystack.initialize', [$store, '__ORDER_ID__']));

            @if (session('clear_cart'))
                cart = [];
                localStorage.removeItem(cartKey);
            @endif

            const money = (minor) => formatter.format((Number(minor) || 0) / 100);
            const save = () => localStorage.setItem(cartKey, JSON.stringify(cart));
            const subtotal = () => cart.reduce((sum, item) => sum + (item.priceMinor * item.quantity), 0);
            const selectedShipping = () => document.querySelector('input[name="shipping_option"]:checked');
            const shippingMinor = () => cart.length > 0 && selectedShipping() ? Number(selectedShipping().dataset.priceMinor || 0) : 0;
            const field = (name) => document.querySelector(`[name="${name}"]`);
            const alertNode = () => document.querySelector('[data-drawer-alert]');
            const setAlert = (message) => alertNode().textContent = message || '';
            const validationMessage = (errors) => {
                const first = errors ? Object.values(errors).flat()[0] : null;
                return first || 'Please check your checkout details and try again.';
            };
            const checkoutPayload = () => ({
                customer: {
                    name: field('checkout_name')?.value.trim() || '',
                    phone: field('checkout_phone')?.value.trim() || '',
                    email: field('checkout_email')?.value.trim() || '',
                    address: field('checkout_address')?.value.trim() || '',
                },
                shipping_option: selectedShipping()?.value || '',
                payment_method: document.querySelector('input[name="payment_method"]:checked')?.value || null,
                items: cart.map((item) => ({
                    product_variant_id: item.productVariantId,
                    quantity: item.quantity,
                })),
            });
            const selectedPaymentMethod = () => document.querySelector('input[name="payment_method"]:checked')?.value || null;
            const syncBankTransferDetails = () => {
                document.querySelectorAll('[data-bank-transfer-details]').forEach((node) => {
                    node.hidden = selectedPaymentMethod() !== 'bank_account';
                });
            };
            const resetPendingCheckout = () => {
                checkoutOrder = null;
                gatewayChargeMinor = 0;
                document.querySelector('[data-order-reference]').textContent = 'Pending';
            };
            const canNavigateToStep = (targetStep) => {
                if (step === 'confirm' || targetStep === 'confirm') {
                    return false;
                }

                return checkoutSteps.indexOf(targetStep) < checkoutSteps.indexOf(step);
            };
            const navigateBackToStep = (targetStep) => {
                if (!canNavigateToStep(targetStep)) {
                    return;
                }

                resetPendingCheckout();
                setAlert('');
                showStep(targetStep);
            };

            const validateShippingStep = () => {
                const requiredFields = ['checkout_name', 'checkout_phone', 'checkout_email', 'checkout_address'];
                const invalid = requiredFields.map(field).find((input) => !input?.checkValidity());

                if (invalid) {
                    invalid.reportValidity();
                    setAlert('Enter your name, email, phone, and delivery address to continue.');
                    return false;
                }

                if (!selectedShipping()) {
                    setAlert('Select a shipping option to continue.');
                    return false;
                }

                if (cart.length === 0) {
                    setAlert('Add an item to your cart before checkout.');
                    showStep('cart');
                    return false;
                }

                if (cart.some((item) => !item.productVariantId)) {
                    setAlert('One or more cart items are unavailable. Please remove and add them again.');
                    return false;
                }

                return true;
            };

            const createCheckoutOrder = async (button) => {
                if (!validateShippingStep()) return;

                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = 'Creating order...';
                setAlert('');

                try {
                    const response = await fetch(@json(route('storefront.storefront.store.checkout', $store)), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(checkoutPayload()),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        setAlert(validationMessage(result.errors));
                        return;
                    }

                    checkoutOrder = result;
                    document.querySelector('[data-order-reference]').textContent = result.order_reference;
                    showStep('payment');
                } catch (error) {
                    setAlert('We could not create your order. Please try again.');
                } finally {
                    button.disabled = false;
                    button.textContent = button.dataset.originalText || 'Continue to payment';
                }
            };

            const initializePaystackPayment = async (button) => {
                if (!checkoutOrder?.order_id) {
                    setAlert('Create your order before payment.');
                    showStep('shipping');
                    return;
                }

                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = 'Opening Paystack...';
                setAlert('');
                let authorizationUrl = null;

                try {
                    const response = await fetch(paystackInitializeUrl.replace('__ORDER_ID__', checkoutOrder.order_id), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ payment_method: selectedPaymentMethod() }),
                    });
                    const contentType = response.headers.get('content-type') || '';
                    const result = contentType.includes('application/json') ? await response.json() : { message: await response.text() };

                    if (!response.ok) {
                        setAlert(validationMessage(result.errors) || result.message || 'Paystack could not initialize this payment.');
                        return;
                    }

                    authorizationUrl = result.authorization_url;
                    gatewayChargeMinor = Number(result.gateway_charge_minor || 0);
                    render();

                    const verifyPaystackReference = async (reference) => {
                        button.textContent = 'Verifying payment...';
                        const verification = await fetch(`${result.verify_url}?reference=${encodeURIComponent(reference || result.reference)}`, {
                            headers: {
                                'Accept': 'application/json',
                            },
                        });
                        const verified = await verification.json();

                        if (!verification.ok) {
                            setAlert(verified.message || 'Paystack could not verify this payment.');
                            return;
                        }

                        cart = [];
                        save();
                        checkoutOrder = null;
                        gatewayChargeMinor = 0;
                        document.querySelector('[data-order-reference]').textContent = verified.order_reference || result.reference;
                        setAlert('');
                        showStep('confirm');
                    };

                    if (!window.PaystackPop) {
                        if (authorizationUrl) {
                            window.location.href = authorizationUrl;
                            return;
                        }

                        throw new Error('Paystack inline script unavailable.');
                    }

                    if (typeof PaystackPop.setup === 'function') {
                        const handler = PaystackPop.setup({
                            key: result.public_key,
                            email: result.email,
                            amount: result.amount,
                            currency: result.currency,
                            ref: result.reference,
                            callback: (paystackResponse) => verifyPaystackReference(paystackResponse.reference),
                            onClose: () => {
                                setAlert('Paystack payment was closed before completion.');
                            },
                        });

                        handler.openIframe();
                        return;
                    }

                    const paystack = new PaystackPop();

                    if (typeof paystack.resumeTransaction === 'function' && result.access_code) {
                        paystack.resumeTransaction(result.access_code, {
                            onSuccess: (transaction) => verifyPaystackReference(transaction.reference),
                            onCancel: () => {
                                setAlert('Paystack payment was closed before completion.');
                            },
                        });
                        return;
                    }

                    if (typeof paystack.newTransaction === 'function') {
                        paystack.newTransaction({
                            key: result.public_key,
                            email: result.email,
                            amount: result.amount,
                            currency: result.currency,
                            reference: result.reference,
                            ref: result.reference,
                            onSuccess: (transaction) => verifyPaystackReference(transaction.reference),
                            onCancel: () => {
                                setAlert('Paystack payment was closed before completion.');
                            },
                        });
                        return;
                    }

                    if (authorizationUrl) {
                        window.location.href = authorizationUrl;
                        return;
                    }

                    throw new Error('Unsupported Paystack inline script.');
                } catch (error) {
                    if (authorizationUrl) {
                        window.location.href = authorizationUrl;
                        return;
                    }

                    console.error(error);
                    setAlert('We could not open Paystack. Please try again.');
                } finally {
                    button.disabled = false;
                    button.textContent = button.dataset.originalText || 'Pay now';
                }
            };

            const showStep = (nextStep) => {
                step = nextStep;
                document.querySelectorAll('[data-checkout-step]').forEach((panel) => panel.hidden = panel.dataset.checkoutStep !== step);
                document.querySelectorAll('[data-progress-step]').forEach((item) => {
                    item.dataset.active = item.dataset.progressStep === step ? 'true' : 'false';
                    item.disabled = !canNavigateToStep(item.dataset.progressTarget);
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
                            <p class="sf-body-md truncate font-bold">${item.name}</p>
                            <p class="sf-body-md text-[var(--store-muted)]">${money(item.priceMinor)}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <button class="rounded border px-2" data-cart-qty="${item.id}" data-delta="-1" type="button">-</button>
                                <span class="sf-body-md min-w-6 text-center font-bold">${item.quantity}</span>
                                <button class="rounded border px-2" data-cart-qty="${item.id}" data-delta="1" type="button">+</button>
                                <button class="sf-caption ml-auto font-bold text-red-600" data-cart-remove="${item.id}" type="button">Remove</button>
                            </div>
                        </div>
                    </div>
                `).join('') : '<div class="sf-body-md rounded-lg border border-dashed border-[var(--store-line)] p-6 text-center text-[var(--store-muted)]">Your cart is empty.</div>';

                document.querySelectorAll('[data-subtotal]').forEach((node) => node.textContent = money(subtotal()));
                document.querySelectorAll('[data-shipping-total]').forEach((node) => node.textContent = money(shippingMinor()));
                document.querySelectorAll('[data-gateway-charge]').forEach((node) => node.textContent = money(gatewayChargeMinor));
                document.querySelectorAll('[data-gateway-charge-row]').forEach((node) => node.classList.toggle('hidden', gatewayChargeMinor <= 0));
                document.querySelectorAll('[data-grand-total]').forEach((node) => node.textContent = money(subtotal() + shippingMinor() + gatewayChargeMinor));
                document.querySelectorAll('[data-requires-cart]').forEach((button) => button.disabled = cart.length === 0);
                syncBankTransferDetails();
            };

            document.addEventListener('change', (event) => {
                if (event.target.matches('input[name="payment_method"]')) {
                    syncBankTransferDetails();
                }
            });

            document.addEventListener('click', (event) => {
                const add = event.target.closest('[data-add-to-cart]');
                if (add) {
                    const product = JSON.parse(add.dataset.product);
                    const requestedQuantity = add.dataset.useDetailQuantity === 'true'
                        ? Number(document.querySelector('[data-detail-quantity]')?.textContent || 1)
                        : 1;
                    const existing = cart.find((item) => item.id === product.id);
                    existing ? existing.quantity += requestedQuantity : cart.push({ ...product, quantity: requestedQuantity });
                    resetPendingCheckout();
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
                    resetPendingCheckout();
                    save();
                    render();
                }

                const remove = event.target.closest('[data-cart-remove]');
                if (remove) {
                    cart = cart.filter((item) => item.id !== remove.dataset.cartRemove);
                    resetPendingCheckout();
                    save();
                    render();
                }

                const progressButton = event.target.closest('[data-progress-target]');
                if (progressButton) {
                    navigateBackToStep(progressButton.dataset.progressTarget);
                    return;
                }

                const stepButton = event.target.closest('[data-go-step]');
                if (stepButton) {
                    if (stepButton.dataset.goStep === 'payment') {
                        createCheckoutOrder(stepButton);
                        return;
                    }
                    setAlert('');
                    showStep(stepButton.dataset.goStep);
                }

                const confirmButton = event.target.closest('[data-confirm-order]');
                if (confirmButton) {
                    if (paystackMethods.includes(selectedPaymentMethod())) {
                        initializePaystackPayment(confirmButton);
                        return;
                    }

                    cart = [];
                    save();
                    checkoutOrder = null;
                    gatewayChargeMinor = 0;
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
    @stack('scripts')
</body>
</html>
