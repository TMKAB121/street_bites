<div>
    <a href="{{ route('auth.login') }}" class="mb-6 inline-block text-sm text-text-muted" wire:navigate>
        ← Back to sign in
    </a>

    <form wire:submit="verify" class="auth-card">
        <h1 class="auth-card__title">Confirm it's you</h1>
        <p class="auth-card__subtitle">
            We sent a 6-digit code to <strong>{{ $email }}</strong>.
        </p>

        @if (session('status'))
            <p class="mt-3 text-sm text-primary">{{ session('status') }}</p>
        @endif

        <div class="field mt-6">
            <label for="code" class="field__label">Verification code</label>
            <input
                id="code"
                type="text"
                inputmode="numeric"
                maxlength="6"
                wire:model="code"
                class="field__input field__input--code"
                placeholder="000000"
                autocomplete="one-time-code"
                autofocus
            >
            @error('code')
                <p class="field__error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" wire:target="verify">
            Verify
        </button>

        <button type="button" wire:click="resend" class="mt-3 w-full text-sm text-text-muted">
            Didn’t get it? Resend code
        </button>
    </form>
</div>
