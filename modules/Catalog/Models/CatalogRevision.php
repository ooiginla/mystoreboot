<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class CatalogRevision extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    protected $keyType = 'string';
}
