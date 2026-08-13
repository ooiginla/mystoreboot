<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\Plan;

final class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensurePlatformAdmin($request);

        return view('subscriptions::admin.plans.index', [
            'plans' => Plan::query()
                ->with(['modules' => fn ($query) => $query->orderByDesc('is_core')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'modules' => Module::query()
                ->orderByDesc('is_core')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'alpha_dash:ascii',
                Rule::unique('plans', 'slug')->ignore($plan->id),
            ],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:42949672.95'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:42949672.95'],
            'currency_code' => ['required', 'string', 'size:3', 'alpha:ascii'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['nullable', 'boolean'],
            'limits' => ['nullable', 'string', 'json'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['integer', Rule::exists('billable_modules', 'id')],
        ]);

        $moduleIds = collect($validated['module_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->merge(Module::query()->where('is_core', true)->pluck('id'))
            ->unique()
            ->values();

        $entitlements = $moduleIds
            ->mapWithKeys(fn (int $moduleId): array => [$moduleId => ['is_enabled' => true]])
            ->all();

        DB::transaction(function () use ($plan, $validated, $entitlements): void {
            $plan->update([
                'name' => trim($validated['name']),
                'slug' => str($validated['slug'])->lower()->value(),
                'monthly_price_minor' => $this->moneyToMinor($validated['monthly_price']),
                'yearly_price_minor' => $this->moneyToMinor($validated['yearly_price']),
                'currency_code' => strtoupper($validated['currency_code']),
                'sort_order' => (int) $validated['sort_order'],
                'limits' => filled($validated['limits'] ?? null)
                    ? json_decode($validated['limits'], true, 512, JSON_THROW_ON_ERROR)
                    : null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            $plan->modules()->sync($entitlements);
        });

        return to_route('admin.subscriptions.plans.index')
            ->with('status', "{$plan->name} plan updated.");
    }

    private function ensurePlatformAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_platform_admin, 403);
    }

    private function moneyToMinor(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
