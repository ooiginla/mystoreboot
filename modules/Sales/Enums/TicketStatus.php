<?php

declare(strict_types=1);

namespace Modules\Sales\Enums;

enum TicketStatus: string
{
    case Queued = 'queued';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'New',
            self::Preparing => 'Cooking',
            self::Ready => 'Ready',
            self::Served => 'Served',
            self::Cancelled => 'Cancelled',
        };
    }

    /** The next state a kitchen hand can bump this ticket to, if any. */
    public function next(): ?self
    {
        return match ($this) {
            self::Queued => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => self::Served,
            default => null,
        };
    }

    public function nextLabel(): ?string
    {
        return match ($this) {
            self::Queued => 'Start cooking',
            self::Preparing => 'Mark ready',
            self::Ready => 'Mark served',
            default => null,
        };
    }

    /** Still occupying the kitchen's attention. */
    public function isLive(): bool
    {
        return in_array($this, [self::Queued, self::Preparing, self::Ready], true);
    }
}
