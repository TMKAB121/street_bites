/**
 * Interactive homepage map (Leaflet + OpenStreetMap tiles, no API key).
 *
 * Registered as the Alpine component `truckMap(pins)` on `alpine:init` — that
 * event comes from Livewire's bundled Alpine (we never import Alpine ourselves,
 * see app.js). Each pin is `{ id, name, lat, lng, tags, url }`; markers use our
 * own SVG pin (a divIcon — Leaflet's default PNG icons break under bundlers and
 * wouldn't match the design system anyway). The Blade component forwards the
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
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Same pin path as .truck-page__pin on the detail page; coloured in map.css.
const PIN_SVG =
    '<svg viewBox="0 0 24 24"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';

// ≈10-mile range around the visitor so plenty of trucks are in view (the truck
// detail pages keep their tighter ~5-mile static maps, GenerateTruckMapImage::ZOOM).
const MAP_ZOOM = 10;

const truckIcon = L.divIcon({
    html: PIN_SVG,
    className: 'truck-map__pin',
    iconSize: [32, 32],
    iconAnchor: [16, 28], // the pin tip (21/24 of the icon height)
    popupAnchor: [0, -26],
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
        markers: [],
        hereMarker: null,

        init() {
            // Fixed-zoom map: min/max pinned to MAP_ZOOM locks every zoom input
            // (which unsettled the pins), and the zoom UI/gestures are dropped
            // so it doesn't look interactive. Panning stays enabled — trucks
            // beyond the view are still reachable by dragging.
            this.map = L.map(this.$refs.canvas, {
                minZoom: MAP_ZOOM,
                maxZoom: MAP_ZOOM,
                zoomControl: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                touchZoom: false,
                boxZoom: false,
            });

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            }).addTo(this.map);

            this.markers = pins.map((pin) => ({
                tags: pin.tags,
                marker: L.marker([pin.lat, pin.lng], { icon: truckIcon, alt: pin.name })
                    .bindPopup(popupFor(pin))
                    .addTo(this.map),
            }));

            // A fixed ~10-mile view rather than fitting every pin — outliers
            // shouldn't zoom the whole city out; they stay reachable by panning.
            const center =
                pins.length > 0
                    ? L.latLngBounds(pins.map((pin) => [pin.lat, pin.lng])).getCenter()
                    : [39.0272, -94.6558];
            this.map.setView(center, MAP_ZOOM);

            // Recenter on any position the page learns of — browser GPS below
            // or a ZIP/address search — so both paths behave identically.
            window.addEventListener('user-located', (event) => this.showVisitor(event.detail));

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
                { maximumAge: 300000 }
            );
        },

        // Drop (or move) the "you are here" dot and centre the map on it.
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
        },

        // Mirrors the results grid: 'all' shows everything, otherwise a pin
        // needs the active cuisine slug among its tags.
        filterPins(tag) {
            this.markers.forEach(({ tags, marker }) => {
                if (tag === 'all' || tags.includes(tag)) {
                    marker.addTo(this.map);
                } else {
                    marker.remove();
                }
            });
        },
    }));

    // Closest-first ordering for a list of truck cards. Attach to a container
    // whose direct children carry data-lat/data-lng; when the map above obtains
    // the visitor's position (the `user-located` event), the children are
    // re-appended closest→furthest. Real DOM order (not CSS `order`) so screen
    // readers and keyboard focus follow the visual order; Alpine bindings on
    // the children (e.g. the grid's x-show filters) survive the moves. Without
    // geolocation the server-rendered alphabetical order simply stands.
    window.Alpine.data('truckDistanceSort', () => ({
        init() {
            window.addEventListener('user-located', (event) => this.reorder(event.detail));
        },

        reorder(here) {
            [...this.$el.children]
                .map((el) => ({ el, score: distanceScore(el.dataset, here) }))
                .sort((a, b) => a.score - b.score)
                .forEach(({ el }) => this.$el.appendChild(el));
        },
    }));

    // ZIP/address fallback for visitors who decline browser geolocation
    // (<x-location-search>). Hidden until `user-location-denied` fires; on
    // submit it asks our /geocode proxy (server-side Nominatim, cached) for
    // rough coordinates and dispatches the same `user-located` event the GPS
    // path uses, so the map and the card sorting react identically.
    window.Alpine.data('locationSearch', (endpoint) => ({
        visible: false,
        query: '',
        busy: false,
        error: null,
        label: null,

        init() {
            window.addEventListener('user-location-denied', () => {
                this.visible = true;
            });
        },

        async search() {
            const q = this.query.trim();

            if (q.length < 3 || this.busy) {
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
                        response.status === 429
                            ? 'Too many searches — give it a minute and try again.'
                            : "We couldn't find that spot — try a ZIP code or a street and city.";

                    return;
                }

                const { lat, lng, label } = await response.json();

                this.label = label;
                window.dispatchEvent(new CustomEvent('user-located', { detail: { lat, lng } }));
            } catch {
                this.label = null;
                this.error = 'Something went wrong — please try again.';
            } finally {
                this.busy = false;
            }
        },
    }));
});

// Squared equirectangular distance — monotonic with true distance at city
// scale, which is all a sort needs. Cards without coordinates go last.
const distanceScore = (dataset, here) => {
    const lat = parseFloat(dataset.lat);
    const lng = parseFloat(dataset.lng);

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
        return Infinity;
    }

    const dLat = lat - here.lat;
    const dLng = (lng - here.lng) * Math.cos((here.lat * Math.PI) / 180);

    return dLat * dLat + dLng * dLng;
};
