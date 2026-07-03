{{-- Multi-branch roll-up visual on the dark ink card --}}
<div class="rounded-3xl border border-white/10 bg-white/[0.03] p-5">
    <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-white">All branches</p>
        <span class="rounded-full bg-brand-500/20 px-2.5 py-1 text-xs font-semibold text-brand-300">Synced 2m ago</span>
    </div>

    <div class="mt-4 space-y-3">
        @foreach ([
            ['Ikeja Main', '₦1.82M', 92, 'text-brand-300'],
            ['Lekki Branch', '₦1.14M', 68, 'text-accent-300'],
            ['Surulere Outlet', '₦840K', 48, 'text-sky-300'],
        ] as [$name, $rev, $pct, $color])
            <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-3.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/5 {{ $color }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V9l6-4 6 4v12M9 21v-4h2v4"/></svg>
                        </span>
                        <span class="text-sm font-medium text-white">{{ $name }}</span>
                    </div>
                    <span class="font-display text-sm font-bold text-white">{{ $rev }}</span>
                </div>
                <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-accent-400" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4 flex items-center justify-between rounded-2xl bg-gradient-to-r from-brand-600 to-brand-700 p-4">
        <div>
            <p class="text-xs text-white/70">Total business revenue</p>
            <p class="font-display text-xl font-bold text-white">₦3.80M</p>
        </div>
        <svg class="h-8 w-8 text-white/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>
    </div>
</div>
