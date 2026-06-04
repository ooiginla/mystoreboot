<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;

final class Module extends Model
{
    protected $guarded = [];

    protected $table = 'billable_modules';

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
