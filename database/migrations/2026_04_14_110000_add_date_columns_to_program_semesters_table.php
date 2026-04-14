<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_semesters', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('semester_no');
            $table->date('start_date')->nullable()->after('semester_no');
            $table->index(['training_program_id', 'start_date'], 'program_semesters_start_date_index');
            $table->index(['training_program_id', 'end_date'], 'program_semesters_end_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('program_semesters', function (Blueprint $table) {
            $table->dropIndex('program_semesters_start_date_index');
            $table->dropIndex('program_semesters_end_date_index');
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};

