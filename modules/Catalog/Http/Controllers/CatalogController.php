<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Catalog\Actions\CreateCategoryAction;
use Modules\Catalog\Actions\GenerateProductContentAction;
use Modules\Catalog\Actions\GenerateProductImageAction;
use Modules\Catalog\Actions\ImportProductsFromImagesAction;
use Modules\Catalog\Actions\ImportProductsFromSheetAction;
use Modules\Catalog\Actions\SaveProductAction;
use Modules\Catalog\Actions\SaveProductAttributeAction;
use Modules\Catalog\Actions\SaveProductCollectionAction;
use Modules\Catalog\Actions\UpdateProductStatusAction;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Http\Requests\ProductAttributeRequest;
use Modules\Catalog\Http\Requests\ProductBadgeRequest;
use Modules\Catalog\Http\Requests\ProductCategoryRequest;
use Modules\Catalog\Http\Requests\ProductCollectionRequest;
use Modules\Catalog\Http\Requests\ProductCustomDefinitionRequest;
use Modules\Catalog\Http\Requests\ProductRequest;
use Modules\Catalog\Http\Requests\ProductStatusRequest;
use Modules\Catalog\Http\Requests\ProductTagRequest;
use Modules\Catalog\Http\Requests\ProductTaxRequest;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductAttributeDefinition;
use Modules\Catalog\Models\ProductBadge;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductCollection;
use Modules\Catalog\Models\ProductCustomDefinition;
use Modules\Catalog\Models\ProductTag;
use Modules\Catalog\Models\ProductTax;
use Modules\Catalog\Models\ProductVariant;
use Modules\Sales\Enums\DiscountType;
use Modules\Sales\Models\SalesCoupon;
use Modules\Tenancy\Models\Tenant;

