{{-- Faux analytics widget on the emerald card --}}
<div class="mt-6 rounded-2xl bg-white/10 p-4 backdrop-blur">
    <div class="flex items-baseline justify-between">
        <p class="font-display text-3xl font-bold text-white">₦4.8M</p>
        <span class="rounded-full bg-white/15 px-2 py-0.5 text-xs font-semibold text-accent-300">+24%</span>
    </div>
    <p class="text-xs text-white/70">Revenue this month</p>

    {{-- line chart --}}
    <svg viewBox="0 0 260 90" class="mt-4 w-full" fill="none" preserveAspectRatio="none">
        <defs>
            <linearGradient id="sbArea" x1="0" y1="0" x2="0" y2="1">
                <stop stop-color="#c2f75a" stop-opacity="0.45" />
                <stop offset="1" stop-color="#c2f75a" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path d="M0 70 L40 60 L80 66 L120 40 L160 48 L200 22 L260 30 L260 90 L0 90 Z" fill="url(#sbArea)" />
        <path d="M0 70 L40 60 L80 66 L120 40 L160 48 L200 22 L260 30" stroke="#e9ff9e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="200" cy="22" r="4" fill="#fff" />
    </svg>

    <div class="mt-4 grid grid-cols-2 gap-2">
        @foreach ([['Best day', 'Saturday'], ['Avg. basket', '₦14,200']] as [$k, $v])
            <div class="rounded-xl bg-white/10 p-3">
                <p class="text-[11px] text-white/60">{{ $k }}</p>
                <p class="text-sm font-bold text-white">{{ $v }}</p>
            </div>
        @endforeach
    </div>
</div>
