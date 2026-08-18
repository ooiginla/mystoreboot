<?php

namespace Tests\Feature;

use Tests\TestCase;

final class TestDatabaseSafetyTest extends TestCase
{
    public function test_test_suite_uses_an_uncached_in_memory_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertFalse(app()->configurationIsCached());
    }
}
