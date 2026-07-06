<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * An admin-managed word added to the text blocklist from the moderation page.
 * Layered on top of the env/config baseline (config/moderation.php) — see the
 * create_moderation_terms migration and App\Actions\ScreenText.
 *
 * Terms are cached so screening isn't a query on every save; the cache is busted
 * on any write (save/delete model events) so an added or removed word takes
 * effect on the very next screen.
 */
#[Fillable(['term'])]
class ModerationTerm extends Model
{
    public const string CACHE_KEY = 'moderation.terms';

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    /**
     * The admin-managed terms, cached. Merged with the env baseline in ScreenText.
     *
     * @return list<string>
     */
    public static function cachedTerms(): array
    {
        /** @var list<string> $terms */
        $terms = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => self::query()->pluck('term')->all(),
        );

        return $terms;
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
