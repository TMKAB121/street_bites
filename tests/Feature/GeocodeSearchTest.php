<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

$nominatimHit = [
    [
        'lat' => '39.0271783',
        'lon' => '-94.6557912',
        'display_name' => 'Mission, Johnson County, Kansas, 66202, United States',
    ],
];

it('geocodes a ZIP or address into rough coordinates', function () use ($nominatimHit): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response($nominatimHit)]);

    $this->getJson(route('geocode', ['q' => '66202']))
        ->assertOk()
        ->assertJson([
            'lat' => 39.0271783,
            'lng' => -94.6557912,
            'label' => 'Mission, Johnson County, Kansas, 66202, United States',
        ]);

    // The server proxies Nominatim with the identifying User-Agent the OSM
    // usage policy requires — the browser never calls Nominatim directly.
    Http::assertSent(fn ($request): bool => str_contains((string) $request->header('User-Agent')[0], 'StreetBites'));
});

it('returns 404 when nothing matches', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

    $this->getJson(route('geocode', ['q' => 'zzzz nowhere at all']))
        ->assertNotFound();
});

it('validates the query', function (): void {
    Http::fake();

    $this->getJson(route('geocode'))->assertUnprocessable();
    $this->getJson(route('geocode', ['q' => 'ab']))->assertUnprocessable();
    $this->getJson(route('geocode', ['q' => str_repeat('a', 121)]))->assertUnprocessable();

    Http::assertNothingSent();
});

it('caches results so repeat searches skip the network', function () use ($nominatimHit): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response($nominatimHit)]);

    $this->getJson(route('geocode', ['q' => '66202']))->assertOk();
    // Same query modulo case/whitespace hits the cache, not Nominatim.
    $this->getJson(route('geocode', ['q' => '  66202 ']))->assertOk();

    Http::assertSentCount(1);
});

it('degrades to 404 when Nominatim is down', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(null, 503)]);

    $this->getJson(route('geocode', ['q' => '66202']))->assertNotFound();
});
