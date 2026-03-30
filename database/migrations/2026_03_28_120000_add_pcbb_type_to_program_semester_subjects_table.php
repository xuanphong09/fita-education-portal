<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE program_semester_subjects MODIFY COLUMN type ENUM('required', 'elective', 'pcbb') NOT NULL DEFAULT 'required'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE program_semester_subjects MODIFY COLUMN type ENUM('required', 'elective') NOT NULL DEFAULT 'required'");
    }
};

