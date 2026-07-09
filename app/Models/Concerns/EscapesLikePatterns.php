<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Shared LIKE-pattern escaping for the search scopes (FoodTruck, Post), so
 * every surface treats user-typed wildcards the same way.
 */
trait EscapesLikePatterns
{
    /**
     * A LIKE pattern that matches the term literally anywhere in a column —
     * user-typed wildcards (%, _) are escaped, not interpreted.
     */
    public static function likePattern(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
