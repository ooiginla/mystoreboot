<?php

declare(strict_types=1);

namespace Modules\Business\Enums;

enum BusinessType: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';
    case ServiceBusiness = 'service_business';
    case RestaurantFood = 'restaurant_food';
    case Pharmacy = 'pharmacy';
    case Supermarket = 'supermarket';
    case FashionStore = 'fashion_store';
    case ElectronicsStore = 'electronics_store';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail',
            self::Wholesale => 'Wholesale',
            self::ServiceBusiness => 'Service business',
            self::RestaurantFood => 'Restaurant / food',
            self::Pharmacy => 'Pharmacy',
            self::Supermarket => 'Supermarket',
            self::FashionStore => 'Fashion store',
            self::ElectronicsStore => 'Electronics store',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
