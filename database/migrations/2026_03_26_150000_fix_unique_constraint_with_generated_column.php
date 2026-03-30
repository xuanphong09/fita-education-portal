<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $indexNames = collect(DB::select('SHOW INDEX FROM `training_programs`'))
            ->pluck('Key_name')
            ->unique()
            ->values();

        // If already fixed, skip
        if ($indexNames->contains('uniq_program_scope_active_marker')) {
            return;
        }

        // Permanently delete all soft-deleted training programs to remove duplicates
        DB::statement('DELETE FROM `training_programs` WHERE `deleted_at` IS NOT NULL');

        // Drop old index first if it exists
        if ($indexNames->contains('uniq_program_scope_version')) {
            DB::statement('ALTER TABLE `training_programs` DROP INDEX `uniq_program_scope_version`');
        }

        if ($indexNames->contains('uniq_program_scope_version_active')) {
            DB::statement('ALTER TABLE `training_programs` DROP INDEX `uniq_program_scope_version_active`');
        }

        // Check if column already exists
        $columns = collect(DB::select("SHOW COLUMNS FROM `training_programs`"))
            ->pluck('Field')
            ->values();

        // Add generated column (VIRTUAL - only computed, not stored)
        // active_marker = 1 when deleted_at IS NULL, NULL when deleted_at IS NOT NULL
        if (!$columns->contains('active_marker')) {
            DB::statement(
                'ALTER TABLE `training_programs` '
                . 'ADD COLUMN `active_marker` INT GENERATED ALWAYS AS (IF(`deleted_at` IS NULL, 1, NULL)) VIRTUAL'
            );
        }

        // Add unique index on (major_id, intake_id, version, active_marker)
        // This allows:
        // - Multiple deleted rows (active_marker=NULL allows duplicates, NULL != NULL in uniqueness)
        // - Only one active row per (major_id, intake_id, version) combo (active_marker=1 is strict)
        DB::statement(
            'ALTER TABLE `training_programs` '
            . 'ADD UNIQUE `uniq_program_scope_active_marker` (`major_id`, `intake_id`, `version`, `active_marker`)'
        );
    }

    public function down(): void
    {
        $indexNames = collect(DB::select('SHOW INDEX FROM `training_programs`'))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if ($indexNames->contains('uniq_program_scope_active_marker')) {
            DB::statement('ALTER TABLE `training_programs` DROP INDEX `uniq_program_scope_active_marker`');
        }

        if (collect(DB::select("SHOW COLUMNS FROM `training_programs` LIKE 'active_marker'"))->count() > 0) {
            DB::statement('ALTER TABLE `training_programs` DROP COLUMN `active_marker`');
        }

        // Restore old unique (if needed)
        if (!$indexNames->contains('uniq_program_scope_version')) {
            DB::statement(
                'ALTER TABLE `training_programs` '
                . 'ADD UNIQUE `uniq_program_scope_version` (`major_id`, `intake_id`, `version`)'
            );
        }
    }
};


