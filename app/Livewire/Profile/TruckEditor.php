<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Actions\StoreTruckImage;
use App\Models\FoodTruck;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
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

    public function save(): void
    {
        // Coerce checkbox values to int before validation (Livewire sends strings).
        $this->selectedTagIds = array_map('intval', $this->selectedTagIds);

        $this->validate($this->rules());

        $truck = $this->truck();
        $truck->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
        ]);

        // Upsert today's operating window (the per-day vendor model).
        $truck->operatingHours()->updateOrCreate(
            ['business_date' => today()],
            ['opens_at' => $this->opensAt, 'closes_at' => $this->closesAt],
        );

        $this->saveMenuItems($truck);
        $this->saveTags($truck);

        $this->dispatch('truck-saved', name: $truck->name)->to(ProfilePage::class);
        $this->toast('Changes saved');
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

        // Refresh so the re-render shows any newly-created tag as checked.
        $this->selectedTagIds = $truck->tags()->pluck('id')->map(fn ($id): int => (int) $id)->all();
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

    public function uploadImage(StoreTruckImage $storeTruckImage): void
    {
        $this->validate([
            'upload' => 'required|image|mimes:jpeg,png,webp|max:5120',
        ]);

        $storeTruckImage($this->truck(), $this->upload);

        $this->reset('upload');
        $this->toast('Photo added');
    }

    public function deleteImage(int $imageId): void
    {
        $image = $this->truck()->images()->whereKey($imageId)->firstOrFail();

        Storage::disk('public')->delete($image->path);
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
        return view('livewire.profile.truck-editor', [
            'images' => $this->truck()->images,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }
}
