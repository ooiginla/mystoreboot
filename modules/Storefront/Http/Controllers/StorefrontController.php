<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\OnlineOrderConfirmationMail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCollection;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Enums\TicketPriority;
use Modules\Customers\Enums\TicketStatus;
use Modules\Customers\Enums\TicketType;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\SupportTicket;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Inventory\Actions\AdjustInventoryReservationAction;
use Modules\Sales\Actions\ExpireOnlineOrderReservationsAction;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\OnlineCollectedPayment;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderPayment;
use Modules\Storefront\Support\StorefrontUrl;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Throwable;

final class StorefrontController extends Controller
{
    public function home(OnlineStore $store, Request $request): View
    {
        $store = $this->preparedStore($store);
        $selectedCategorySlug = $request->string('category')->toString();
        $selectedCategory = $store->categories
            ->first(fn ($category): bool => $category->slug === $selectedCategorySlug);

        $products = $this->productsFor($store, ProductType::Product)
            ->when($selectedCategorySlug !== '', function ($query) use ($selectedCategorySlug): void {
                $query->whereHas('category', fn ($category) => $category->where('slug', $selectedCategorySlug));
            })
            ->latest()
            ->paginate(16)
            ->withQueryString();
        $productCollections = ProductCollection::query()
            ->with([
                'products' => fn ($query) => $query
                    ->with([
                        'badges',
                        'category',
                        'images',
                        'tenant',
                        'variants' => fn ($variantQuery) => $variantQuery
                            ->where('status', ProductStatus::Active->value)
                            ->oldest('id'),
                    ])
                    ->where('tenant_id', $store->tenant_id)
                    ->where('product_type', ProductType::Product->value)
                    ->where('status', ProductStatus::Active->value)
                    ->latest('products.id'),
            ])
            ->where('tenant_id', $store->tenant_id)
            ->where('collection_type', 'manual')
            ->where('is_visible', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByRaw('sort_order is null')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (ProductCollection $collection): bool => $collection->products->isNotEmpty())
            ->values();

        return view('storefront::home', [
            'store' => $store,
            'products' => $products,
            'productCollections' => $productCollections,
            'selectedCategory' => $selectedCategorySlug,
            'selectedCategoryName' => $selectedCategory?->name,
            'selectedCollection' => null,
        ]);
    }

    public function category(OnlineStore $store, string $categorySlug): View
    {
        $store = $this->preparedStore($store);
        $category = $store->categories
            ->first(fn ($item): bool => $item->slug === $categorySlug
                && ($item->category_type?->value ?? (string) $item->category_type) === 'product');

        abort_unless($category, 404);

        return view('storefront::home', [
            'store' => $store,
            'products' => $this->productsFor($store, ProductType::Product)
                ->where('category_id', $category->id)
                ->latest()
                ->paginate(16),
            'productCollections' => collect(),
            'selectedCategory' => $category->slug,
            'selectedCategoryName' => $category->name,
            'selectedCollection' => null,
        ]);
    }

    public function collection(OnlineStore $store, string $collectionSlug): View
    {
        $store = $this->preparedStore($store);
        $collection = $store->productCollections
            ->first(fn (ProductCollection $item): bool => ($item->slug ?: (string) $item->id) === $collectionSlug);

        abort_unless($collection, 404);

        return view('storefront::home', [
            'store' => $store,
            'products' => $this->productsFor($store, ProductType::Product)
                ->whereHas('collections', fn ($query) => $query->whereKey($collection->id))
                ->latest()
                ->paginate(16),
            'productCollections' => collect(),
            'selectedCategory' => '',
            'selectedCategoryName' => null,
            'selectedCollection' => $collection,
        ]);
    }

    public function services(OnlineStore $store): View
    {
        $store = $this->preparedStore($store);

        return view('storefront::services', [
            'store' => $store,
            'services' => $this->productsFor($store, ProductType::Service)
                ->latest()
                ->paginate(16)
                ->withQueryString(),
        ]);
    }

    public function product(OnlineStore $store, string $productSlug): View
    {
        return $this->showCatalogItem($store, $productSlug, ProductType::Product);
    }

    public function service(OnlineStore $store, string $serviceSlug): View
    {
        return $this->showCatalogItem($store, $serviceSlug, ProductType::Service);
    }

