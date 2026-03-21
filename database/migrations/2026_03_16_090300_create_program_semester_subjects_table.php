<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_semester_subjects', function (Blueprint $table) {
            // ID mapping mon hoc trong hoc ky
            $table->id();

            // Hoc ky thuoc CTDT
            $table->foreignId('program_semester_id')->constrained()->cascadeOnDelete();

            // Mon hoc duoc gan vao hoc ky
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();

            // Loai mon: bat buoc hoac tu chon
            $table->enum('type', ['required', 'elective'])->default('required');

            //ghi chu neu co
            $table->text('notes')->nullable();

            // Thu tu hien thi mon trong hoc ky
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            // 1 mon khong duoc lap lai trong cung 1 hoc ky
            $table->unique(['program_semester_id', 'subject_id'], 'uniq_semester_subject');

            // Toi uu truy van danh sach mon theo thu tu
            $table->index(['program_semester_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_semester_subjects');
    }
};


