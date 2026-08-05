<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('program_semester_subjects', function (Blueprint $table) {
            $table->foreignId('substitute_for_id')
                ->nullable()
                ->after('type')
                ->comment('Lưu ID môn học gốc nếu đây là môn thay thế')
                ->constrained('subjects')
                ->nullOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_semester_subjects', function (Blueprint $table) {
            $table->dropColumn('substitute_for_id');
        });
    }
};
