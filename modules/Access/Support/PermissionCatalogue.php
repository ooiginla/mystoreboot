<?php

declare(strict_types=1);

namespace Modules\Access\Support;

/**
 * The single source of truth for Storeboot's role-based access control.
 *
 *  - definitions(): every atomic permission (slug => module, name, description, sensitive)
 *  - modules():     module access-level bundles (None/View/Operate/Manage) + sensitive actions
 *  - templates():   the default system roles and the permissions they grant
 *  - limits():      configurable numeric limits attached to sensitive permissions
 *  - approvable():  actions that can route through the prepare → approve workflow
 *
 * Module "levels" are interface conveniences that expand to atomic permissions.
 * Storeboot always stores and enforces the atomic slugs.
 */
final class PermissionCatalogue
{
    /**
     * Every atomic permission. slug => [module, name, description, sensitive].
     *
     * @return array<string, array{module: string, name: string, description: string, sensitive: bool}>
     */
    public static function definitions(): array
    {
        $p = [];
        $add = function (string $module, string $slug, string $name, string $desc, bool $sensitive = false) use (&$p): void {
            $p[$slug] = ['module' => $module, 'name' => $name, 'description' => $desc, 'sensitive' => $sensitive];
        };

        // Dashboard
        $add('dashboard', 'dashboard.view', 'View dashboard', 'See the analytics dashboard and business KPIs.');

        // Sales & POS
        $add('sales', 'sales.view', 'View sales', 'View sales orders, receipts and history.');
        $add('sales', 'sales.create', 'Create sales', 'Ring up sales and record orders at the POS.');
        $add('sales', 'sales.payments.receive', 'Receive payments', 'Collect and record customer payments.');
        $add('sales', 'sales.update', 'Update sales', 'Edit order details and delivery information.');
        $add('sales', 'sales.till.manage', 'Manage till', 'Open, reconcile and close cashier tills.');
        $add('sales', 'sales.refunds.request', 'Request refunds', 'Raise a refund request for approval.', true);
        $add('sales', 'sales.refunds.approve', 'Approve refunds', 'Approve or reject refund requests.', true);
        $add('sales', 'sales.refunds.issue', 'Issue refunds', 'Directly process refunds and returns.', true);
        $add('sales', 'sales.orders.void', 'Void completed sales', 'Cancel or void a completed sale.', true);
        $add('sales', 'sales.prices.override', 'Override prices', 'Change an item price at the point of sale.', true);
        $add('sales', 'sales.discounts.override', 'Override discounts', 'Apply discounts above the standard limit.', true);
        $add('sales', 'sales.costs.view', 'View costs & profit', 'See product cost prices, margins and profit.', true);
        $add('sales', 'sales.till.variance.writeoff', 'Write off till variance', 'Close a till while booking a cash variance as loss/gain.', true);

        // Products & Catalog
        $add('catalog', 'catalog.view', 'View products', 'Browse products, services and prices.');
        $add('catalog', 'catalog.create', 'Add products', 'Create new products and services.');
        $add('catalog', 'catalog.update', 'Edit products', 'Edit product details, categories and prices.');
        $add('catalog', 'catalog.delete', 'Delete products', 'Remove products and services.', true);
        $add('catalog', 'catalog.costs.view', 'View product costs', 'See product cost prices and margins in the catalogue.', true);

        // Inventory & Stock
        $add('inventory', 'inventory.view', 'View inventory', 'View stock levels and locations.');
        $add('inventory', 'inventory.receive', 'Receive stock', 'Record incoming stock.');
        $add('inventory', 'inventory.transfer', 'Transfer stock', 'Move stock between branches and locations.');
        $add('inventory', 'inventory.count', 'Count stock', 'Perform stock counts.');
        $add('inventory', 'inventory.manage', 'Manage inventory setup', 'Manage locations and reorder settings.');
        $add('inventory', 'inventory.adjust', 'Adjust stock', 'Manually adjust stock quantities.', true);
        $add('inventory', 'inventory.adjustments.approve', 'Approve adjustments', 'Approve stock adjustment requests.', true);

        // Purchasing & Suppliers
        $add('procurement', 'procurement.view', 'View purchasing', 'View vendors and purchase orders.');
        $add('procurement', 'procurement.create', 'Create purchase orders', 'Raise purchase orders and manage vendors.');
        $add('procurement', 'procurement.receive', 'Receive goods', 'Record goods received against purchase orders.');
        $add('procurement', 'procurement.approve', 'Approve purchase orders', 'Approve purchase orders for fulfilment.', true);
        $add('procurement', 'procurement.payments.record', 'Record vendor payments', 'Record payments made to vendors.', true);

        // Customers & Support
        $add('customers', 'customers.view', 'View customers', 'View customer records and history.');
        $add('customers', 'customers.create', 'Add customers', 'Create and edit customer records.');
        $add('customers', 'customers.manage', 'Manage CRM', 'Manage groups, follow-ups and support tickets.');
        $add('customers', 'customers.export', 'Export customers', 'Export customer data.', true);

        // Finance & Accounting
        $add('finance', 'finance.view', 'View finance', 'View the finance overview and account balances.');
        $add('finance', 'finance.expenses.create', 'Record expenses', 'Create and record business expenses.');
        $add('finance', 'finance.expenses.approve', 'Approve expenses', 'Approve expenses and expense payments.', true);
        $add('finance', 'finance.journals.create', 'Prepare journals', 'Create draft journal entries.');
        $add('finance', 'finance.journals.post', 'Post journals', 'Post journal entries to the ledger.', true);
        $add('finance', 'finance.bank.manage', 'Manage banking', 'Record bank movements and settlements.', true);
        $add('finance', 'finance.reports.view', 'View financial reports', 'Open financial reports (P&L, balance sheet, etc.).');
        $add('finance', 'finance.reports.export', 'Export financial reports', 'Download and export financial reports.', true);

        // HR & Payroll
        $add('hr', 'hr.staff.view', 'View staff', 'View staff records.');
        $add('hr', 'hr.staff.manage', 'Manage staff', 'Create and edit staff records and deductions.');
        $add('hr', 'payroll.prepare', 'Prepare payroll', 'Prepare payroll runs without approving them.');
        $add('hr', 'payroll.run', 'Run payroll', 'Process payroll runs.', true);
        $add('hr', 'payroll.approve', 'Approve payroll', 'Approve prepared payroll runs.', true);
        $add('hr', 'payroll.export', 'Export payroll', 'Export payroll and payslip data.', true);

        // Settlements (money movement)
        $add('settlements', 'settlements.view', 'View settlements', 'View business and online payment settlements.');
        $add('settlements', 'settlements.manage', 'Manage settlements', 'Create, post and reconcile settlements.', true);

        // Administration
        $add('admin', 'branches.manage', 'Manage branches', 'Create and edit branches and departments.', true);
        $add('admin', 'business.settings.manage', 'Manage business settings', 'Edit the business profile, payment accounts and settings.', true);
        $add('admin', 'users.invite', 'Invite users', 'Invite users to the organization.', true);
        $add('admin', 'users.manage', 'Manage users', 'Manage organization users and their assignments.', true);
        $add('admin', 'roles.manage', 'Manage roles', 'Create, edit and assign roles and permissions.', true);
        $add('admin', 'subscriptions.manage', 'Manage subscription', 'Manage the subscription plan and modules.', true);
        $add('admin', 'billing.manage', 'Manage billing', 'Manage billing and payment of the subscription.', true);
        $add('admin', 'storefront.manage', 'Manage online store', 'Configure the customer-facing online store.', true);

        return $p;
    }

