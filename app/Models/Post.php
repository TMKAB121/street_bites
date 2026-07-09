<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\EscapesLikePatterns;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A news story or event — one content type for both: a post with an
 * `event_date` is an event, one without is a plain story. Authored only by
 * admins from /admin/news (no vendor path, so no moderation pipeline and no
 * soft deletes); the body is Markdown, rendered in safe mode on the fly —
 * deliberately uncached, since admin-authored volume never justifies a
 * body_html column. The slug and excerpt are derived accessors (no columns),
 * so they can never go stale after a title or body edit.
 *
 * @property ?Carbon $event_date the pure event date; a `date` cast Larastan
 *                               otherwise reads as a string off the `date` column
 */
#[Fillable(['user_id', 'title', 'body', 'event_date', 'event_location', 'cover_image_path', 'is_published', 'published_at'])]
class Post extends Model
{
    use EscapesLikePatterns;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /**
     * The admin who authored the post — nullable, since posts outlive their
     * author's account (the FK nulls on user deletion).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whether this post is an event (has a date) rather than a plain story. */
    public function isEvent(): bool
    {
        return $this->event_date !== null;
    }

    /**
     * The descriptive URL segment for the story page — derived from the title
     * on the fly (no column), so it always reflects the current title. The id
     * in the route is what identifies the post; a stale slug 404s rather than
     * redirects (mirrors FoodTruck::slug()).
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function slug(): Attribute
    {
        return Attribute::get(
            fn (): string => Str::slug($this->title) ?: 'post',
        );
    }

    /**
     * The Markdown body rendered to HTML, in safe mode: raw HTML in the source
     * is stripped (never parsed) and unsafe link/image schemes are dropped, so
     * the {!! !!} print on the story page can't become an XSS vector even if an
     * admin pastes something hostile.
     *
     * @return Attribute<HtmlString, never>
     */
    protected function bodyHtml(): Attribute
    {
        return Attribute::get(
            fn (): HtmlString => new HtmlString(Str::markdown($this->body, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])),
        );
    }

    /**
     * A plain-text teaser for list rows and the meta description — the rendered
     * body with the markup stripped back out (the cheap, correct way to drop
     * Markdown syntax from the text), capped at 160 chars.
     *
     * @return Attribute<string, never>
     */
    protected function excerpt(): Attribute
    {
        return Attribute::get(
            fn (): string => Str::limit(trim(strip_tags($this->body_html->toHtml())), 160),
        );
    }

    /**
     * Absolute URL of the cover image (1200×630 JPEG — see StorePostCoverImage),
     * or null when the post has none. Absolute because it doubles as og:image.
     */
    public function coverUrl(): ?string
    {
        if ($this->cover_image_path === null) {
            return null;
        }

        return Storage::disk(config('filesystems.public_disk'))->url($this->cover_image_path);
    }

    /**
     * Posts whose title or body contains the term. One scope backs the /news
     * landing page (and any future suggest endpoint), mirroring
     * FoodTruck::search().
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = self::likePattern($term);

        $query->where(fn (Builder $q) => $q
            ->where('title', 'like', $like)
            ->orWhere('body', 'like', $like));
    }
}