final class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $user, $tenants);

        abort_if(! $tenant, 403);

        $productQuery = fn (ProductType $type) => Product::query()
            ->with([
                'badges',
                'category',
                'collections',
                'images',
                'options.values',
                'tags',
                'taxes',
                'attributeValues.definition',
                'variants' => fn ($query) => $query->with('optionValues.option')->oldest('id'),
            ])
            ->where('tenant_id', $tenant->id)
            ->where('product_type', $type->value)
            ->orderByDesc('id');

        $productItems = $productQuery(ProductType::Product)
            ->paginate(20, ['*'], 'products_page')
            ->withQueryString()
            ->fragment('products');
        $serviceItems = $productQuery(ProductType::Service)
            ->paginate(20, ['*'], 'services_page')
            ->withQueryString()
            ->fragment('services');
        $customProducts = $productQuery(ProductType::Product)->get();
        $customDefinitions = ProductCustomDefinition::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $visibleProducts = $productItems->getCollection()
            ->merge($serviceItems->getCollection())
            ->merge($customProducts)
            ->unique('id')
            ->values();

        $categories = ProductCategory::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();
        $productCollections = ProductCollection::query()
            ->withCount('products')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return view('catalog::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'products' => $visibleProducts,
            'productItems' => $productItems,
            'serviceItems' => $serviceItems,
            'customProducts' => $customProducts,
            'customDefinitions' => $customDefinitions,
            'categories' => $categories,
            'productBadges' => ProductBadge::query()->withCount('products')->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'productCollections' => $productCollections,
            'tags' => ProductTag::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'taxes' => ProductTax::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'attributes' => ProductAttributeDefinition::query()->with('values')->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'coupons' => SalesCoupon::query()->where('tenant_id', $tenant->id)->latest()->get(),
            'productCategories' => $categories->where('category_type', CategoryType::Product),
            'serviceCategories' => $categories->where('category_type', CategoryType::Service),
            'categoryTypes' => CategoryType::options(),
            'discountTypes' => DiscountType::cases(),
            'productTypes' => ProductType::options(),
            'productStatuses' => ProductStatus::cases(),
            'taxBehaviors' => TaxBehavior::options(),
            'stats' => [
                'products' => Product::query()->where('tenant_id', $tenant->id)->where('product_type', ProductType::Product->value)->count(),
                'services' => Product::query()->where('tenant_id', $tenant->id)->where('product_type', ProductType::Service->value)->count(),
                'categories' => $categories->count(),
                'collections' => $productCollections->count(),
                'badges' => ProductBadge::query()->where('tenant_id', $tenant->id)->count(),
                'tags' => ProductTag::query()->where('tenant_id', $tenant->id)->count(),
                'taxes' => ProductTax::query()->where('tenant_id', $tenant->id)->count(),
                'attributes' => ProductAttributeDefinition::query()->where('tenant_id', $tenant->id)->count(),
                'custom_fields' => $customDefinitions->count(),
                'variants' => ProductVariant::query()->where('tenant_id', $tenant->id)->count(),
            ],
        ]);
    }

    public function storeProduct(ProductRequest $request, SaveProductAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $product = $action->execute($request->validated());

        return redirect()
            ->route('admin.catalog.index', ['tenant' => $product->tenant_id])
            ->with('status', "{$product->name} saved.");
    }

    public function storeCustomDefinition(ProductCustomDefinitionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        ProductCustomDefinition::query()->create([
            'tenant_id' => $data['tenant_id'],
            'name' => $data['name'],
            'values' => collect(explode(',', $data['values']))->map(fn (string $value): string => trim($value))->filter()->values()->all(),
            'is_customer_selectable' => (bool) $data['is_customer_selectable'],
        ]);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $data['tenant_id']]).'#tags-attributes')
            ->with('catalog_accordion', 'custom')
            ->with('status', "Custom key {$data['name']} created.");
    }

    public function importProductsFromImages(Request $request, ImportProductsFromImagesAction $action): RedirectResponse
    {
        $tenantId = $request->string('tenant_id')->toString();
        $this->authorizeTenantIdAccess($request->user(), $tenantId);

        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'images' => ['required', 'array', 'min:1', 'max:40'],
            'images.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
        ]);

        $tenant = Tenant::query()->findOrFail($tenantId);
        $result = $action->execute($data['images'], $tenantId, $tenant->currency_code ?? 'NGN');

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tenantId]).'#products')
            ->with('status', $result['count'].' draft product(s) imported from photos. Set prices and publish them when ready.');
    }

    public function importProductsFromSheet(Request $request, ImportProductsFromSheetAction $action): RedirectResponse
    {
        $tenantId = $request->string('tenant_id')->toString();
        $this->authorizeTenantIdAccess($request->user(), $tenantId);

        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            // Validate by extension: .xlsx is a ZIP, so PHP's MIME guesser mislabels it.
            'sheet' => ['required', 'file', 'max:20480'],
        ]);

        $extension = strtolower((string) $data['sheet']->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'tsv', 'txt', 'xlsx'], true)) {
            return back()->withErrors(['sheet' => 'Upload a CSV or Excel (.xlsx) file.']);
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $result = $action->execute($data['sheet'], $tenantId, $tenant->currency_code ?? 'NGN');

        if ($result['count'] === 0) {
            return redirect()
                ->to(route('admin.catalog.index', ['tenant' => $tenantId]).'#products')
                ->with('status', "We couldn't read any products from that file. Check that it has product rows and try again.");
        }

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tenantId]).'#products')
            ->with('status', $result['count'].' draft product(s) imported and cleaned up from your file. Review, set prices, and publish them when ready.');
    }

    public function generateProductImage(Request $request, Product $product, GenerateProductImageAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $product->tenant_id);

        try {
            $action->execute($product);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['product_image' => $exception->getMessage()]);
        }

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $product->tenant_id]).'#products')
            ->with('status', "AI image generated for {$product->name}.");
    }

    public function generateProductContent(Request $request, GenerateProductContentAction $action): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'field' => ['required', Rule::in(['description', 'specifications'])],
            'prompt' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:180'],
            'brand' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'specifications' => ['nullable', 'string', 'max:8000'],
        ]);
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $tenant = Tenant::query()->findOrFail($data['tenant_id']);

        try {
            return response()->json($action->execute(
                $data['field'],
                (string) ($data['prompt'] ?? ''),
                $data,
                $tenant,
            ));
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function updateProduct(ProductRequest $request, Product $product, SaveProductAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $product->tenant_id);
        abort_unless($request->string('tenant_id')->toString() === $product->tenant_id, 403);

        $updatedProduct = $action->execute($request->validated(), $product);

        return redirect()
            ->route('admin.catalog.index', ['tenant' => $updatedProduct->tenant_id])
            ->with('status', "{$updatedProduct->name} updated.");
    }

    public function updateProductStatus(
        ProductStatusRequest $request,
        Product $product,
        UpdateProductStatusAction $action,
    ): RedirectResponse {
        $this->authorizeTenantIdAccess($request->user(), $product->tenant_id);
        abort_unless($request->string('tenant_id')->toString() === $product->tenant_id, 403);

        $status = ProductStatus::from($request->string('status')->toString());
        $updatedProduct = $action->execute($product, $status);
        $fragment = $updatedProduct->product_type === ProductType::Service ? 'services' : 'products';

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $updatedProduct->tenant_id]).'#'.$fragment)
            ->with('status', "{$updatedProduct->name} is now {$status->label()}.");
    }

    public function destroyProduct(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $product->tenant_id);

        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
        ]);
        abort_unless($data['tenant_id'] === $product->tenant_id, 403);

        $tenantId = $product->tenant_id;
        $name = $product->name;
        $fragment = $product->product_type === ProductType::Service ? 'services' : 'products';

        $product->delete();

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tenantId]).'#'.$fragment)
            ->with('status', "{$name} deleted.");
    }

    public function storeCategory(ProductCategoryRequest $request, CreateCategoryAction $action): JsonResponse|RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $category = $action->execute($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'category_type' => $category->category_type->value,
                    'parent_id' => $category->parent_id,
                ],
                'message' => "Category {$category->name} created.",
            ], 201);
        }

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $category->tenant_id]).'#categories')
            ->with('status', "Category {$category->name} created.");
    }

    public function storeTag(ProductTagRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $tag = ProductTag::query()->create($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tag->tenant_id]).'#tags-attributes')
            ->with('catalog_accordion', 'tags')
            ->with('status', "Tag {$tag->name} created.");
    }

    public function updateTag(ProductTagRequest $request, ProductTag $tag): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $tag->tenant_id);
        abort_unless($data['tenant_id'] === $tag->tenant_id, 403);

        $tag->update($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tag->tenant_id]).'#tags-attributes')
            ->with('catalog_accordion', 'tags')
            ->with('status', "Tag {$tag->name} updated.");
    }

    public function storeBadge(ProductBadgeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $badge = ProductBadge::query()->create($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $badge->tenant_id]).'#badges-collections')
            ->with('catalog_accordion', 'badges')
            ->with('status', "Badge {$badge->name} created.");
    }

    public function updateBadge(ProductBadgeRequest $request, ProductBadge $badge): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $badge->tenant_id);
        abort_unless($data['tenant_id'] === $badge->tenant_id, 403);

        $badge->update($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $badge->tenant_id]).'#badges-collections')
            ->with('catalog_accordion', 'badges')
            ->with('status', "Badge {$badge->name} updated.");
    }

    public function storeProductCollection(
        ProductCollectionRequest $request,
        SaveProductCollectionAction $action,
    ): RedirectResponse {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $collection = $action->execute($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $collection->tenant_id]).'#badges-collections')
            ->with('catalog_accordion', 'collections')
            ->with('status', "Collection {$collection->name} created.");
    }

    public function updateProductCollection(
        ProductCollectionRequest $request,
        ProductCollection $collection,
        SaveProductCollectionAction $action,
    ): RedirectResponse {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $collection->tenant_id);
        abort_unless($data['tenant_id'] === $collection->tenant_id, 403);

        $updatedCollection = $action->execute($data, $collection);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $updatedCollection->tenant_id]).'#badges-collections')
            ->with('catalog_accordion', 'collections')
            ->with('status', "Collection {$updatedCollection->name} updated.");
    }

    public function storeTax(ProductTaxRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $tax = ProductTax::query()->create($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tax->tenant_id]).'#taxes-coupons')
            ->with('catalog_accordion', 'taxes')
            ->with('status', "Tax {$tax->name} created.");
    }

    public function updateTax(ProductTaxRequest $request, ProductTax $tax): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $tax->tenant_id);
        abort_unless($data['tenant_id'] === $tax->tenant_id, 403);

        $tax->update($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tax->tenant_id]).'#taxes-coupons')
            ->with('catalog_accordion', 'taxes')
            ->with('status', "Tax {$tax->name} updated.");
    }

    public function destroyTax(Request $request, ProductTax $tax): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $tax->tenant_id);
        $tenantId = $tax->tenant_id;
        $name = $tax->name;

        $tax->delete();

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tenantId]).'#taxes-coupons')
            ->with('catalog_accordion', 'taxes')
            ->with('status', "Tax {$name} deleted.");
    }

    public function storeAttribute(ProductAttributeRequest $request, SaveProductAttributeAction $action): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $attribute = $action->execute($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $attribute->tenant_id]).'#tags-attributes')
            ->with('catalog_accordion', 'attributes')
            ->with('status', "Attribute {$attribute->name} created.");
    }

    public function updateAttribute(ProductAttributeRequest $request, ProductAttributeDefinition $attribute, SaveProductAttributeAction $action): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $attribute->tenant_id);
        abort_unless($data['tenant_id'] === $attribute->tenant_id, 403);

        $updatedAttribute = $action->execute($data, $attribute);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $updatedAttribute->tenant_id]).'#tags-attributes')
            ->with('catalog_accordion', 'attributes')
            ->with('status', "Attribute {$updatedAttribute->name} updated.");
    }

    /**
     * @return EloquentCollection<int, Tenant>
     */
    private function visibleTenantsFor(User $user): EloquentCollection
    {
        if ($user->is_platform_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Tenant>  $visibleTenants
     */
    private function resolveTenant(Request $request, User $user, EloquentCollection $visibleTenants): ?Tenant
    {
        $tenantId = $request->string('tenant')->toString();

        if ($tenantId !== '') {
            abort_unless($visibleTenants->contains('id', $tenantId), 403);

            return Tenant::query()->find($tenantId);
        }

        return $visibleTenants->first();
    }

    private function authorizeTenantIdAccess(?User $user, string $tenantId): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->is_platform_admin) {
            return;
        }

        abort_unless(
            TenantMembership::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('status', MembershipStatus::Active->value)
                ->exists(),
            403,
        );
    }
}
