<?php

declare(strict_types=1);

namespace Modules\Inventory\Enums;

enum InventoryMovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Damaged = 'damaged';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stock-in',
            self::StockOut => 'Stock-out',
            self::AdjustmentIn => 'Adjustment in',
            self::AdjustmentOut => 'Adjustment out',
            self::TransferOut => 'Transfer out',
            self::TransferIn => 'Transfer in',
            self::Damaged => 'Damaged stock',
            self::Returned => 'Returned stock',
        };
    }

    public function stockDeltaSign(): int
    {
        return match ($this) {
            self::StockIn, self::AdjustmentIn, self::TransferIn, self::Returned => 1,
            self::StockOut, self::AdjustmentOut, self::TransferOut, self::Damaged => -1,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->reject(fn (self $type): bool => in_array($type, [self::TransferIn, self::TransferOut], true))
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
