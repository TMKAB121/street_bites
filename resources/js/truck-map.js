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
 * The map centres on the browser's geolocation when the user grants it and
 * quietly falls back to fitting all pins when they don't.
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

            navigator.geolocation?.getCurrentPosition(
                (position) => {
                    const here = [position.coords.latitude, position.coords.longitude];

                    L.circleMarker(here, {
                        className: 'truck-map__here',
                        radius: 7,
                    })
                        .bindTooltip('You are here')
                        .addTo(this.map);

                    this.map.setView(here, MAP_ZOOM);

                    // Let the rest of the page react to the visitor's position
                    // (truckDistanceSort reorders the card lists on this).
                    window.dispatchEvent(
                        new CustomEvent('user-located', {
                            detail: { lat: here[0], lng: here[1] },
                        })
                    );
                },
                () => {}, // denied/unavailable — keep the pin-centred view
                { maximumAge: 300000 }
            );
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
