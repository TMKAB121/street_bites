<div>
    <a href="{{ route('home') }}" class="mb-6 inline-block text-sm text-text-muted" wire:navigate>
        ← Back to home
    </a>

    <form wire:submit="submit" class="auth-card">
        <h1 class="auth-card__title">Welcome back</h1>
        <p class="auth-card__subtitle">Sign in to your Street Bites account.</p>

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

        <div class="field mt-4">
            <label for="password" class="field__label">Password</label>
            <input
                id="password"
                type="password"
                wire:model="password"
                class="field__input"
                placeholder="••••••••••••"
                autocomplete="current-password"
            >
            @error('password')
                <p class="field__error">{{ $message }}</p>
            @enderror
        </div>

        <a href="{{ route('auth.password.request') }}" class="mt-2 block text-right text-sm text-text-muted" wire:navigate>
            Forgot password?
        </a>

        <button type="submit" class="btn btn-primary mt-6 w-full" wire:loading.attr="disabled" wire:target="submit">
            <span wire:loading.remove wire:target="submit">Sign in</span>
            <span wire:loading wire:target="submit">Checking…</span>
        </button>

        <a href="{{ route('auth.email') }}" class="mt-4 block text-center text-sm text-text-muted" wire:navigate>
            New here? Create an account
        </a>
    </form>
</div>
