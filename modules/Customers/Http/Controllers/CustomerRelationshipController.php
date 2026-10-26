<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Actions\AdjustCustomerBalanceAction;
use Modules\Customers\Enums\CustomerCreditPolicy;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Enums\FollowUpStatus;
use Modules\Customers\Enums\TicketPriority;
use Modules\Customers\Enums\TicketStatus;
use Modules\Customers\Enums\TicketType;
use Modules\Customers\Http\Requests\CustomerBalanceAdjustmentRequest;
use Modules\Customers\Http\Requests\CustomerFollowUpRequest;
use Modules\Customers\Http\Requests\CustomerGroupRequest;
use Modules\Customers\Http\Requests\CustomerPriceListItemRequest;
use Modules\Customers\Http\Requests\CustomerPriceListRequest;
use Modules\Customers\Http\Requests\CustomerPurchaseRequest;
use Modules\Customers\Http\Requests\CustomerRequest;
use Modules\Customers\Http\Requests\CustomerSpecificPriceRequest;
use Modules\Customers\Http\Requests\SupportTicketRequest;
use Modules\Customers\Http\Requests\SupportTicketResponseRequest;
use Modules\Customers\Http\Requests\SupportTicketStatusRequest;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerFollowUp;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Models\CustomerPriceList;
use Modules\Customers\Models\CustomerPriceListItem;
use Modules\Customers\Models\CustomerPurchase;
use Modules\Customers\Models\CustomerSpecificPrice;
use Modules\Customers\Models\SupportTicket;
use Modules\Customers\Support\CustomerCreditService;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Models\Tenant;

final class CustomerRelationshipController extends Controller
{
    public function index(Request $request, CustomerCreditService $credit): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $search = trim($request->string('search')->toString());
        $groupId = $request->string('group_id')->toString();
        $status = $request->string('status')->toString();
        $ticketSearch = trim($request->string('ticket_search')->toString());
        $ticketCategories = ['General enquiry', 'Product issue', 'Service request', 'Billing', 'Delivery', 'Return/refund', 'Technical support', 'Internal operations'];
        $requestedTicketStatus = $request->string('ticket_status')->toString();
        $ticketStatus = in_array($requestedTicketStatus, TicketStatus::values(), true) ? $requestedTicketStatus : '';
        $requestedTicketCategory = $request->string('ticket_category')->toString();
        $ticketCategory = in_array($requestedTicketCategory, $ticketCategories, true) ? $requestedTicketCategory : '';
        $pricingCustomerId = $request->string('pricing_customer_id')->toString();
        $pricingVariantId = $request->string('pricing_variant_id')->toString();
        $perPage = in_array($request->integer('per_page'), [10, 20, 50, 100], true)
            ? $request->integer('per_page')
            : 10;

