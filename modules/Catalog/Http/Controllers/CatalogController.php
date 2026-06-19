<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Catalog\Actions\CreateCategoryAction;
use Modules\Catalog\Actions\SaveProductAttributeAction;
use Modules\Catalog\Actions\SaveProductAction;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Http\Requests\ProductAttributeRequest;
use Modules\Catalog\Http\Requests\ProductCategoryRequest;
use Modules\Catalog\Http\Requests\ProductRequest;
use Modules\Catalog\Http\Requests\ProductTagRequest;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductAttributeDefinition;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductTag;
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

        $products = Product::query()
            ->with([
                'category',
                'options.values',
                'tags',
                'attributeValues.definition',
                'variants' => fn ($query) => $query->with('optionValues.option')->oldest('id'),
            ])
            ->where('tenant_id', $tenant->id)
            ->when($request->filled('type'), fn ($query) => $query->where('product_type', $request->string('type')->toString()))
            ->latest()
            ->get();

        $categories = ProductCategory::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return view('catalog::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'products' => $products,
            'categories' => $categories,
            'tags' => ProductTag::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'attributes' => ProductAttributeDefinition::query()->with('values')->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'productCategories' => $categories->where('category_type', CategoryType::Product),
            'serviceCategories' => $categories->where('category_type', CategoryType::Service),
            'categoryTypes' => CategoryType::options(),
            'productTypes' => ProductType::options(),
            'productStatuses' => ProductStatus::cases(),
            'taxBehaviors' => TaxBehavior::options(),
            'stats' => [
                'products' => Product::query()->where('tenant_id', $tenant->id)->where('product_type', ProductType::Product->value)->count(),
                'services' => Product::query()->where('tenant_id', $tenant->id)->where('product_type', ProductType::Service->value)->count(),
                'categories' => $categories->count(),
                'tags' => ProductTag::query()->where('tenant_id', $tenant->id)->count(),
                'attributes' => ProductAttributeDefinition::query()->where('tenant_id', $tenant->id)->count(),
                'variants' => $products->sum(fn (Product $product): int => $product->variants->count()),
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

    public function updateProduct(ProductRequest $request, Product $product, SaveProductAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $product->tenant_id);
        abort_unless($request->string('tenant_id')->toString() === $product->tenant_id, 403);

        $updatedProduct = $action->execute($request->validated(), $product);

        return redirect()
            ->route('admin.catalog.index', ['tenant' => $updatedProduct->tenant_id])
            ->with('status', "{$updatedProduct->name} updated.");
    }

    public function storeCategory(ProductCategoryRequest $request, CreateCategoryAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $category = $action->execute($request->validated());

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
            ->to(route('admin.catalog.index', ['tenant' => $tag->tenant_id]).'#tags')
            ->with('status', "Tag {$tag->name} created.");
    }

    public function updateTag(ProductTagRequest $request, ProductTag $tag): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $tag->tenant_id);
        abort_unless($data['tenant_id'] === $tag->tenant_id, 403);

        $tag->update($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $tag->tenant_id]).'#tags')
            ->with('status', "Tag {$tag->name} updated.");
    }

    public function storeAttribute(ProductAttributeRequest $request, SaveProductAttributeAction $action): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $data['tenant_id']);

        $attribute = $action->execute($data);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $attribute->tenant_id]).'#attributes')
            ->with('status', "Attribute {$attribute->name} created.");
    }

    public function updateAttribute(ProductAttributeRequest $request, ProductAttributeDefinition $attribute, SaveProductAttributeAction $action): RedirectResponse
    {
        $data = $request->validated();
        $this->authorizeTenantIdAccess($request->user(), $attribute->tenant_id);
        abort_unless($data['tenant_id'] === $attribute->tenant_id, 403);

        $updatedAttribute = $action->execute($data, $attribute);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $updatedAttribute->tenant_id]).'#attributes')
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
