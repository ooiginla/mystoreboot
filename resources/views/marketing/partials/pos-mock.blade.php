{{-- Faux point-of-sale screen --}}
<div class="mt-6 grid grid-cols-1 gap-3 rounded-t-2xl border-t border-zinc-100 bg-zinc-50/60 p-4 sm:grid-cols-5 dark:border-white/5 dark:bg-ink-950/40">
    {{-- product grid --}}
    <div class="sm:col-span-3">
        <div class="grid grid-cols-3 gap-2">
            @foreach ([['Rice', '₦45k', 'bg-amber-100 text-amber-700'], ['Oil', '₦8.5k', 'bg-brand-100 text-brand-700'], ['Milk', '₦3.2k', 'bg-sky-100 text-sky-700'], ['Bread', '₦1.2k', 'bg-orange-100 text-orange-700'], ['Sugar', '₦2.8k', 'bg-rose-100 text-rose-700'], ['Soap', '₦900', 'bg-violet-100 text-violet-700']] as [$name, $price, $c])
                <div class="rounded-xl border border-zinc-100 bg-white p-2.5 dark:border-white/5 dark:bg-white/[0.03]">
                    <div class="mb-2 grid h-9 w-9 place-items-center rounded-lg {{ $c }} text-xs font-bold dark:bg-white/5 dark:text-zinc-200">{{ substr($name, 0, 2) }}</div>
                    <p class="truncate text-xs font-semibold text-zinc-800 dark:text-zinc-100">{{ $name }}</p>
                    <p class="text-[11px] text-zinc-400">{{ $price }}</p>
                </div>
            @endforeach
        </div>
    </div>
    {{-- cart --}}
    <div class="flex flex-col rounded-xl border border-zinc-100 bg-white p-3 sm:col-span-2 dark:border-white/5 dark:bg-white/[0.03]">
        <p class="text-xs font-bold text-zinc-800 dark:text-white">Current sale</p>
        <ul class="mt-2 flex-1 space-y-2">
            @foreach ([['Rice 50kg', '₦45,000'], ['Cooking Oil', '₦8,500'], ['Milk Pack ×2', '₦6,400']] as [$n, $p])
                <li class="flex items-center justify-between text-[11px]">
                    <span class="text-zinc-600 dark:text-zinc-300">{{ $n }}</span>
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ $p }}</span>
                </li>
            @endforeach
        </ul>
        <div class="mt-2 flex items-center justify-between border-t border-zinc-100 pt-2 dark:border-white/5">
            <span class="text-xs text-zinc-500">Total</span>
            <span class="font-display text-sm font-bold text-zinc-900 dark:text-white">₦59,900</span>
        </div>
        <div class="mt-2 rounded-lg bg-brand-600 py-2 text-center text-xs font-semibold text-white">Charge ₦59,900</div>
    </div>
</div>
