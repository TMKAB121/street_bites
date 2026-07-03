# resources/views — Blade views & components

Full-page views live here (`welcome.blade.php`, `trucks/show.blade.php`,
`livewire/**`, `styleguide.blade.php`, `layouts/**`) alongside reusable
**anonymous Blade components** in `components/`. Each component pairs a CSS partial
(`resources/css/components/`, visuals only) with a `.blade.php` file (markup +
`@props`). CSS/token conventions live in `resources/css/CLAUDE.md`.

Every full-page `<head>` (both layouts, `welcome`, `styleguide`) carries the
favicon links — `/favicon.svg` (`type="image/svg+xml"`), `/favicon.ico`
(`sizes="48x48"` fallback), and `/apple-touch-icon.png` — right after `<title>`.
Include all three in any new full-page view (brand-asset detail is in the root
`CLAUDE.md`, *Front-end / design system*).

## Blade components

| Component | CSS partial | Notes |
|---|---|---|
| `<x-mobile-nav>` | `nav.css` | Bottom tab bar (Home/Map/Favorites + auth-aware slot: **Login** when guest, **Profile** when signed in) |
| `<x-mobile-header>` | `header.css` | Top bar: hamburger + brand + map + search; hamburger opens a full-screen Alpine menu (last link is the same auth-aware Login/Profile slot). The brand is the SVG logo (`/images/street-bites-logo.svg`, sized by `.mobile-header__brand-logo`), not text — keep `alt="Street Bites"` on the `<img>` |
| `<x-food-truck-card>` | `card.css` | Image + title + Mustard FIND NOW CTA; `image` prop, graceful placeholder when null. Discovery cards link to `/trucks/{id}` |
| `<x-card-carousel>` | `carousel.css` | Slot-based horizontal scroller; any child card becomes a snap item |
| `<x-truck-filters>` | `filters.css` | Scrollable cuisine pills driven from the `Tag` DB. Each pill click sets Alpine's local active state **and** dispatches a `tag-filter` window event (`{ tag: slug }`). The results grid listens with `@tag-filter.window` and uses `x-show` to filter cards client-side. Props: `tags` (Collection of Tag models) |
| `<x-truck-form>` | `truck-form.css` | The vendor edit form. **Nested inside the `TruckEditor` Livewire view** (not a standalone demo): it compiles inline, so its `wire:model` / `wire:click` bind to the component. Includes the **Now Open** CTA (`goLiveNow`) and **Set my location** pin CTA (`setLocation`, passes the browser timezone). Props: `truck-id`, `menu-items`, `images`, `all-tags`, `located-at`, `location-label`, `opens-at`, `timezone` |
| `<x-truck-map>` | `map.css` | Interactive **Leaflet** map of pinned trucks on the home page (JS in `resources/js/truck-map.js`). Serializes `$trucks` to pin markers, centres on the visitor's GPS, and forwards the `tag-filter` window event to filter pins in lockstep with the grid. Props: `trucks` (Collection of FoodTruck models) |
| `<x-location-search>` | `location-search.css` | ZIP/address fallback on the home page for visitors who decline browser geolocation. Hidden (`x-cloak`) until the `user-location-denied` window event fires; the `locationSearch` Alpine component (`resources/js/truck-map.js`) geocodes the entry via `GET /api/geocode` and dispatches the same `user-located` event the GPS path uses. Reuses `.field__input` (auth.css) + `.btn-mustard`. No props |
| `<x-toast>` | `toast.css` | App-wide transient confirmations. Alpine-only; listens for the browser `toast` event Livewire dispatches (`$this->dispatch('toast', message:…, type:…)`). Stacked once in `layouts/shell.blade.php` |
| `<x-cookie-consent>` | `cookie-consent.css` | GDPR consent banner + the persistent round "cookie preferences" button that reopens it. Stacked once per full page (both layouts, `welcome`, `styleguide`). Reads the encrypted `cookie_consent` cookie server-side (no flash), posts choices to `cookie-consent.store`, and force-opens on the `cookie_consent.required` session flash. **Accept and Decline share one class — equal prominence is a GDPR rule, never fork them.** Props: `fixed`, `demo` (styleguide: in-flow, never posts) |

Conventions for these components:

- **Mobile-only:** the header/nav apply `md:hidden`; desktop variants are
  deferred. Positioning utilities (`fixed`, `top-0`/`bottom-0`, `md:hidden`) live
  on the component element via `$attributes->class([...])`, **not** in the CSS
  partial — the partial owns visuals only. A `fixed` prop (default `true`) toggles
  in-flow rendering for styleguide demos.
- **Icons are inline SVG** (no icon library) — store path data in a `@php $icons`
  array and inject with `{!! … !!}`; styling comes from CSS (`stroke: currentcolor`).
- **Carousels & the filter row use native CSS scroll-snap** (`overflow-x` +
  `scroll-snap-type`), not a JS slider lib — Livewire-safe and ADA-native. The
  scroll region is `tabindex="0"` + `role="region"` for keyboard scrolling.

The Alpine/Livewire rules (don't start a second Alpine; `@livewireStyles` /
`@livewireScripts` in every full-page view) and the route→view map live in the
root `CLAUDE.md` (*JavaScript / Alpine* and *Pages*).
