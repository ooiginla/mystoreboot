<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Enums\TicketPriority;
use Modules\Customers\Enums\TicketStatus;
use Modules\Customers\Enums\TicketType;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\SupportTicket;

final class StorefrontController extends Controller
{
    public function home(OnlineStore $store, Request $request): View
    {
        $store = $this->preparedStore($store);

        $products = $this->productsFor($store, ProductType::Product)
            ->when($request->filled('category'), function ($query) use ($request): void {
                $query->whereHas('category', fn ($category) => $category->where('slug', $request->string('category')->toString()));
            })
            ->latest()
            ->paginate(16)
            ->withQueryString();

        return view('storefront::home', [
            'store' => $store,
            'products' => $products,
            'selectedCategory' => $request->string('category')->toString(),
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

    private function preparedStore(OnlineStore $store): OnlineStore
    {
        abort_unless($store->is_active, 404);

        return $store->loadMissing(['tenant', 'categories.children', 'fulfilmentBranch']);
    }

    private function productsFor(OnlineStore $store, ProductType $type)
    {
        $categoryIds = $store->categories->pluck('id');

        return Product::query()
            ->with(['category', 'variants.optionValues.option', 'tags'])
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

        $product->load(['category', 'variants.optionValues.option', 'tags', 'attributeValues.definition']);

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
}
