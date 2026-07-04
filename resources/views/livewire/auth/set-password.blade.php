<div>
    <form wire:submit="submit" class="auth-card">
        <h1 class="auth-card__title">Set your password</h1>
        <p class="auth-card__subtitle">Last step — choose a password to finish creating your account.</p>

        <div class="field mt-6">
            <label for="password" class="field__label">Password</label>
            <input
                id="password"
                type="password"
                wire:model="password"
                class="field__input"
                autocomplete="new-password"
                autofocus
            >
            @error('password')
                <p class="field__error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation" class="field__label">Confirm password</label>
            <input
                id="password_confirmation"
                type="password"
                wire:model="password_confirmation"
                class="field__input"
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" wire:target="submit">
            Create account
        </button>
    </form>
</div>
