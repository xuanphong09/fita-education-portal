<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_semesters', function (Blueprint $table) {
            // ID hoc ky trong CTDT
            $table->id();

            // Lien ket den chuong trinh dao tao
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();

            // So hoc ky (vd: 1..8)
            $table->unsignedTinyInteger('semester_no');

            // Tong tin chi du kien cua hoc ky
            $table->unsignedSmallInteger('total_credits')->default(0);

            $table->timestamps();

            // Moi CTDT chi co 1 ban ghi cho moi so hoc ky
            $table->unique(['training_program_id', 'semester_no']);

            // Toi uu truy van danh sach hoc ky theo thu tu
            $table->index(['training_program_id', 'semester_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_semesters');
    }
};