    public function page(OnlineStore $store, string $page): View
    {
        $store = $this->preparedStore($store);

        abort_unless(array_key_exists($page, $this->pageTitles()), 404);

        return view('storefront::page', [
            'store' => $store,
            'pageKey' => $page,
            'title' => $this->pageTitles()[$page],
            'content' => trim((string) data_get($store->pages, $page)),
        ]);
    }

    public function faq(OnlineStore $store): View
    {
        return view('storefront::faq', [
            'store' => $this->preparedStore($store),
        ]);
    }

    public function contact(OnlineStore $store): View
    {
        return view('storefront::contact', [
            'store' => $this->preparedStore($store),
        ]);
    }

    public function track(OnlineStore $store, Request $request): View
    {
        $store = $this->preparedStore($store);
        $reference = strtoupper(trim($request->string('reference')->toString()));
        $order = null;
        $timeline = [];

        if ($reference !== '') {
            // Scope to THIS store's tenant so one storefront can only reveal its
            // own orders, even though tracking references are globally unique.
            $order = SalesOrder::query()
                ->with(['items', 'customer', 'payments'])
                ->where('tenant_id', $store->tenant_id)
                ->where('source', 'online')
                ->where('tracking_reference', $reference)
                ->first();

            if ($order) {
                $timeline = $this->orderTimeline($order);
            }
        }

        return view('storefront::track', [
            'store' => $store,
            'reference' => $reference,
            'order' => $order,
            'timeline' => $timeline,
            'searched' => $request->has('reference'),
        ]);
    }

    /**
     * A buyer-facing progress timeline derived from the order's current
     * statuses and timestamps.
     *
     * @return list<array{label: string, note: string, done: bool, at: Carbon|null}>
     */
    private function orderTimeline(SalesOrder $order): array
    {
        if ($order->order_status === SalesOrderStatus::Cancelled) {
            return [
                ['label' => 'Order placed', 'note' => 'We received your order.', 'done' => true, 'at' => $order->created_at],
                ['label' => 'Order cancelled', 'note' => 'This order was cancelled.', 'done' => true, 'at' => $order->updated_at],
            ];
        }

        $paid = in_array($order->payment_status, [SalesPaymentStatus::Paid, SalesPaymentStatus::PartiallyPaid], true);
        $delivery = (string) ($order->delivery_status ?? 'pending');
        $processing = in_array($order->order_status, [SalesOrderStatus::Processing, SalesOrderStatus::Completed], true);
        $outForDelivery = in_array($delivery, ['out_for_delivery', 'delivered'], true);
        $delivered = $delivery === 'delivered' || $order->order_status === SalesOrderStatus::Completed;

        return [
            ['label' => 'Order placed', 'note' => 'We received your order.', 'done' => true, 'at' => $order->created_at],
            ['label' => $paid ? 'Payment received' : 'Payment pending', 'note' => $order->payment_status->label(), 'done' => $paid, 'at' => $paid ? ($order->payments->sortByDesc('id')->first()?->created_at ?? $order->created_at) : null],
            ['label' => 'Processing', 'note' => 'Your order is being prepared.', 'done' => $processing, 'at' => null],
            ['label' => 'Out for delivery', 'note' => 'On its way to you.', 'done' => $outForDelivery, 'at' => null],
            ['label' => 'Delivered', 'note' => 'Order completed.', 'done' => $delivered, 'at' => null],
        ];
    }

