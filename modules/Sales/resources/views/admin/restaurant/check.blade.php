@php
    use Modules\Sales\Enums\CheckStatus;

    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $plain = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2, '.', '');
    $qty = fn ($v): string => \Modules\Inventory\Support\Quantity::format($v ?? 0);
    $tenantParam = request('tenant');
    $unfired = $check->unfiredItems();
    $fired = $check->firedItems();
    $live = $check->liveItems();
    $voided = $check->items->whereNotNull('voided_at')->values();
    $isOpen = (bool) $check->check_status?->isOpen();
    $isBilled = $check->check_status === CheckStatus::BillPrinted;
    $tracked = collect($trackedVariantIds ?? []);
    $recipeItems = collect($recipeVariantIds ?? []);
    $shortfallsByItem = collect($shortfalls ?? []);
    $shortNames = $shortfallsByItem->flatten(1)->pluck('name')->unique()->values();
    $pending = collect($pendingVoids ?? []);
    $availableAt = fn (?int $locationId, ?int $variantId): float => (float) ($stock[$locationId][$variantId] ?? 0);
    // Stores that actually hold this item — what to offer when the guest's own bar is dry.
    $storesWith = fn (?int $variantId) => $locations->filter(
        fn ($l): bool => $availableAt($l->id, $variantId) > 0,
    );
    $rate = (float) $check->service_charge_rate;
    $paidMinor = (int) $check->paid_minor;
    $balanceMinor = (int) $check->balance_minor;
    $canSplit = $isOpen && $paidMinor === 0 && ($live->count() > 1 || (float) ($live->first()?->quantity ?? 0) > 1);
    $canMerge = $isOpen && ($mergeable ?? collect())->isNotEmpty();
    $voidItemAction = route('admin.sales.restaurant.checks.items.void', ['order' => $check->id, 'item' => '__ITEM__']);
@endphp

