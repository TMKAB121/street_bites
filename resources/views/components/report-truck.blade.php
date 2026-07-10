@props([
    'truckId',
    'label' => 'this truck',
    'reported' => false,
])

{{--
    Quiet "report this truck" footer control — a megaphone the visitor taps to
    flag the truck as potentially offensive. Available to everyone (guests too,
    unlike the favourite star), surface-only: it never takes the truck down, it
    just adds it to the moderation queue's Reported tab. One tap settles into a
    "Reported" state via the `reportToggle` Alpine component (resources/js/report.js);
    the hover/focus tooltip explains what reporting does.
    - $truckId:  the FoodTruck id being reported.
    - $label:    truck name for the accessible label.
    - $reported: server-rendered initial state (a signed-in user who already
                 reported sees it done on revisit; guests always start fresh).
--}}
<div {{ $attributes->class('report-truck') }}>
    <button
        type="button"
        class="report-truck__btn"
        x-data="reportToggle(
            {{ Js::from(route('trucks.report', $truckId)) }},
            {{ Js::from((bool) $reported) }},
            {{ Js::from(csrf_token()) }},
        )"
        :class="{ 'report-truck__btn--done': reported }"
        :disabled="reported || busy"
        aria-describedby="report-truck-tip-{{ $truckId }}"
        @click="report"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m3 11 18-5v12L3 14v-3z" />
            <path d="M11.6 16.8a3 3 0 1 1-5.8-1.6" />
        </svg>
        <span x-show="!reported">Report {{ $label }}</span>
        <span x-show="reported" x-cloak>Reported — thanks for the heads-up</span>
    </button>

    <span id="report-truck-tip-{{ $truckId }}" class="report-truck__tip" role="tooltip">
        Flags this truck for our moderators to review for offensive content.
    </span>
</div>
