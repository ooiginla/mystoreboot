@props([
    'markOnly' => false,
    'wordClass' => 'text-zinc-900 dark:text-white',
])

{{-- Storeboot logo: an ascending "boot-up" bar chart that resolves into an upward arrow,
     set on a gradient tile. Reads as store growth + momentum. Works on light & dark. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 font-display font-bold tracking-tight select-none']) }}>
    <span class="relative grid place-items-center">
        <svg viewBox="0 0 40 40" width="36" height="36" fill="none" aria-hidden="true" class="drop-shadow-sm">
            <defs>
                <linearGradient id="sbLogoTile" x1="6" y1="4" x2="34" y2="36" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#22dd85" />
                    <stop offset="0.55" stop-color="#06c168" />
                    <stop offset="1" stop-color="#009a53" />
                </linearGradient>
                <linearGradient id="sbLogoSpark" x1="12" y1="28" x2="30" y2="10" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#ffffff" />
                    <stop offset="1" stop-color="#eafff4" />
                </linearGradient>
            </defs>
            <rect x="2" y="2" width="36" height="36" rx="11" fill="url(#sbLogoTile)" />
            <rect x="2" y="2" width="36" height="36" rx="11" fill="#000" opacity="0.06" />
            {{-- ascending bars --}}
            <rect x="10.5" y="22.5" width="4.4" height="8" rx="2" fill="#ffffff" opacity="0.9" />
            <rect x="17.8" y="18" width="4.4" height="12.5" rx="2" fill="#ffffff" opacity="0.9" />
            <rect x="25.1" y="13.5" width="4.4" height="17" rx="2" fill="#ffffff" opacity="0.55" />
            {{-- upward arrow tracing the growth --}}
            <path d="M11 24.5 L20 17 L27.5 20 L31 11.5" stroke="url(#sbLogoSpark)" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M26 11 L31.5 11 L31.5 16.5" stroke="url(#sbLogoSpark)" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
    @unless ($markOnly)
        <span class="text-xl {{ $wordClass }}">Store<span class="sb-text-gradient">boot</span></span>
    @endunless
</span>
