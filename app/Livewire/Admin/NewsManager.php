<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\StorePostCoverImage;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The news & events authoring page — the only way content enters the /news
 * section. Admin-only (the ADMIN_EMAILS allowlist) by design: an admin form
 * behind the existing auth + EnsureAdmin stack beats an IP-allowlisted API
 * (no token to manage, survives IP churn, protected by the email-OTP second
 * factor). One form edits one post at a time; the list below it covers every
 * post, drafts included. No screening pass — the authors are the moderators.
 *
 * Access is gated by EnsureAdmin on the route; every action re-checks
 * isAdmin() too (AuthorizesAdmin), so a crafted Livewire request can never
 * reach an action without admin rights.
 */
#[Layout('layouts::shell', ['active' => 'news', 'title' => 'News admin', 'robots' => 'noindex'])]
class NewsManager extends Component
{
    use AuthorizesAdmin;
    use WithFileUploads;

    /** Working set cap — most-recent posts; pagination is a later concern. */
    private const int LIMIT = 50;

    /** Upload cap for cover images, in kilobytes (validation `max:` rule). */
    private const int MAX_UPLOAD_KB = 5120;

    /** The post being edited, or null when the form is composing a new one. */
    public ?int $editingId = null;

    public string $title = '';

    /** Markdown source. */
    public string $body = '';

    /** Event date as an HTML date-input string ("YYYY-MM-DD"); null = a plain story. */
    public ?string $eventDate = null;

    public string $eventLocation = '';

    public mixed $upload = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    /** Load a post into the form for editing. */
    public function edit(int $postId): void
    {
        $post = $this->post($postId);

        $this->editingId = $post->id;
        $this->title = $post->title;
        $this->body = $post->body;
        $this->eventDate = $post->event_date?->format('Y-m-d');
        $this->eventLocation = (string) $post->event_location;
        $this->reset('upload');
        $this->resetValidation();
    }

    /** Clear the form back to composing a new post. */
    public function startNew(): void
    {
        $this->reset('editingId', 'title', 'body', 'eventDate', 'eventLocation', 'upload');
        $this->resetValidation();
    }

    /**
     * Create or update the post from the form. New posts start as drafts —
     * publishing is a separate, deliberate action on the list row.
     */
    public function save(StorePostCoverImage $storeCover): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
            'eventDate' => 'nullable|date_format:Y-m-d',
            'eventLocation' => 'nullable|string|max:255',
            'upload' => 'nullable|image|mimes:jpeg,png,webp|max:'.self::MAX_UPLOAD_KB,
        ]);

        $attributes = [
            'title' => $this->title,
            'body' => $this->body,
            'event_date' => $this->eventDate,
            'event_location' => $this->eventLocation !== '' ? $this->eventLocation : null,
        ];

        if ($this->editingId === null) {
            $post = Post::query()->create([...$attributes, 'user_id' => auth()->id()]);
            $this->editingId = $post->id;
        } else {
            $post = $this->post($this->editingId);
            $post->update($attributes);
        }

        if ($this->upload !== null) {
            // Replace, not accumulate: a post has one cover, so the old file
            // goes when a new one lands (the DB cascade never touches the disk).
            $previous = $post->cover_image_path;
            $post->update(['cover_image_path' => $storeCover($post, $this->upload)]);

            if ($previous !== null) {
                Storage::disk(config('filesystems.public_disk'))->delete($previous);
            }

            $this->reset('upload');
        }

        $this->toast($post->is_published ? 'Post saved' : 'Draft saved');
    }

    /**
     * Make the post publicly visible. The first publish stamps published_at
     * (the byline date and the /news sort key); re-publishing after an
     * unpublish keeps the original date.
     */
    public function publish(int $postId): void
    {
        $post = $this->post($postId);

        $post->update([
            'is_published' => true,
            'published_at' => $post->published_at ?? now(),
        ]);

        $this->toast('Post published');
    }

    /** Hide the post from /news again; published_at survives for a re-publish. */
    public function unpublish(int $postId): void
    {
        $this->post($postId)->update(['is_published' => false]);

        $this->toast('Post unpublished');
    }

    /** Drop the cover image (file + column); the post falls back to the default og-image. */
    public function removeCover(int $postId): void
    {
        $post = $this->post($postId);

        if ($post->cover_image_path !== null) {
            Storage::disk(config('filesystems.public_disk'))->delete($post->cover_image_path);
            $post->update(['cover_image_path' => null]);
        }

        $this->toast('Cover removed');
    }

    /** Hard delete — no soft-delete evidence trail here; the authors are the moderators. */
    public function deletePost(int $postId): void
    {
        $post = $this->post($postId);

        Storage::disk(config('filesystems.public_disk'))->deleteDirectory("post-covers/{$post->id}");
        $post->delete();

        if ($this->editingId === $postId) {
            $this->startNew();
        }

        $this->toast('Post deleted');
    }

    public function render(): View
    {
        return view('livewire.admin.news-manager', [
            'posts' => Post::query()->latest('id')->limit(self::LIMIT)->get(),
        ]);
    }

    /** Resolve a post after re-asserting admin rights. */
    private function post(int $postId): Post
    {
        $this->authorizeAdmin();

        return Post::query()->findOrFail($postId);
    }
}