    /**
     * Module access-level bundles + sensitive actions, for the role editor UI.
     *
     * @return array<string, array{label: string, levels: array<string, list<string>>, sensitive: list<string>}>
     */
    public static function modules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'levels' => ['view' => ['dashboard.view'], 'operate' => ['dashboard.view'], 'manage' => ['dashboard.view']],
                'sensitive' => [],
            ],
            'sales' => [
                'label' => 'Sales & POS',
                'levels' => [
                    'view' => ['sales.view'],
                    'operate' => ['sales.view', 'sales.create', 'sales.payments.receive', 'sales.till.manage'],
                    'manage' => ['sales.view', 'sales.create', 'sales.payments.receive', 'sales.till.manage', 'sales.update'],
                ],
                'sensitive' => ['sales.refunds.request', 'sales.refunds.approve', 'sales.refunds.issue', 'sales.orders.void', 'sales.prices.override', 'sales.discounts.override', 'sales.costs.view', 'sales.till.variance.writeoff'],
            ],
            'catalog' => [
                'label' => 'Products & Services',
                'levels' => [
                    'view' => ['catalog.view'],
                    'operate' => ['catalog.view', 'catalog.create', 'catalog.update'],
                    'manage' => ['catalog.view', 'catalog.create', 'catalog.update'],
                ],
                'sensitive' => ['catalog.delete', 'catalog.costs.view'],
            ],
            'inventory' => [
                'label' => 'Inventory & Stock',
                'levels' => [
                    'view' => ['inventory.view'],
                    'operate' => ['inventory.view', 'inventory.receive', 'inventory.transfer', 'inventory.count'],
                    'manage' => ['inventory.view', 'inventory.receive', 'inventory.transfer', 'inventory.count', 'inventory.manage'],
                ],
                'sensitive' => ['inventory.adjust', 'inventory.adjustments.approve'],
            ],
            'procurement' => [
                'label' => 'Purchasing & Suppliers',
                'levels' => [
                    'view' => ['procurement.view'],
                    'operate' => ['procurement.view', 'procurement.create', 'procurement.receive'],
                    'manage' => ['procurement.view', 'procurement.create', 'procurement.receive'],
                ],
                'sensitive' => ['procurement.approve', 'procurement.payments.record'],
            ],
            'customers' => [
                'label' => 'Customers & Support',
                'levels' => [
                    'view' => ['customers.view'],
                    'operate' => ['customers.view', 'customers.create'],
                    'manage' => ['customers.view', 'customers.create', 'customers.manage'],
                ],
                'sensitive' => ['customers.export'],
            ],
            'finance' => [
                'label' => 'Finance & Accounting',
                'levels' => [
                    'view' => ['finance.view', 'finance.reports.view'],
                    'operate' => ['finance.view', 'finance.reports.view', 'finance.expenses.create', 'finance.journals.create'],
                    'manage' => ['finance.view', 'finance.reports.view', 'finance.expenses.create', 'finance.journals.create'],
                ],
                'sensitive' => ['finance.expenses.approve', 'finance.journals.post', 'finance.bank.manage', 'finance.reports.export'],
            ],
            'hr' => [
                'label' => 'HR & Payroll',
                'levels' => [
                    'view' => ['hr.staff.view'],
                    'operate' => ['hr.staff.view', 'payroll.prepare'],
                    'manage' => ['hr.staff.view', 'hr.staff.manage', 'payroll.prepare'],
                ],
                'sensitive' => ['payroll.run', 'payroll.approve', 'payroll.export'],
            ],
            'settlements' => [
                'label' => 'Settlements',
                'levels' => [
                    'view' => ['settlements.view'],
                    'operate' => ['settlements.view'],
                    'manage' => ['settlements.view'],
                ],
                'sensitive' => ['settlements.manage'],
            ],
            'admin' => [
                'label' => 'Administration',
                'levels' => [
                    'view' => [],
                    'operate' => [],
                    'manage' => ['branches.manage', 'business.settings.manage', 'users.invite', 'users.manage', 'storefront.manage'],
                ],
                'sensitive' => ['roles.manage', 'subscriptions.manage', 'billing.manage'],
            ],
        ];
    }

    /**
     * Configurable numeric limits attached to a sensitive permission.
     *
     * @return array<string, array{name: string, type: string, permission: string, description: string}>
     */
    public static function limits(): array
    {
        return [
            'sales.discount.max_percent' => ['name' => 'Maximum discount %', 'type' => 'percent', 'permission' => 'sales.create', 'description' => 'Largest discount this role can apply at the point of sale.'],
            'sales.refund.max_minor' => ['name' => 'Maximum refund amount', 'type' => 'money', 'permission' => 'sales.refunds.issue', 'description' => 'Largest refund this role can issue without approval.'],
            'inventory.adjustment.max_minor' => ['name' => 'Maximum stock adjustment value', 'type' => 'money', 'permission' => 'inventory.adjust', 'description' => 'Largest inventory adjustment value allowed without approval.'],
        ];
    }

    /**
     * Actions that support a prepare → approve workflow (when enabled in Business Settings).
     *
     * @return array<string, array{name: string, request: string, approve: string, setting: string}>
     */
    public static function approvable(): array
    {
        return [
            'refund' => ['name' => 'Refunds', 'request' => 'sales.refunds.request', 'approve' => 'sales.refunds.approve', 'setting' => 'refund'],
            'inventory_adjustment' => ['name' => 'Inventory adjustments', 'request' => 'inventory.adjust', 'approve' => 'inventory.adjustments.approve', 'setting' => 'inventory_adjustment'],
            'purchase_order' => ['name' => 'Purchase orders', 'request' => 'procurement.create', 'approve' => 'procurement.approve', 'setting' => 'purchase_order'],
            'expense' => ['name' => 'Expenses', 'request' => 'finance.expenses.create', 'approve' => 'finance.expenses.approve', 'setting' => 'expense'],
            'journal' => ['name' => 'Journal entries', 'request' => 'finance.journals.create', 'approve' => 'finance.journals.post', 'setting' => 'journal'],
            'payroll' => ['name' => 'Payroll', 'request' => 'payroll.prepare', 'approve' => 'payroll.approve', 'setting' => 'payroll'],
        ];
    }

    /**
     * Default system role templates. permissions '*' means every permission.
     *
     * @return array<string, array{name: string, description: string, protected: bool, permissions: string|list<string>, limits?: array<string, int|float>}>
     */
    public static function templates(): array
    {
        return [
            'business-owner' => [
                'name' => 'Business Owner',
                'description' => 'Full, unrestricted access to the entire business, including users, roles, billing and settings.',
                'protected' => true,
                'permissions' => '*',
            ],
            'branch-manager' => [
                'name' => 'Branch Manager',
                'description' => 'Runs day-to-day operations in assigned branches: sales, inventory, customers, purchasing and branch reporting.',
                'protected' => false,
                'permissions' => [
                    'dashboard.view',
                    'sales.view', 'sales.create', 'sales.payments.receive', 'sales.till.manage', 'sales.update',
                    'sales.refunds.approve', 'sales.refunds.issue', 'sales.discounts.override', 'sales.costs.view', 'sales.till.variance.writeoff',
                    'catalog.view', 'catalog.create', 'catalog.update',
                    'inventory.view', 'inventory.receive', 'inventory.transfer', 'inventory.count', 'inventory.adjust', 'inventory.adjustments.approve', 'inventory.manage',
                    'procurement.view', 'procurement.create', 'procurement.receive',
                    'customers.view', 'customers.create', 'customers.manage',
                    'finance.reports.view',
                ],
                'limits' => ['sales.discount.max_percent' => 20],
            ],
            'cashier' => [
                'name' => 'Cashier / Sales Staff',
                'description' => 'Uses the POS to create sales, receive payments and print receipts in assigned branches. Cannot view costs, refund, void or access finance.',
                'protected' => false,
                'permissions' => [
                    'sales.view', 'sales.create', 'sales.payments.receive', 'sales.till.manage',
                    'sales.refunds.request',
                    'customers.view', 'customers.create',
                ],
                'limits' => ['sales.discount.max_percent' => 5],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Manages finance, expenses, journals, payment accounts, settlements and financial reports. No access to users, roles or operations.',
                'protected' => false,
                'permissions' => [
                    'dashboard.view',
                    'finance.view', 'finance.expenses.create', 'finance.expenses.approve', 'finance.journals.create', 'finance.journals.post', 'finance.bank.manage',
                    'finance.reports.view', 'finance.reports.export',
                    'settlements.view', 'settlements.manage',
                    'sales.view', 'sales.costs.view',
                    'procurement.view', 'procurement.payments.record',
                ],
            ],
            'inventory-officer' => [
                'name' => 'Inventory Officer',
                'description' => 'Views products and stock, receives, transfers, counts and adjusts inventory. No finance, payroll or user access.',
                'protected' => false,
                'permissions' => [
                    'catalog.view',
                    'inventory.view', 'inventory.receive', 'inventory.transfer', 'inventory.count', 'inventory.adjust', 'inventory.manage',
                    'procurement.view', 'procurement.receive',
                ],
                'limits' => ['inventory.adjustment.max_minor' => 5000000],
            ],
            'hr-admin' => [
                'name' => 'HR / Admin Officer',
                'description' => 'Manages staff records and designated payroll functions. No finance, sales, inventory, billing or role management.',
                'protected' => false,
                'permissions' => [
                    'hr.staff.view', 'hr.staff.manage', 'payroll.prepare', 'payroll.run', 'payroll.export',
                ],
            ],
        ];
    }

    /**
     * The highest module access level (manage → operate → view → none) whose bundle is
     * fully contained in the given permission slugs. Empty bundles are not selectable.
     *
     * @param  list<string>  $slugs
     */
    public static function resolveLevel(string $module, array $slugs): string
    {
        $levels = self::modules()[$module]['levels'] ?? [];
        $set = array_flip($slugs);

        foreach (['manage', 'operate', 'view'] as $level) {
            $bundle = $levels[$level] ?? [];

            if ($bundle === []) {
                continue;
            }

            if (count(array_intersect_key(array_flip($bundle), $set)) === count($bundle)) {
                return $level;
            }
        }

        return 'none';
    }

    /**
     * Every atomic slug governed by a module's level bundles (i.e. not a sensitive action).
     *
     * @return list<string>
     */
    public static function levelSlugsForModule(string $module): array
    {
        $levels = self::modules()[$module]['levels'] ?? [];

        return array_values(array_unique(array_merge(...array_values($levels) ?: [[]])));
    }

    /**
     * Resolve a template's permission slugs to a concrete list.
     *
     * @return list<string>
     */
    public static function templatePermissions(string $key): array
    {
        $template = self::templates()[$key] ?? null;

        if (! $template) {
            return [];
        }

        if ($template['permissions'] === '*') {
            return array_keys(self::definitions());
        }

        return $template['permissions'];
    }
}
