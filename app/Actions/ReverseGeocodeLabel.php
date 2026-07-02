<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turn a GPS position into a short human label ("Johnson Dr, Mission") via the
 * OSM Nominatim reverse-geocoding API — free and keyless, like the map tiles.
 *
 * Called once per vendor pin-set, which keeps us comfortably inside the
 * Nominatim usage policy (identifying User-Agent, ≤1 request/second). Returns
 * null on any failure — the label is nice-to-have; the pin must never depend
 * on it.
 */
final class ReverseGeocodeLabel
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    private const USER_AGENT = 'StreetBites/1.0 (Laravel food-truck app; local dev)';

    public function __invoke(float $latitude, float $longitude): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(5)
                ->get(self::ENDPOINT, [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                    'zoom' => 17, // street level
                    'addressdetails' => 1,
                ]);

            if ($response->failed()) {
                return null;
            }

            $address = $response->json('address');
            $address = is_array($address) ? $address : [];

            $pick = function (array $keys) use ($address): ?string {
                foreach ($keys as $key) {
                    $value = $address[$key] ?? null;

                    if (is_string($value) && $value !== '') {
                        return $value;
                    }
                }

                return null;
            };

            $label = implode(', ', array_filter([
                $pick(['road', 'pedestrian']),
                $pick(['neighbourhood', 'suburb', 'city', 'town', 'village']),
            ]));

            return $label === '' ? null : mb_substr($label, 0, 255);
        } catch (Throwable) {
            return null;
        }
    }
}
