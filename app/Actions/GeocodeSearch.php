<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turn a free-text ZIP code or street address into rough GPS coordinates via
 * the OSM Nominatim search (forward-geocoding) API — the same free, keyless
 * service ReverseGeocodeLabel uses in the other direction.
 *
 * This backs the home-page fallback for visitors who decline browser
 * geolocation. Calls are proxied through the server (never the browser) so the
 * Nominatim usage policy is honoured — identifying User-Agent, ≤1 request/sec —
 * and the route caches results so repeat searches skip the network entirely.
 * Returns null on any failure or no-match; the caller decides how to degrade.
 */
final class GeocodeSearch
{
    private const string ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    private const string USER_AGENT = 'StreetBites/1.0 (Laravel food-truck app; local dev)';

    /**
     * @return array{lat: float, lng: float, label: string}|null
     */
    public function __invoke(string $query): ?array
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(5)
                ->get(self::ENDPOINT, [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    // Bias bare ZIP codes to the US — "66202" alone is ambiguous
                    // worldwide. Widen this list if the app expands abroad.
                    'countrycodes' => 'us',
                ]);

            if ($response->failed()) {
                return null;
            }

            $results = $response->json();
            $hit = is_array($results) ? ($results[0] ?? null) : null;

            if (! is_array($hit)) {
                return null;
            }

            $lat = filter_var($hit['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($hit['lon'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($lat === false || $lng === false) {
                return null;
            }

            $label = $hit['display_name'] ?? null;
            $label = is_string($label) && $label !== '' ? $label : $query;

            return [
                'lat' => $lat,
                'lng' => $lng,
                'label' => mb_substr($label, 0, 255),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