    public function submitContact(OnlineStore $store, Request $request): RedirectResponse
    {
        $store = $this->preparedStore($store);

        if ($store->maintenance_mode || ! $store->is_active) {
            throw ValidationException::withMessages([
                'message' => 'This store is temporarily unavailable. Please try again soon.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'phone' => ['required', 'string', 'max:60'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $nameParts = preg_split('/\s+/', trim($data['name']), 2) ?: [];
        $customer = Customer::query()->firstOrCreate(
            ['tenant_id' => $store->tenant_id, 'phone' => $data['phone']],
            [
                'first_name' => $nameParts[0] ?? $data['name'],
                'last_name' => $nameParts[1] ?? null,
                'email' => $data['email'] ?? null,
                'status' => CustomerStatus::Active->value,
            ],
        );

        $customer->fill([
            'first_name' => $nameParts[0] ?? $customer->first_name,
            'last_name' => $nameParts[1] ?? $customer->last_name,
            'email' => $data['email'] ?? $customer->email,
        ])->save();

        SupportTicket::query()->create([
            'tenant_id' => $store->tenant_id,
            'customer_id' => $customer->id,
            'ticket_number' => $this->ticketNumber($store),
            'type' => TicketType::Enquiry->value,
            'category' => 'Online store contact',
            'priority' => TicketPriority::Normal->value,
            'status' => TicketStatus::Open->value,
            'subject' => $data['subject'],
            'description' => $data['message'],
        ]);

        return back()->with('status', 'Your message has been sent. The store team will respond shortly.');
    }

    public function checkout(OnlineStore $store, Request $request): JsonResponse
    {
        $store = $this->preparedStore($store);

        if ($store->maintenance_mode || ! $store->is_active) {
            throw ValidationException::withMessages([
                'store' => 'This store is temporarily unavailable. Please try again soon.',
            ]);
        }

        $data = $request->validate([
            'customer.name' => ['required', 'string', 'max:160'],
            'customer.email' => ['required', 'email:rfc', 'max:160'],
            'customer.phone' => ['required', 'string', 'max:60'],
            'customer.address' => ['required', 'string', 'max:1000'],
            'customer.save_address' => ['sometimes', 'boolean'],
            'customer.address_label' => ['nullable', 'required_if:customer.save_address,true', 'string', 'max:80'],
            'shipping_option' => ['required', 'string', 'max:120'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        // Lazily release expired reservations before this checkout so a stalled
        // scheduler never permanently locks stock behind abandoned unpaid orders.
        if (app(TenantModuleAccess::class)->allows($store->tenant, 'inventory')) {
            app(ExpireOnlineOrderReservationsAction::class)->execute($store->tenant_id);
        }

        try {
            $order = DB::transaction(function () use ($store, $data): SalesOrder {
                $customerData = $data['customer'];
                $nameParts = preg_split('/\s+/', trim($customerData['name']), 2) ?: [];
                $email = Str::lower(trim($customerData['email']));

                $customer = Customer::query()
                    ->where('tenant_id', $store->tenant_id)
                    ->where(fn ($query) => $query->where('email', $email)->orWhere('phone', $customerData['phone']))
                    ->first() ?? new Customer(['tenant_id' => $store->tenant_id]);

                $customer->fill([
                    'first_name' => $nameParts[0] ?? $customer->first_name,
                    'last_name' => $nameParts[1] ?? $customer->last_name,
                    'email' => $email,
                    'phone' => $customerData['phone'],
                    'address' => $customerData['address'],
                    'status' => CustomerStatus::Active->value,
                ])->save();

                $matchingAddress = $customer->addresses()
                    ->where('address', $customerData['address'])
                    ->first();

                if ((bool) ($customerData['save_address'] ?? false)) {
                    $label = trim((string) $customerData['address_label']);
                    $addressWithLabel = $customer->addresses()->where('label', $label)->first();
                    $savedAddress = $customer->addresses()->updateOrCreate(
                        ['label' => $label],
                        [
                            'tenant_id' => $store->tenant_id,
                            'address' => $customerData['address'],
                            'is_default' => $addressWithLabel?->is_default ?? ! $customer->addresses()->exists(),
                            'last_used_at' => now(),
                        ],
                    );
                    $matchingAddress = $savedAddress;
                }

                $matchingAddress?->update(['last_used_at' => now()]);

                $items = collect($data['items'])->map(function (array $item) use ($store): array {
                    $variant = ProductVariant::query()
                        ->with('product.taxes')
                        ->where('tenant_id', $store->tenant_id)
                        ->where('status', ProductStatus::Active->value)
                        ->findOrFail($item['product_variant_id']);

                    $categoryIds = $store->categories->pluck('id');
                    abort_unless(
                        $variant->product
                        && $variant->product->status === ProductStatus::Active
                        && ($categoryIds->isEmpty() || $categoryIds->contains($variant->product->category_id)),
                        422,
                        'One or more cart items are unavailable.',
                    );

                    $quantity = (int) $item['quantity'];
                    $unitPriceMinor = (int) $variant->selling_price_minor;
                    $lineSubtotalMinor = $quantity * $unitPriceMinor;
                    $selectedTaxRate = $variant->product?->taxes?->sum(fn ($tax): float => (float) $tax->rate) ?? 0.0;
                    $taxRate = $variant->tax_behavior === TaxBehavior::Taxable
                        ? (float) ($selectedTaxRate > 0 ? $selectedTaxRate : ($variant->tax_rate ?? $variant->product?->tax_rate ?? $store->tenant?->default_tax_rate ?? 0))
                        : 0.0;

                    return [
                        'variant' => $variant,
                        'quantity' => $quantity,
                        'unit_price_minor' => $unitPriceMinor,
                        'unit_cost_minor' => (bool) ($store->tenant?->settings['use_estimated_cost_for_cogs'] ?? false)
                            ? (int) ($variant->cost_price_minor ?: $variant->product?->base_cost_price_minor ?: 0)
                            : 0,
                        'line_subtotal_minor' => $lineSubtotalMinor,
                        'tax_minor' => (int) round($lineSubtotalMinor * ($taxRate / 100)),
                    ];
                })->values();

                $shippingMinor = $this->shippingMinor($store, (string) $data['shipping_option']);
                $subtotalMinor = (int) $items->sum('line_subtotal_minor');
                $taxMinor = (int) $items->sum('tax_minor');
                $totalMinor = $subtotalMinor + $taxMinor + $shippingMinor;

                // Reserve stock so two shoppers cannot buy the same last unit. The
                // reservation holds availability until the order is paid & completed,
                // or expires and is auto-cancelled to free the stock for others. Only
                // applies when inventory is tracked at an active fulfilment location;
                // stores without inventory tracking check out unchanged.
                $reservationLocationId = null;
                $reservedUntil = null;
                $stockReserved = false;

                if (app(TenantModuleAccess::class)->allows($store->tenant, 'inventory')) {
                    $reservations = app(AdjustInventoryReservationAction::class);
                    $reservationLocationId = $reservations->resolveActiveLocationId($store->tenant_id, (int) $store->fulfilment_branch_id);

                    if ($reservationLocationId) {
                        foreach ($items as $item) {
                            if ($item['variant']->product?->product_type !== ProductType::Product) {
                                continue;
                            }

                            $reservations->reserve($store->tenant_id, $reservationLocationId, (int) $item['variant']->id, (int) $item['quantity']);
                        }

                        $reservedUntil = now()->addMinutes(max(1, (int) ($store->reservation_hold_minutes ?: 30)));
                        $stockReserved = true;
                    }
                }

                $order = SalesOrder::query()->create([
                    'tenant_id' => $store->tenant_id,
                    'branch_id' => $store->fulfilment_branch_id,
                    'inventory_location_id' => $reservationLocationId,
                    'stock_reserved' => $stockReserved,
                    'reserved_until' => $reservedUntil,
                    'customer_id' => $customer->id,
                    'source' => 'online',
                    'order_number' => $this->salesOrderNumber('SO', $store->tenant_id),
                    'invoice_number' => $this->salesOrderNumber('INV', $store->tenant_id),
                    'receipt_number' => $this->salesOrderNumber('RCT', $store->tenant_id),
                    'order_status' => SalesOrderStatus::Pending->value,
                    'payment_status' => SalesPaymentStatus::Pending->value,
                    'order_date' => now()->toDateString(),
                    'is_credit_sale' => false,
                    'subtotal_minor' => $subtotalMinor,
                    'tax_minor' => $taxMinor,
                    'shipping_minor' => $shippingMinor,
                    'total_minor' => $totalMinor,
                    'paid_minor' => 0,
                    'change_due_minor' => 0,
                    'payment_method' => $data['payment_method'] ?? null,
                    'delivery_method' => $data['shipping_option'],
                    'delivery_status' => 'pending',
                    'delivery_address' => $customerData['address'],
                ]);

                foreach ($items as $item) {
                    $variant = $item['variant'];
                    $order->items()->create([
                        'tenant_id' => $store->tenant_id,
                        'product_variant_id' => $variant->id,
                        'item_name' => $variant->product?->name.' / '.$variant->variant_name,
                        'sku' => $variant->sku,
                        'quantity' => $item['quantity'],
                        'unit_price_minor' => $item['unit_price_minor'],
                        'unit_cost_minor' => $item['unit_cost_minor'],
                        'tax_minor' => $item['tax_minor'],
                        'line_total_minor' => $item['line_subtotal_minor'] + $item['tax_minor'],
                    ]);
                }

                return $order->refresh();
            });
        } catch (ValidationException $exception) {
            // Surface sold-out / reservation errors as JSON so the checkout UI can
            // show them instead of following a redirect to an HTML page.
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        $this->sendOrderConfirmation($store, $order);

        return response()->json([
            'order_id' => $order->id,
            'order_reference' => $order->order_number,
        ]);
    }

    public function lookupCustomer(OnlineStore $store, Request $request): JsonResponse
    {
        $store = $this->preparedStore($store);
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
        ]);
        $customer = Customer::query()
            ->with(['addresses' => fn ($query) => $query
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->orderBy('label')])
            ->where('tenant_id', $store->tenant_id)
            ->whereRaw('LOWER(email) = ?', [Str::lower(trim($data['email']))])
            ->first();

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'customer' => [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'addresses' => $customer->addresses->map(fn ($address): array => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'address' => $address->address,
                    'is_default' => $address->is_default,
                ])->values(),
            ],
        ]);
    }

    public function initializePaystackPayment(OnlineStore $store, SalesOrder $order, Request $request): JsonResponse
    {
        $store = $this->preparedStore($store);
        $this->assertStoreOrder($store, $order);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:storeboot_paystack,self_hosted_paystack'],
        ]);

        abort_unless(in_array($data['payment_method'], (array) $store->payment_methods, true), 422, 'This payment method is not enabled for this store.');
        abort_unless($order->payment_status !== SalesPaymentStatus::Paid, 422, 'This order has already been paid.');
        abort_unless($order->total_minor > 0, 422, 'This order cannot be paid online.');

        $keys = $this->paystackKeys($store, $data['payment_method']);
        $baseTotalMinor = max(0, $order->total_minor - (int) ($order->gateway_charge_minor ?? 0));
        $gatewayChargeMinor = $this->paymentGatewayChargeMinor($store->tenant_id, $baseTotalMinor);
        $order->update([
            'payment_method' => $data['payment_method'],
            'gateway_charge_minor' => $gatewayChargeMinor,
            'total_minor' => $baseTotalMinor + $gatewayChargeMinor,
        ]);
        $order->refresh();
        $reference = 'PSK-'.$order->id.'-'.Str::upper(Str::random(10));

        $response = Http::withToken($keys['secret_key'])
            ->acceptJson()
            ->asJson()
            ->post(rtrim((string) config('services.paystack.base_url'), '/').'/transaction/initialize', [
                'email' => $order->customer?->email,
                'amount' => $order->total_minor,
                'currency' => $store->tenant?->currency_code ?? 'NGN',
                'reference' => $reference,
                'callback_url' => StorefrontUrl::route($store, 'paystack.callback'),
                'metadata' => [
                    'store_id' => $store->id,
                    'tenant_id' => $store->tenant_id,
                    'sales_order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_method' => $data['payment_method'],
                    'gateway_charge_minor' => $gatewayChargeMinor,
                ],
            ]);

        if (! $response->successful() || ! (bool) data_get($response->json(), 'status')) {
            throw ValidationException::withMessages([
                'payment' => data_get($response->json(), 'message', 'Paystack could not initialize this payment. Please try again.'),
            ]);
        }

        return response()->json([
            'authorization_url' => data_get($response->json(), 'data.authorization_url'),
            'access_code' => data_get($response->json(), 'data.access_code'),
            'reference' => data_get($response->json(), 'data.reference', $reference),
            'public_key' => $keys['public_key'],
            'email' => $order->customer?->email,
            'amount' => $order->total_minor,
            'gateway_charge_minor' => $gatewayChargeMinor,
            'base_total_minor' => $baseTotalMinor,
            'currency' => $store->tenant?->currency_code ?? 'NGN',
            'verify_url' => StorefrontUrl::route($store, 'checkout.paystack.verify', ['order' => $order]),
        ]);
    }

