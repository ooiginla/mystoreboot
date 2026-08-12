<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_the_ai_assisted_section(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="ai"', false)
            ->assertSee('Let AI do the busywork of setting up shop.')
            ->assertSee('Turn photos into products')
            ->assertSee('Generate product images')
            ->assertSee('Write your store pages')
            ->assertSee('Get found on Google')
            ->assertSee('data-marketing-menu-label>Menu</span>', false)
            ->assertSee('href="#ai"', false);
    }
}
