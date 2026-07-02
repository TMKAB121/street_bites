# tests — Pest conventions

Tests use **Pest** (on PHPUnit). Run: `lando pest` (or `lando composer test`).

- **DB tests `use RefreshDatabase`** — the schema is migrated fresh and each test
  runs in a transaction that is rolled back. The separate `steet_bites_testing`
  database only needs to *exist* (created on `lando start`); never seed it manually.
- **Lazy Livewire components:** pass `['truckId' => …, 'lazy' => false]` to
  `Livewire::test()` so `mount()` runs immediately instead of deferring (e.g.
  `TruckEditor`). Ownership-guard tests can also `->set('truckId', …)` then assert
  `->assertForbidden()`.
- **Never hit the network.** External HTTP (OSM Nominatim geocoding, etc.) is faked
  with `Http::fake([...])`. Design actions so the network path is skipped when a
  fixture/cache is already present (e.g. `GenerateTruckMapImage` short-circuits on a
  cached file), so tests exercise the cache path with `Storage::fake('public')`
  rather than rendering real tiles.
- **Clock-sensitive tests** use `travelTo(...)` / `travelBack()` — e.g. asserting a
  truck-local `opens_at` is stamped from a fixed UTC "now".
- **Hashing override:** `phpunit.xml` sets `HASH_DRIVER=bcrypt` (rounds=4) for
  speed — don't assert the Argon2id hash format under the test driver, and the
  Have-I-Been-Pwned breach check is skipped under `runningUnitTests()`.
