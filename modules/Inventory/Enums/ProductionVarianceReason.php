<?php

declare(strict_types=1);

namespace Modules\Inventory\Enums;

enum ProductionVarianceReason: string
{
    case Burnt = 'burnt';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Overproduction = 'overproduction';
    case WrongRecipe = 'wrong_recipe';
    case CustomerReturn = 'customer_return';
    case QualityFailure = 'quality_failure';
    case StaffConsumption = 'staff_consumption';
    case UnknownLoss = 'unknown_loss';
    case Weather = 'weather';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Burnt => 'Burnt',
            self::Damaged => 'Damaged',
            self::Expired => 'Expired',
            self::Overproduction => 'Overproduction',
            self::WrongRecipe => 'Wrong recipe',
            self::CustomerReturn => 'Customer return',
            self::QualityFailure => 'Quality failure',
            self::StaffConsumption => 'Staff consumption',
            self::UnknownLoss => 'Unknown loss',
            self::Weather => 'Weather',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $reason): array => [$reason->value => $reason->label()],
        )->all();
    }
}
