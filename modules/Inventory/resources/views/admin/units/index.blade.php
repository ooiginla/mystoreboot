<x-layouts.admin title="Units of measure">
    <div class="topbar">
        <div>
            <div class="eyebrow">Advanced inventory</div>
            <h1>Units of measure</h1>
            <p class="subtle">Measurement categories and units for {{ $tenant->name }}. Assign a category to a product or raw material to stock and trade in these units.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.inventory.units.index') }}" style="min-width: 260px;">
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

    @include('inventory::admin.units.partials.units-manage')
</x-layouts.admin>
