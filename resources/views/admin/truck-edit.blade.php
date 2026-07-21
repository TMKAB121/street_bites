{{--
    Admin edit page for an UNCLAIMED truck (admin.trucks.edit). Lets a moderator
    fix imported/seeded data before a vendor claims the listing. It embeds the same
    TruckEditor the profile uses — non-lazy here (there's no disclosure list to
    expand), and the editor's own truck() guard permits admins on unclaimed trucks.
    Owned trucks 404 at the route, so this only ever edits an ownerless listing.
--}}
<x-layouts::shell
    :title="'Edit '.$truck->name.' — Street Bites'"
    description="Admin editing of an unclaimed truck listing."
    robots="noindex"
    active="profile"
>
    <a href="{{ route('admin.trucks') }}" class="truck-page__back">&larr; Moderation queue</a>

    <header class="mb-6">
        <h1 class="text-xl font-semibold text-primary">Edit unclaimed truck</h1>
        <p class="text-text-muted mt-1 text-sm">
            This listing has no owner yet. Your edits publish it the same way a
            vendor's save would; a screening flag holds it for review. When someone
            claims it, they take over editing.
        </p>
    </header>

    <livewire:profile.truck-editor :truck-id="$truck->id" :lazy="false" />
</x-layouts::shell>
