<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Only sellable-point locations can be used by the POS. Existing branch locations are
 * where retail sells today, so mark them sellable to preserve current behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_locations')->where('location_type', 'branch')->update(['is_sellable_point' => true]);
    }

    public function down(): void
    {
        // Leave the flags in place.
    }
};
