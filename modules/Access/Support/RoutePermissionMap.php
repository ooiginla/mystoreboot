<?php

declare(strict_types=1);

namespace Modules\Access\Support;

/**
 * Maps admin route names to the permission(s) they require.
 *
 *  - a string slug            → the user must hold that permission
 *  - a list of slugs          → the user must hold ANY one of them
 *  - self::ALLOW              → always allowed for any active member (landing/utility routes)
 *  - not present (null)       → DENIED by default
 *
 * Deny-by-default: every admin.* route that is not listed here (and is not ALLOW)
 * is refused unless the viewer is a platform admin or the tenant has enforcement off.
 */
final class RoutePermissionMap
{
    public const ALLOW = '*allow*';

    /**
     * @return array<string, string|list<string>>
     */
    public static function map(): array
    {
        return [
            // Landing / utility
            'admin.home' => self::ALLOW,
            'admin.active-branch.update' => self::ALLOW,

            // Analytics
            'admin.analytics.index' => 'dashboard.view',

            // Access — roles & users
            'admin.access.roles.create' => 'roles.manage',
            'admin.access.roles.store' => 'roles.manage',
            'admin.access.roles.edit' => 'roles.manage',
            'admin.access.roles.update' => 'roles.manage',
            'admin.access.roles.duplicate' => 'roles.manage',
            'admin.access.roles.destroy' => 'roles.manage',
            'admin.access.tenant-users.store' => ['users.manage', 'users.invite'],
            'admin.access.memberships.update' => 'users.manage',
            'admin.access.review.index' => ['roles.manage', 'users.manage'],

            // Business setup
            'admin.business.index' => ['business.settings.manage', 'branches.manage', 'users.manage', 'roles.manage', 'subscriptions.manage'],
            'admin.business.organizations.index' => 'business.settings.manage',
            'admin.business.organizations.show' => 'business.settings.manage',
            'admin.business.online-store.index' => 'storefront.manage',
            'admin.business.online-store.address-availability' => 'storefront.manage',
            'admin.business.online-store.save' => 'storefront.manage',
            'admin.business.online-store.ai-content' => 'storefront.manage',
            'admin.business.profile.save' => 'business.settings.manage',
            'admin.business.payment-methods.save' => 'business.settings.manage',
            'admin.business.approvals.save' => 'business.settings.manage',
            'admin.business.payout.save' => 'business.settings.manage',
            'admin.business.banks.index' => 'business.settings.manage',
            'admin.business.resolve-account' => 'business.settings.manage',
            'admin.access.approvals.index' => self::ALLOW,
            'admin.access.approvals.approve' => self::ALLOW,
            'admin.access.approvals.reject' => self::ALLOW,
            'admin.business.payment-accounts.store' => 'business.settings.manage',
            'admin.business.payment-accounts.update' => 'business.settings.manage',
            'admin.business.payment-accounts.destroy' => 'business.settings.manage',
            'admin.business.subscriptions.store' => 'subscriptions.manage',
            'admin.business.subscriptions.update' => 'subscriptions.manage',
            'admin.business.subscriptions.modules.update' => 'subscriptions.manage',
            'admin.business.branches.store' => 'branches.manage',
            'admin.business.branches.update' => 'branches.manage',
            'admin.business.departments.store' => 'branches.manage',
            'admin.business.departments.update' => 'branches.manage',

            // Platform subscription plans (controller additionally requires platform admin)
            'admin.subscriptions.plans.index' => 'subscriptions.manage',
            'admin.subscriptions.plans.update' => 'subscriptions.manage',

            // Catalog
            'admin.catalog.index' => 'catalog.view',
            'admin.catalog.raw-materials.index' => 'catalog.view',
            'admin.catalog.raw-materials.store' => 'catalog.create',
            'admin.catalog.raw-materials.update' => 'catalog.update',
            'admin.catalog.raw-materials.destroy' => 'catalog.delete',
            'admin.catalog.products.store' => 'catalog.create',
            'admin.catalog.products.import' => 'catalog.create',
            'admin.catalog.products.import-sheet' => 'catalog.create',
            'admin.catalog.products.ai-content' => 'catalog.update',
            'admin.catalog.products.generate-image' => 'catalog.update',
            'admin.catalog.products.update' => 'catalog.update',
            'admin.catalog.products.stock.adjust' => 'inventory.manage',
            'admin.catalog.vendors.quick-store' => 'catalog.update',
            'admin.catalog.products.status.update' => 'catalog.update',
            'admin.catalog.products.destroy' => 'catalog.delete',
            'admin.catalog.custom-definitions.store' => 'catalog.update',
            'admin.catalog.categories.store' => 'catalog.update',
            'admin.catalog.tags.store' => 'catalog.update',
            'admin.catalog.tags.update' => 'catalog.update',
            'admin.catalog.badges.store' => 'catalog.update',
            'admin.catalog.badges.update' => 'catalog.update',
            'admin.catalog.product-collections.store' => 'catalog.update',
            'admin.catalog.product-collections.update' => 'catalog.update',
            'admin.catalog.taxes.store' => 'catalog.update',
            'admin.catalog.taxes.update' => 'catalog.update',
            'admin.catalog.taxes.destroy' => 'catalog.update',
            'admin.catalog.attributes.store' => 'catalog.update',
            'admin.catalog.attributes.update' => 'catalog.update',

            // Inventory
            'admin.inventory.index' => 'inventory.view',
            'admin.inventory.locations.store' => 'inventory.manage',
            'admin.inventory.locations.update' => 'inventory.manage',
            'admin.inventory.location-types.store' => 'inventory.manage',
            'admin.inventory.location-types.update' => 'inventory.manage',
            'admin.inventory.location-types.destroy' => 'inventory.manage',
            'admin.inventory.movements.store' => ['inventory.receive', 'inventory.transfer', 'inventory.count', 'inventory.adjust'],
            'admin.inventory.reorder.save' => 'inventory.manage',
            'admin.inventory.reorder-levels.index' => 'inventory.view',
            'admin.inventory.production.index' => 'inventory.view',
            'admin.inventory.production.show' => 'inventory.view',
            'admin.inventory.production.finished-products.store' => 'inventory.manage',
            'admin.inventory.production.finished-products.destroy' => 'inventory.manage',
            'admin.inventory.production.recipes.store' => 'inventory.manage',
            'admin.inventory.production.recipes.update' => 'inventory.manage',
            'admin.inventory.production.recipes.destroy' => 'inventory.manage',
            'admin.inventory.production.record' => 'inventory.manage',
            'admin.inventory.production.stock-policy' => 'inventory.manage',
            'admin.inventory.units.index' => 'inventory.view',
            'admin.inventory.unit-categories.store' => 'inventory.manage',
            'admin.inventory.unit-categories.update' => 'inventory.manage',
            'admin.inventory.unit-categories.destroy' => 'inventory.manage',
            'admin.inventory.units.store' => 'inventory.manage',
            'admin.inventory.units.update' => 'inventory.manage',
            'admin.inventory.units.destroy' => 'inventory.manage',
            'admin.inventory.batches.index' => 'inventory.view',
            'admin.inventory.batches.show' => 'inventory.view',
            'admin.inventory.stock-counts.index' => 'inventory.view',
            'admin.inventory.stock-counts.show' => 'inventory.view',
            'admin.inventory.stock-counts.store' => 'inventory.count',
            'admin.inventory.stock-counts.items.update' => 'inventory.count',
            'admin.inventory.stock-counts.cancel' => 'inventory.count',
            // Posting a count writes real adjustments, so it needs the adjustment approval right.
            'admin.inventory.stock-counts.post' => 'inventory.adjustments.approve',
            'admin.inventory.requisitions.index' => 'inventory.view',
            'admin.inventory.requisitions.store' => 'inventory.manage',
            'admin.inventory.requisitions.approve' => 'inventory.manage',
            'admin.inventory.requisitions.reject' => 'inventory.manage',
            'admin.inventory.requisitions.fulfil' => 'inventory.manage',
            'admin.inventory.requisitions.cancel' => 'inventory.manage',

            // Procurement
            'admin.procurement.index' => 'procurement.view',
            'admin.procurement.vendors.store' => 'procurement.create',
            'admin.procurement.vendors.update' => 'procurement.create',
            'admin.procurement.purchase-orders.store' => 'procurement.create',
            'admin.procurement.purchase-orders.update' => 'procurement.create',
            'admin.procurement.purchase-orders.approve' => 'procurement.approve',
            'admin.procurement.purchase-orders.cancel' => 'procurement.create',
            'admin.procurement.purchase-orders.receive' => 'procurement.receive',
            'admin.procurement.payments.store' => 'procurement.payments.record',

            // Customers
            'admin.customers.index' => 'customers.view',
            'admin.customers.customers.store' => 'customers.create',
            'admin.customers.customers.update' => 'customers.create',
            'admin.customers.groups.store' => 'customers.manage',
            'admin.customers.purchases.store' => 'customers.manage',
            'admin.customers.follow-ups.store' => 'customers.manage',
            'admin.customers.follow-ups.complete' => 'customers.manage',
            'admin.customers.tickets.store' => 'customers.manage',
            'admin.customers.tickets.update' => 'customers.manage',
            'admin.customers.tickets.status.update' => 'customers.manage',
            'admin.customers.tickets.claim' => 'customers.manage',
            'admin.customers.tickets.responses.store' => 'customers.manage',

            // Sales & POS
            'admin.sales.index' => 'sales.create',
            'admin.sales.orders.index' => 'sales.view',
            'admin.sales.retail-pos' => 'sales.create',
            'admin.sales.customers.quick' => 'customers.create',
            'admin.sales.tills.open' => 'sales.till.manage',
            // Restaurant POS
            'admin.sales.restaurant.floor' => 'pos.restaurant.operate',
            'admin.sales.restaurant.check' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.open' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.items.store' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.items.destroy' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.items.source' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.fire' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.cancel' => 'pos.restaurant.operate',
            // 6d — bill and settle
            'admin.sales.restaurant.checks.bill' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.print' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.payments.store' => 'sales.payments.receive',
            'admin.sales.restaurant.checks.service-charge' => 'sales.service-charge.manage',
            'admin.sales.restaurant.settings.update' => 'sales.service-charge.manage',
            'admin.sales.restaurant.tables.clean' => 'pos.restaurant.operate',
            // 6e — split, merge and void
            'admin.sales.restaurant.checks.split' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.merge' => 'pos.restaurant.operate',
            'admin.sales.restaurant.checks.items.void' => 'sales.void',
            'admin.sales.restaurant.checks.void' => 'sales.void',
            // 6c — modifiers
            'admin.sales.restaurant.modifiers.index' => 'pos.restaurant.modifiers.manage',
            'admin.sales.restaurant.modifiers.store' => 'pos.restaurant.modifiers.manage',
            'admin.sales.restaurant.modifiers.update' => 'pos.restaurant.modifiers.manage',
            'admin.sales.restaurant.modifiers.destroy' => 'pos.restaurant.modifiers.manage',
            'admin.sales.restaurant.areas.index' => 'pos.restaurant.tables.manage',
            'admin.sales.restaurant.areas.store' => 'pos.restaurant.tables.manage',
            'admin.sales.restaurant.tables.store' => 'pos.restaurant.tables.manage',
            'admin.sales.restaurant.areas.update' => 'pos.restaurant.tables.manage',
            'admin.sales.restaurant.tables.update' => 'pos.restaurant.tables.manage',
            'admin.sales.restaurant.tables.status' => 'pos.restaurant.tables.manage',
            'admin.sales.kds.index' => 'kds.view',
            'admin.sales.kds.station' => 'kds.view',
            'admin.sales.kds.tickets.advance' => 'kds.operate',
            'admin.sales.tills.movements.store' => 'sales.till.manage',
            'admin.sales.tills.close' => 'sales.till.manage',
            'admin.sales.orders.store' => 'sales.create',
            'admin.sales.orders.cancel' => 'sales.orders.void',
            'admin.sales.orders.mark-refunded' => 'sales.refunds.issue',
            'admin.sales.orders.status.update' => 'sales.update',
            'admin.sales.orders.delivery-status.update' => 'sales.update',
            'admin.sales.orders.payments.store' => 'sales.payments.receive',
            'admin.sales.orders.returns.store' => ['sales.refunds.issue', 'sales.refunds.request'],
            'admin.sales.coupons.store' => 'sales.discounts.override',
            'admin.sales.settlements.index' => 'settlements.view',
            'admin.sales.settlements.statement' => 'settlements.view',
            'admin.sales.settlements.payment-status' => 'settlements.manage',
            'admin.sales.settlements.payout-mode' => 'settlements.manage',
            'admin.sales.wallet.index' => 'settlements.view',
            'admin.sales.wallet.statement' => 'settlements.view',
            'admin.sales.wallet.preview' => 'settlements.view',
            'admin.sales.wallet.withdraw' => 'settlements.manage',

            // HR & Payroll
            'admin.hr-payroll.index' => 'hr.staff.view',
            'admin.hr-payroll.staff.store' => 'hr.staff.manage',
            'admin.hr-payroll.staff.update' => 'hr.staff.manage',
            'admin.hr-payroll.deductions.store' => 'hr.staff.manage',
            'admin.hr-payroll.payroll-runs.store' => ['payroll.run', 'payroll.prepare'],

            // Finance
            'admin.finance.index' => 'finance.reports.view',
            'admin.finance.reports.show' => 'finance.reports.view',
            'admin.finance.reports.download' => 'finance.reports.export',
            'admin.finance.chart-of-accounts' => 'finance.view',
            'admin.finance.expenses' => 'finance.view',
            'admin.finance.journals' => 'finance.view',
            'admin.finance.journals.download' => 'finance.reports.export',
            'admin.finance.expense-categories.store' => 'finance.expenses.create',
            'admin.finance.expense-categories.update' => 'finance.expenses.create',
            'admin.finance.expenses.store' => 'finance.expenses.create',
            'admin.finance.journals.store' => 'finance.journals.create',
            'admin.finance.bank-movements.store' => 'finance.bank.manage',
        ];
    }

    /**
     * The requirement for a route name: a slug, a list of slugs (any-of),
     * self::ALLOW, or null when unmapped (deny by default).
     *
     * @return string|list<string>|null
     */
    public static function for(?string $routeName): string|array|null
    {
        if ($routeName === null) {
            return null;
        }

        return self::map()[$routeName] ?? null;
    }
}
