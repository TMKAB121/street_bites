<?php

declare(strict_types=1);

use App\Models\FoodTruck;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// /sitemap.xml — the crawler-facing index of public pages (robots.txt points
// here). Static pages plus every published truck's canonical detail URL.

it('serves valid XML with the static pages', function (): void {
    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse();

    // Children live in the sitemap default namespace — SimpleXML property
    // access ($xml->url) only sees un-namespaced children. Collected with a
    // foreach because every child shares the key 'url', which collapses under
    // iterator_to_array/collect().
    $locs = [];

    foreach ($xml->children('http://www.sitemaps.org/schemas/sitemap/0.9')->url as $url) {
        $locs[] = (string) $url->loc;
    }

    expect($locs)->toContain(route('home'))
        ->toContain(route('about'))
        ->toContain(route('news.index'));
});

it('lists published trucks with their canonical slug URL and lastmod', function (): void {
    $truck = FoodTruck::factory()->published()->create(['name' => 'Taco Titan']);

    $response = $this->get('/sitemap.xml')->assertOk();

    $response->assertSee(route('trucks.show', [$truck, $truck->slug]), false);
    $response->assertSee('<lastmod>'.$truck->updated_at?->toAtomString().'</lastmod>', false);
});

it('excludes unpublished trucks', function (): void {
    $truck = FoodTruck::factory()->create(['name' => 'Hidden Hauler', 'is_published' => false]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee(route('trucks.show', [$truck, $truck->slug]), false);
});

it('lists published posts with their canonical slug URL and lastmod', function (): void {
    $post = Post::factory()->published()->create(['title' => 'Taco Festival Recap']);

    $response = $this->get('/sitemap.xml')->assertOk();

    $response->assertSee(route('news.show', [$post, $post->slug]), false);
    $response->assertSee('<lastmod>'.$post->updated_at?->toAtomString().'</lastmod>', false);
});

it('excludes draft posts', function (): void {
    $post = Post::factory()->create(['title' => 'Hidden Draft Story']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee(route('news.show', [$post, $post->slug]), false);
});

it('reflects a newly published truck immediately', function (): void {
    $this->get('/sitemap.xml')->assertDontSee('brand-new-bites', false);

    $truck = FoodTruck::factory()->published()->create(['name' => 'Brand New Bites']);

    $this->get('/sitemap.xml')
        ->assertSee(route('trucks.show', [$truck, $truck->slug]), false);
});
