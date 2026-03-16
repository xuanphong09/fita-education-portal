<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FULLTEXT indexes on STORED generated search columns.
     *
     * Benefits vs LIKE on generated columns:
     *   - InnoDB inverted-word index → word-level lookup instead of full scan.
     *   - Single MATCH query replaces three separate LIKE conditions per term.
     *   - Relevant for datasets with 10 k+ rows; safe to add earlier.
     *
     * Requirements:
     *   - MySQL 8.0+ or MariaDB 10.5+ (FULLTEXT on STORED generated columns).
     *   - Migration 2026_03_12_120000 must have run first (the generated columns).
     *
     * FULLTEXT minimum token size (InnoDB default = 3 chars).
     * Terms shorter than 3 chars still fall back to LIKE in the query layer.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            // SQLite / pgsql: skip silently (no FULLTEXT support needed for dev/test).
            return;
        }

        if (!Schema::hasColumn('posts', 'title_vi_search')) {
            // Generated columns not yet created – bail; the query layer has a LIKE fallback.
            return;
        }

        DB::statement(
            'ALTER TABLE `posts` ADD FULLTEXT INDEX `ft_posts_vi` (`title_vi_search`, `excerpt_vi_search`, `content_vi_search`)'
        );
        DB::statement(
            'ALTER TABLE `posts` ADD FULLTEXT INDEX `ft_posts_en` (`title_en_search`, `excerpt_en_search`, `content_en_search`)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['ft_posts_vi', 'ft_posts_en'] as $index) {
            try {
                DB::statement("ALTER TABLE `posts` DROP INDEX `{$index}`");
            } catch (\Throwable) {
                // Index may not exist – ignore.
            }
        }
    }
};

