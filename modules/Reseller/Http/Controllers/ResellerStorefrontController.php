<?php

declare(strict_types=1);

namespace Modules\Reseller\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\OnlineStore;
use Modules\Reseller\Enums\PricingMode;
use Modules\Reseller\Enums\ProductAvailability;
use Modules\Reseller\Models\ResellerOrder;
use Modules\Reseller\Models\ResellerPayment;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Storefront\Support\StorefrontUrl;

final class ResellerStorefrontController extends Controller
{
    public function home(OnlineStore $store, Request $request): View
    {
        $store = $this->store($store);
        $search = Str::limit(trim($request->string('search')->toString()), 100, '');
        $category = trim($request->string('category')->toString());
        $query = $this->availableProducts($store)
            ->when($search !== '', fn ($builder) => $builder->where(function ($searchQuery) use ($search): void {
                $searchQuery->whereLike('name', '%'.$search.'%')
                    ->orWhereLike('short_description', '%'.$search.'%')
                    ->orWhereLike('brand', '%'.$search.'%')
                    ->orWhereLike('sku', '%'.$search.'%');
            }))
            ->when($category !== '', fn ($builder) => $builder->where('category', $category));

        return view('reseller::storefront.home', [
            'store' => $store,
            'settings' => $this->settings($store),
            'products' => $query->latest('last_checked_at')->paginate(16)->withQueryString(),
            'categories' => ResellerProduct::query()
                ->where('tenant_id', $store->tenant_id)
                ->where('is_visible', true)
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'selectedCategory' => $category,
            'search' => $search,
            'canonical' => StorefrontUrl::route($store),
            'metaDescription' => "Shop products curated by {$store->store_name}.",
        ]);
    }

    public function category(OnlineStore $store, string $categorySlug): View
    {
        $store = $this->store($store);
        $category = ResellerProduct::query()
            ->where('tenant_id', $store->tenant_id)
            ->where('is_visible', true)
            ->whereNotNull('category')
            ->pluck('category')
            ->first(fn (string $name): bool => Str::slug($name) === $categorySlug);
        abort_unless($category, 404);

        request()->merge(['category' => $category]);

        return $this->home($store, request());
    }

    public function product(OnlineStore $store, string $productSlug): View
    {
        $store = $this->store($store);
        abort_unless(ctype_digit($productSlug), 404);
        $product = $this->availableProducts($store)->with('supplier')->findOrFail((int) $productSlug);

        return view('reseller::storefront.product', [
            'store' => $store,
            'settings' => $this->settings($store),
            'product' => $product,
            'relatedProducts' => $this->availableProducts($store)
                ->whereKeyNot($product->id)
                ->when($product->category, fn ($query) => $query->where('category', $product->category))
                ->limit(4)
                ->get(),
            'canonical' => StorefrontUrl::route($store, 'products.show', ['productSlug' => $product->id]),
            'metaDescription' => Str::limit(strip_tags((string) $product->short_description), 155, ''),
        ]);
    }

