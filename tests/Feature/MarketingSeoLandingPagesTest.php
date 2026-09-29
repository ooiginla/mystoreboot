<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\SeoLandingPages;
use Tests\TestCase;

final class MarketingSeoLandingPagesTest extends TestCase
{
    public function test_every_seo_solution_page_is_public_and_complete(): void
    {
        foreach (SeoLandingPages::pages() as $slug => $page) {
            $response = $this->get(route('solutions.'.$slug));

            $response->assertOk()
                ->assertSee($page['h1'])
                ->assertSee($page['meta_description'], false)
                ->assertSee('rel="canonical"', false)
                ->assertSee('SoftwareApplication')
                ->assertSee('FAQPage')
                ->assertSee('BreadcrumbList')
                ->assertSee(asset($page['image']), false)
                ->assertSee('Start free');

            $this->assertGreaterThan(
                1500,
                str_word_count(strip_tags($response->getContent())),
                "The {$slug} page should contain substantial, useful copy."
            );
        }
    }

    public function test_marketing_sitemap_contains_every_solution_page(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml');

        foreach (SeoLandingPages::slugs() as $slug) {
            $response->assertSee(route('solutions.'.$slug), false);
        }
    }
}