        $customers = Customer::query()
            ->with(['group.priceList', 'priceList'])
            ->where('tenant_id', $tenant->id)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('phone', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($groupId !== '', fn ($query) => $query->where('customer_group_id', $groupId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'customers_page')
            ->withQueryString()
            ->fragment('customers');
        $allCustomers = Customer::query()
            ->with(['group.priceList', 'priceList', 'purchases'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->get();
        $customers->getCollection()->each(fn (Customer $customer) => $customer->setAttribute('credit_profile', $credit->profile($customer)));

        $groups = CustomerGroup::query()->with('priceList')->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $priceLists = CustomerPriceList::query()
            ->withCount(['items', 'groups', 'customers'])
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();
        $specificPrices = CustomerSpecificPrice::query()
            ->with(['customer', 'variant.product'])
            ->where('tenant_id', $tenant->id)
            ->when($pricingCustomerId !== '', fn ($query) => $query->where('customer_id', $pricingCustomerId))
            ->when($pricingVariantId !== '', fn ($query) => $query->where('product_variant_id', $pricingVariantId))
            ->latest()
            ->get();
        $pricingVariants = $this->pricingVariants($tenant);
        $followUps = CustomerFollowUp::query()->with('customer')->where('tenant_id', $tenant->id)->where('status', FollowUpStatus::Pending->value)->oldest('due_date')->get();
        $ticketQuery = SupportTicket::query()->with(['customer', 'assignee', 'responses.user'])->where('tenant_id', $tenant->id);
        $allTickets = (clone $ticketQuery)->latest()->get();
        $tickets = $ticketQuery
            ->when($ticketStatus !== '', fn ($query) => $query->where('status', $ticketStatus))
            ->when($ticketCategory !== '', fn ($query) => $query->where('category', $ticketCategory))
            ->when($ticketSearch !== '', fn ($query) => $query->where(function ($query) use ($ticketSearch): void {
                $query->where('ticket_number', 'like', "%{$ticketSearch}%")
                    ->orWhere('subject', 'like', "%{$ticketSearch}%")
                    ->orWhereHas('customer', fn ($query) => $query
                        ->where('first_name', 'like', "%{$ticketSearch}%")
                        ->orWhere('last_name', 'like', "%{$ticketSearch}%")
                        ->orWhere('phone', 'like', "%{$ticketSearch}%")
                        ->orWhere('email', 'like', "%{$ticketSearch}%"));
            }))
            ->latest()
            ->get();
        $purchases = CustomerPurchase::query()->with('customer')->where('tenant_id', $tenant->id)->latest('purchase_date')->limit(100)->get();

        return view('customers::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'customers' => $customers,
            'allCustomers' => $allCustomers,
            'groups' => $groups,
            'priceLists' => $priceLists,
            'specificPrices' => $specificPrices,
            'pricingVariants' => $pricingVariants,
            'creditPolicies' => CustomerCreditPolicy::cases(),
            'followUps' => $followUps,
            'tickets' => $tickets,
            'allTickets' => $allTickets,
            'purchases' => $purchases,
            'users' => User::query()
                ->whereHas('tenantMemberships', fn ($query) => $query
                    ->where('tenant_id', $tenant->id)
                    ->where('status', MembershipStatus::Active->value))
                ->orderBy('name')
                ->get(),
            'search' => $search,
            'filters' => [
                'group_id' => $groupId,
                'status' => $status,
                'ticket_search' => $ticketSearch,
                'ticket_status' => $ticketStatus,
                'ticket_category' => $ticketCategory,
                'pricing_customer_id' => $pricingCustomerId,
                'pricing_variant_id' => $pricingVariantId,
                'per_page' => $perPage,
            ],
            'ticketCategories' => $ticketCategories,
            'followUpChannels' => ['Phone call', 'WhatsApp', 'SMS', 'Email', 'In-person visit', 'Social media'],
            'customerStatuses' => CustomerStatus::cases(),
            'followUpStatuses' => FollowUpStatus::cases(),
            'ticketTypes' => TicketType::cases(),
            'ticketPriorities' => TicketPriority::cases(),
            'ticketStatuses' => TicketStatus::cases(),
            'insights' => $this->insights($allCustomers, $followUps),
            'stats' => [
                'customers' => $allCustomers->count(),
                'active_customers' => $allCustomers->filter(fn (Customer $customer): bool => $customer->status === CustomerStatus::Active)->count(),
                'top_customers' => $allCustomers->filter(fn (Customer $customer): bool => $customer->purchases->sum('amount_minor') > 0)->count(),
                'open_tickets' => $allTickets->whereIn('status.value', ['open', 'in_progress'])->count(),
                'due_follow_ups' => $followUps->filter(fn (CustomerFollowUp $followUp): bool => $followUp->due_date->lte(now()))->count(),
            ],
        ]);
    }

    public function showCustomer(
        Request $request,
        Customer $customer,
        CustomerCreditService $credit,
    ): View {
        $this->authorizeTenantIdAccess($request->user(), $customer->tenant_id);

        $tenant = Tenant::query()->findOrFail($customer->tenant_id);
        $customer->load([
            'group.priceList',
            'priceList',
            'specificPrices.variant.product',
            'purchases' => fn ($query) => $query->latest('purchase_date')->latest('id'),
            'followUps' => fn ($query) => $query->latest('due_date')->latest('id'),
            'tickets' => fn ($query) => $query->with('assignee')->latest(),
            'balanceAdjustments' => fn ($query) => $query->with('recordedBy')->latest('adjustment_date')->latest('id'),
        ]);

        $today = now($tenant->timezone ?: config('app.timezone'));
        $salesTotals = SalesOrder::query()
            ->selectRaw('COALESCE(SUM(total_minor - refunded_minor), 0) as sales_overall_minor')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN order_date >= ? THEN total_minor - refunded_minor ELSE 0 END), 0) as sales_current_year_minor',
                [$today->copy()->startOfYear()->toDateString()],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN order_date >= ? THEN total_minor - refunded_minor ELSE 0 END), 0) as sales_current_month_minor',
                [$today->copy()->startOfMonth()->toDateString()],
            )
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('order_status', '!=', SalesOrderStatus::Cancelled->value)
            ->whereDate('order_date', '<=', $today->toDateString())
            ->first();

        return view('customers::admin.show', [
            'tenant' => $tenant,
            'customer' => $customer,
            'creditProfile' => $credit->profile($customer),
            'salesTotals' => [
                'overall' => (int) ($salesTotals?->sales_overall_minor ?? 0),
                'year' => (int) ($salesTotals?->sales_current_year_minor ?? 0),
                'month' => (int) ($salesTotals?->sales_current_month_minor ?? 0),
            ],
            'groups' => CustomerGroup::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'priceLists' => CustomerPriceList::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'customerStatuses' => CustomerStatus::cases(),
            'creditPolicies' => CustomerCreditPolicy::cases(),
        ]);
    }

    public function storeCustomer(CustomerRequest $request): RedirectResponse
    {
        $tenantId = $request->string('tenant_id')->toString();
        $this->authorizeTenantIdAccess($request->user(), $tenantId);
        $customer = Customer::query()->create($this->authorisedCustomerData($request->validated(), $request->user(), $tenantId));

        return redirect()->to(route('admin.customers.index', ['tenant' => $customer->tenant_id]).'#customers')->with('status', "Customer {$customer->name} saved.");
    }

    public function updateCustomer(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $customer->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $customer->tenant_id, 403);
        $customer->update($this->authorisedCustomerData($data, $request->user(), $customer->tenant_id, true));

        $destination = $request->string('return_to')->toString() === 'customer'
            ? route('admin.customers.customers.show', ['customer' => $customer, 'tenant' => $customer->tenant_id])
            : route('admin.customers.index', ['tenant' => $customer->tenant_id]).'#customers';

        return redirect()->to($destination)->with('status', "Customer {$customer->name} updated.");
    }

    public function storeGroup(CustomerGroupRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $tenantId = $request->string('tenant_id')->toString();
        $group = CustomerGroup::query()->create($this->authorisedGroupData($request->validated(), $request->user(), $tenantId));

        return redirect()->to(route('admin.customers.index', ['tenant' => $group->tenant_id]).'#groups')->with('status', "Customer group {$group->name} created.");
    }

    public function updateGroup(CustomerGroupRequest $request, CustomerGroup $group): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $group->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $group->tenant_id, 403);
        $group->update($this->authorisedGroupData($data, $request->user(), $group->tenant_id, true));

        return redirect()->to(route('admin.customers.index', ['tenant' => $group->tenant_id]).'#groups')
            ->with('status', "Customer group {$group->name} updated.");
    }

    public function storePriceList(CustomerPriceListRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $priceList = CustomerPriceList::query()->create($request->validated());

        return redirect()->to(route('admin.customers.index', ['tenant' => $priceList->tenant_id]).'#price-lists')
            ->with('status', "Pricelist {$priceList->name} created.");
    }

    public function showPriceList(Request $request, CustomerPriceList $priceList): View
    {
        $this->authorizeTenantIdAccess($request->user(), $priceList->tenant_id);

        $tenant = Tenant::query()->findOrFail($priceList->tenant_id);
        $priceList->load([
            'items.variant.product',
            'groups' => fn ($query) => $query->orderBy('name'),
            'customers' => fn ($query) => $query->orderBy('first_name')->orderBy('last_name'),
        ])->loadCount(['items', 'groups', 'customers']);

        return view('customers::admin.price-list', [
            'tenant' => $tenant,
            'priceList' => $priceList,
            'pricingVariants' => $this->pricingVariants($tenant),
        ]);
    }

    public function updatePriceList(CustomerPriceListRequest $request, CustomerPriceList $priceList): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $priceList->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $priceList->tenant_id, 403);
        $priceList->update($data);

        $destination = $request->string('return_to')->toString() === 'price_list'
            ? route('admin.customers.price-lists.show', ['priceList' => $priceList, 'tenant' => $priceList->tenant_id])
            : route('admin.customers.index', ['tenant' => $priceList->tenant_id]).'#price-lists';

        return redirect()->to($destination)
            ->with('status', "Pricelist {$priceList->name} updated.");
    }

    public function storePriceListItem(CustomerPriceListItemRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $item = CustomerPriceListItem::query()->create([
            'tenant_id' => $data['tenant_id'],
            'customer_price_list_id' => $data['customer_price_list_id'],
            'product_variant_id' => $data['product_variant_id'],
            'minimum_quantity' => $data['minimum_quantity'],
            'price_type' => $data['price_type'],
            'unit_price_minor' => $data['price_type'] === 'fixed' ? $this->moneyToMinor($data['unit_price']) : 0,
            'discount_percent' => $data['price_type'] === 'percentage' ? $data['discount_percent'] : null,
        ]);

        return redirect()->route('admin.customers.price-lists.show', [
            'priceList' => $item->customer_price_list_id,
            'tenant' => $item->tenant_id,
        ])
            ->with('status', 'Price-list quantity tier saved.');
    }

    public function updatePriceListItem(
        CustomerPriceListItemRequest $request,
        CustomerPriceListItem $item,
    ): RedirectResponse {
        $this->authorizeTenantIdAccess($request->user(), $item->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $item->tenant_id, 403);

        $item->update([
            'customer_price_list_id' => $data['customer_price_list_id'],
            'product_variant_id' => $data['product_variant_id'],
            'minimum_quantity' => $data['minimum_quantity'],
            'price_type' => $data['price_type'],
            'unit_price_minor' => $data['price_type'] === 'fixed' ? $this->moneyToMinor($data['unit_price']) : 0,
            'discount_percent' => $data['price_type'] === 'percentage' ? $data['discount_percent'] : null,
        ]);

        return redirect()->route('admin.customers.price-lists.show', [
            'priceList' => $item->customer_price_list_id,
            'tenant' => $item->tenant_id,
        ])->with('status', 'Price-list product updated.');
    }

    public function destroyPriceListItem(Request $request, CustomerPriceListItem $item): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $item->tenant_id);
        $tenantId = $item->tenant_id;
        $priceListId = $item->customer_price_list_id;
        $item->delete();

        return redirect()->route('admin.customers.price-lists.show', [
            'priceList' => $priceListId,
            'tenant' => $tenantId,
        ])
            ->with('status', 'Price-list quantity tier removed.');
    }

    public function storeSpecificPrice(CustomerSpecificPriceRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $price = CustomerSpecificPrice::query()->create(collect($data)->except('unit_price')->all() + [
            'tenant_id' => $data['tenant_id'],
            'unit_price_minor' => $this->moneyToMinor($data['unit_price']),
        ]);

        return redirect()->to(route('admin.customers.index', ['tenant' => $price->tenant_id]).'#customer-pricing')
            ->with('status', 'Customer-specific price saved.');
    }

    public function updateSpecificPrice(
        CustomerSpecificPriceRequest $request,
        CustomerSpecificPrice $price,
    ): RedirectResponse {
        $this->authorizeTenantIdAccess($request->user(), $price->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $price->tenant_id, 403);

        $price->update(collect($data)->except(['tenant_id', 'unit_price'])->all() + [
            'unit_price_minor' => $this->moneyToMinor($data['unit_price']),
        ]);

        return redirect()->to(route('admin.customers.index', ['tenant' => $price->tenant_id]).'#customer-pricing')
            ->with('status', 'Customer-specific price updated.');
    }

    public function destroySpecificPrice(Request $request, CustomerSpecificPrice $price): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $price->tenant_id);
        $tenantId = $price->tenant_id;
        $price->delete();

        return redirect()->to(route('admin.customers.index', ['tenant' => $tenantId]).'#customer-pricing')
            ->with('status', 'Customer-specific price removed.');
    }

    public function adjustBalance(
        CustomerBalanceAdjustmentRequest $request,
        Customer $customer,
        AdjustCustomerBalanceAction $action,
    ): RedirectResponse {
        $this->authorizeTenantIdAccess($request->user(), $customer->tenant_id);
        abort_unless($request->string('tenant_id')->toString() === $customer->tenant_id, 403);
        $action->execute($customer, $request->validated(), $request->user());

        $destination = $request->string('return_to')->toString() === 'customer'
            ? route('admin.customers.customers.show', ['customer' => $customer, 'tenant' => $customer->tenant_id])
            : route('admin.customers.index', ['tenant' => $customer->tenant_id]).'#customers';

        return redirect()->to($destination)
            ->with('status', "{$customer->name}'s account balance adjusted.");
    }

    public function storePurchase(CustomerPurchaseRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $purchase = CustomerPurchase::query()->create(collect($data)->except('amount')->all() + [
            'amount_minor' => $this->moneyToMinor($data['amount']),
        ]);
        $purchase->customer->increment('loyalty_points', (int) ($data['loyalty_points_awarded'] ?? 0));
        $purchase->customer->update(['last_purchase_at' => $purchase->purchase_date]);

        return redirect()->to(route('admin.customers.index', ['tenant' => $purchase->tenant_id]).'#history')->with('status', 'Purchase history recorded.');
    }

    public function storeFollowUp(CustomerFollowUpRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $followUp = CustomerFollowUp::query()->create($request->validated());

        return redirect()->to(route('admin.customers.index', ['tenant' => $followUp->tenant_id]).'#follow-ups')->with('status', 'Follow-up scheduled.');
    }

    public function completeFollowUp(Request $request, CustomerFollowUp $followUp): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $followUp->tenant_id);
        $followUp->update([
            'status' => FollowUpStatus::Completed->value,
            'completed_at' => now(),
        ]);

        return redirect()->to(route('admin.customers.index', ['tenant' => $followUp->tenant_id]).'#follow-ups')->with('status', 'Follow-up completed.');
    }

    public function storeTicket(SupportTicketRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $ticket = SupportTicket::query()->create($data + [
            'ticket_number' => $this->generateTicketNumber($data['tenant_id']),
            'resolved_at' => in_array($data['status'], [TicketStatus::Resolved->value, TicketStatus::Closed->value], true) ? now() : null,
        ]);

        return redirect()->to(route('admin.customers.index', ['tenant' => $ticket->tenant_id]).'#tickets')->with('status', "Ticket {$ticket->ticket_number} created.");
    }

    public function updateTicket(SupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $ticket->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $ticket->tenant_id, 403);
        $ticket->update($data + [
            'resolved_at' => in_array($data['status'], [TicketStatus::Resolved->value, TicketStatus::Closed->value], true) ? ($ticket->resolved_at ?? now()) : null,
        ]);

        return redirect()->to(route('admin.customers.index', ['tenant' => $ticket->tenant_id]).'#tickets')->with('status', "Ticket {$ticket->ticket_number} updated.");
    }

    public function updateTicketStatus(SupportTicketStatusRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $ticket->tenant_id);
        $status = $request->enum('status', TicketStatus::class);

        $ticket->update([
            'status' => $status->value,
            'resolved_at' => in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)
                ? ($ticket->resolved_at ?? now())
                : null,
        ]);

        return redirect()->to(route('admin.customers.index', ['tenant' => $ticket->tenant_id]).'#tickets')
            ->with('status', "Ticket {$ticket->ticket_number} status changed to {$status->label()}.");
    }

    public function claimTicket(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $ticket->tenant_id);
        /** @var User $user */
        $user = $request->user();

        if ($ticket->assigned_to && $ticket->assigned_to !== $user->id) {
            return redirect()->to(route('admin.customers.index', ['tenant' => $ticket->tenant_id]).'#tickets')
                ->withErrors(['assigned_to' => 'This ticket is already assigned to another user.']);
        }

        SupportTicket::query()
            ->whereKey($ticket->id)
            ->where('tenant_id', $ticket->tenant_id)
            ->whereNull('assigned_to')
            ->update(['assigned_to' => $user->id]);

        $ticket->refresh();

        if ($ticket->assigned_to !== $user->id) {
            return redirect()->to(route('admin.customers.index', ['tenant' => $ticket->tenant_id]).'#tickets')
                ->withErrors(['assigned_to' => 'This ticket was claimed by another user.']);
        }

        return redirect()->to(route('admin.customers.index', ['tenant' => $ticket->tenant_id]).'#tickets')
            ->with('status', "Ticket {$ticket->ticket_number} assigned to you.");
    }

    public function storeTicketResponse(SupportTicketResponseRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $ticket->tenant_id);
        $ticket->responses()->create($request->validated() + [
            'tenant_id' => $ticket->tenant_id,
            'user_id' => $request->user()?->id,
            'is_internal' => $request->boolean('is_internal'),
        ]);

        return redirect()->to(route('admin.customers.index', ['tenant' => $ticket->tenant_id]).'#tickets')->with('status', 'Ticket response added.');
    }

    /**
     * @param  EloquentCollection<int, Customer>  $customers
     * @param  EloquentCollection<int, CustomerFollowUp>  $followUps
     * @return array<string, mixed>
     */
    private function insights(EloquentCollection $customers, EloquentCollection $followUps): array
    {
        $topCustomers = $customers
            ->sortByDesc(fn (Customer $customer): int => $customer->purchases->sum('amount_minor'))
            ->take(5)
            ->values();
        $inactiveCustomers = $customers
            ->filter(fn (Customer $customer): bool => ! $customer->last_purchase_at || $customer->last_purchase_at->lt(now()->subDays(60)))
            ->take(8)
            ->values();
        $followUpRecommendations = $followUps
            ->filter(fn (CustomerFollowUp $followUp): bool => $followUp->due_date->lte(now()->addDays(7)))
            ->take(8)
            ->values();

        return [
            'top_customers' => $topCustomers,
            'inactive_customers' => $inactiveCustomers,
            'follow_up_recommendations' => $followUpRecommendations,
            'targeted_offers' => $inactiveCustomers->map(fn (Customer $customer): array => [
                'customer' => $customer,
                'offer' => $customer->purchases->isNotEmpty()
                    ? 'Send a win-back offer based on '.$customer->purchases->sortByDesc('purchase_date')->first()->product_summary
                    : 'Send a welcome discount and collect first purchase preference.',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function customerData(array $data): array
    {
        return collect($data)->except('credit_limit')->all() + [
            'credit_limit_minor' => filled($data['credit_limit'] ?? null)
                ? $this->moneyToMinor($data['credit_limit'])
                : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function authorisedCustomerData(array $data, User $user, string $tenantId, bool $updating = false): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $result = $this->customerData($data);

        if (! $user->is_platform_admin && ! $user->hasPermission($tenant, 'customers.pricing.manage')) {
            unset($result['customer_price_list_id']);
        }
        if (! $user->is_platform_admin && ! $user->hasPermission($tenant, 'customers.credit.manage')) {
            unset($result['credit_policy'], $result['credit_limit_minor'], $result['credit_terms_days'], $result['credit_hold']);
        }

        if (! $updating) {
            $result['credit_policy'] ??= CustomerCreditPolicy::Inherit->value;
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    private function groupData(array $data): array
    {
        return collect($data)->except('credit_limit')->all() + [
            'credit_limit_minor' => filled($data['credit_limit'] ?? null)
                ? $this->moneyToMinor($data['credit_limit'])
                : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function authorisedGroupData(array $data, User $user, string $tenantId, bool $updating = false): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $result = $this->groupData($data);

        if (! $user->is_platform_admin && ! $user->hasPermission($tenant, 'customers.pricing.manage')) {
            unset($result['customer_price_list_id']);
        }
        if (! $user->is_platform_admin && ! $user->hasPermission($tenant, 'customers.credit.manage')) {
            unset($result['credit_enabled'], $result['credit_limit_minor'], $result['credit_terms_days']);
        }

        if (! $updating) {
            $result['credit_enabled'] ??= false;
        }

        return $result;
    }

    private function generateTicketNumber(string $tenantId): string
    {
        return 'TCK-'.now()->format('Ymd').'-'.str_pad((string) (SupportTicket::query()->where('tenant_id', $tenantId)->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }

    private function pricingVariants(Tenant $tenant): EloquentCollection
    {
        return ProductVariant::query()
            ->with('product')
            ->where('tenant_id', $tenant->id)
            ->where('status', ProductStatus::Active->value)
            ->whereHas('product', fn ($query) => $query
                ->whereIn('product_type', array_map(fn (ProductType $type): string => $type->value, ProductType::sellable()))
                ->where('status', ProductStatus::Active->value))
            ->get()
            ->sortBy(fn (ProductVariant $variant): string => strtolower(($variant->product?->name ?? '').' '.$variant->variant_name))
            ->values();
    }

    private function visibleTenantsFor(User $user): EloquentCollection
    {
        if ($user->is_platform_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))->orderBy('name')->get();
    }

    private function resolveTenant(Request $request, EloquentCollection $visibleTenants): ?Tenant
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

        abort_unless(TenantMembership::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('status', MembershipStatus::Active->value)->exists(), 403);
    }
}
