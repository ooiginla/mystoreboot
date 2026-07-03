{{-- Framed product demo with a play button that opens the lightbox video --}}
<div class="relative mx-auto max-w-4xl">
    <div class="pointer-events-none absolute -inset-4 -z-10 rounded-[36px] bg-gradient-to-br from-brand-400/20 via-transparent to-accent-400/20 blur-2xl"></div>

    <button type="button" onclick="sbOpenVideo()" aria-label="Play product demo"
        class="group relative block w-full overflow-hidden rounded-[26px] border border-zinc-200/80 bg-white text-left shadow-2xl shadow-brand-950/10 dark:border-white/10 dark:bg-ink-900 dark:shadow-black/40">
        {{-- top bar --}}
        <div class="flex items-center gap-2 border-b border-zinc-100 bg-zinc-50/80 px-4 py-3 dark:border-white/5 dark:bg-white/[0.03]">
            <span class="h-3 w-3 rounded-full bg-red-400/80"></span>
            <span class="h-3 w-3 rounded-full bg-amber-400/80"></span>
            <span class="h-3 w-3 rounded-full bg-green-400/80"></span>
            <span class="mx-auto text-xs font-medium text-zinc-400">Storeboot — 90 second tour</span>
        </div>

        {{-- animated preview surface --}}
        <div class="relative aspect-video overflow-hidden bg-gradient-to-br from-zinc-50 to-white dark:from-ink-900 dark:to-ink-950">
            <div class="sb-grid-bg absolute inset-0 text-brand-500/50"></div>

            {{-- floating faux cards --}}
            <div class="sb-float absolute left-6 top-8 w-40 rounded-xl border border-zinc-200/80 bg-white/90 p-3 shadow-lg backdrop-blur dark:border-white/10 dark:bg-ink-800/80">
                <p class="text-[10px] text-zinc-400">New sale</p>
                <p class="font-display text-sm font-bold text-zinc-900 dark:text-white">₦24,500</p>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10"><div class="h-full w-2/3 rounded-full bg-brand-500"></div></div>
            </div>
            <div class="sb-float-slow absolute bottom-8 right-6 w-44 rounded-xl border border-zinc-200/80 bg-white/90 p-3 shadow-lg backdrop-blur dark:border-white/10 dark:bg-ink-800/80">
                <p class="text-[10px] text-zinc-400">Weekly revenue</p>
                <div class="mt-1.5 flex h-10 items-end gap-1">
                    @foreach ([40,65,50,80,60,90] as $h)
                        <div class="flex-1 rounded-sm bg-brand-400" style="height: {{ $h }}%"></div>
                    @endforeach
                </div>
            </div>
            <div class="absolute left-1/2 top-1/2 hidden w-48 -translate-x-1/2 -translate-y-1/2 rounded-xl border border-zinc-200/80 bg-white/90 p-3 shadow-lg backdrop-blur sm:block dark:border-white/10 dark:bg-ink-800/80">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500/15 text-brand-600 dark:text-brand-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    </span>
                    <div><p class="text-[10px] text-zinc-400">Synced to cloud</p><p class="text-xs font-bold text-zinc-900 dark:text-white">All branches</p></div>
                </div>
            </div>

            {{-- play button --}}
            <span class="absolute inset-0 grid place-items-center">
                <span class="relative grid h-20 w-20 place-items-center">
                    <span class="sb-pulse-ring absolute inline-flex h-full w-full rounded-full bg-brand-500/40"></span>
                    <span class="relative grid h-16 w-16 place-items-center rounded-full bg-brand-600 text-white shadow-xl shadow-brand-600/40 transition group-hover:scale-110">
                        <svg class="h-7 w-7 translate-x-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7L8 5Z"/></svg>
                    </span>
                </span>
            </span>
        </div>
    </button>
</div>
