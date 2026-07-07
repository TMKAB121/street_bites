/**
 * Interactive homepage map (Leaflet + OpenStreetMap tiles, no API key).
 *
 * Registered as the Alpine component `truckMap(pins)` on `alpine:init` — that
 * event comes from Livewire's bundled Alpine (we never import Alpine ourselves,
 * see app.js). Each pin is `{ id, name, lat, lng, tags, url }`; markers use the
 * Street Bites brand pin — the master icon served from public/images (NOT
 * Leaflet's own bundled marker-icon.png, which breaks under bundlers). The Blade
 * component forwards the
 * window `tag-filter` event dispatched by <x-truck-filters> to filterPins(), so
 * the pins follow the same client-side cuisine filtering as the results grid.
 *
 * The visitor's position flows through two window events that decouple the
 * pieces on the page:
 *   - `user-located` `{ lat, lng }` — dispatched by the geolocation success
 *     callback here AND by the `locationSearch` ZIP/address fallback below.
 *     The map recenters + drops the "you are here" dot; `truckDistanceSort`
 *     reorders the card lists.
 *   - `user-location-denied` — dispatched when the visitor declines (or the
 *     browser lacks) geolocation; `locationSearch` reveals itself on it.
 *
 * Once a location is known, every result surface applies the MAX_RADIUS_MILES
 * cap: `truckDistanceSort` and `truckRadiusFilter` hide out-of-range cards,
 * and the map drops out-of-range pins. The location is remembered for the
 * browser session (sessionStorage) so map-less pages — the search landing
 * page — filter too, without their own geolocation prompt.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
// Collapses stacked pins into a count bubble at low zoom (breweries, festivals,
// corporate lots where trucks group up) — expands back to individual brand pins
// as the visitor zooms in. Patches the imported L in place.
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

// The Street Bites master pin mark (public/images, served at the site root) —
// the chili teardrop with the taco glyph, so every marker reads as the brand.
const PIN_ICON_URL = '/images/street-bites-icon.png';

// Initial view: ≈5-mile radius around the visitor (the truck detail pages use
// tighter ~2.5-mile static maps, GenerateTruckMapImage::ZOOM). The visitor can
// zoom freely from here; OSM_MAX_ZOOM is the deepest tile level OSM serves.
const MAP_ZOOM = 12;
const OSM_MAX_ZOOM = 19;

// No result surface (grid, carousel, map pins, search results) shows a truck
// further than this from the visitor once their location is known — a truck
// 100+ miles away isn't somewhere they'll actually eat.
const MAX_RADIUS_MILES = 100;

// A cached GPS fix this recent is accurate enough for a 100-mile radius cap —
// skip the slow fresh-fix round-trip when the browser has one.
const GEO_FIX_MAX_AGE_MS = 300000; // 5 minutes

const HTTP_TOO_MANY_REQUESTS = 429;

// Two variants of the same brand pin, distinguished only by a modifier class:
// open trucks keep the vivid mark (white face), closed ones are dimmed to a
// muted grey so open/closed reads at a glance — colouring lives in map.css.
const makeIcon = (open) =>
    L.icon({
        iconUrl: PIN_ICON_URL,
        className: open ? 'truck-map__pin' : 'truck-map__pin truck-map__pin--closed',
        iconSize: [40, 40],
        iconAnchor: [20, 38], // the teardrop tip (near the bottom of the icon)
        popupAnchor: [0, -36], // popup sits just above the pin's crown
    });

// Popup content built via DOM (not an HTML string) so truck names never
// inject markup.
const popupFor = (pin) => {
    const link = document.createElement('a');
    link.href = pin.url;
    link.textContent = pin.name;

    return link;
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('truckMap', (pins) => ({
        map: null,
        clusterGroup: null,
        markers: [],
        hereMarker: null,
        here: null,
        activeTag: 'all',

        init() {
            // Zoomable map: opens at MAP_ZOOM (~5-mile radius) and the visitor
            // can zoom in/out from there (controls, wheel, pinch, double-click
            // — Leaflet's defaults). Capped at OSM's deepest tile level.
            this.map = L.map(this.$refs.canvas, {
                maxZoom: OSM_MAX_ZOOM,
            });

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: OSM_MAX_ZOOM,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            }).addTo(this.map);

            // Pins live in a cluster group (not directly on the map) so grouped
            // trucks collapse into a count bubble; refreshPins() owns each
            // marker's membership as cuisine/radius filters change.
            this.clusterGroup = L.markerClusterGroup();
            this.map.addLayer(this.clusterGroup);

            this.markers = pins.map((pin) => ({
                tags: pin.tags,
                lat: pin.lat,
                lng: pin.lng,
                marker: L.marker([pin.lat, pin.lng], { icon: makeIcon(pin.open), alt: pin.name }).bindPopup(
                    popupFor(pin)
                ),
            }));

            // Seed the cluster group — markers are no longer added at build
            // time, so this initial pass populates the map (and later re-runs
            // apply the cuisine/radius filters).
            this.refreshPins();

            // Open at MAP_ZOOM rather than fitting every pin — outliers
            // shouldn't zoom the whole city out; they stay reachable by
            // panning or zooming out.
            const center =
                pins.length > 0
                    ? L.latLngBounds(pins.map((pin) => [pin.lat, pin.lng])).getCenter()
                    : [39.0272, -94.6558];
            this.map.setView(center, MAP_ZOOM);

            // Recenter on any position the page learns of — browser GPS below
            // or a ZIP/address search — so both paths behave identically. The
            // map is the only writer of the remembered location: the truck
            // form's pin fallback dispatches the same event for the *truck's*
            // location on a page with no map, so it never leaks in here.
            window.addEventListener('user-located', (event) => {
                rememberLocation(event.detail);
                this.showVisitor(event.detail);
            });

            // A location from earlier in the session applies immediately — no
            // wait on the GPS round-trip; a fresh fix simply supersedes it.
            const stored = storedLocation();

            if (stored) {
                this.showVisitor(stored);
            }

            if (!navigator.geolocation) {
                // No geolocation API at all — surface the search fallback.
                // Deferred so every component's listener is attached first.
                setTimeout(() => window.dispatchEvent(new CustomEvent('user-location-denied')));

                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    // truckDistanceSort reorders the card lists on this, and
                    // showVisitor() above recenters the map.
                    window.dispatchEvent(
                        new CustomEvent('user-located', {
                            detail: {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude,
                            },
                        })
                    );
                },
                // Denied/unavailable — keep the pin-centred view and reveal
                // the ZIP/address fallback instead.
                () => window.dispatchEvent(new CustomEvent('user-location-denied')),
                { maximumAge: GEO_FIX_MAX_AGE_MS }
            );
        },

        // Drop (or move) the "you are here" dot and centre the map on it at
        // the default zoom — a new location warrants a fresh ~5-mile view.
        showVisitor({ lat, lng }) {
            const here = [lat, lng];

            if (this.hereMarker) {
                this.hereMarker.setLatLng(here);
            } else {
                this.hereMarker = L.circleMarker(here, {
                    className: 'truck-map__here',
                    radius: 7,
                })
                    .bindTooltip('You are here')
                    .addTo(this.map);
            }

            this.map.setView(here, MAP_ZOOM);

            this.here = { lat, lng };
            this.refreshPins();
        },

        // Mirrors the results grid: 'all' shows everything, otherwise a pin
        // needs the active cuisine slug among its tags.
        filterPins(tag) {
            this.activeTag = tag;
            this.refreshPins();
        },

        // A pin shows when it matches the active cuisine AND sits within
        // MAX_RADIUS_MILES of the visitor (an unknown location keeps every
        // pin) — so the map never advertises a truck the card lists hide.
        refreshPins() {
            this.markers.forEach(({ tags, lat, lng, marker }) => {
                const cuisineOk = this.activeTag === 'all' || tags.includes(this.activeTag);
                const nearOk = this.here === null || !beyondRadius({ lat, lng }, this.here);

                if (cuisineOk && nearOk) {
                    this.clusterGroup.addLayer(marker);
                } else {
                    this.clusterGroup.removeLayer(marker);
                }
            });
        },
    }));

    // Open-first, then closest-first ordering for a list of truck cards, plus
    // the MAX_RADIUS_MILES cap. Attach to a container whose card children carry
    // data-open + data-lat/data-lng; when the page learns the visitor's
    // position (the `user-located` event, or one remembered from earlier in
    // the session), the cards are re-appended open→closed, closest→furthest
    // within each group — and any card beyond the radius is hidden via the
    // `hidden` attribute, whose preflight `!important` outranks the cuisine
    // filter's x-show inline style, so the two never fight. Cards without
    // coordinates sort last but stay visible — "unknown" isn't "far".
    // Real DOM order (not CSS `order`) so screen readers and keyboard focus
    // follow the visual order; Alpine bindings on the children survive the
    // moves. Without a location the server-rendered order (already open-first,
    // then alphabetical) stands and nothing is hidden. `allBeyondRadius`
    // drives the grid's client-side empty-state message.
    window.Alpine.data('truckDistanceSort', () => ({
        allBeyondRadius: false,

        init() {
            window.addEventListener('user-located', (event) => this.reorder(event.detail));

            const stored = storedLocation();

            if (stored) {
                this.reorder(stored);
            }
        },

        reorder(here) {
            // Only coordinate-bearing children are cards — the empty-state
            // messages stay put and unsorted.
            const cards = [...this.$el.children]
                .filter((el) => 'lat' in el.dataset)
                // openRank 0 for open trucks so they sort ahead of closed ones.
                .map((el) => ({
                    el,
                    openRank: el.dataset.open === '1' ? 0 : 1,
                    miles: milesFrom(el.dataset, here),
                }));

            [...cards]
                .sort((a, b) => a.openRank - b.openRank || (a.miles ?? Infinity) - (b.miles ?? Infinity))
                .forEach(({ el }) => this.$el.appendChild(el));

            cards.forEach(({ el, miles }) => {
                el.hidden = miles !== null && miles > MAX_RADIUS_MILES;
            });

            this.allBeyondRadius = cards.length > 0 && cards.every(({ el }) => el.hidden);
        },
    }));

    // The radius cap alone, for card lists that keep their server order — the
    // Popular carousel and the search results grid. Attach to any ancestor of
    // cards wrapped in data-lat/data-lng elements; once a location is known
    // (live event or remembered from the session), out-of-range cards are
    // hidden. `anyInRange` lets a section step aside entirely when nothing
    // remains; `hiddenCount` feeds "n hidden" notes.
    window.Alpine.data('truckRadiusFilter', () => ({
        hiddenCount: 0,
        anyInRange: true,

        init() {
            window.addEventListener('user-located', (event) => this.apply(event.detail));

            const stored = storedLocation();

            if (stored) {
                this.apply(stored);
            }
        },

        apply(here) {
            const cards = [...this.$el.querySelectorAll('[data-lat]')];

            cards.forEach((el) => {
                el.hidden = beyondRadius(el.dataset, here);
            });

            this.hiddenCount = cards.filter((el) => el.hidden).length;
            this.anyInRange = cards.length === 0 || this.hiddenCount < cards.length;
        },
    }));

    // ZIP/address fallback for visitors who decline browser geolocation
    // (<x-location-search>). Hidden until `user-location-denied` fires; on
    // submit it asks our /geocode proxy (server-side Nominatim, cached) for
    // rough coordinates and dispatches the same `user-located` event the GPS
    // path uses — as a *bubbling* DOM event, so window listeners (the map,
    // the card sorting) still hear it AND an ancestor can catch its own
    // instance's result (the truck form's pin fallback does exactly that).
    // Pass alwaysVisible: true to skip the hidden-until-denied behaviour when
    // the surrounding markup controls visibility itself.
    window.Alpine.data('locationSearch', (endpoint, alwaysVisible = false) => ({
        visible: alwaysVisible,
        query: '',
        busy: false,
        error: null,
        label: null,

        init() {
            if (!alwaysVisible) {
                window.addEventListener('user-location-denied', () => {
                    this.visible = true;
                });
            }
        },

        async search() {
            const q = this.query.trim();

            if (this.busy) {
                return;
            }

            // No native form validation (the markup is form-free so it can
            // nest inside the truck editor's form), so guard here instead.
            if (q.length < 3) {
                this.error = 'Enter at least a ZIP code — 3 characters or more.';

                return;
            }

            this.busy = true;
            this.error = null;

            try {
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    this.label = null;
                    this.error =
                        response.status === HTTP_TOO_MANY_REQUESTS
                            ? 'Too many searches — give it a minute and try again.'
                            : "We couldn't find that spot — try a ZIP code or a street and city.";

                    return;
                }

                const { lat, lng, label } = await response.json();

                this.label = label;
                // $dispatch bubbles from this element up through window.
                this.$dispatch('user-located', { lat, lng });
            } catch {
                this.label = null;
                this.error = 'Something went wrong — please try again.';
            } finally {
                this.busy = false;
            }
        },
    }));
});

// Equirectangular distance in miles — plenty accurate at the 100-mile scale
// the radius cap needs, and monotonic for sorting. Accepts anything carrying
// lat/lng (a card's dataset strings, a pin's numbers). Returns null when
// coordinates are missing so an unpinned truck is never mistaken for a far
// one — callers decide what "unknown" means (sort last, stay visible).
const MILES_PER_DEGREE = 69.172;

const milesFrom = (point, here) => {
    const lat = parseFloat(point.lat);
    const lng = parseFloat(point.lng);

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
        return null;
    }

    const dLat = lat - here.lat;
    const dLng = (lng - here.lng) * Math.cos((here.lat * Math.PI) / 180);

    return Math.sqrt(dLat * dLat + dLng * dLng) * MILES_PER_DEGREE;
};

const beyondRadius = (point, here) => {
    const miles = milesFrom(point, here);

    return miles !== null && miles > MAX_RADIUS_MILES;
};

// Human-readable "how far" label for a distance readout. Tenths under 10 miles
// (where the extra precision reads meaningfully), whole miles beyond; a very
// close truck avoids a misleading "0 miles". Singular "mile" at exactly 1.
const formatMiles = (miles) => {
    if (miles < 0.1) {
        return 'Less than 0.1 miles away';
    }

    const value = miles < 10 ? Math.round(miles * 10) / 10 : Math.round(miles);

    return `${value} ${value === 1 ? 'mile' : 'miles'} away`;
};

// Fill every `.truck-distance` element with how far its truck is from the
// visitor, once a location is known (the `user-located` event or one remembered
// from earlier in the session). Each element reads coordinates from its nearest
// `[data-lat]` ancestor — the discovery-card wrapper on card grids, the element
// itself on the truck detail page. Hidden until a location arrives, and left
// hidden for trucks without a pin (unknown ≠ near). Purely presentational: the
// radius cap and open-first sorting are handled separately.
const refreshDistances = (here) => {
    document.querySelectorAll('.truck-distance').forEach((el) => {
        const origin = el.closest('[data-lat]');
        const miles = origin ? milesFrom(origin.dataset, here) : null;

        if (miles === null) {
            el.hidden = true;

            return;
        }

        el.textContent = formatMiles(miles);
        el.hidden = false;
    });
};

// The last location the visitor shared, remembered for the browser session so
// map-less pages (the search landing page) can apply the radius cap without
// their own geolocation prompt, and revisits filter before the GPS fix lands.
// Written only by truckMap's user-located listener — see the note there.
const LOCATION_KEY = 'street-bites:user-location';

const storedLocation = () => {
    try {
        const point = JSON.parse(sessionStorage.getItem(LOCATION_KEY) ?? 'null');

        return typeof point?.lat === 'number' && typeof point?.lng === 'number' ? point : null;
    } catch {
        return null;
    }
};

const rememberLocation = (point) => {
    try {
        sessionStorage.setItem(LOCATION_KEY, JSON.stringify(point));
    } catch {
        // Storage unavailable (locked-down private mode) — the live event
        // still filters this page; only the cross-page memory is lost.
    }
};

// Wire the distance readouts to the same location signals the map and card
// sorting use. Placed at the end of the module so the immediate stored-location
// pass runs after storedLocation()/milesFrom() are initialised.
window.addEventListener('user-located', (event) => refreshDistances(event.detail));

const applyStoredDistances = () => {
    const stored = storedLocation();

    if (stored) {
        refreshDistances(stored);
    }
};

// A location remembered from earlier in the session fills the readouts on load,
// before (or without) a fresh GPS fix. Guarded on readyState so the query runs
// against a parsed DOM whether this module executes before or after it.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyStoredDistances);
} else {
    applyStoredDistances();
}
