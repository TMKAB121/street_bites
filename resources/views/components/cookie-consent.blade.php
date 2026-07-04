@props([
    'fixed' => true,
    'demo' => false,
])

{{--
    GDPR cookie-consent banner + persistent preferences widget, stacked once per
    page (both layouts, welcome, styleguide). All-or-nothing by design: the app
    sets no analytics/marketing cookies, so there are no categories to pick —
    accept essential cookies (sign-in, favorites) or browse anonymously.

    - $fixed: pin to the viewport (real-app default); :fixed="false" renders
      in-flow (e.g. the styleguide demo).
    - $demo: styleguide mode — always starts open and never posts; choices only
      toggle local state.

    Compliance notes, enforced here and in cookie-consent.css:
    - Nothing is pre-checked and no choice is implied: the banner opens until an
      explicit decision is made, and both outcomes are one tap.
    - Accept and Decline share ONE class (.cookie-consent__btn) — identical
      size, color, and font. Never restyle one of them (equal prominence).
    - Withdrawal is as easy as consent: the round cookie button stays on every
      page and reopens this banner with both choices.
    - Every decision is documented server-side (POST cookie-consent.store →
      cookie_consents table).

    The consent state is read server-side from the (encrypted) cookie, so there
    is no flash of the banner for visitors who already chose. The
    'cookie_consent.required' session flash — set by the RequireCookieConsent
    middleware — force-opens the banner with an explanation.
--}}
@php
    $status = request()->cookie(\App\Models\CookieConsent::COOKIE_NAME);
    $status = in_array($status, [
        \App\Models\CookieConsent::STATUS_ACCEPTED,
        \App\Models\CookieConsent::STATUS_DECLINED,
    ], true) ? $status : null;
    $required = (bool) session('cookie_consent.required');
@endphp

<div
    {{ $attributes }}
    x-data="{
        status: {{ Js::from($demo ? null : $status) }},
        required: {{ Js::from(! $demo && $required) }},
        demo: {{ Js::from((bool) $demo) }},
        open: false,
        busy: false,
        init() {
            this.open = this.demo || this.required || this.status === null;
        },
        async choose(choice) {
            if (this.demo) {
                this.status = choice;
                this.open = false;
                return;
            }
            if (this.busy) return;
            this.busy = true;
            try {
                const response = await fetch({{ Js::from(route('cookie-consent.store')) }}, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': {{ Js::from(csrf_token()) }},
                    },
                    body: JSON.stringify({ status: choice }),
                });
                if (!response.ok) return;
                const data = await response.json();
                this.status = data.status;
                this.required = false;
                this.open = false;
                // Withdrawing consent also signed the visitor out server-side;
                // land them on the home page as a consistent guest.
                if (data.signedOut) window.location.assign({{ Js::from(route('home')) }});
            } finally {
                this.busy = false;
            }
        },
    }"
>
    <section
        x-show="open"
        x-cloak
        role="dialog"
        aria-label="Cookie consent"
        @class(['cookie-consent', 'fixed inset-x-0 bottom-20 z-50 px-4 md:bottom-6' => $fixed])
    >
        <div class="cookie-consent__panel mx-auto max-w-md">
            <h2 class="cookie-consent__title">Cookies at Street Bites</h2>

            <p class="cookie-consent__body">
                We use only essential cookies — the ones that keep you signed in and
                remember your favorite trucks. No analytics, no ads, no tracking, and
                nothing is set until you choose. Decline and you can still browse every
                truck, but sign-in and favorites won't work. Change your mind anytime
                via the cookie button in the corner.
            </p>

            {{-- Why the banner reopened: a consent-gated page bounced them here. --}}
            <p class="cookie-consent__notice" x-show="required" x-cloak>
                Signing in and favorites need cookies — accept below to continue.
            </p>

            <p class="cookie-consent__status" x-show="status !== null" x-cloak>
                Your current choice: <strong x-text="status"></strong>.
            </p>

            {{-- Equal prominence (GDPR): both buttons share .cookie-consent__btn. --}}
            <div class="cookie-consent__actions">
                <button type="button" class="cookie-consent__btn" :disabled="busy" @click="choose('accepted')">
                    Accept cookies
                </button>
                <button type="button" class="cookie-consent__btn" :disabled="busy" @click="choose('declined')">
                    Decline cookies
                </button>
            </div>
        </div>
    </section>

    {{-- Persistent, always-reachable re-entry point: withdrawing consent must be
         as easy as giving it, so this stays on every page once the banner closes. --}}
    <button
        type="button"
        x-show="!open"
        x-cloak
        @click="open = true"
        aria-label="Cookie preferences"
        aria-haspopup="dialog"
        @class(['cookie-consent__fab', 'fixed bottom-24 left-4 z-40 md:bottom-6' => $fixed])
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M21 12a9 9 0 1 1-9-9c0 2 1.5 3.5 3.5 3.5.3 2 1.7 3.4 3.7 3.6.5.6.8 1.2.8 1.9Z"/>
            <path d="M9 9h.01"/>
            <path d="M14 13h.01"/>
            <path d="M9 15h.01"/>
            <path d="M13 17h.01"/>
        </svg>
    </button>
</div>
