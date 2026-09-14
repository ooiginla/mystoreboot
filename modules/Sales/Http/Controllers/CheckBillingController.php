<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Sales\Actions\PrintBillAction;
use Modules\Sales\Actions\RecalculateCheckTotalsAction;
use Modules\Sales\Actions\SettleCheckPaymentAction;
use Modules\Sales\Enums\CheckStatus;
use Modules\Sales\Http\Controllers\Concerns\ResolvesRestaurantTenant;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesTillSession;
use Modules\Sales\Support\DineInSettings;

/**
 * 6d — the end of the meal: print the bill, take payment, settle, release the table.
 */
final class CheckBillingController extends Controller
{
    use ResolvesRestaurantTenant;

    public function printBill(Request $request, SalesOrder $order, PrintBillAction $action): RedirectResponse
    {
        $this->checkFor($request, $order);

        $action->execute($order);

        $params = ['order' => $order->id, 'print' => 1];

        if ($request->filled('tenant')) {
            $params['tenant'] = $request->string('tenant')->toString();
        }

        return redirect()->to(route('admin.sales.restaurant.checks.bill', $params));
    }

    /** The printable bill — its own bare page, sized for a receipt printer or A4. */
    public function bill(Request $request, SalesOrder $order): View
    {
        $tenant = $this->checkFor($request, $order);

        $order->load(['items.modifiers', 'table', 'serviceArea', 'server', 'payments', 'branch']);

        return view('sales::admin.restaurant.bill', [
            'tenant' => $tenant,
            'check' => $order,
            'checkUrl' => $this->checkUrl($request, $order),
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    public function storePayment(Request $request, SalesOrder $order, SettleCheckPaymentAction $action): RedirectResponse
    {
        $tenant = $this->checkFor($request, $order, $tenants, $user);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'business_payment_account_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:120'],
        ]);

        // The money lands in the till of whoever takes it, exactly as at the counter.
        $till = SalesTillSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $order->branch_id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        $check = $action->execute($order, [...$data, 'sales_till_session_id' => $till?->id], $user);

        if ($check->check_status === CheckStatus::Settled) {
            return redirect()->to($this->floorUrl($request))->with('status', sprintf(
                '%s paid in full. %s',
                $check->order_number,
                $check->table ? "Table {$check->table->name} needs clearing before the next guests." : '',
            ));
        }

        return redirect()->to($this->checkUrl($request, $order))->with('status', sprintf(
            'Payment recorded. %s %s still to pay.',
            $tenant->currency_code,
            number_format($check->balance_minor / 100, 2),
        ));
    }

    /**
     * Waive the service charge on this bill (a complaint, a regular) or put it back.
     */
    public function serviceCharge(Request $request, SalesOrder $order, RecalculateCheckTotalsAction $totals): RedirectResponse
    {
        $tenant = $this->checkFor($request, $order);

        if (! $order->check_status?->isOpen()) {
            return redirect()->to($this->checkUrl($request, $order))->withErrors(['check' => 'This check is no longer open.']);
        }

        if ((int) $order->paid_minor > 0) {
            return redirect()->to($this->checkUrl($request, $order))
                ->withErrors(['check' => 'Payment has already been taken on this bill, so its service charge cannot change now.']);
        }

        $waive = $request->boolean('waive');

        $order->update(['service_charge_rate' => $waive ? 0 : DineInSettings::serviceChargeRate($tenant)]);
        $order->reopenIfBilled();
        $totals->execute($order);

        return redirect()->to($this->checkUrl($request, $order))->with('status', $waive
            ? 'Service charge removed from this bill.'
            : 'Service charge added back to this bill.');
    }
}
