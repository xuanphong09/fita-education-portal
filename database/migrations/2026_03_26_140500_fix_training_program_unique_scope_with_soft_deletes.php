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

        if (!$indexNames->contains('idx_training_programs_major_id_fk')) {
            DB::statement('ALTER TABLE `training_programs` ADD INDEX `idx_training_programs_major_id_fk` (`major_id`)');
        }

        if (!$indexNames->contains('idx_training_programs_intake_id_fk')) {
            DB::statement('ALTER TABLE `training_programs` ADD INDEX `idx_training_programs_intake_id_fk` (`intake_id`)');
        }

        $indexNames = collect(DB::select('SHOW INDEX FROM `training_programs`'))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if ($indexNames->contains('uniq_program_scope_version_active')) {
            return;
        }

        if ($indexNames->contains('uniq_program_scope_version')) {
            DB::statement(
                'ALTER TABLE `training_programs` '
                . 'DROP INDEX `uniq_program_scope_version`, '
                . 'ADD UNIQUE `uniq_program_scope_version_active` (`major_id`, `intake_id`, `version`, `deleted_at`)'
            );

            return;
        }

        DB::statement(
            'ALTER TABLE `training_programs` '
            . 'ADD UNIQUE `uniq_program_scope_version_active` (`major_id`, `intake_id`, `version`, `deleted_at`)'
        );
    }

    public function down(): void
    {
        $indexNames = collect(DB::select('SHOW INDEX FROM `training_programs`'))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if ($indexNames->contains('uniq_program_scope_version') && !$indexNames->contains('uniq_program_scope_version_active')) {
            return;
        }

        if ($indexNames->contains('uniq_program_scope_version_active')) {
            DB::statement(
                'ALTER TABLE `training_programs` '
                . 'DROP INDEX `uniq_program_scope_version_active`, '
                . 'ADD UNIQUE `uniq_program_scope_version` (`major_id`, `intake_id`, `version`)'
            );
        }
    }
};