<x-layouts.admin :title="'Table '.($check->table?->name ?? 'Check')">
    <style>
        .check-meta { display: flex; flex-wrap: wrap; gap: 10px 26px; margin: 8px 0 0; }
        .check-meta div { display: grid; gap: 2px; }
        .check-meta dt { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #667085; }
        .check-meta dd { margin: 0; font-size: 1rem; font-weight: 800; color: #101828; }
        .status-pill { display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: .78rem; font-weight: 800; }
        .status-pill.open { background: #fef3f2; color: #b42318; }
        .status-pill.billed { background: #eef4ff; color: #3538cd; }

        .check-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: flex-end; }
        /* Destructive actions sit apart from routine ones, never beside Fire or Pay. */
        .danger-zone { padding-left: 12px; margin-left: 4px; border-left: 2px solid #fecdca; }

        .bill-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 14px; }
        .bill-tab { padding: 7px 13px; border: 1px solid var(--line); border-radius: 10px; background: #fff; font-weight: 700; font-size: .85rem; text-decoration: none; color: #344054; }
        .bill-tab.on { border-color: #101828; background: #101828; color: #fff; }
        .bill-tab small { font-weight: 600; opacity: .75; margin-left: 5px; }

        .check-layout { display: grid; grid-template-columns: minmax(320px, 440px) 1fr; gap: 18px; align-items: start; }
        @media (max-width: 980px) { .check-layout { grid-template-columns: 1fr; } }

        .pad { border: 1px solid var(--line); border-radius: 14px; background: #fff; overflow: hidden; position: sticky; top: 12px; }
        .pad-head { padding: 14px 16px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
        .pad-head h2 { margin: 0; font-size: 1.35rem; }
        .pad-body { padding: 6px 16px 14px; max-height: 44vh; overflow-y: auto; }
        .pad-foot { padding: 14px 16px; border-top: 1px solid var(--line); background: #fafbfc; }

        .line { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f0f1f4; }
        .line:last-child { border-bottom: 0; }
        .line-name { font-weight: 650; }
        .line-sub { font-size: 0.78rem; color: #667085; }
        .line-money { font-weight: 700; white-space: nowrap; }
        .line-source { font-size: .74rem; color: #667085; margin-top: 3px; }
        .line-source.is-short { color: #b42318; font-weight: 750; }
        .line-source-form { margin: 5px 0 0; }
        .line-source-form select { font-size: .76rem; padding: 3px 6px; height: auto; max-width: 210px; }
        .line-right { display: flex; align-items: center; gap: 7px; }
        .line-right form { margin: 0; }
        .mods { margin: 4px 0 0; padding: 0; list-style: none; display: grid; gap: 2px; }
        .mods li { font-size: .8rem; color: #344054; font-weight: 650; }
        .mods li.is-removal { color: #b42318; font-weight: 800; }
        /* Only offered on unsent lines — a sent line has to be voided, not deleted. */
        .line-remove { width: 26px; height: 26px; display: grid; place-items: center; padding: 0;
            border: 1px solid #f3b7ab; border-radius: 7px; background: #fff; color: #b42318;
            font-size: .78rem; font-weight: 800; cursor: pointer; line-height: 1; }
        .line-remove:hover { background: #fef3f2; border-color: #f04438; }
        .line-void { padding: 3px 9px; border: 1px solid #f3b7ab; border-radius: 7px; background: #fff; color: #b42318;
            font-size: .72rem; font-weight: 800; cursor: pointer; }
        .line-void:hover { background: #fef3f2; }

        /* The single most dangerous mistake on a restaurant POS is a server believing
           food is on when it is not. Unfired lines are visually loud and unmissable. */
        .line-unfired { border-left: 3px dashed #f79009; padding-left: 10px; background: #fffaf0; border-radius: 0 8px 8px 0; }
        .chip { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; vertical-align: 1px; }
        .chip-unsent { background: #f79009; color: #fff; }
        .chip-sent { background: #ecfdf3; color: #067647; }
        .chip-pending { background: #f4ebff; color: #6941c6; }
        .voided-block { margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--line); }
        .voided-block summary { cursor: pointer; font-size: .8rem; font-weight: 700; color: #667085; }
        .voided-line { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; font-size: .82rem; color: #98a2b3; }
        .voided-line .n { text-decoration: line-through; }

        .totals-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; padding: 3px 0; gap: 8px; }
        .totals-row.grand { font-size: 1.35rem; font-weight: 800; padding-top: 9px; margin-top: 7px; border-top: 2px solid var(--line); }
        .totals-row.due { font-size: 1.1rem; font-weight: 800; color: #b42318; }
        .sc-toggle { display: inline; margin: 0 0 0 6px; }
        .sc-toggle button { border: 0; background: none; padding: 0; color: #475467; font-size: .74rem; font-weight: 700; text-decoration: underline; cursor: pointer; }

        /* Fire is the primary action and lives alone, nowhere near anything destructive. */
        .fire-btn { width: 100%; padding: 15px; font-size: 1.05rem; font-weight: 800; letter-spacing: .01em; }
        .fire-btn[disabled], .bill-btn[disabled], .pay-btn[disabled] { opacity: .45; cursor: not-allowed; }
        .bill-btn { width: 100%; padding: 12px; font-size: .98rem; font-weight: 800; margin-top: 10px; }
        .fire-hint { text-align: center; font-size: 0.78rem; color: #667085; margin-top: 8px; }

        .settle { margin-top: 14px; border: 2px solid #c7d7fe; background: #f5f8ff; border-radius: 12px; padding: 13px; }
        .settle h3 { margin: 0 0 2px; font-size: 1rem; }
        .settle-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; margin-top: 10px; }
        .settle-grid .full { grid-column: 1 / -1; }
        .settle label { font-size: .74rem; font-weight: 800; color: #475467; text-transform: uppercase; letter-spacing: .04em; }
        .settle input, .settle select { width: 100%; }
        .even-split { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; font-size: .8rem; }
        .even-split button { padding: 4px 10px; border: 1px solid #c7d7fe; border-radius: 8px; background: #fff; font-weight: 800; cursor: pointer; }
        .change-due { font-weight: 800; color: #067647; font-size: .95rem; }
        .pay-btn { width: 100%; padding: 13px; font-size: 1rem; font-weight: 800; margin-top: 10px; }
        .till-warning { margin-top: 10px; padding: 9px 11px; border-radius: 9px; background: #fffaeb; color: #b54708; font-size: .82rem; font-weight: 650; }
        .payment-row { display: flex; justify-content: space-between; font-size: .82rem; padding: 4px 0; color: #344054; }

        .menu-panel { border: 1px solid var(--line); border-radius: 14px; background: #fff; }
        .menu-head { padding: 14px 16px; border-bottom: 1px solid var(--line); display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .menu-search { flex: 1; min-width: 180px; }
        .cat-bar { display: flex; gap: 7px; flex-wrap: wrap; padding: 12px 16px 0; }
        .cat-pill { padding: 6px 13px; border-radius: 999px; border: 1px solid var(--line); background: #fff; font-size: 0.83rem; font-weight: 650; cursor: pointer; }
        .cat-pill.on { background: #101828; color: #fff; border-color: #101828; }

        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 11px; padding: 14px 16px 18px; }
        .menu-item { border: 1px solid var(--line); border-radius: 11px; padding: 13px 12px; background: #fff; cursor: pointer; text-align: left; min-height: 84px; display: flex; flex-direction: column; justify-content: space-between; }
        .menu-item:hover { border-color: #101828; }
        .menu-item .m-name { font-weight: 700; font-size: 0.94rem; line-height: 1.25; }
        .menu-item .m-var { font-size: 0.74rem; color: #667085; }
        .menu-item .m-price { font-weight: 800; margin-top: 8px; display: flex; justify-content: space-between; align-items: center; }
        .menu-item .m-choice { font-size: .66rem; font-weight: 800; color: #6941c6; background: #f4ebff; border-radius: 999px; padding: 1px 7px; }
        .menu-empty { padding: 30px 16px; text-align: center; }
        .menu-locked { padding: 12px 16px; background: #eef4ff; color: #3538cd; font-size: .85rem; font-weight: 650; border-bottom: 1px solid var(--line); }

        .ticket-row { display: flex; justify-content: space-between; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f0f1f4; font-size: 0.88rem; }

        /* Modifier sheet: large targets, a required group blocks Add until answered. */
        #modifier-sheet { width: min(620px, 96vw); }
        .mod-group { margin-bottom: 16px; }
        .mod-group-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
        .mod-group-head h3 { margin: 0; font-size: 1rem; }
        .mod-rule { font-size: .74rem; font-weight: 800; padding: 2px 9px; border-radius: 999px; background: #f2f4f7; color: #475467; }
        .mod-rule.need { background: #fef0c7; color: #b54708; }
        .mod-rule.done { background: #dcfae6; color: #067647; }
        .mod-options { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
        .mod-option { border: 2px solid var(--line); border-radius: 11px; padding: 12px; background: #fff; text-align: left; cursor: pointer; font-weight: 700; min-height: 58px; }
        .mod-option small { display: block; font-weight: 650; color: #667085; margin-top: 3px; }
        .mod-option.on { border-color: #101828; background: #101828; color: #fff; }
        .mod-option.on small { color: #d0d5dd; }
        .sheet-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .stepper { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .stepper button { width: 42px; height: 42px; border: 0; background: #f9fafb; font-size: 1.2rem; font-weight: 800; cursor: pointer; }
        .stepper span { min-width: 44px; text-align: center; font-weight: 800; font-size: 1.05rem; }
        .sheet-total { font-size: 1.2rem; font-weight: 800; }

        .dialog-table { width: 100%; border-collapse: collapse; }
        .dialog-table td { padding: 8px 6px; border-bottom: 1px solid #f0f1f4; vertical-align: middle; }
        .dialog-table input[type="number"] { width: 90px; }
        .merge-option { display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; margin-bottom: 8px; cursor: pointer; }
        .merge-option:has(input:checked) { border-color: #101828; background: #f9fafb; }
        .waste-note { padding: 10px 12px; border-radius: 10px; background: #fef3f2; color: #912018; font-size: .85rem; font-weight: 600; margin-bottom: 12px; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow"><a href="{{ $floorUrl }}">← Floor</a></div>
            <h1>
                Table {{ $check->table?->name }}
                <span class="status-pill {{ $isBilled ? 'billed' : 'open' }}">{{ $check->check_status?->label() }}</span>
            </h1>
            {{-- Key/value rather than a dot-separated run-on: each fact is labelled and
                 its value is the thing that stands out. --}}
            <dl class="check-meta">
                <div><dt>Area</dt><dd>{{ $check->serviceArea?->name ?? '—' }}</dd></div>
                <div><dt>Guests</dt><dd>{{ $check->cover_count }}</dd></div>
                <div><dt>Open</dt><dd>{{ $check->openMinutes() }} min</dd></div>
                <div><dt>Check</dt><dd>{{ $check->order_number }}</dd></div>
                @if ($check->server)
                    <div><dt>Server</dt><dd>{{ $check->server->name }}</dd></div>
                @endif
            </dl>
        </div>
        @if ($isOpen)
            <div class="check-actions">
                @if ($canSplit)
                    <button class="btn secondary" type="button" data-dialog-open="split-dialog">Split bill</button>
                @endif
                @if ($canMerge)
                    <button class="btn ghost" type="button" data-dialog-open="merge-dialog">Merge…</button>
                @endif
                <div class="danger-zone">
                    @if ($fired->isEmpty())
                        <form method="POST" action="{{ route('admin.sales.restaurant.checks.cancel', $check) }}"
                              onsubmit="return confirm('Cancel this check and free table {{ $check->table?->name }}?');">
                            @csrf
                            <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                            <button class="btn danger" type="submit">Cancel check</button>
                        </form>
                    @elseif ($checkVoidPending ?? false)
                        <span class="chip chip-pending" style="font-size:.75rem;">Void of whole check waiting for a manager</span>
                    @elseif ($paidMinor === 0)
                        @permission('sales.void')
                        <button class="btn danger" type="button" data-dialog-open="void-check-dialog">Void check</button>
                        @endpermission
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    @if (($tableChecks ?? collect())->count() > 1)
        {{-- A split table: every bill at it, one tap apart. --}}
        <nav class="bill-tabs" aria-label="Bills at this table">
            @foreach ($tableChecks as $sibling)
                <a class="bill-tab {{ $sibling->id === $check->id ? 'on' : '' }}"
                   href="{{ route('admin.sales.restaurant.check', array_filter(['order' => $sibling->id, 'tenant' => $tenantParam])) }}">
                    {{ $sibling->order_number }}<small>{{ $money($sibling->total_minor) }}</small>
                </a>
            @endforeach
        </nav>
    @endif

    <div class="check-layout">
        {{-- The bill --}}
        <div class="pad">
            <div class="pad-head">
                <h2>The check</h2>
                <span class="subtle">{{ $fired->count() }} sent · {{ $unfired->count() }} waiting</span>
            </div>
            <div class="pad-body">
                @forelse ($live as $item)
                    <div class="line {{ $item->isFired() ? '' : 'line-unfired' }}">
                        <div>
                            <div class="line-name">
                                {{ $qty($item->quantity) }} × {{ $item->item_name }}
                                @if ($item->isFired())
                                    <span class="chip chip-sent">Sent</span>
                                @else
                                    <span class="chip chip-unsent">Not sent</span>
                                @endif
                                @if ($pending->has($item->id))
                                    <span class="chip chip-pending">Void waiting for manager</span>
                                @endif
                            </div>
                            @if ($item->modifiers->isNotEmpty())
                                <ul class="mods">
                                    @foreach ($item->modifiers as $modifier)
                                        <li class="{{ $modifier->isRemoval() ? 'is-removal' : '' }}">
                                            {{ $modifier->isRemoval() ? '✕' : '+' }} {{ $modifier->option_name }}
                                            @if ((int) $modifier->price_delta_minor !== 0)
                                                <span class="subtle">({{ $modifier->price_delta_minor > 0 ? '+' : '−' }}{{ $money(abs($modifier->price_delta_minor)) }})</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="line-sub">
                                Course {{ $item->course }}@if ($item->seat_number) · Seat {{ $item->seat_number }}@endif
                                @if ($item->isFired()) · {{ $item->fired_at->format('H:i') }} @endif
                            </div>
                            @if ($recipeItems->contains($item->product_variant_id))
                                @php
                                    // Made to order: no stock of its own, but its ingredients
                                    // still come off a specific kitchen's shelf.
                                    $madeAt = $locations->firstWhere('id', $item->inventory_location_id);
                                    $isStation = (bool) ($madeAt?->is_prep_station);
                                    $lineShort = $shortfallsByItem->get($item->id, []);
                                @endphp
                                <div class="line-source {{ ($madeAt && $lineShort === []) ? '' : 'is-short' }}">
                                    @if ($madeAt)
                                        From <strong>{{ $madeAt->name }}</strong> · made to order
                                        @unless ($isStation) — not a prep station, so no kitchen screen @endunless
                                        @if ($lineShort !== [])
                                            {{-- Named before Fire is pressed, so nobody discovers it
                                                 from a failed save. --}}
                                            <div>Short on
                                                {{ collect($lineShort)->map(fn ($i) => $i['name'].' (need '.\Modules\Inventory\Support\Quantity::format($i['need']).', have '.\Modules\Inventory\Support\Quantity::format($i['have']).')')->implode(', ') }}
                                            </div>
                                        @endif
                                    @else
                                        No kitchen set — nowhere to take the ingredients from
                                    @endif
                                </div>
                            @elseif ($tracked->contains($item->product_variant_id))
                                @php
                                    $from = $locations->firstWhere('id', $item->inventory_location_id);
                                    $have = $availableAt($item->inventory_location_id, $item->product_variant_id);
                                    $isShort = $have < (float) $item->quantity;
                                    $elsewhere = $storesWith($item->product_variant_id)
                                        ->reject(fn ($l): bool => $l->id === $item->inventory_location_id);
                                @endphp
                                <div class="line-source {{ $isShort && ! $item->isFired() ? 'is-short' : '' }}">
                                    From <strong>{{ $from?->name ?? 'no store' }}</strong> ·
                                    {{ \Modules\Inventory\Support\Quantity::format($have) }} available
                                    @if ($isShort && ! $item->isFired()) — not enough @endif
                                </div>
                                @if (! $item->isFired() && $elsewhere->isNotEmpty())
                                    {{-- The pool bar is dry, the lounge bar has it: pour from
                                         there and record it, rather than faking a transfer. --}}
                                    <form class="line-source-form" method="POST"
                                          action="{{ route('admin.sales.restaurant.checks.items.source', ['order' => $check->id, 'item' => $item->id]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                        <select name="inventory_location_id" onchange="this.form.submit()" aria-label="Pour {{ $item->item_name }} from">
                                            <option value="{{ $item->inventory_location_id }}">Pour from…</option>
                                            @foreach ($elsewhere as $option)
                                                <option value="{{ $option->id }}">
                                                    {{ $option->name }} ({{ \Modules\Inventory\Support\Quantity::format($availableAt($option->id, $item->product_variant_id)) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            @endif
                        </div>
                        <div class="line-right">
                            <span class="line-money">{{ $money((int) round((float) $item->quantity * (int) $item->unit_price_minor)) }}</span>
                            @if ($isOpen && ! $item->isFired())
                                <form method="POST" action="{{ route('admin.sales.restaurant.checks.items.destroy', ['order' => $check->id, 'item' => $item->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                    <button class="line-remove" type="submit" title="Remove {{ $item->item_name }}" aria-label="Remove {{ $item->item_name }}">✕</button>
                                </form>
                            @elseif ($isOpen && ! $pending->has($item->id) && $paidMinor === 0)
                                @permission('sales.void')
                                <button class="line-void" type="button" data-dialog-open="void-item-dialog"
                                        data-void-item="{{ $item->id }}" data-void-name="{{ $item->item_name }}"
                                        data-void-qty="{{ $qty($item->quantity) }}">Void</button>
                                @endpermission
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="subtle" style="padding: 18px 0; text-align:center;">Nothing on this check yet. Pick items from the menu.</p>
                @endforelse

                @if ($voided->isNotEmpty())
                    {{-- Voided lines stay visible as a record: the food was still made. --}}
                    <details class="voided-block">
                        <summary>Voided ({{ $voided->count() }}) — recorded as waste</summary>
                        @foreach ($voided as $item)
                            <div class="voided-line">
                                <span><span class="n">{{ $qty($item->quantity) }} × {{ $item->item_name }}</span> · {{ $item->void_reason }}</span>
                                <span class="n">{{ $money((int) round((float) $item->quantity * (int) $item->unit_price_minor)) }}</span>
                            </div>
                        @endforeach
                    </details>
                @endif
            </div>
            <div class="pad-foot">
                <div class="totals-row"><span>Subtotal</span><span>{{ $money($check->subtotal_minor) }}</span></div>
                @if ((int) $check->tax_minor > 0)
                    <div class="totals-row"><span>Tax</span><span>{{ $money($check->tax_minor) }}</span></div>
                @endif
                @if ($rate > 0 || ($standardServiceChargeRate ?? 0) > 0)
                    <div class="totals-row">
                        <span>
                            Service charge @if ($rate > 0) ({{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%) @else <span class="subtle">(waived)</span> @endif
                            @if ($isOpen && $paidMinor === 0)
                                @permission('sales.service-charge.manage')
                                <form class="sc-toggle" method="POST" action="{{ route('admin.sales.restaurant.checks.service-charge', $check) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                    <input type="hidden" name="waive" value="{{ $rate > 0 ? 1 : 0 }}">
                                    <button type="submit">{{ $rate > 0 ? 'Waive' : 'Add back' }}</button>
                                </form>
                                @endpermission
                            @endif
                        </span>
                        <span>{{ $money($check->service_charge_minor) }}</span>
                    </div>
                @endif
                <div class="totals-row grand"><span>Total</span><span>{{ $tenant->currency_code }} {{ $money($check->total_minor) }}</span></div>
                @if ($paidMinor > 0)
                    <div class="totals-row"><span>Paid</span><span>{{ $money($paidMinor) }}</span></div>
                    <div class="totals-row due"><span>Still to pay</span><span>{{ $tenant->currency_code }} {{ $money($balanceMinor) }}</span></div>
                @endif

                @if ($isOpen && ! $isBilled)
                    <form method="POST" action="{{ route('admin.sales.restaurant.checks.fire', $check) }}" style="margin-top: 14px;"
                          @if ($shortNames->isNotEmpty())
                              onsubmit="return confirm('The kitchen is short on {{ $shortNames->implode(', ') }}. Send anyway and let them sort it out?');"
                          @endif>
                        @csrf
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        @if ($shortNames->isNotEmpty())
                            {{-- Consent to go short travels with the request; without it the
                                 short line stays on the pad instead of going negative. --}}
                            <input type="hidden" name="allow_short" value="1">
                        @endif
                        <button class="btn primary fire-btn" type="submit" @disabled($unfired->isEmpty())>
                            @if ($unfired->isEmpty())
                                Nothing to send
                            @else
                                Send {{ $unfired->count() }} item{{ $unfired->count() === 1 ? '' : 's' }} to kitchen
                            @endif
                        </button>
                    </form>
                    <p class="fire-hint">
                        @if ($unfired->isEmpty())
                            Everything on this check has been sent.
                        @elseif ($shortNames->isNotEmpty())
                            The kitchen is short on {{ $shortNames->implode(', ') }}. Sending anyway lets them
                            borrow or improvise — the shortfall will show as negative stock.
                        @else
                            Once sent, the kitchen starts cooking and items can no longer be removed freely.
                        @endif
                    </p>

                    {{-- Kept apart from Fire so the two are never confused. --}}
                    <form method="POST" action="{{ route('admin.sales.restaurant.checks.print', $check) }}">
                        @csrf
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        <button class="btn secondary bill-btn" type="submit" @disabled($live->isEmpty() || $unfired->isNotEmpty() || $pending->isNotEmpty())>
                            Print bill
                        </button>
                    </form>
                    @if ($live->isNotEmpty() && $unfired->isNotEmpty())
                        <p class="fire-hint">Send or remove the waiting items before printing the bill.</p>
                    @elseif ($pending->isNotEmpty())
                        <p class="fire-hint">A void is waiting for a manager — print once it is decided.</p>
                    @endif
                @endif

                @if ($isBilled)
                    <div class="settle">
                        <div style="display:flex; justify-content:space-between; align-items:baseline; gap:8px;">
                            <h3>Take payment</h3>
                            <a class="subtle" href="{{ route('admin.sales.restaurant.checks.bill', array_filter(['order' => $check->id, 'tenant' => $tenantParam])) }}" target="_blank" rel="noopener">Reprint bill</a>
                        </div>
                        <div class="subtle" style="font-size:.8rem;">Bill printed {{ $check->bill_printed_at?->format('H:i') }}. Adding anything reopens it.</div>

                        @if ($check->payments->isNotEmpty())
                            <div style="margin-top:8px;">
                                @foreach ($check->payments as $payment)
                                    <div class="payment-row"><span>{{ $payment->payment_method }} · {{ $payment->created_at?->format('H:i') }}</span><strong>{{ $money($payment->amount_minor) }}</strong></div>
                                @endforeach
                            </div>
                        @endif

                        @permission('sales.payments.receive')
                        <form method="POST" action="{{ route('admin.sales.restaurant.checks.payments.store', $check) }}" data-pay-form data-balance="{{ $balanceMinor }}">
                            @csrf
                            <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                            <div class="settle-grid">
                                <div class="full even-split">
                                    Split evenly between
                                    @foreach ([2, 3, 4, 5, 6] as $ways)
                                        <button type="button" data-split-ways="{{ $ways }}">{{ $ways }}</button>
                                    @endforeach
                                    <span class="subtle" data-split-note></span>
                                </div>
                                <div>
                                    <label for="pay-method">Method</label>
                                    <select id="pay-method" name="payment_method" data-pay-method required>
                                        @foreach ($paymentMethods as $method)
                                            <option value="{{ $method }}">{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="pay-amount">Amount</label>
                                    <input id="pay-amount" name="amount" type="number" min="0.01" step="0.01" max="{{ $plain($balanceMinor) }}" value="{{ $plain($balanceMinor) }}" data-pay-amount required>
                                </div>
                                <div data-cash-only>
                                    <label for="pay-received">Cash received</label>
                                    <input id="pay-received" type="number" min="0" step="0.01" data-pay-received placeholder="Optional">
                                </div>
                                <div data-cash-only style="align-self:end;">
                                    <span class="change-due" data-change-due></span>
                                </div>
                                @if ($paymentAccounts->isNotEmpty())
                                    <div class="full" data-noncash-only hidden>
                                        <label for="pay-account">Received into</label>
                                        <select id="pay-account" name="business_payment_account_id">
                                            <option value="">Choose account…</option>
                                            @foreach ($paymentAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->identifier }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="full" data-noncash-only hidden>
                                    <label for="pay-ref">Reference</label>
                                    <input id="pay-ref" name="reference_number" maxlength="120" placeholder="Card slip or transfer reference">
                                </div>
                            </div>
                            @if (! $till)
                                <div class="till-warning">
                                    You have no till open at this branch, so there is nowhere for the money to go.
                                    <a href="{{ route('admin.sales.index', array_filter(['tenant' => $tenantParam])) }}">Open a till</a>, then come back.
                                </div>
                            @endif
                            <button class="btn primary pay-btn" type="submit" @disabled(! $till)>
                                Take payment
                            </button>
                        </form>
                        @endpermission
                    </div>
                @endif
            </div>
        </div>

        {{-- The menu --}}
        <div>
            <div class="menu-panel">
                <div class="menu-head">
                    <h2 class="panel-title" style="margin:0;">Menu</h2>
                    <div class="menu-search"><input type="search" id="menu-search" placeholder="Search the menu…" aria-label="Search menu"></div>
                </div>
                @if ($isBilled)
                    <div class="menu-locked">The bill has been printed. Adding an item reopens it, and it will need printing again.</div>
                @endif
                <div class="cat-bar" id="cat-bar"></div>
                <form method="POST" action="{{ route('admin.sales.restaurant.checks.items.store', $check) }}" id="add-form">
                    @csrf
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <input type="hidden" name="items[0][product_variant_id]" id="pick-variant">
                    <input type="hidden" name="items[0][quantity]" id="pick-qty" value="1">
                    <input type="hidden" name="items[0][course]" id="pick-course" value="1">
                    <div id="pick-modifiers"></div>
                </form>
                <div class="menu-grid" id="menu-grid"></div>
            </div>

            @if ($check->tickets->isNotEmpty())
                <section class="panel" style="margin-top: 16px;">
                    <div class="panel-header"><h2 class="panel-title">Kitchen tickets</h2></div>
                    <div class="panel-body">
                        @foreach ($check->tickets->sortByDesc('fired_at') as $ticket)
                            <div class="ticket-row">
                                <div>
                                    <strong>{{ $ticket->ticket_number }}</strong>
                                    <span class="subtle">· {{ $ticket->station?->name ?? 'Kitchen' }} · course {{ $ticket->course }}</span>
                                </div>
                                <div>
                                    <span class="badge {{ $ticket->status->value === 'ready' ? 'success' : 'neutral' }}">{{ $ticket->status->label() }}</span>
                                    <span class="subtle">{{ $ticket->fired_at?->format('H:i') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>

    {{-- Modifier sheet: opened by tapping a menu item that offers choices. --}}
    <dialog class="dialog" id="modifier-sheet">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title" data-sheet-title>Item</h2>
                <p class="subtle" data-sheet-base></p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
        </div>
        <div class="dialog-body">
            <div data-sheet-groups></div>
            <div class="sheet-foot">
                <div class="stepper" aria-label="Quantity">
                    <button type="button" data-step="-1" aria-label="One less">−</button>
                    <span data-sheet-qty>1</span>
                    <button type="button" data-step="1" aria-label="One more">+</button>
                </div>
                <span class="sheet-total" data-sheet-total></span>
                <button class="btn primary" type="button" data-sheet-add style="padding: 13px 22px; font-weight: 800;">Add to check</button>
            </div>
        </div>
    </dialog>

    @if ($isOpen)
        @permission('sales.void')
        <dialog class="dialog" id="void-item-dialog">
            <div class="dialog-header">
                <div><h2 class="panel-title">Void <span data-void-title></span></h2></div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
            </div>
            <div class="dialog-body">
                <div class="waste-note">
                    The kitchen already has this. Voiding takes it off the bill and records what it used as waste —
                    stock is not put back. If your business needs approval, a manager is asked first.
                </div>
                <form class="mini-form" method="POST" action="" data-void-form data-action-template="{{ $voidItemAction }}">
                    @csrf
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <div class="form-grid">
                        <div class="field" data-void-qty-field>
                            <label for="void-qty">How many</label>
                            <input id="void-qty" name="quantity" type="number" min="1" step="any" data-void-qty>
                            <small class="subtle">Of <span data-void-of></span> on the bill.</small>
                        </div>
                        <div class="field">
                            <label for="void-reason">Reason</label>
                            <select id="void-reason" name="reason" required>
                                <option value="">Choose a reason…</option>
                                @foreach ($voidReasons as $reason)
                                    <option value="{{ $reason }}">{{ $reason }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field full">
                            <label for="void-note">Note (optional)</label>
                            <input id="void-note" name="note" maxlength="120" placeholder="e.g. steak sent back as overcooked">
                        </div>
                    </div>
                    <div class="button-row">
                        <button class="btn secondary" type="button" data-dialog-close>Keep it</button>
                        <button class="btn danger" type="submit">Void and record as waste</button>
                    </div>
                </form>
            </div>
        </dialog>

        @if ($fired->isNotEmpty() && $paidMinor === 0)
            <dialog class="dialog" id="void-check-dialog">
                <div class="dialog-header">
                    <div><h2 class="panel-title">Void the whole check</h2></div>
                    <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
                </div>
                <div class="dialog-body">
                    <div class="waste-note">
                        Everything already sent ({{ $fired->count() }} line{{ $fired->count() === 1 ? '' : 's' }}) is recorded as waste,
                        anything not yet sent is dropped, and the table is marked for cleaning.
                    </div>
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.checks.void', $check) }}">
                        @csrf
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        <div class="form-grid">
                            <div class="field">
                                <label for="void-check-reason">Reason</label>
                                <select id="void-check-reason" name="reason" required>
                                    <option value="">Choose a reason…</option>
                                    @foreach ($voidReasons as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="void-check-note">Note (optional)</label>
                                <input id="void-check-note" name="note" maxlength="120">
                            </div>
                        </div>
                        <div class="button-row">
                            <button class="btn secondary" type="button" data-dialog-close>Keep the check</button>
                            <button class="btn danger" type="submit">Void check</button>
                        </div>
                    </form>
                </div>
            </dialog>
        @endif
        @endpermission

        @if ($canSplit)
            <dialog class="dialog" id="split-dialog">
                <div class="dialog-header">
                    <div>
                        <h2 class="panel-title">Split the bill</h2>
                        <p class="subtle">Tick what moves to a new bill at this table. To share one bill equally, don't split — use "Split evenly" when paying.</p>
                    </div>
                    <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
                </div>
                <div class="dialog-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.checks.split', $check) }}" data-split-form>
                        @csrf
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        <table class="dialog-table">
                            @foreach ($live as $item)
                                <tr>
                                    <td style="width:34px;"><input type="checkbox" data-split-check aria-label="Move {{ $item->item_name }}"></td>
                                    <td>
                                        <strong>{{ $item->item_name }}</strong>
                                        @if ($item->modifiers->isNotEmpty())
                                            <div class="subtle" style="font-size:.78rem;">{{ $item->modifiers->pluck('option_name')->implode(', ') }}</div>
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        <input type="number" name="moves[{{ $item->id }}]" min="0" max="{{ $qty($item->quantity) }}" step="any"
                                               value="{{ $qty($item->quantity) }}" disabled data-split-qty aria-label="How many {{ $item->item_name }}">
                                        <span class="subtle">of {{ $qty($item->quantity) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                        <div class="form-grid" style="margin-top:12px;">
                            <div class="field">
                                <label for="split-covers">Guests moving to the new bill</label>
                                <input id="split-covers" name="covers" type="number" min="1" max="{{ max(1, (int) $check->cover_count) }}" value="1">
                            </div>
                        </div>
                        <div class="button-row">
                            <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                            <button class="btn primary" type="submit" data-split-submit disabled>Move to a new bill</button>
                        </div>
                    </form>
                </div>
            </dialog>
        @endif

        @if ($canMerge)
            <dialog class="dialog" id="merge-dialog">
                <div class="dialog-header">
                    <div>
                        <h2 class="panel-title">Merge another bill into this one</h2>
                        <p class="subtle">Everything on the chosen bill moves here, and that bill is closed. Kitchen tickets follow the food.</p>
                    </div>
                    <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
                </div>
                <div class="dialog-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.checks.merge', $check) }}">
                        @csrf
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        @foreach ($mergeable as $other)
                            <label class="merge-option">
                                <input type="radio" name="source_check_id" value="{{ $other->id }}" required>
                                <span style="flex:1;">
                                    <strong>Table {{ $other->table?->name ?? '—' }}</strong>
                                    <span class="subtle">· {{ $other->order_number }} · {{ $other->cover_count }} guest{{ (int) $other->cover_count === 1 ? '' : 's' }}</span>
                                </span>
                                <strong>{{ $money($other->total_minor) }}</strong>
                            </label>
                        @endforeach
                        <div class="button-row">
                            <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                            <button class="btn primary" type="submit">Merge into this bill</button>
                        </div>
                    </form>
                </div>
            </dialog>
        @endif
    @endif

    <script>
        (function () {
            const MENU = @json($jsMenu);
            const grid = document.getElementById('menu-grid');
            const catBar = document.getElementById('cat-bar');
            const search = document.getElementById('menu-search');
            const form = document.getElementById('add-form');
            const pick = document.getElementById('pick-variant');
            const pickQty = document.getElementById('pick-qty');
            const pickMods = document.getElementById('pick-modifiers');
            const canAdd = @json($isOpen);
            if (!grid) return;

            const categories = ['All', ...Array.from(new Set(MENU.map((m) => m.category))).sort()];
            let activeCat = 'All';

            function money(minor) {
                return (minor / 100).toFixed(2);
            }

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function render() {
                const term = (search.value || '').trim().toLowerCase();
                const rows = MENU.filter((m) => {
                    const inCat = activeCat === 'All' || m.category === activeCat;
                    const inTerm = !term || (m.name + ' ' + (m.variant || '')).toLowerCase().includes(term);
                    return inCat && inTerm;
                });

                if (!rows.length) {
                    grid.innerHTML = '<div class="menu-empty subtle">Nothing matches. Clear the search or pick another category.</div>';
                    return;
                }

                grid.innerHTML = rows.map((m) => `
                    <button type="button" class="menu-item" data-variant="${m.id}" ${canAdd ? '' : 'disabled'}>
                        <div>
                            <div class="m-name">${escapeHtml(m.name)}</div>
                            ${m.variant ? `<div class="m-var">${escapeHtml(m.variant)}</div>` : ''}
                        </div>
                        <div class="m-price">${money(m.price)}${m.modifiers.length ? '<span class="m-choice">Choices</span>' : ''}</div>
                    </button>
                `).join('');
            }

            catBar.innerHTML = categories.map((c) => `<button type="button" class="cat-pill${c === 'All' ? ' on' : ''}" data-cat="${escapeHtml(c)}">${escapeHtml(c)}</button>`).join('');

            catBar.addEventListener('click', function (e) {
                const pill = e.target.closest('[data-cat]');
                if (!pill) return;
                activeCat = pill.dataset.cat;
                catBar.querySelectorAll('.cat-pill').forEach((p) => p.classList.toggle('on', p === pill));
                render();
            });

            search.addEventListener('input', render);

            function submitItem(variantId, quantity, optionIds) {
                pick.value = variantId;
                pickQty.value = quantity;
                pickMods.innerHTML = optionIds.map((id) => `<input type="hidden" name="items[0][modifiers][]" value="${id}">`).join('');
                form.submit();
            }

            // ---- Modifier sheet ----------------------------------------------------
            const sheet = document.getElementById('modifier-sheet');
            const sheetGroups = sheet.querySelector('[data-sheet-groups]');
            const sheetAdd = sheet.querySelector('[data-sheet-add]');
            const sheetTotal = sheet.querySelector('[data-sheet-total]');
            const sheetQty = sheet.querySelector('[data-sheet-qty]');
            let current = null;
            let chosen = {};
            let quantity = 1;

            function groupSatisfied(group) {
                const count = (chosen[group.id] || []).length;
                return count >= group.min && (group.max === null || count <= group.max);
            }

            function refreshSheet() {
                let unit = current.price;
                current.modifiers.forEach((group) => {
                    (chosen[group.id] || []).forEach((id) => {
                        const option = group.options.find((o) => o.id === id);
                        if (option) unit += option.price;
                    });
                    const rule = sheetGroups.querySelector(`[data-rule="${group.id}"]`);
                    if (rule) {
                        rule.classList.toggle('need', group.min > 0 && !groupSatisfied(group));
                        rule.classList.toggle('done', group.min > 0 && groupSatisfied(group));
                    }
                });
                unit = Math.max(0, unit);
                sheetQty.textContent = quantity;
                sheetTotal.textContent = money(unit * quantity);
                const ready = current.modifiers.every(groupSatisfied);
                sheetAdd.disabled = !ready;
                sheetAdd.textContent = ready ? 'Add to check' : 'Choose the required options';
            }

            function openSheet(item) {
                current = item;
                chosen = {};
                quantity = 1;
                sheet.querySelector('[data-sheet-title]').textContent = item.name + (item.variant ? ' — ' + item.variant : '');
                sheet.querySelector('[data-sheet-base]').textContent = 'From ' + money(item.price);
                sheetGroups.innerHTML = item.modifiers.map((group) => `
                    <div class="mod-group">
                        <div class="mod-group-head">
                            <h3>${escapeHtml(group.name)}</h3>
                            <span class="mod-rule" data-rule="${group.id}">${escapeHtml(group.rule)}</span>
                        </div>
                        <div class="mod-options">
                            ${group.options.map((o) => `
                                <button type="button" class="mod-option" data-group="${group.id}" data-option="${o.id}" aria-pressed="false">
                                    ${escapeHtml(o.name)}
                                    <small>${o.price === 0 ? 'No charge' : (o.price > 0 ? '+' : '−') + money(Math.abs(o.price))}</small>
                                </button>`).join('')}
                        </div>
                    </div>`).join('');
                refreshSheet();
                if (typeof sheet.showModal === 'function') sheet.showModal(); else sheet.setAttribute('open', '');
            }

            sheetGroups.addEventListener('click', function (e) {
                const button = e.target.closest('[data-option]');
                if (!button) return;
                const group = current.modifiers.find((g) => g.id === Number(button.dataset.group));
                const id = Number(button.dataset.option);
                let list = chosen[group.id] || [];

                if (list.includes(id)) {
                    list = list.filter((x) => x !== id);
                } else if (group.max === 1) {
                    list = [id]; // pick-one groups behave like radio buttons
                } else if (group.max === null || list.length < group.max) {
                    list = [...list, id];
                }

                chosen[group.id] = list;
                sheetGroups.querySelectorAll(`[data-group="${group.id}"]`).forEach((b) => {
                    const on = list.includes(Number(b.dataset.option));
                    b.classList.toggle('on', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                refreshSheet();
            });

            sheet.querySelectorAll('[data-step]').forEach((b) => b.addEventListener('click', function () {
                quantity = Math.max(1, Math.min(99, quantity + Number(b.dataset.step)));
                refreshSheet();
            }));

            sheetAdd.addEventListener('click', function () {
                if (sheetAdd.disabled) return;
                submitItem(current.id, quantity, Object.values(chosen).flat());
            });

            // One tap adds one of a plain item — the fastest possible path during service.
            // An item with choices opens the sheet instead.
            grid.addEventListener('click', function (e) {
                const button = e.target.closest('[data-variant]');
                if (!button || !canAdd) return;
                const item = MENU.find((m) => m.id === Number(button.dataset.variant));
                if (!item) return;
                if (item.modifiers.length) openSheet(item); else submitItem(item.id, 1, []);
            });

            render();

            // ---- Void a sent line ---------------------------------------------------
            const voidForm = document.querySelector('[data-void-form]');
            document.querySelectorAll('[data-void-item]').forEach((button) => button.addEventListener('click', function () {
                if (!voidForm) return;
                const quantityOnBill = parseFloat(button.dataset.voidQty) || 1;
                voidForm.action = voidForm.dataset.actionTemplate.replace('__ITEM__', button.dataset.voidItem);
                document.querySelector('[data-void-title]').textContent = button.dataset.voidName;
                document.querySelector('[data-void-of]').textContent = button.dataset.voidQty;
                const qtyInput = voidForm.querySelector('[data-void-qty]');
                qtyInput.value = button.dataset.voidQty;
                qtyInput.max = quantityOnBill;
                voidForm.querySelector('[data-void-qty-field]').hidden = quantityOnBill <= 1;
            }));

            // ---- Split -------------------------------------------------------------
            const splitForm = document.querySelector('[data-split-form]');
            if (splitForm) {
                const submit = splitForm.querySelector('[data-split-submit]');
                const sync = () => {
                    let any = false;
                    splitForm.querySelectorAll('tr').forEach((row) => {
                        const box = row.querySelector('[data-split-check]');
                        const input = row.querySelector('[data-split-qty]');
                        input.disabled = !box.checked; // unticked lines are not sent at all
                        if (box.checked && parseFloat(input.value) > 0) any = true;
                    });
                    submit.disabled = !any;
                };
                splitForm.addEventListener('change', sync);
                splitForm.addEventListener('input', sync);
            }

            // ---- Payment -----------------------------------------------------------
            const payForm = document.querySelector('[data-pay-form]');
            if (payForm) {
                const balance = parseInt(payForm.dataset.balance, 10) || 0;
                const method = payForm.querySelector('[data-pay-method]');
                const amount = payForm.querySelector('[data-pay-amount]');
                const received = payForm.querySelector('[data-pay-received]');
                const change = payForm.querySelector('[data-change-due]');
                const note = payForm.querySelector('[data-split-note]');

                const isCash = () => (method.value || '').toLowerCase().includes('cash');
                const syncMethod = () => {
                    payForm.querySelectorAll('[data-cash-only]').forEach((el) => { el.hidden = !isCash(); });
                    payForm.querySelectorAll('[data-noncash-only]').forEach((el) => { el.hidden = isCash(); });
                    syncChange();
                };
                const syncChange = () => {
                    const got = Math.round((parseFloat(received.value) || 0) * 100);
                    const due = Math.round((parseFloat(amount.value) || 0) * 100);
                    change.textContent = isCash() && got > due ? 'Change: ' + money(got - due) : '';
                };

                method.addEventListener('change', syncMethod);
                amount.addEventListener('input', syncChange);
                received.addEventListener('input', function () {
                    // Cash handed over covers at most what is owed; the rest is change.
                    const got = Math.round((parseFloat(received.value) || 0) * 100);
                    if (got > 0) amount.value = money(Math.min(got, balance));
                    syncChange();
                });

                payForm.querySelectorAll('[data-split-ways]').forEach((b) => b.addEventListener('click', function () {
                    const ways = Number(b.dataset.splitWays);
                    const share = Math.min(balance, Math.ceil(balance / ways));
                    amount.value = money(share);
                    note.textContent = ways + ' ways · ' + money(share) + ' each';
                    syncChange();
                }));

                syncMethod();
            }
        })();
    </script>
</x-layouts.admin>
