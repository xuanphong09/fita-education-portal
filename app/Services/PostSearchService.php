<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Shared post-search logic used by both:
 *   - PostIndex Livewire page (client/posts/⚡index.blade.php)
 *   - GlobalSearch Livewire component (client/⚡global-search.blade.php)
 *
 * Strategy (priority order):
 *   1. FULLTEXT BOOLEAN MODE on virtual search columns  (fastest at scale)
 *   2. LIKE on virtual search columns                   (medium)
 *   3. JSON_EXTRACT + LIKE on raw JSON columns          (fallback, always works)
 */
class PostSearchService
{
    /** @var bool|null  Cached per PHP process */
    private static ?bool $hasSearchColumns = null;

    /** @var bool|null */
    private static ?bool $hasFulltextIndexes = null;

    /* ------------------------------------------------------------------ */
    /*  Schema introspection helpers (cached)                              */
    /* ------------------------------------------------------------------ */

    public static function hasSearchColumns(): bool
    {
        if (self::$hasSearchColumns === null) {
            self::$hasSearchColumns = Schema::hasColumns('posts', [
                'title_vi_search', 'title_en_search',
                'excerpt_vi_search', 'excerpt_en_search',
                'content_vi_search', 'content_en_search',
                'slug_vi_search', 'slug_en_search',
            ]);
        }

        return self::$hasSearchColumns;
    }

    public static function hasFulltextIndexes(): bool
    {
        if (self::$hasFulltextIndexes === null) {
            self::$hasFulltextIndexes = false;

            if (self::hasSearchColumns() && DB::getDriverName() === 'mysql') {
                try {
                    $rows = DB::select(
                        "SHOW INDEX FROM `posts` WHERE `Key_name` IN ('ft_posts_vi', 'ft_posts_en')"
                    );
                    $found = count(array_unique(array_column((array) $rows, 'Key_name')));
                    self::$hasFulltextIndexes = $found >= 2;
                } catch (\Throwable) {
                    // keep false
                }
            }
        }

        return self::$hasFulltextIndexes;
    }

    /* ------------------------------------------------------------------ */
    /*  Public API                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Parse a raw search string into clean, non-empty tokens.
     *
     * @return string[]
     */
    public static function parseTerms(string $search): array
    {
        $search = trim(preg_replace('/\s+/u', ' ', $search) ?? '');

        return $search !== ''
            ? (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            : [];
    }

    /**
     * Apply locale-aware content filter to a Post query.
     * In EN locale, hide posts without any English content.
     */
    public static function applyLocaleFilter(Builder $query, bool $isEn): Builder
    {
        if (!$isEn) {
            return $query;
        }

        if (self::hasSearchColumns()) {
            $query->whereNotNull('content_en_search')
                  ->where('content_en_search', '!=', '');
        } else {
            $query->whereRaw(
                "COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(content, '$.en'))), ''), NULL) IS NOT NULL"
            );
        }

        return $query;
    }

    /**
     * Apply full-text / LIKE search terms to a Post query.
     *
     * Each term is added as an AND condition so that all tokens must match.
     *
     * @param  Builder  $query
     * @param  string[] $terms   Already-split, non-empty tokens (use parseTerms())
     * @param  bool     $isEn
     */
    public static function applyTerms(Builder $query, array $terms, bool $isEn): Builder
    {
        if (empty($terms)) {
            return $query;
        }

        $hasSearchColumns   = self::hasSearchColumns();
        $hasFulltextIndexes = self::hasFulltextIndexes();

        $titleLocaleColumn   = $isEn ? 'title_en_search'   : 'title_vi_search';
        $excerptLocaleColumn = $isEn ? 'excerpt_en_search' : 'excerpt_vi_search';
        $contentLocaleColumn = $isEn ? 'content_en_search' : 'content_vi_search';
        $slugLocaleColumn    = $isEn ? 'slug_en_search'    : 'slug_vi_search';
        $jsonLocalePath      = '$.' . ($isEn ? 'en' : 'vi');

        $ftColumns = $isEn
            ? 'title_en_search, excerpt_en_search, content_en_search'
            : 'title_vi_search, excerpt_vi_search, content_vi_search';

        $escapeLike = static function (string $value): string {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        };

        foreach ($terms as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }

            $keyword  = '%' . $escapeLike($term) . '%';
            $slugTerm = Str::slug($term);

            // InnoDB default min token length = 3; shorter terms fall back to LIKE.
            $canUseFt = $hasFulltextIndexes && mb_strlen($term) >= 3;

            // Strip FULLTEXT operator chars so we don't crash in BOOLEAN MODE.
            $ftTerm = trim(preg_replace('/[+\-><()~*"@]+/', ' ', $term) ?? $term);

            $query->where(function ($q) use (
                $canUseFt,
                $ftColumns,
                $ftTerm,
                $hasSearchColumns,
                $jsonLocalePath,
                $keyword,
                $slugTerm,
                $escapeLike,
                $titleLocaleColumn,
                $excerptLocaleColumn,
                $contentLocaleColumn,
                $slugLocaleColumn,
            ) {
                // ── Primary match ──────────────────────────────────────────
                if ($canUseFt && $ftTerm !== '') {
                    // Single index scan – much faster at scale.
                    $q->whereRaw("MATCH({$ftColumns}) AGAINST(? IN BOOLEAN MODE)", [$ftTerm]);
                } elseif ($hasSearchColumns) {
                    $q->where($titleLocaleColumn, 'LIKE', $keyword)
                      ->orWhere($excerptLocaleColumn, 'LIKE', $keyword)
                      ->orWhere($contentLocaleColumn, 'LIKE', $keyword)
                      ->orWhere('slug', 'LIKE', $keyword);
                } else {
                    // JSON fallback (SQLite / MySQL without generated columns).
                    $q->whereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '{$jsonLocalePath}')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                            [$keyword]
                        )
                        ->orWhereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(excerpt, '{$jsonLocalePath}')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                            [$keyword]
                        )
                        ->orWhereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(content, '{$jsonLocalePath}')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                            [$keyword]
                        );
                }

                // ── Slug match (always LIKE; slugs are diacritic-free) ──────
                // Handles cases like "hoi thao" → slug "hoi-thao" even when
                // FULLTEXT / collation would miss the accented form.
                if ($slugTerm !== '') {
                    $slugKeyword = '%' . $escapeLike($slugTerm) . '%';
                    if ($hasSearchColumns) {
                        $q->orWhere($slugLocaleColumn, 'LIKE', $slugKeyword);
                    } else {
                        $q->orWhereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(slug_translations, '{$jsonLocalePath}')), '') LIKE ? ESCAPE '\\\\'",
                            [$slugKeyword]
                        );
                    }
                    $q->orWhere('slug', 'LIKE', $slugKeyword);
                }
            });
        }

        return $query;
    }
}

