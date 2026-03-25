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
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('staff_code', 20)->unique(); // Mã cán bộ
            $table->string('slug')->unique();
            $table->string('gender')->nullable(); // Giới tính
            $table->foreignId('department_id')->nullable()->constrained('departments'); // Bộ môn (VD: KTPM, MMT)
            $table->string('degree')->nullable(); // Học vị (ThS, TS...)
            $table->string('academic_title')->nullable(); // Học hàm (PGS, GS...)
            $table->string('phone', 20)->nullable();
            $table->json('positions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturers');
    }
};
