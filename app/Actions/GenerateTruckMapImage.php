<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FoodTruck;
use DantSu\OpenStreetMapStaticAPI\LatLng;
use DantSu\OpenStreetMapStaticAPI\OpenStreetMap;
use DantSu\OpenStreetMapStaticAPI\TileLayer;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Provide the cached static-map image for a truck's pinned GPS location,
 * rendering it from OpenStreetMap tiles (free, keyless) when needed.
 *
 * The cache is the file path itself: a fingerprint of (lat, lng, zoom, size)
 * lives in the filename, so moving the pin changes the expected path and the
 * next page view regenerates — no schema or bookkeeping required. Stale
 * siblings are deleted on store. Rendering fetches ~a dozen 256px tiles from
 * the OSM tile server; the per-pin cache is also what keeps us inside the OSM
 * tile usage policy (attribution is baked into the image AND linked in the UI).
 */
final class GenerateTruckMapImage
{
    /** ~15 m/px at lat 39° → a 640px-wide image spans ~3 miles each side of the pin. */
    public const int ZOOM = 13;

    public const int WIDTH = 640;

    public const int HEIGHT = 320;

    /** OSM tile policy requires an identifying User-Agent (no browser impersonation). */
    private const string USER_AGENT = 'StreetBites/1.0 (Laravel food-truck app; local dev)';

    /**
     * Return the public-disk path of the truck's map, rendering and storing it
     * on a cache miss. Null when the truck has no pin or rendering fails (OSM
     * downtime must hide the map section, never break the page).
     */
    public function __invoke(FoodTruck $truck): ?string
    {
        $path = self::relativePath($truck);

        if ($path === null) {
            return null;
        }

        // Everyday hot path — cached file, no network.
        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        try {
            return $this->storeRendered($truck, $this->render($truck));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Deterministic cache path for the truck's current pin, or null without one.
     * The decimal casts give stable strings, so equal pins hash identically.
     */
    public static function relativePath(FoodTruck $truck): ?string
    {
        if ($truck->latitude === null || $truck->longitude === null) {
            return null;
        }

        $fingerprint = mb_substr(sha1(
            $truck->latitude.'|'.$truck->longitude.'|'.self::ZOOM.'|'.self::WIDTH.'x'.self::HEIGHT
        ), 0, 16);

        return "truck-maps/{$truck->id}/{$fingerprint}.png";
    }

    /**
     * Persist rendered PNG bytes at the fingerprint path and drop stale
     * siblings (maps for previous pins). Split from render() so the cleanup
     * behaviour is testable without touching the network.
     */
    public function storeRendered(FoodTruck $truck, string $png): string
    {
        $path = self::relativePath($truck);

        if ($path === null) {
            throw new \LogicException('Cannot store a map for a truck without a pinned location.');
        }

        Storage::disk('public')->put($path, $png);

        foreach (Storage::disk('public')->files("truck-maps/{$truck->id}") as $file) {
            if ($file !== $path) {
                Storage::disk('public')->delete($file);
            }
        }

        return $path;
    }

    /**
     * Fetch OSM tiles and compose the map PNG (network). No baked-in marker —
     * the pin is a CSS overlay centred on the image, so the cached map stays
     * marker-free.
     */
    private function render(FoodTruck $truck): string
    {
        // The tile fetcher builds a default Referer from these superglobals and
        // warns (→ ErrorException) when they're absent, so backfill for CLI
        // runs (tinker, future queue workers). Harmless under HTTP.
        $_SERVER['REQUEST_SCHEME'] ??= 'https';
        $_SERVER['HTTP_HOST'] ??= (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $_SERVER['REQUEST_URI'] ??= '/';

        $tileLayer = new TileLayer(
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            '© OpenStreetMap contributors',
            'abc',
            [
                // The library's default curl options win over CURLOPT_USERAGENT
                // (array union keeps its fake-browser UA), but a User-Agent
                // header set here takes precedence inside curl itself.
                CURLOPT_HTTPHEADER => ['User-Agent: '.self::USER_AGENT],
                CURLOPT_CONNECTTIMEOUT => 5,
            ],
            failCurlOnError: true,
        );

        $map = new OpenStreetMap(
            new LatLng((float) $truck->latitude, (float) $truck->longitude),
            self::ZOOM,
            self::WIDTH,
            self::HEIGHT,
            $tileLayer,
        );

        return $map->getImage()->getDataPNG();
    }
}
