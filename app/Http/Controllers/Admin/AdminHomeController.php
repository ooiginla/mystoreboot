<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveBranchManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Support\PermissionService;

/**
 * The /admin landing. Sends each user to the first area their role can access,
 * so no one lands on a page they are denied (e.g. a cashier goes straight to POS).
 */
final class AdminHomeController extends Controller
{
    /**
     * Ordered destinations: permission slug => route name.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const DESTINATIONS = [
        ['dashboard.view', 'admin.analytics.index'],
        ['sales.create', 'admin.sales.retail-pos'],
        ['sales.view', 'admin.sales.orders.index'],
        ['inventory.view', 'admin.inventory.index'],
        ['catalog.view', 'admin.catalog.index'],
        ['customers.view', 'admin.customers.index'],
        ['procurement.view', 'admin.procurement.index'],
        ['finance.reports.view', 'admin.finance.index'],
        ['finance.view', 'admin.finance.expenses'],
        ['hr.staff.view', 'admin.hr-payroll.index'],
        ['settlements.view', 'admin.sales.settlements.index'],
        ['business.settings.manage', 'admin.business.index'],
        ['branches.manage', 'admin.business.index'],
        ['users.manage', 'admin.business.index'],
        ['roles.manage', 'admin.business.index'],
    ];

    public function index(Request $request): RedirectResponse|View
    {
        /** @var User $user */
        $user = $request->user();

        $tenantParam = $request->query('tenant');
        $suffix = is_string($tenantParam) && $tenantParam !== '' ? ['tenant' => $tenantParam] : [];

        $branchState = app(ActiveBranchManager::class)->stateForRequest($request, $user);

        // Do not send a user without branch context into a branch-scoped module.
        // The admin layout opens its mandatory branch picker on this safe page.
        if ($branchState['shouldPrompt']) {
            return view('admin.home');
        }

        if ($user->is_platform_admin) {
            return redirect()->route('admin.analytics.index', $suffix);
        }

        $tenant = $branchState['tenant'];

        if (! $tenant) {
            return redirect()->route('admin.business.index', $suffix);
        }

        $permissions = app(PermissionService::class);

        foreach (self::DESTINATIONS as [$slug, $route]) {
            if ($permissions->has($user, $tenant, $slug)) {
                return redirect()->route($route, $suffix);
            }
        }

        // No accessible area — send to business setup, which will show a no-access notice.
        return redirect()->route('admin.business.index', $suffix);
    }
}
