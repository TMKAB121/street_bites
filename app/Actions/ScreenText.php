<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ModerationTerm;

/**
 * A coarse, free content screen over vendor-supplied text (truck name,
 * description, menu items). Whole-word, case-insensitive matching against the
 * blocklist — a stopgap that always runs, even locally. Returns the matched
 * terms; an empty result means "clean". The admin moderation queue is the real
 * backstop; this just surfaces the obvious cases so they never publish silently.
 * Swap in AWS Comprehend toxicity later.
 *
 * The blocklist is the union of the env/config baseline (config/moderation.php,
 * an un-deletable floor) and the admin-managed terms in moderation_terms
 * (editable from the moderation page, effective immediately — no redeploy).
 */
final class ScreenText
{
    /**
     * @return list<string> matched blocklist terms (empty = passed)
     */
    public function __invoke(string ...$texts): array
    {
        $blocklist = $this->blocklist();

        if ($blocklist === []) {
            return [];
        }

        $haystack = mb_strtolower(implode(' ', $texts));
        $matched = [];

        foreach ($blocklist as $term) {
            if ($term === '') {
                continue;
            }

            if (preg_match('/\b'.preg_quote($term, '/').'\b/u', $haystack) === 1) {
                $matched[] = $term;
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * The effective blocklist: the env/config baseline unioned with the
     * admin-managed DB terms, normalised (trimmed, lowercase, de-duped, no
     * blanks) so matching is consistent however a term was entered.
     *
     * @return list<string>
     */
    private function blocklist(): array
    {
        /** @var list<string> $baseline */
        $baseline = config('moderation.text_blocklist', []);

        $terms = array_map(
            fn (string $term): string => mb_strtolower(trim($term)),
            array_merge($baseline, ModerationTerm::cachedTerms()),
        );

        return array_values(array_unique(array_filter($terms, fn (string $term): bool => $term !== '')));
    }
}