    public function verifyPaystackStoreCallback(OnlineStore $store, Request $request): RedirectResponse
    {
        $reference = $request->string('reference')->toString();

        if (! preg_match('/^PSK-(\d+)-/i', $reference, $matches)) {
            return redirect()
                ->to(StorefrontUrl::route($store))
                ->with('payment_error', 'Paystack did not return a valid order reference.');
        }

        $order = SalesOrder::query()->find((int) $matches[1]);

        if (! $order) {
            return redirect()
                ->to(StorefrontUrl::route($store))
                ->with('payment_error', 'We could not find the order for this Paystack payment.');
        }

        return $this->verifyPaystackPayment($store, $order, $request);
    }

    public function verifyPaystackPayment(OnlineStore $store, SalesOrder $order, Request $request): JsonResponse|RedirectResponse
    {
        $store = $this->preparedStore($store);
        $this->assertStoreOrder($store, $order);

        $reference = $request->string('reference')->toString();

        if ($reference === '') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Paystack did not return a payment reference.'], 422);
            }

            return redirect()
                ->to(StorefrontUrl::route($store))
                ->with('payment_error', 'Paystack did not return a payment reference.');
        }

        $paymentMethod = in_array($order->payment_method, ['storeboot_paystack', 'self_hosted_paystack'], true)
            ? $order->payment_method
            : 'storeboot_paystack';
        $keys = $this->paystackKeys($store, $paymentMethod);
        $response = Http::withToken($keys['secret_key'])
            ->acceptJson()
            ->get(rtrim((string) config('services.paystack.base_url'), '/').'/transaction/verify/'.rawurlencode($reference));
        $payload = $response->json();

        if (! $response->successful() || ! (bool) data_get($payload, 'status') || data_get($payload, 'data.status') !== 'success') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => data_get($payload, 'message', 'Paystack could not verify this payment.'),
                ], 422);
            }

            return redirect()
                ->to(StorefrontUrl::route($store))
                ->with('payment_error', data_get($payload, 'message', 'Paystack could not verify this payment.'));
        }

        $amountMinor = (int) data_get($payload, 'data.amount', 0);
        $currency = (string) data_get($payload, 'data.currency', '');

        if ($amountMinor < $order->total_minor || ! hash_equals(strtoupper($store->tenant?->currency_code ?? 'NGN'), strtoupper($currency))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The verified Paystack payment did not match this order.'], 422);
            }

            return redirect()
                ->to(StorefrontUrl::route($store))
                ->with('payment_error', 'The verified Paystack payment did not match this order.');
        }

        DB::transaction(function () use ($order, $reference, $amountMinor, $payload): void {
            $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            $payment = SalesOrderPayment::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->where('reference_number', $reference)
                ->first();

            if (! $payment) {
                $payment = $lockedOrder->payments()->create([
                    'tenant_id' => $lockedOrder->tenant_id,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => $lockedOrder->payment_method ?? 'Paystack',
                    'amount_minor' => min($amountMinor, $lockedOrder->total_minor),
                    'reference_number' => $reference,
                    'notes' => 'Verified Paystack payment.',
                ]);
            }

            $paidMinor = (int) $lockedOrder->payments()->sum('amount_minor');
            $feesMinor = (int) data_get($payload, 'data.fees', 0);
            $paidAmountMinor = min($amountMinor, $lockedOrder->total_minor);
            $shippingAmountMinor = (int) $lockedOrder->shipping_minor;
            $gatewayChargeMinor = (int) ($lockedOrder->gateway_charge_minor ?? 0);
            $productAmountMinor = max(0, $paidAmountMinor - $shippingAmountMinor - $gatewayChargeMinor);

            OnlineCollectedPayment::query()->updateOrCreate([
                'tenant_id' => $lockedOrder->tenant_id,
                'provider' => 'paystack',
                'provider_reference' => $reference,
            ], [
                'branch_id' => $lockedOrder->branch_id,
                'sales_order_id' => $lockedOrder->id,
                'sales_order_payment_id' => $payment?->id,
                'payment_method' => $lockedOrder->payment_method,
                'gateway_reference' => data_get($payload, 'data.id') ? (string) data_get($payload, 'data.id') : null,
                'customer_email' => data_get($payload, 'data.customer.email', $lockedOrder->customer?->email),
                'currency' => (string) data_get($payload, 'data.currency', 'NGN'),
                'product_amount_minor' => $productAmountMinor,
                'shipping_amount_minor' => $shippingAmountMinor,
                'gateway_charge_minor' => $gatewayChargeMinor,
                'amount_minor' => $paidAmountMinor,
                'fees_minor' => $feesMinor,
                'net_amount_minor' => max(0, $paidAmountMinor - $feesMinor),
                'status' => 'successful',
                'is_settled' => false,
                'collected_at' => data_get($payload, 'data.paid_at') ? (string) data_get($payload, 'data.paid_at') : now(),
                'verified_at' => now(),
                'raw_payload' => $payload,
            ]);

            $lockedOrder->update([
                'paid_minor' => min($paidMinor, $lockedOrder->total_minor),
                'payment_status' => $paidMinor >= $lockedOrder->total_minor
                    ? SalesPaymentStatus::Paid->value
                    : SalesPaymentStatus::PartiallyPaid->value,
                // Paid orders no longer auto-cancel; the reservation is held until
                // the order is completed (stock deducted) or later cancelled.
                'reserved_until' => null,
            ]);

            // Recognise the gateway receipt now: debit Online Payment Clearing and
            // credit Customer Deposits (unearned revenue) until the order completes.
            app(PostJournalEntryAction::class)->execute(
                $lockedOrder->tenant_id,
                now()->toDateString(),
                'Online deposit received for '.$lockedOrder->order_number,
                [
                    ['account_code' => '1060', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => (int) $payment->amount_minor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ['account_code' => '2310', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => (int) $payment->amount_minor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                ],
                'sales_order_payment',
                $payment->id,
                'deposit_received',
            );
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Payment successful.',
                'order_reference' => $order->order_number,
            ]);
        }

        return redirect()
            ->to(StorefrontUrl::route($store))
            ->with('status', 'Payment successful. Your order reference is '.$order->order_number.'.')
            ->with('clear_cart', true);
    }

    private function preparedStore(OnlineStore $store): OnlineStore
    {
        abort_unless($store->is_active, 404);

        return $store->loadMissing([
            'tenant',
            'categories.children',
            'fulfilmentBranch',
            'productCollections' => fn ($query) => $query
                ->where('collection_type', 'manual')
                ->where('is_visible', true)
                ->where(fn ($dateQuery) => $dateQuery->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($dateQuery) => $dateQuery->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->whereHas('products', fn ($productQuery) => $productQuery
                    ->where('products.tenant_id', $store->tenant_id)
                    ->where('product_type', ProductType::Product->value)
                    ->where('status', ProductStatus::Active->value))
                ->orderByRaw('sort_order is null')
                ->orderBy('sort_order')
                ->orderBy('name'),
        ]);
    }

    private function sendOrderConfirmation(OnlineStore $store, SalesOrder $order): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $order->loadMissing(['customer', 'items', 'branch']);

        if (! filled($order->customer?->email)) {
            return;
        }

        try {
            Mail::to($order->customer->email)->send(new OnlineOrderConfirmationMail($store, $order));
        } catch (Throwable $exception) {
            Log::warning('Online order confirmation email could not be sent.', [
                'sales_order_id' => $order->id,
                'order_number' => $order->order_number,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function productsFor(OnlineStore $store, ProductType $type)
    {
        $categoryIds = $store->categories->pluck('id');

        return Product::query()
            ->with([
                'badges' => fn ($query) => $query->where('is_visible', true),
                'category',
                'images',
                'variants' => fn ($query) => $query
                    ->where('status', ProductStatus::Active->value)
                    ->oldest('id')
                    ->with('optionValues.option'),
                'tags',
                'taxes',
            ])
            ->where('tenant_id', $store->tenant_id)
            ->where('status', ProductStatus::Active->value)
            ->where('product_type', $type->value)
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('category_id', $categoryIds));
    }

    private function showCatalogItem(OnlineStore $store, string $slug, ProductType $type): View
    {
        $store = $this->preparedStore($store);
        $categoryIds = $store->categories->pluck('id');
        $product = Product::query()
            ->where('tenant_id', $store->tenant_id)
            ->where('slug', $slug)
            ->where('product_type', $type->value)
            ->firstOrFail();

        abort_unless(
            $product->status === ProductStatus::Active
            && ($categoryIds->isEmpty() || $categoryIds->contains($product->category_id)),
            404,
        );

        $product->load([
            'badges' => fn ($query) => $query->where('is_visible', true),
            'category',
            'images',
            'variants' => fn ($query) => $query
                ->where('status', ProductStatus::Active->value)
                ->oldest('id')
                ->with('optionValues.option'),
            'tags',
            'taxes',
            'attributeValues.definition',
        ]);

        $related = $this->productsFor($store, $type)
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->latest()
            ->limit(4)
            ->get();

        if ($related->count() < 4) {
            $fallback = $this->productsFor($store, $type)
                ->whereKeyNot($product->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->latest()
                ->limit(4 - $related->count())
                ->get();

            $related = $related->concat($fallback);
        }

        return view('storefront::product', [
            'store' => $store,
            'product' => $product,
            'relatedProducts' => $related,
            'catalogType' => $type,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function pageTitles(): array
    {
        return [
            'about_us' => 'About Us',
            'terms_of_use' => 'Terms of Service',
            'return_policy' => 'Refunds',
            'privacy_policy' => 'Privacy Policy',
            'shipping_information' => 'Shipping Info',
        ];
    }

    private function ticketNumber(OnlineStore $store): string
    {
        do {
            $number = 'WEB-'.Str::upper(Str::random(8));
        } while (SupportTicket::query()->where('tenant_id', $store->tenant_id)->where('ticket_number', $number)->exists());

        return $number;
    }

    private function salesOrderNumber(string $prefix, string $tenantId): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) (SalesOrder::query()->where('tenant_id', $tenantId)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function shippingMinor(OnlineStore $store, string $location): int
    {
        $options = collect((array) $store->shipping_options);

        if ($options->isEmpty() && $location === 'default') {
            return 0;
        }

        $option = $options->first(fn (array $option): bool => (string) ($option['location'] ?? '') === $location);

        if (! $option) {
            throw ValidationException::withMessages([
                'shipping_option' => 'Select a valid shipping option.',
            ]);
        }

        return (int) round(((float) ($option['price'] ?? 0)) * 100);
    }

    private function assertStoreOrder(OnlineStore $store, SalesOrder $order): void
    {
        abort_unless($order->tenant_id === $store->tenant_id && $order->source === 'online', 404);
        $order->loadMissing('customer');
    }

    /**
     * @return array{public_key: string, secret_key: string}
     */
    private function paystackKeys(OnlineStore $store, string $paymentMethod): array
    {
        $publicKey = $paymentMethod === 'self_hosted_paystack'
            ? (string) data_get($store->payment_settings, 'paystack.public_key')
            : (string) config('services.paystack.public_key');
        $secretKey = $paymentMethod === 'self_hosted_paystack'
            ? (string) data_get($store->payment_settings, 'paystack.private_key')
            : (string) config('services.paystack.secret_key');

        if ($publicKey === '' || $secretKey === '') {
            throw ValidationException::withMessages([
                'payment' => 'Paystack is not fully configured for this store.',
            ]);
        }

        return [
            'public_key' => $publicKey,
            'secret_key' => $secretKey,
        ];
    }

    private function paymentGatewayChargeMinor(string $tenantId, int $amountMinor): int
    {
        if ($amountMinor <= 0) {
            return 0;
        }

        $config = DB::table('global_configs')
            ->where('key', 'PAYMENT_GATEWAY_CHARGE')
            ->where('tenant_id', $tenantId)
            ->value('value');

        $config ??= DB::table('global_configs')
            ->where('key', 'PAYMENT_GATEWAY_CHARGE')
            ->whereNull('tenant_id')
            ->value('value');

        $values = is_string($config) && $config !== ''
            ? json_decode($config, true)
            : [];

        if (! is_array($values)) {
            $values = [];
        }

        $percentageRate = (float) ($values['percentage_rate'] ?? 1.5);
        $fixedAmountMinor = (int) ($values['fixed_amount_minor'] ?? 10000);

        return max(0, (int) ceil($amountMinor * ($percentageRate / 100)) + $fixedAmountMinor);
    }
}
