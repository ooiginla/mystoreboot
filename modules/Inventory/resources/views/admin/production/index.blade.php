@php
    use Modules\Catalog\Enums\ProductType;
    $tenantParam = request('tenant');
@endphp

<x-layouts.admin title="Production & Recipes">
    <div class="topbar">
        <div>
            <div class="eyebrow">Kitchen production</div>
            <h1>Finished products</h1>
            <p class="subtle">Items you produce from recipes for {{ $tenant->name }}. Open one to manage its recipe, margins and production history.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.inventory.production.index') }}" style="min-width: 260px;">
                <select name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <div style="margin-bottom:16px;">
        <button class="btn primary" type="button" data-dialog-open="add-finished-dialog">Add finished product</button>
    </div>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Finished products</h2></div>
        <div class="panel-body">
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead><tr><th>Name</th><th>Type</th><th>Recipe</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($finished as $item)
                            @php $fv = $item->variants->first(); $hasRecipe = $fv && in_array($fv->id, $recipeVariantIds, true); @endphp
                            <tr>
                                <td><a href="{{ route('admin.inventory.production.show', array_filter(['product' => $item->id, 'tenant' => $tenantParam ?: null])) }}">{{ $item->name }}</a></td>
                                <td>{{ $item->product_type?->label() }}</td>
                                <td>@if ($hasRecipe)<span class="badge success">Set</span>@else<span class="badge warning">Not set</span>@endif</td>
                                <td style="text-align:right; display:flex; gap:8px; justify-content:flex-end;">
                                    <a class="btn accent" href="{{ route('admin.inventory.production.show', array_filter(['product' => $item->id, 'tenant' => $tenantParam ?: null])) }}">Open</a>
                                    <form method="POST" action="{{ route('admin.inventory.production.finished-products.destroy', $item->id) }}" onsubmit="return confirm('Remove this finished product? Its recipe is kept but it leaves this list.');">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                        <button class="btn ghost" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="subtle">No finished products yet. Add one to build its recipe.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <dialog class="dialog" id="add-finished-dialog">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Add finished product</h2>
                <p class="subtle">Pick an existing product or raw material to produce via a recipe.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.inventory.production.finished-products.store') }}">
                @csrf
                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                <div class="form-grid">
                    <div class="field">
                        <label>Product type</label>
                        <select data-finished-type>
                            <option value="product">Product</option>
                            <option value="raw_material">Raw material</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Finished product</label>
                        <select name="product_variant_id" data-finished-item required></select>
                    </div>
                </div>
                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Add</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        (function () {
            const ADDABLE = @json($jsAddable);
            const typeSel = document.querySelector('[data-finished-type]');
            const itemSel = document.querySelector('[data-finished-item]');
            if (! typeSel || ! itemSel) return;
            function fill() {
                const type = typeSel.value;
                itemSel.innerHTML = '';
                itemSel.add(new Option('Select…', ''));
                ADDABLE.filter((i) => ! type || String(i.type) === String(type)).forEach((i) => itemSel.add(new Option(i.label, i.id)));
            }
            typeSel.addEventListener('change', fill);
            fill();
        })();
    </script>
</x-layouts.admin>
