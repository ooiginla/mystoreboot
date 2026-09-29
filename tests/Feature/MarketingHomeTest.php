<?php

namespace Tests\Feature;

use App\Support\SeoLandingPages;
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

    public function test_home_page_exposes_every_solution_in_navigation_and_visual_cards(): void
    {
        $response = $this->get('/')
            ->assertOk()
            ->assertSee('Solutions for the way you work')
            ->assertSee('aria-haspopup="true"', false)
            ->assertSee('SoftwareApplication');

        foreach (SeoLandingPages::pages() as $slug => $page) {
            $response
                ->assertSee(route('solutions.'.$slug), false)
                ->assertSee(asset($page['image']), false);
        }
    }
}
