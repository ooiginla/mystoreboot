{{-- Faux Storeboot dashboard — themed for light & dark --}}
<div class="flex bg-zinc-50/60 dark:bg-ink-950/40">
    {{-- sidebar --}}
    <aside class="hidden w-16 shrink-0 flex-col items-center gap-4 border-r border-zinc-100 py-5 sm:flex dark:border-white/5">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 text-white"><x-brand-logo mark-only class="scale-[0.7]" /></span>
        @foreach (['M4 7h16M4 7l1-3h14l1 3M4 7v12h16V7', 'M9 3v18m6-18v18M3 9h18', 'M15 19v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M3 3v18h18M7 14l3-3 3 3 5-6'] as $n => $d)
            <span class="grid h-9 w-9 place-items-center rounded-xl {{ $n === 0 ? 'bg-brand-500/12 text-brand-600 dark:text-brand-400' : 'text-zinc-400' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}"/></svg>
            </span>
        @endforeach
    </aside>

    {{-- main --}}
    <div class="min-w-0 flex-1 p-5 sm:p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-zinc-400">Good morning, Amaka 👋</p>
                <h4 class="font-display text-base font-bold text-zinc-900 dark:text-white">Business overview</h4>
            </div>
            <span class="hidden items-center gap-1.5 rounded-full bg-brand-500/12 px-2.5 py-1 text-xs font-semibold text-brand-700 sm:inline-flex dark:text-brand-300">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span> Live
            </span>
        </div>

        {{-- stat cards --}}
        <div class="mt-4 grid grid-cols-3 gap-3">
            @foreach ([['Revenue', '₦1.24M', '+18%'], ['Orders', '342', '+9%'], ['Profit', '₦486K', '+22%']] as [$label, $val, $delta])
                <div class="rounded-xl border border-zinc-100 bg-white p-3 dark:border-white/5 dark:bg-white/[0.03]">
                    <p class="text-[11px] text-zinc-400">{{ $label }}</p>
                    <p class="mt-1 font-display text-base font-bold text-zinc-900 dark:text-white">{{ $val }}</p>
                    <p class="mt-0.5 text-[11px] font-semibold text-brand-600 dark:text-brand-400">{{ $delta }}</p>
                </div>
            @endforeach
        </div>

        {{-- chart + list --}}
        <div class="mt-3 grid gap-3 sm:grid-cols-5">
            <div class="rounded-xl border border-zinc-100 bg-white p-4 sm:col-span-3 dark:border-white/5 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Sales this week</p>
                    <p class="text-[11px] text-zinc-400">Mon–Sun</p>
                </div>
                <div class="mt-4 flex h-28 items-end justify-between gap-2">
                    @foreach ([45, 62, 38, 78, 55, 92, 70] as $h)
                        <div class="flex flex-1 flex-col items-center gap-1.5">
                            <div class="w-full origin-bottom rounded-md bg-gradient-to-t from-brand-500 to-brand-400" style="height: {{ $h }}%; animation: sb-bar 1s cubic-bezier(.22,1,.36,1) both;"></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="rounded-xl border border-zinc-100 bg-white p-4 sm:col-span-2 dark:border-white/5 dark:bg-white/[0.03]">
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Top products</p>
                <ul class="mt-3 space-y-2.5">
                    @foreach ([['Rice 50kg', '₦186K', 'bg-brand-500'], ['Cooking Oil', '₦142K', 'bg-accent-500'], ['Milk Pack', '₦98K', 'bg-sky-400']] as [$name, $amt, $dot])
                        <li class="flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $name }}</span>
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ $amt }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
