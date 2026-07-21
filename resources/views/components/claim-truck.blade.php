@props([
    'truck',
    'pending' => false,
])

{{--
    Unclaimed-truck disclosure + "Claim this truck" call to action, shown on the
    public detail page only when the truck has no owner (user_id null). It sets
    expectations (this listing was seeded by us, not the owner) and offers the
    real vendor a path to take it over — an admin approves the claim before
    ownership transfers.
    - $truck:   the unclaimed FoodTruck.
    - $pending: whether the signed-in visitor already has a pending claim on it.
--}}
<section class="claim-truck" aria-label="Unclaimed listing">
    <p class="claim-truck__notice">
        <svg class="claim-truck__icon" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8h.01M11 12h1v4h1" />
        </svg>
        This listing was added by our systems and isn’t managed by the owner yet —
        details may be incomplete.
    </p>

    @guest
        <p class="claim-truck__prompt">Own this truck?</p>
        <a href="{{ route('auth.login') }}" class="btn btn-mustard claim-truck__cta">
            Sign in to claim this truck
        </a>
    @else
        @if ($pending)
            <p class="claim-truck__pending">
                Claim submitted — we’re reviewing it. You’ll get an email once it’s approved.
            </p>
        @elseif (auth()->user()->isBanned())
            {{-- A blocked user can't claim; the route rejects it too. Show nothing
                 beyond the disclaimer. --}}
        @else
            <div x-data="{ claiming: false }">
                <p class="claim-truck__prompt">Own this truck?</p>
                <button
                    type="button"
                    class="btn btn-mustard claim-truck__cta"
                    x-show="!claiming"
                    @click="claiming = true"
                >
                    Claim this truck
                </button>

                <form
                    method="POST"
                    action="{{ route('trucks.claim', $truck) }}"
                    class="claim-truck__form"
                    x-show="claiming"
                    x-cloak
                >
                    @csrf
                    <label for="claim-message-{{ $truck->id }}" class="claim-truck__label">
                        Help us verify (optional) — a business email, website, or social profile
                    </label>
                    <textarea
                        id="claim-message-{{ $truck->id }}"
                        name="message"
                        class="field__input claim-truck__textarea"
                        rows="3"
                        maxlength="1000"
                        placeholder="e.g. I run @{{ $truck->name }} — hello@example.com"
                    ></textarea>
                    <div class="claim-truck__actions">
                        <button
                            type="button"
                            class="btn claim-truck__cancel"
                            @click="claiming = false"
                        >
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-mustard claim-truck__submit">
                            Submit claim
                        </button>
                    </div>
                </form>
            </div>
        @endif
    @endguest
</section>
