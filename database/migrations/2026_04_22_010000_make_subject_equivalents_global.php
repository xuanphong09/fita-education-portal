<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep one row per subject-equivalent pair before dropping program scope.
        DB::statement("\n            DELETE se1
            FROM subject_equivalents se1
            INNER JOIN subject_equivalents se2
                ON se1.subject_id = se2.subject_id
                AND se1.equivalent_subject_id = se2.equivalent_subject_id
                AND se1.id > se2.id
        ");

        Schema::table('subject_equivalents', function (Blueprint $table): void {
            $table->dropForeign(['training_program_id']);
            $table->dropUnique('uniq_subject_equivalent');
            $table->dropIndex('idx_sub_eq_program_subject');
            $table->dropIndex('idx_sub_eq_program_equivalent');
            $table->dropColumn('training_program_id');

            $table->unique(['subject_id', 'equivalent_subject_id'], 'uniq_subject_equivalent_global');
            $table->index(['subject_id'], 'idx_sub_eq_subject');
            $table->index(['equivalent_subject_id'], 'idx_sub_eq_equivalent');
        });
    }

    public function down(): void
    {
        Schema::table('subject_equivalents', function (Blueprint $table): void {
            $table->dropUnique('uniq_subject_equivalent_global');
            $table->dropIndex('idx_sub_eq_subject');
            $table->dropIndex('idx_sub_eq_equivalent');

            // Best-effort rollback: training_program_id cannot be reconstructed deterministically.
            $table->unsignedBigInteger('training_program_id')->nullable()->after('id');
            $table->index(['training_program_id', 'subject_id'], 'idx_sub_eq_program_subject');
            $table->index(['training_program_id', 'equivalent_subject_id'], 'idx_sub_eq_program_equivalent');
            $table->unique(['training_program_id', 'subject_id', 'equivalent_subject_id'], 'uniq_subject_equivalent');
        });
    }
};

