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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('student_code', 10)->unique();
//            $table->string('full_name');
            $table->string('class_name', 50)->nullable();
            $table->string('gender')->nullable(); // Giới tính
            $table->foreignId('intake_id')->nullable()->constrained('intakes'); // Khóa
            $table->foreignId('major_id')->nullable()->constrained('majors'); // Chuyên ngành
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
