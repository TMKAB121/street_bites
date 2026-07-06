<div>
    <a href="{{ route('auth.login') }}" class="mb-6 inline-block text-sm text-text-muted" wire:navigate>
        ← Back to sign in
    </a>

    <form wire:submit="submit" class="auth-card">
        <h1 class="auth-card__title">Reset your password</h1>
        <p class="auth-card__subtitle">Enter your email and we’ll send you a code to reset your password.</p>

        <div class="field mt-6">
            <label for="email" class="field__label">Email</label>
            <input
                id="email"
                type="email"
                wire:model="email"
                class="field__input"
                placeholder="you@example.com"
                autocomplete="email"
                autofocus
            >
            @error('email')
                <p class="field__error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary mt-6 w-full" wire:loading.attr="disabled" wire:target="submit">
            <span wire:loading.remove wire:target="submit">Send reset code</span>
            <span wire:loading wire:target="submit">Sending…</span>
        </button>
    </form>
</div>
