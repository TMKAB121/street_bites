<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Actions\ReverseGeocodeLabel;
use App\Actions\ScreenImage;
use App\Actions\ScreenText;
use App\Actions\StoreTruckImage;
use App\Enums\SocialPlatform;
use App\Mail\TruckHeldForReview;
use App\Models\FoodTruck;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The editable form for a single food truck, nested inside ProfilePage and
 * rendered lazily — so its data loads in a follow-up request when the vendor
 * expands the (otherwise collapsed) truck card.
 *
 * The vendor model is per-day: the only operating window edited here is today's,
 * upserted into truck_operating_hours on save.
 */
#[Lazy]
class TruckEditor extends Component
{
    use WithFileUploads;

    /** Upload cap for truck photos, in kilobytes (validation `max:` rule). */
    private const int MAX_UPLOAD_KB = 5120;

    public int $truckId;

    public string $name = '';

    public string $description = '';

    /** Today's open/close, as HTML time-input strings ("HH:MM"). */
    public ?string $opensAt = null;

    public ?string $closesAt = null;

    /**
     * Repeatable menu rows: ['id' => ?int, 'name', 'description', 'price', 'is_available'].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $menuItems = [];

    public mixed $upload = null;

    /**
     * Repeatable social-link rows: ['id' => ?int, 'url' => string]. Only the
     * URL is edited — the platform (which brand icon shows on the truck page)
     * is detected from the URL's host on save.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $socialLinks = [];

    /**
     * IDs of cuisine tags currently selected for this truck. Livewire populates
     * this from the checkbox group in truck-form via wire:model.
     *
     * @var array<int, int>
     */
    public array $selectedTagIds = [];

    /** Name typed into the "add new tag" input. */
    public string $newTagName = '';

    public function mount(int $truckId): void
    {
        $truck = $this->truck();

        $this->name = $truck->name;
        $this->description = (string) $truck->description;

        $today = $truck->todayHours;
        $this->opensAt = $today?->opens_at ? mb_substr((string) $today->opens_at, 0, 5) : null;
        $this->closesAt = $today?->closes_at ? mb_substr((string) $today->closes_at, 0, 5) : null;

        $this->menuItems = $truck->menuItems->map(fn ($item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'description' => (string) $item->description,
            'price' => $item->price_cents === null ? '' : number_format($item->price_cents / 100, 2, '.', ''),
            'is_available' => $item->is_available,
        ])->all();

        $this->selectedTagIds = $truck->tags->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->socialLinks = $truck->socialLinks->map(fn ($link): array => [
            'id' => $link->id,
            'url' => $link->url,
        ])->all();
    }

