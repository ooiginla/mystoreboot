<?php

declare(strict_types=1);

namespace Modules\Sales\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Dirty = 'dirty';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Reserved => 'Reserved',
            self::Dirty => 'Needs cleaning',
        };
    }

    /**
     * Colour token for the table map. Paired with a text label everywhere it is used —
     * colour is never the only signal.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Available => 'free',
            self::Occupied => 'busy',
            self::Reserved => 'held',
            self::Dirty => 'dirty',
        };
    }

    public function canSeat(): bool
    {
        return $this !== self::Occupied;
    }
}
