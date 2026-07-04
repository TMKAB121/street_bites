{{--
    App-wide toast region. Listens for the browser `toast` event that Livewire
    dispatches (detail = { message, type }) and shows a brief, auto-dismissing
    notification. Alpine-only — no server round-trip to display or hide. Stack it
    once in a layout (it lives above the bottom nav on mobile).

    Trigger from anywhere: $this->dispatch('toast', message: 'Saved', type: 'success').
--}}
<div
    x-data="{
        toasts: [],
        durationMs: 3500,
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: detail.message, type: detail.type || 'success' });
            setTimeout(() => this.remove(id), this.durationMs);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }"
    @toast.window="add($event.detail)"
    class="toast-stack"
    aria-live="polite"
    aria-atomic="true"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="toast"
            :class="'toast--' + toast.type"
            role="status"
            @click="remove(toast.id)"
            x-transition:enter="toast--enter"
            x-transition:enter-start="toast--enter-start"
            x-transition:enter-end="toast--enter-end"
            x-transition:leave="toast--leave"
            x-transition:leave-start="toast--enter-end"
            x-transition:leave-end="toast--enter-start"
        >
            <span class="toast__icon" aria-hidden="true">
                <svg x-show="toast.type === 'success'" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>
                <svg x-show="toast.type !== 'success'" viewBox="0 0 24 24"><path d="M12 8v5"/><path d="M12 16h.01"/><circle cx="12" cy="12" r="9"/></svg>
            </span>
            <span class="toast__message" x-text="toast.message"></span>
        </div>
    </template>
</div>