    /**
     * Resolve the truck and assert the current user owns it. Re-run on every
     * action so a tampered request can never reach another vendor's truck.
     */
    private function truck(): FoodTruck
    {
        $truck = FoodTruck::query()->findOrFail($this->truckId);

        abort_unless($truck->user_id === auth()->id(), 403);

        return $truck;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'opensAt' => 'nullable|date_format:H:i',
            'closesAt' => 'nullable|date_format:H:i',
            'menuItems' => 'array',
            'menuItems.*.name' => 'required|string|max:255',
            'menuItems.*.description' => 'nullable|string|max:500',
            'menuItems.*.price' => 'nullable|numeric|min:0|max:99999.99',
            'selectedTagIds' => 'array',
            'selectedTagIds.*' => 'integer|exists:tags,id',
            'newTagName' => 'nullable|string|max:100',
            'socialLinks' => 'array',
            'socialLinks.*.url' => 'required|url:http,https|max:255',
        ];
    }

    /**
     * Readable names for the repeatable-row fields in validation messages
     * ("The link field is required", not "The social links.0.url field …").
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'socialLinks.*.url' => 'link',
        ];
    }

    public function addMenuItem(): void
    {
        $this->menuItems[] = ['id' => null, 'name' => '', 'description' => '', 'price' => '', 'is_available' => true];
    }

    public function removeMenuItem(int $index): void
    {
        unset($this->menuItems[$index]);
        $this->menuItems = array_values($this->menuItems);
    }

    public function addSocialLink(): void
    {
        $this->socialLinks[] = ['id' => null, 'url' => ''];
    }

    public function removeSocialLink(int $index): void
    {
        unset($this->socialLinks[$index]);
        $this->socialLinks = array_values($this->socialLinks);
    }

    public function save(ScreenText $screenText): void
    {
        // Livewire sends checkbox values as strings.
        $this->selectedTagIds = array_map(intval(...), $this->selectedTagIds);

        $this->validate($this->rules());

        /** @var User $user */
        $user = auth()->user();

        // A blocked vendor can neither publish new trucks nor re-publish existing
        // ones (their trucks are already unpublished — see ModerationQueue).
        if ($user->isBanned()) {
            $this->toast('Your account is blocked from publishing trucks.', 'error');

            return;
        }

        $truck = $this->truck();

        // Auto-screen before going live: text now, images already screened at
        // upload. Any flag holds the truck out of discovery for admin review
        // rather than publishing it. Clean trucks publish instantly, as before.
        $textFlags = $screenText(...$this->screenableText());
        $imageFlagged = $truck->images()->where('screen_status', FoodTruck::SCREEN_FLAGGED)->exists();
        $flagged = $textFlags !== [] || $imageFlagged;

        $truck->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
        ]);

        if ($flagged) {
            // Held out of discovery until an admin approves; emails the admins on
            // the transition into review (see holdForReview).
            $this->holdForReview($truck, $this->flagReason($textFlags, $imageFlagged));
        } else {
            // Clean: publish instantly, keeping any admin sign-off it already had
            // (no re-queue spam on an hours edit).
            $truck->update([
                'is_published' => true,
                'screen_status' => FoodTruck::SCREEN_PASSED,
                'moderation_reason' => null,
            ]);
        }

        // Upsert today's operating window (the per-day vendor model).
        $truck->operatingHours()->updateOrCreate(
            ['business_date' => today()],
            ['opens_at' => $this->opensAt, 'closes_at' => $this->closesAt],
        );

        $this->saveMenuItems($truck);
        $this->saveTags($truck);
        $this->saveSocialLinks($truck);

        $this->dispatch('truck-saved', name: $truck->name)->to(ProfilePage::class);

        $flagged
            ? $this->toast('Submitted for review — it’ll go live once approved.', 'info')
            : $this->toast('Changes saved');
    }

    /**
     * Every vendor-typed string that should run through the text screen: the
     * truck name, description, and each menu item's name and description.
     *
     * @return list<string>
     */
    private function screenableText(): array
    {
        $texts = [$this->name, $this->description];

        foreach ($this->menuItems as $item) {
            $texts[] = (string) ($item['name'] ?? '');
            $texts[] = (string) ($item['description'] ?? '');
        }

        return $texts;
    }

    /**
     * A short human-readable reason for the moderation queue, capped to the
     * column width. Names the matched text terms and/or notes a flagged image.
     *
     * @param  list<string>  $textFlags
     */
    private function flagReason(array $textFlags, bool $imageFlagged): string
    {
        $parts = [];

        if ($textFlags !== []) {
            $parts[] = 'Text: '.implode(', ', $textFlags);
        }

        if ($imageFlagged) {
            $parts[] = 'Flagged image';
        }

        return Str::limit(implode('; ', $parts), 250, '');
    }

    /**
     * Take a truck out of discovery pending admin review, and email the admins
     * on the *transition* into that state (not on every edit of an already-held
     * truck). Shared by save() and image upload so a flag from either path holds
     * the truck immediately and consistently — an offensive photo can't linger
     * on a live truck until the vendor's next save.
     */
    private function holdForReview(FoodTruck $truck, string $reason): void
    {
        $alreadyHeld = $truck->screen_status === FoodTruck::SCREEN_FLAGGED;

        $truck->update([
            'is_published' => false,
            'screen_status' => FoodTruck::SCREEN_FLAGGED,
            'moderation_reason' => $reason,
            'reviewed_at' => null,
        ]);

        if (! $alreadyHeld) {
            /** @var User $user */
            $user = auth()->user();
            $this->notifyAdminsOfHold($truck->name, $user->email, $reason);
        }
    }

    /**
     * Email the moderation admins that a truck was just held for review. Queued
     * (the mailable is ShouldQueue), so it never blocks the save; a no-op when
     * no admin emails are configured.
     */
    private function notifyAdminsOfHold(string $truckName, string $ownerEmail, string $reason): void
    {
        /** @var list<string> $admins */
        $admins = config('admin.emails', []);

        if ($admins === []) {
            return;
        }

        Mail::to($admins)->send(new TruckHeldForReview(
            truckName: $truckName,
            ownerEmail: $ownerEmail,
            reason: $reason,
            reviewUrl: route('admin.trucks'),
        ));
    }

    /**
     * Pin the truck at the vendor's current position — "I'm parked here for
     * the day". The coordinates come from the browser's geolocation API via
     * the Set-my-location button; the truck-page map cache is keyed on
     * lat/lng, so the next visit renders the map at the new pin automatically.
     * The pin also refreshes location_label via reverse geocoding — replaced
     * (or cleared, if the lookup fails) rather than kept, because a label from
     * a previous spot is worse than none.
     */
    public function setLocation(ReverseGeocodeLabel $reverseGeocodeLabel, float $latitude, float $longitude, string $timezone = ''): void
    {
        $truck = $this->truck();

        abort_unless(
            $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180,
            422,
        );

        $truck->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_label' => $reverseGeocodeLabel($latitude, $longitude),
            'located_at' => now(),
            // The vendor is at the truck, so their browser timezone is the
            // truck's — remember it for local-time display and "Now Open".
            'timezone' => $this->resolveTimezone($timezone, $truck),
        ]);

        $this->toast('Location pinned — eaters can find you on the map');
    }

    /**
     * "Now Open" — stamp today's opening time at the current moment. The vendor
     * flips this when they start serving, so the open time is the truck-local
     * wall clock right now (not the app's UTC clock). Persists straight away
     * like a save; the close time is still set/edited separately.
     */
    public function goLiveNow(string $timezone = ''): void
    {
        $truck = $this->truck();
        $tz = $this->resolveTimezone($timezone, $truck);

        if ($tz !== $truck->timezone) {
            $truck->update(['timezone' => $tz]);
        }

        $localNow = now()->setTimezone($tz);
        $this->opensAt = $localNow->format('H:i');

        // Match save()/todayHours: one row per business date, today's window.
        $truck->operatingHours()->updateOrCreate(
            ['business_date' => today()],
            ['opens_at' => $this->opensAt],
        );

        $this->toast('You’re open — opened at '.$localNow->format('g:i A'));
    }

    /**
     * "Closing Up" — the mirror of goLiveNow(): stamp today's closing time at
     * the current truck-local moment when the vendor stops serving. Persists
     * straight away so isOpenNow() flips to closed immediately (a null
     * closes_at otherwise reads as "still open").
     */
    public function closeNow(string $timezone = ''): void
    {
        $truck = $this->truck();
        $tz = $this->resolveTimezone($timezone, $truck);

        if ($tz !== $truck->timezone) {
            $truck->update(['timezone' => $tz]);
        }

        $localNow = now()->setTimezone($tz);
        $this->closesAt = $localNow->format('H:i');

        // Same one-row-per-business-date window as goLiveNow()/save().
        $truck->operatingHours()->updateOrCreate(
            ['business_date' => today()],
            ['closes_at' => $this->closesAt],
        );

        $this->toast('You’re closed — closed at '.$localNow->format('g:i A'));
    }

    /**
     * Pick a valid IANA timezone: the browser-supplied one if it's real,
     * otherwise the truck's stored timezone, otherwise the app default. Guards
     * against a tampered/garbage `timezone` payload reaching the database.
     */
    private function resolveTimezone(string $candidate, FoodTruck $truck): string
    {
        if ($candidate !== '' && in_array($candidate, timezone_identifiers_list(), true)) {
            return $candidate;
        }

        return $truck->timezone ?? config('app.timezone');
    }

    /**
     * Find-or-create a tag by the typed name and add it to the current
     * selection. Called from the "Add" button in the tag picker fieldset so the
     * vendor doesn't have to wait until Save to see the new pill appear.
     */
    public function addTag(): void
    {
        $name = trim($this->newTagName);

        if ($name === '') {
            return;
        }

        $tag = Tag::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        );

        if (! in_array($tag->id, $this->selectedTagIds, true)) {
            $this->selectedTagIds[] = $tag->id;
        }

        $this->newTagName = '';
    }

    /**
     * Fire an app-wide toast. Dispatched as a browser event so the <x-toast>
     * region in the layout can show it; the user gets confirmation that an
     * otherwise-silent save/upload/delete actually happened.
     */
    private function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    /**
     * Sync cuisine tags. If $newTagName is still set (vendor typed a name but
     * didn't click Add), find-or-create it here so saving the form is one action.
     * Mirrors saveMenuItems() but uses sync() because tags are shared taxonomy
     * rows — the truck doesn't own them, it only references them via pivot.
     */
    private function saveTags(FoodTruck $truck): void
    {
        $ids = $this->selectedTagIds;

        if (trim($this->newTagName) !== '') {
            $tag = Tag::query()->firstOrCreate(
                ['slug' => Str::slug($this->newTagName)],
                ['name' => trim($this->newTagName)],
            );

            if (! in_array($tag->id, $ids, true)) {
                $ids[] = $tag->id;
            }

            $this->newTagName = '';
        }

        $truck->tags()->sync($ids);

        $this->selectedTagIds = $truck->tags()->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Reconcile social links the same way as menu items: update, create, and
     * delete removed rows. The platform is re-detected from the URL on every
     * save, so editing a row's link to a different network swaps its icon too.
     */
    private function saveSocialLinks(FoodTruck $truck): void
    {
        $keptIds = [];

        foreach (array_values($this->socialLinks) as $order => $row) {
            $url = trim((string) ($row['url'] ?? ''));

            $link = $truck->socialLinks()->updateOrCreate(
                ['id' => $row['id'] ?? null],
                [
                    'url' => $url,
                    'platform' => SocialPlatform::fromUrl($url),
                    'sort_order' => $order,
                ],
            );

            $keptIds[] = $link->id;
        }

        $truck->socialLinks()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Reconcile the menu: update existing rows, create new ones, and delete any
     * the vendor removed from the form.
     */
    private function saveMenuItems(FoodTruck $truck): void
    {
        $keptIds = [];

        foreach (array_values($this->menuItems) as $order => $row) {
            $attributes = [
                'name' => $row['name'],
                'description' => ($row['description'] ?? '') ?: null,
                'price_cents' => ($row['price'] ?? '') === '' ? null : (int) round((float) $row['price'] * 100),
                'is_available' => (bool) ($row['is_available'] ?? true),
                'sort_order' => $order,
            ];

            $item = $truck->menuItems()->updateOrCreate(
                ['id' => $row['id'] ?? null],
                $attributes,
            );

            $keptIds[] = $item->id;
        }

        $truck->menuItems()->whereNotIn('id', $keptIds)->delete();
    }

    public function uploadImage(StoreTruckImage $storeTruckImage, ScreenImage $screenImage): void
    {
        $this->validate([
            'upload' => 'required|image|mimes:jpeg,png,webp|max:'.self::MAX_UPLOAD_KB,
        ]);

        $truck = $this->truck();
        $image = $storeTruckImage($truck, $this->upload);

        // Screen the image on the way in. A flag both records the result on the
        // image row and immediately holds the whole truck out of discovery — so
        // an offensive photo can never sit on a live truck while it waits for an
        // admin, rather than only being caught on the vendor's next save.
        $labels = $screenImage((string) $this->upload->getRealPath(), (string) $this->upload->getClientOriginalName());

        $this->reset('upload');

        if ($labels === []) {
            $this->toast('Photo added');

            return;
        }

        $image->update([
            'screen_status' => FoodTruck::SCREEN_FLAGGED,
            'flag_labels' => Str::limit(implode(', ', $labels), 250, ''),
        ]);

        $this->holdForReview($truck, 'Flagged image');

        $this->toast('Photo added, but flagged — your truck is held for review until it’s approved.', 'info');
    }

    public function deleteImage(int $imageId): void
    {
        $image = $this->truck()->images()->whereKey($imageId)->firstOrFail();

        Storage::disk(config('filesystems.public_disk'))->delete($image->path);
        $image->delete();

        $this->toast('Photo removed');
    }

    public function deleteTruck(): void
    {
        $this->truck()->delete();

        // The truck is gone — don't let render() re-resolve it (findOrFail would
        // throw). The parent drops this card from the expanded set.
        $this->skipRender();

        $this->dispatch('truck-deleted', truckId: $this->truckId)->to(ProfilePage::class);
        $this->toast('Truck deleted');
    }

    public function placeholder(): View
    {
        return view('livewire.profile.truck-editor-placeholder');
    }

    public function render(): View
    {
        $truck = $this->truck();

        return view('livewire.profile.truck-editor', [
            'images' => $truck->images,
            'allTags' => Tag::query()->orderBy('name')->get(),
            'locatedAt' => $truck->located_at,
            'locationLabel' => $truck->location_label,
            'timezone' => $truck->timezone ?? config('app.timezone'),
        ]);
    }
}
