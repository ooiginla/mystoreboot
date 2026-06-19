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
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Enums\FollowUpStatus;
use Modules\Customers\Enums\TicketPriority;
use Modules\Customers\Enums\TicketStatus;
use Modules\Customers\Enums\TicketType;
use Modules\Customers\Http\Requests\CustomerFollowUpRequest;
use Modules\Customers\Http\Requests\CustomerGroupRequest;
use Modules\Customers\Http\Requests\CustomerPurchaseRequest;
use Modules\Customers\Http\Requests\CustomerRequest;
use Modules\Customers\Http\Requests\SupportTicketRequest;
use Modules\Customers\Http\Requests\SupportTicketResponseRequest;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerFollowUp;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Models\CustomerPurchase;
use Modules\Customers\Models\SupportTicket;
use Modules\Tenancy\Models\Tenant;

final class CustomerRelationshipController extends Controller
{
    public function index(Request $request): View
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

        $customers = Customer::query()
            ->with(['group', 'purchases', 'followUps', 'tickets'])
            ->where('tenant_id', $tenant->id)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('phone', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($groupId !== '', fn ($query) => $query->where('customer_group_id', $groupId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->get();

        $allCustomers = Customer::query()
            ->with(['group', 'purchases', 'followUps', 'tickets'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->get();
        $groups = CustomerGroup::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $followUps = CustomerFollowUp::query()->with('customer')->where('tenant_id', $tenant->id)->where('status', FollowUpStatus::Pending->value)->oldest('due_date')->get();
        $ticketQuery = SupportTicket::query()->with(['customer', 'assignee', 'responses.user'])->where('tenant_id', $tenant->id);
        $allTickets = (clone $ticketQuery)->latest()->get();
        $tickets = $ticketQuery
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
            'followUps' => $followUps,
            'tickets' => $tickets,
            'allTickets' => $allTickets,
            'purchases' => $purchases,
            'users' => User::query()->orderBy('name')->get(),
            'search' => $search,
            'filters' => ['group_id' => $groupId, 'status' => $status, 'ticket_search' => $ticketSearch],
            'ticketCategories' => ['General enquiry', 'Product issue', 'Service request', 'Billing', 'Delivery', 'Return/refund', 'Technical support', 'Internal operations'],
            'followUpChannels' => ['Phone call', 'WhatsApp', 'SMS', 'Email', 'In-person visit', 'Social media'],
            'customerStatuses' => CustomerStatus::cases(),
            'followUpStatuses' => FollowUpStatus::cases(),
            'ticketTypes' => TicketType::cases(),
            'ticketPriorities' => TicketPriority::cases(),
            'ticketStatuses' => TicketStatus::cases(),
            'insights' => $this->insights($allCustomers, $followUps),
            'stats' => [
                'customers' => $allCustomers->count(),
                'top_customers' => $allCustomers->filter(fn (Customer $customer): bool => $customer->purchases->sum('amount_minor') > 0)->count(),
                'open_tickets' => $allTickets->whereIn('status.value', ['open', 'in_progress'])->count(),
                'due_follow_ups' => $followUps->filter(fn (CustomerFollowUp $followUp): bool => $followUp->due_date->lte(now()))->count(),
            ],
        ]);
    }

    public function storeCustomer(CustomerRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $customer = Customer::query()->create($this->customerData($request->validated()));

        return redirect()->to(route('admin.customers.index', ['tenant' => $customer->tenant_id]).'#customers')->with('status', "Customer {$customer->name} saved.");
    }

    public function updateCustomer(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $customer->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $customer->tenant_id, 403);
        $customer->update($this->customerData($data));

        return redirect()->to(route('admin.customers.index', ['tenant' => $customer->tenant_id]).'#customers')->with('status', "Customer {$customer->name} updated.");
    }

    public function storeGroup(CustomerGroupRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $group = CustomerGroup::query()->create($request->validated());

        return redirect()->to(route('admin.customers.index', ['tenant' => $group->tenant_id]).'#groups')->with('status', "Customer group {$group->name} created.");
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
        return collect($data)->except('account_balance')->all() + [
            'account_balance_minor' => $this->moneyToMinor($data['account_balance'] ?? 0),
        ];
    }

    private function generateTicketNumber(string $tenantId): string
    {
        return 'TCK-'.now()->format('Ymd').'-'.str_pad((string) (SupportTicket::query()->where('tenant_id', $tenantId)->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
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
