<?php

declare(strict_types=1);

namespace Modules\Inventory\Support;

use Modules\Catalog\Models\ProductVariant;
use RuntimeException;

/**
 * Resolves which inventory location a sales-order line depletes from. One customer,
 * one bill, but lines can fan out to different stores (grill store, kitchen store),
 * so depletion is decided per line:
 *
 *   explicit line location → the product's prep station → the caller's fallback
 *   store → (nothing).
 *
 * Since prep stations became flagged inventory locations, the station a product is made
 * at *is* the store its ingredients come from — the two can no longer disagree.
 */
final class LocationResolver
{
    public function resolveForLine(
        ?int $explicitLocationId,
        ?ProductVariant $variant,
        ?int $fallbackLocationId = null,
    ): ?int {
        if ($explicitLocationId !== null) {
            return $explicitLocationId;
        }

        $stationLocationId = $variant?->product?->prep_location_id;
        if ($stationLocationId !== null) {
            return (int) $stationLocationId;
        }

        return $fallbackLocationId !== null ? (int) $fallbackLocationId : null;
    }

    public function resolveForLineOrFail(
        ?int $explicitLocationId,
        ?ProductVariant $variant,
        ?int $fallbackLocationId = null,
    ): int {
        $locationId = $this->resolveForLine($explicitLocationId, $variant, $fallbackLocationId);

        if ($locationId === null) {
            throw new RuntimeException(
                'No inventory location could be resolved for this line: set a line location, a prep station for the product, or a store on the service area.',
            );
        }

        return $locationId;
    }
}
