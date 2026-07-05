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
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
// Khóa ngoại liên kết với sinh viên và môn học
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();

            // Lưu thông tin học kỳ (vd: '2023-2024.1' hoặc 'HK1_2023_2024')
            $table->string('academic_semester')->nullable();

            // Các trường điểm
            $table->float('final_score')->nullable();
            $table->float('score_10')->nullable();
            $table->float('score_4')->nullable();
            $table->string('letter_grade', 2)->nullable();

            // Cờ đánh dấu trạng thái môn học
            $table->boolean('is_passed')->default(false); // Đã qua môn
            $table->boolean('is_studying')->default(false); // Đang học chưa có điểm

            $table->timestamps();

            // Index giúp truy vấn nhanh hơn khi check điểm theo sinh viên + môn
            $table->index(['student_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
