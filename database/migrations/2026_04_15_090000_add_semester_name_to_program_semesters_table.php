<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_semesters', function (Blueprint $table) {
            $table->string('semester_name', 50)->nullable()->after('semester_no');
            $table->index(['training_program_id', 'semester_name'], 'program_semesters_semester_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('program_semesters', function (Blueprint $table) {
            $table->dropIndex('program_semesters_semester_name_index');
            $table->dropColumn('semester_name');
        });
    }
};

