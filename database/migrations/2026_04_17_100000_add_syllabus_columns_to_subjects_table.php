<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('syllabus_path')->nullable()->after('is_active');
            $table->string('syllabus_original_name')->nullable()->after('syllabus_path');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['syllabus_path', 'syllabus_original_name']);
        });
    }
};

