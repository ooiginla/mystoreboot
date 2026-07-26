<?php

declare(strict_types=1);

namespace Modules\Access\Support;

/**
 * Generates the plain-language "What this role can do" summary from a set of
 * permission slugs and limits. Used on the role list, in the editor, and when
 * assigning a role to a user.
 */
final class RoleSummaryBuilder
{
    /**
     * @param  list<string>  $slugs
     * @param  array<string, int|float>  $limits
     */
    public static function build(array $slugs, array $limits = [], string $currency = 'NGN'): string
    {
        $has = fn (string $slug): bool => in_array($slug, $slugs, true);
        $set = array_flip($slugs);
        $all = PermissionCatalogue::definitions();

        // Full access shortcut.
        if (count(array_intersect_key($all, $set)) === count($all)) {
            return 'Full, unrestricted access to the entire business, including users, roles, billing and settings.';
        }

        $can = [];
        $cannot = [];

        // Sales
        if ($has('sales.create')) {
            $sale = 'create sales and receive payments';
            if (isset($limits['sales.discount.max_percent'])) {
                $sale .= ', apply discounts up to '.self::trimNumber($limits['sales.discount.max_percent']).'%';
            } elseif ($has('sales.discounts.override')) {
                $sale .= ', apply discounts';
            }
            $can[] = $sale;
        } elseif ($has('sales.view')) {
            $can[] = 'view sales';
        }
        if ($has('sales.refunds.issue') && isset($limits['sales.refund.max_minor'])) {
            $can[] = 'issue refunds up to '.$currency.' '.number_format($limits['sales.refund.max_minor'] / 100);
        } elseif ($has('sales.refunds.issue')) {
            $can[] = 'issue refunds';
        } elseif ($has('sales.refunds.request')) {
            $can[] = 'request refunds (approval required)';
        }
        if (! $has('sales.costs.view') && ($has('sales.view') || $has('catalog.view'))) {
            $cannot[] = 'view product costs or profit';
        }
        if (! $has('sales.orders.void') && $has('sales.create')) {
            $cannot[] = 'void completed sales';
        }

        // Inventory
        if ($has('inventory.adjust')) {
            $can[] = 'manage and adjust inventory';
        } elseif ($has('inventory.receive')) {
            $can[] = 'receive and move stock';
        } elseif ($has('inventory.view')) {
            $can[] = 'view inventory';
        } else {
            $cannot[] = 'access inventory';
        }

        // Products
        if ($has('catalog.update')) {
            $can[] = 'manage products';
        } elseif ($has('catalog.view')) {
            $can[] = 'view products';
        }

        // Customers
        if ($has('customers.manage')) {
            $can[] = 'manage customers';
        } elseif ($has('customers.view')) {
            $can[] = 'view customers';
        }

        // Purchasing
        if ($has('procurement.create')) {
            $can[] = 'manage purchasing';
        } elseif ($has('procurement.view')) {
            $can[] = 'view purchasing';
        }

        // Finance
        if ($has('finance.journals.post') || $has('finance.expenses.approve')) {
            $can[] = 'manage finance, expenses and journals';
        } elseif ($has('finance.reports.view')) {
            $can[] = 'view financial reports';
        } else {
            $cannot[] = 'access finance';
        }

        // HR / Payroll
        if ($has('payroll.run') || $has('payroll.approve')) {
            $can[] = 'manage staff and payroll';
        } elseif ($has('hr.staff.view')) {
            $can[] = 'view staff';
        } else {
            $cannot[] = 'access payroll';
        }

        // Administration
        if (! $has('users.manage') && ! $has('roles.manage')) {
            $cannot[] = 'manage users, roles or business settings';
        } elseif ($has('roles.manage')) {
            $can[] = 'manage users, roles and settings';
        }

        $sentence = '';
        if ($can !== []) {
            $sentence .= 'Can '.self::joinList($can).'.';
        }
        if ($cannot !== []) {
            $sentence .= ($sentence !== '' ? ' ' : '').'Cannot '.self::joinList(array_slice($cannot, 0, 4)).'.';
        }

        return $sentence !== '' ? $sentence : 'No permissions assigned yet.';
    }

    /**
     * Format a numeric limit without trailing decimal zeros (20.00 → "20", 2.50 → "2.5").
     */
    private static function trimNumber(int|float $value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * @param  list<string>  $items
     */
    private static function joinList(array $items): string
    {
        $items = array_values(array_unique($items));

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
