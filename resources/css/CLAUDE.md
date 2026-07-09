# resources/css — styling & design system

Styling is **Tailwind CSS 4**, configured CSS-first (there is no
`tailwind.config.js`). The "Urban Vibrant" design system from the style guide is
encoded as Tailwind `@theme` tokens in `theme.css`, which generate both CSS
variables and utility classes from one source of truth.

CSS is split into partials, all imported by `app.css` (order matters: tokens →
base → components):

```
resources/css/
├── app.css              # entry — @import 'tailwindcss' + the partials below
├── theme.css            # @theme: design tokens (colors, type scale, radius, a11y)
├── base.css             # body, ADA tap targets, focus-visible ring, [x-cloak]
└── components/
    ├── button.css       # .btn / .btn-primary / .btn-accent / .btn-mustard
    ├── card.css         # .food-truck-card (image + title + FIND NOW CTA; __status "Now Open" badge + pulsing dot; __distance "X miles away" line, JS-filled)
    ├── favorite.css     # .fav-toggle (favourite star chip — chili when active; passes 3:1 non-text contrast on white, unlike mustard/tangerine)
    ├── nav.css          # .mobile-nav (bottom tab bar)
    ├── header.css       # .mobile-header + .mobile-search (search form, submit magnifier + typeahead dropdown) + .mobile-menu
    ├── carousel.css     # .card-carousel (CSS scroll-snap)
    ├── filters.css      # .filter-row / .filter-pill
    ├── map.css          # .truck-map (Leaflet homepage map: brand-icon pins w/ white-disc face + open/closed dimming, "you are here" marker, and unlayered restyle of Leaflet's popup + zoom control + markercluster count bubble → brand chili)
    ├── auth.css         # .auth-card + .field (login / sign-up form styling)
    ├── location-search.css # .location-search (home-page ZIP/address fallback; reuses .field__input)
    ├── profile.css      # .profile + .profile__support (config-gated Buy-Me-a-Coffee callout) + .fav-list (slim favourites rows) + .truck-disclosure (collapsible owned-truck cards)
    ├── admin.css        # .moderation (admin queue: blocklist chips, truck rows, status badges + actions — chili flagged / mustard live / grey held)
    ├── truck-form.css   # .truck-form + .menu-row + .social-row + .truck-image (vendor edit form)
    ├── truck-page.css   # .truck-page (public truck detail page: static map + brand-icon pin overlay matching the home map, Get-directions CTA, hours, __distance line, menu)
    ├── social-links.css # .social-links (truck detail page: single-tone brand-icon row of social profiles)
    ├── about.css        # .about-page (About us: pull-quote, prose rhythm, follow-along link cards)
    ├── news.css         # .news-search + .news-card (/news rows) + .news-page (story page: cover, event call-out, __body Markdown prose) + .news-admin (authoring form extras)
    ├── tag-picker.css   # .tag-picker + .tag-pill (cuisine tag toggles in truck editor)
    ├── toast.css        # .toast / .toast-stack (transient save/upload confirmations)
    └── cookie-consent.css # .cookie-consent (GDPR banner + preferences fab; Accept/Decline share one equal-prominence class)
```

- Tokens are the source of truth — edit `theme.css`, never hard-code hex values
  in components. `--color-accent-chili` auto-generates `bg-accent-chili`,
  `text-accent-chili`, etc.
- Mustard (`--color-accent-mustard`) and Tangerine (`--color-accent-tangerine`)
  must use **dark text only** (both fail contrast with white). On a *dark* surface
  (e.g. the bottom nav, the header menu) mustard text is fine — the rule is
  white-bg only. Tangerine is currently used for the active filter pill.
- A component's CSS partial owns **visuals only** (BEM classes in `@layer
  components`); positioning utilities (`fixed`, `md:hidden`, …) live on the Blade
  element, not the partial — see `resources/views/CLAUDE.md`.

(Stylelint owns CSS lint/formatting — `lando npm run lint:css`; do **not** point
Prettier at `*.css`. Build/dev commands are in the root `CLAUDE.md`.)
