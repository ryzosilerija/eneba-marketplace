<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

/**
 * Typo-tolerant product search using PostgreSQL's pg_trgm extension.
 *
 * A trigram is a 3-letter chunk of a word ("cyb", "ybe", "ber", ...). Two strings that share many
 * trigrams are similar even if one has a typo, so "cyberpnk" still matches "Cyberpunk 2077".
 * word_similarity(q, name) compares the query against the best-matching part of the name, so
 * partial input like "witch" also scores high for "The Witcher 3".
 * We combine it with a plain ILIKE '%q%' so exact substrings always match.
 */
final class SearchService
{
    /** Minimum word_similarity for a fuzzy match (0..1). 0.3 is pg_trgm's own default. */
    private const THRESHOLD = 0.3;

    /**
     * Returns a SQL condition + params for matching the query against the listing alias "t".
     * @return array{0: string, 1: array<string, string>}
     */
    public function condition(string $query, string $prefix = 's'): array
    {
        $q = $this->normalize($query);
        $like = '%' . addcslashes($q, '%_\\') . '%';
        // Short queries (1-2 chars) produce noisy trigram matches, so only use substring matching.
        if (mb_strlen($q) < 3) {
            return ["t.name ILIKE :{$prefix}like", ["{$prefix}like" => $like]];
        }
        return [
            "(t.name ILIKE :{$prefix}like OR word_similarity(:{$prefix}q, t.name) >= " . self::THRESHOLD . ')',
            ["{$prefix}like" => $like, "{$prefix}q" => $q],
        ];
    }

    /**
     * ORDER BY expression ranking best matches first: names starting with the query, then
     * substring matches, then by fuzzy similarity score.
     * @return array{0: string, 1: array<string, string>}
     */
    public function relevanceOrder(string $query, string $prefix = 'r'): array
    {
        $q = $this->normalize($query);
        return [
            "(t.name ILIKE :{$prefix}start) DESC, (t.name ILIKE :{$prefix}like) DESC, word_similarity(:{$prefix}q, t.name) DESC, t.sales_count DESC",
            [
                "{$prefix}start" => addcslashes($q, '%_\\') . '%',
                "{$prefix}like"  => '%' . addcslashes($q, '%_\\') . '%',
                "{$prefix}q"     => $q,
            ],
        ];
    }

    /** Quick results for the search-as-you-type dropdown. */
    public function suggest(string $query, int $limit = 6): array
    {
        if (mb_strlen($this->normalize($query)) < 2) {
            return [];
        }
        [$where, $params] = $this->condition($query);
        [$order, $orderParams] = $this->relevanceOrder($query);
        $result = Product::list($where, $params + $orderParams, $order, $limit, 0);
        return array_map(fn ($p) => [
            'slug'      => $p['slug'],
            'name'      => $p['name'],
            'platform'  => $p['platform'],
            'region'    => $p['region'],
            'cover'     => $p['cover'],
            'min_price' => $p['min_price'],
        ], $result['items']);
    }

    private function normalize(string $q): string
    {
        return trim(preg_replace('/\s+/', ' ', $q));
    }
}