    public function checkout(OnlineStore $store, Request $request): JsonResponse
    {
        $store = $this->store($store);
        $settings = $this->settings($store);
        $paymentMethods = $this->paymentMethods($store);
        $data = $request->validate([
            'customer.name' => ['required', 'string', 'max:160'],
            'customer.email' => ['required', 'email:rfc', 'max:160'],
            'customer.phone' => ['required', 'string', 'max:60'],
            'customer.address' => ['required', 'string', 'max:1000'],
            'customer.city' => ['required', 'string', 'max:120'],
            'shipping_option' => ['required', 'string', 'max:120'],
            'payment_method' => ['required', Rule::in($paymentMethods)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
        $requestedIds = collect($data['items'])->pluck('product_variant_id')->map(fn ($id): int => (int) $id)->unique();
        $products = $this->availableProducts($store)->whereIn('id', $requestedIds)->get()->keyBy('id');

        if ($products->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages(['items' => 'One or more reseller products are unavailable.']);
        }

        $staleBefore = now()->subHours($settings->stale_after_hours);

        if ($products->contains(fn (ResellerProduct $product): bool => $product->last_checked_at->isBefore($staleBefore))) {
            throw ValidationException::withMessages(['items' => 'One or more products need a fresh supplier check before checkout.']);
        }

        $order = DB::transaction(function () use ($store, $settings, $data, $products): ResellerOrder {
            $items = collect($data['items'])->map(function (array $item) use ($products): array {
                $product = $products->get((int) $item['product_variant_id']);
                $quantity = (int) $item['quantity'];

                return ['product' => $product, 'quantity' => $quantity, 'line_total_minor' => $product->selling_price_minor * $quantity];
            });
            $subtotal = (int) $items->sum('line_total_minor');
            $delivery = $this->shippingMinor($store, $data['shipping_option']);
            $reference = $this->nextReference($store->tenant_id);
            $order = ResellerOrder::query()->create([
                'tenant_id' => $store->tenant_id,
                'order_reference' => $reference,
                'customer_name' => $data['customer']['name'],
                'customer_email' => Str::lower($data['customer']['email']),
                'customer_phone' => $data['customer']['phone'],
                'delivery_address' => [
                    'address' => $data['customer']['address'],
                    'city' => $data['customer']['city'],
                    'shipping_option' => $data['shipping_option'],
                    'notes' => $data['notes'] ?? null,
                ],
                'currency_code' => $store->tenant->currency_code,
                'subtotal_minor' => $subtotal,
                'delivery_minor' => $delivery,
                'total_minor' => $subtotal + $delivery,
                'payment_status' => 'pending',
                'order_status' => 'confirmed',
                'placed_at' => now(),
            ]);

            foreach ($items as $item) {
                /** @var ResellerProduct $product */
                $product = $item['product'];
                $order->items()->create([
                    'tenant_id' => $store->tenant_id,
                    'reseller_product_id' => $product->id,
                    'supplier_id' => $product->supplier_id,
                    'product_name' => $product->name,
                    'source_store_name' => $product->source_store_name,
                    'product_url' => $product->product_url,
                    'image_url' => $product->main_image_url,
                    'sku' => $product->sku,
                    'source_price_minor' => $product->source_price_minor,
                    'percentage_markup_basis_points' => in_array($settings->pricing_mode, [PricingMode::Percentage, PricingMode::Combined], true) ? $settings->percentage_markup_basis_points : 0,
                    'fixed_markup_minor' => in_array($settings->pricing_mode, [PricingMode::Fixed, PricingMode::Combined], true) ? $settings->fixed_markup_minor : 0,
                    'unit_selling_price_minor' => $product->selling_price_minor,
                    'quantity' => $item['quantity'],
                    'line_total_minor' => $item['line_total_minor'],
                ]);
            }

            ResellerPayment::query()->create([
                'tenant_id' => $store->tenant_id,
                'reseller_order_id' => $order->id,
                'provider' => $data['payment_method'],
                'provider_reference' => $reference,
                'amount_minor' => $order->total_minor,
                'currency_code' => $order->currency_code,
                'type' => 'payment',
                'status' => 'pending',
            ]);

            return $order;
        });

        return response()->json([
            'order_id' => $order->id,
            'order_reference' => $order->order_reference,
        ]);
    }

    public function track(OnlineStore $store, Request $request): View
    {
        $store = $this->store($store);
        $reference = strtoupper(trim($request->string('reference')->toString()));
        $order = $reference === '' ? null : ResellerOrder::query()
            ->with('items')
            ->where('tenant_id', $store->tenant_id)
            ->where('order_reference', $reference)
            ->first();

        return view('reseller::storefront.track', compact('store', 'reference', 'order'));
    }

    public function sitemap(OnlineStore $store): Response
    {
        $store = $this->store($store);
        $urls = collect([['loc' => StorefrontUrl::route($store), 'lastmod' => $store->updated_at]]);
        $this->availableProducts($store)->get()->each(fn (ResellerProduct $product) => $urls->push([
            'loc' => StorefrontUrl::route($store, 'products.show', ['productSlug' => $product->id]),
            'lastmod' => $product->updated_at,
        ]));

        return response()->view('storefront::sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function store(OnlineStore $store): OnlineStore
    {
        $store->loadMissing('tenant');
        abort_unless($store->is_active && $store->tenant?->isReseller(), 404);
        $store->setRelation('categories', collect());
        $store->setRelation('productCollections', collect());
        $store->payment_methods = $this->paymentMethods($store);

        return $store;
    }

    private function settings(OnlineStore $store): ResellerSetting
    {
        return ResellerSetting::query()->firstOrCreate(['tenant_id' => $store->tenant_id]);
    }

    private function availableProducts(OnlineStore $store)
    {
        return ResellerProduct::query()
            ->where('tenant_id', $store->tenant_id)
            ->where('is_visible', true)
            ->where('is_excluded', false)
            ->where('availability', ProductAvailability::InStock->value);
    }

    /** @return list<string> */
    private function paymentMethods(OnlineStore $store): array
    {
        $methods = collect((array) $store->getRawOriginal('payment_methods') ? json_decode((string) $store->getRawOriginal('payment_methods'), true) : $store->payment_methods)
            ->filter(fn (mixed $method): bool => is_string($method) && ! in_array($method, ['storeboot_paystack', 'self_hosted_paystack'], true))
            ->values()
            ->all();

        return $methods === [] ? ['place_order'] : $methods;
    }

    private function shippingMinor(OnlineStore $store, string $selected): int
    {
        $option = collect((array) $store->shipping_options)->first(fn (array $row): bool => (string) ($row['location'] ?? '') === $selected);

        if (! $option && $selected !== 'default') {
            throw ValidationException::withMessages(['shipping_option' => 'Select a valid shipping option.']);
        }

        return (int) round((float) ($option['price'] ?? 0) * 100);
    }

    private function nextReference(string $tenantId): string
    {
        do {
            $reference = 'RS-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (ResellerOrder::query()->where('tenant_id', $tenantId)->where('order_reference', $reference)->exists());

        return $reference;
    }
}
