<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            // Ngành (field) - cấp cao hơn chuyên ngành
            $table->foreignId('program_major_id')->nullable()->after('major_id')->constrained('program_majors')->nullOnDelete();
        });

        // Cập nhật unique constraint để bao gồm program_major_id
        // (Phiên bản phải duy nhất trong scope: program_major + major + intake)
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_major_id');
        });
    }
};

