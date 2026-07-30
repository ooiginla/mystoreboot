@php
    $badgeVariant = $product->variants->sortBy('selling_price_minor')->first();
    $badgePrice = (int) ($badgeVariant?->selling_price_minor ?? $product->base_price_minor);
    $badgeComparePrice = (int) ($badgeVariant?->compare_at_price_minor ?? $product->compare_at_price_minor ?? 0);
    $displayBadges = collect();

    if ($badgeComparePrice > $badgePrice) {
        $displayBadges->push([
            'name' => 'Sale',
            'background_color' => '#111827',
            'text_color' => '#ffffff',
        ]);
    }

    if ($product->relationLoaded('badges')) {
        $product->badges
            ->where('is_visible', true)
            ->reject(fn ($badge) => $displayBadges->contains(fn (array $item): bool => strtolower($item['name']) === strtolower($badge->name)))
            ->each(function ($badge) use ($displayBadges): void {
                if ($displayBadges->count() >= 2) {
                    return;
                }

                $displayBadges->push([
                    'name' => $badge->name,
                    'background_color' => $badge->background_color,
                    'text_color' => $badge->text_color,
                ]);
            });
    }
@endphp

@if ($displayBadges->isNotEmpty())
    <div class="absolute left-3 top-3 z-10 flex max-w-[calc(100%-1.5rem)] flex-wrap gap-1.5">
        @foreach ($displayBadges as $badge)
            <span class="sf-label-md rounded-full px-3 py-1" style="background: {{ $badge['background_color'] }}; color: {{ $badge['text_color'] }};">{{ $badge['name'] }}</span>
        @endforeach
    </div>
@endif
