<div class="fixed inset-0 z-[80] hidden bg-black/40 backdrop-blur-sm" data-cart-backdrop></div>
<aside class="fixed right-0 top-0 z-[90] flex h-full w-full max-w-[420px] translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300" data-cart-drawer aria-label="Shopping cart">
    <div class="border-b border-[var(--store-line)] p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="store-display text-2xl font-black">Checkout</h2>
                <p class="text-sm text-[var(--store-muted)]">Complete your purchase</p>
            </div>
            <button type="button" class="rounded-full p-2 hover:bg-[var(--store-soft)]" data-cart-close aria-label="Close cart">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="mt-5 grid grid-cols-4 gap-2 text-center text-[11px] font-bold">
            @foreach (['cart' => 'Cart', 'shipping' => 'Shipping', 'payment' => 'Payment', 'confirm' => 'Confirm'] as $key => $label)
                <div data-progress-step="{{ $key }}" class="[&[data-active=true]_.dot]:bg-[var(--store-primary)] [&[data-active=true]_.dot]:text-white [&[data-active=true]_.label]:text-[var(--store-primary)]">
                    <span class="dot mx-auto flex h-7 w-7 items-center justify-center rounded-full bg-[var(--store-soft)] text-[var(--store-muted)]">{{ $loop->iteration }}</span>
                    <span class="label mt-1 block text-[var(--store-muted)]">{{ $label }}</span>
                </div>
            @endforeach
        </div>
        <p class="mt-3 min-h-5 text-sm font-semibold text-red-600" data-drawer-alert></p>
    </div>

    <div class="flex-1 overflow-y-auto p-5">
        <section data-checkout-step="cart">
            <div class="space-y-3" data-cart-items></div>
        </section>

        <section data-checkout-step="shipping" hidden>
            <h3 class="store-display text-xl font-black">Shipping information</h3>
            <div class="mt-4 grid gap-3">
                <input class="store-input" name="checkout_name" placeholder="Full name" autocomplete="name">
                <input class="store-input" name="checkout_phone" placeholder="Phone number" autocomplete="tel">
                <input class="store-input" name="checkout_email" placeholder="Email address" autocomplete="email">
                <textarea class="store-input min-h-24" name="checkout_address" placeholder="Delivery address"></textarea>
            </div>
            <h4 class="mt-5 font-bold">Shipping option</h4>
            <div class="mt-3 grid gap-2">
                @forelse ((array) $store->shipping_options as $option)
                    @php $priceMinor = (int) round(((float) ($option['price'] ?? 0)) * 100); @endphp
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-[var(--store-line)] p-3 hover:bg-[var(--store-soft)]">
                        <span class="flex items-center gap-3">
                            <input type="radio" name="shipping_option" data-price-minor="{{ $priceMinor }}" value="{{ $option['location'] ?? '' }}">
                            <span class="font-semibold">{{ $option['location'] ?? 'Shipping' }}</span>
                        </span>
                        <span class="font-bold">{{ $currencySymbol }}{{ number_format(((float) ($option['price'] ?? 0)), 2) }}</span>
                    </label>
                @empty
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-[var(--store-line)] p-3">
                        <span class="flex items-center gap-3">
                            <input type="radio" name="shipping_option" data-price-minor="0" value="default" checked>
                            <span class="font-semibold">Standard shipping</span>
                        </span>
                        <span class="font-bold">Free</span>
                    </label>
                @endforelse
            </div>
        </section>

        <section data-checkout-step="payment" hidden>
            <h3 class="store-display text-xl font-black">Payment method</h3>
            <div class="mt-4 grid gap-2">
                @forelse ((array) $store->payment_methods as $method)
                    <label class="cursor-pointer rounded-lg border border-[var(--store-line)] p-3 hover:bg-[var(--store-soft)]">
                        <span class="flex items-center gap-3">
                            <input type="radio" name="payment_method" value="{{ $method }}" @checked($loop->first)>
                            <span class="font-semibold">{{ $paymentLabels[$method] ?? Str::headline($method) }}</span>
                        </span>
                    </label>
                @empty
                    <label class="cursor-pointer rounded-lg border border-[var(--store-line)] p-3">
                        <span class="flex items-center gap-3">
                            <input type="radio" name="payment_method" value="place_order" checked>
                            <span class="font-semibold">Place order</span>
                        </span>
                    </label>
                @endforelse
            </div>
            @if (in_array('bank_account', (array) $store->payment_methods, true) && filled($store->bank_accounts))
                <div class="mt-4 rounded-lg bg-[var(--store-soft)] p-4 text-sm">
                    <p class="font-bold">Bank transfer details</p>
                    @foreach ((array) $store->bank_accounts as $account)
                        <p class="mt-2">{{ $account['bank_name'] ?? '' }} | {{ $account['account_name'] ?? '' }} | {{ $account['account_number'] ?? '' }}</p>
                    @endforeach
                </div>
            @endif
        </section>

        <section data-checkout-step="confirm" hidden>
            <div class="py-8 text-center">
                <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full text-white" style="background: var(--store-secondary);">
                    <span class="material-symbols-outlined text-5xl">check</span>
                </div>
                <h3 class="store-display mt-5 text-2xl font-black">Order confirmed</h3>
                <p class="mt-2 text-sm text-[var(--store-muted)]">Your cart has been cleared. Payment processing will be connected in the next task.</p>
            </div>
        </section>
    </div>

    <div class="border-t border-[var(--store-line)] p-5">
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span>Subtotal</span><strong data-subtotal>{{ $currencySymbol }}0.00</strong></div>
            <div class="flex justify-between"><span>Shipping</span><strong data-shipping-total>{{ $currencySymbol }}0.00</strong></div>
            <div class="flex justify-between text-lg text-[var(--store-primary)]"><span class="font-black">Total</span><strong data-grand-total>{{ $currencySymbol }}0.00</strong></div>
        </div>
        <div class="mt-4 grid gap-2">
            <button type="button" class="store-btn store-btn-primary w-full disabled:cursor-not-allowed disabled:opacity-50" data-go-step="shipping" data-checkout-step="cart" data-requires-cart>Checkout</button>
            <button type="button" class="store-btn store-btn-primary w-full" data-go-step="payment" data-checkout-step="shipping">Continue to payment</button>
            <button type="button" class="store-btn store-btn-secondary w-full" data-confirm-order data-checkout-step="payment">Pay now</button>
            <button type="button" class="store-btn store-btn-primary w-full" data-cart-close data-checkout-step="confirm">Continue shopping</button>
        </div>
    </div>
</aside>
