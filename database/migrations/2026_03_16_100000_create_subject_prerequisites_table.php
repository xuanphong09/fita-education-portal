<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_prerequisites', function (Blueprint $table) {
            // ID quan he tien quyet
            $table->id();
            // Hoc ky
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();

            // Mon hoc hien tai (mon A)
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();

            // Mon hoc tien quyet (mon B phai hoc truoc)
            $table->foreignId('prerequisite_subject_id')->constrained('subjects')->restrictOnDelete();
            $table->timestamps();

            // Khong lap lai cung 1 cap A -> B
            $table->unique(['training_program_id','subject_id', 'prerequisite_subject_id'], 'uniq_subject_prerequisite');

            // Toi uu khi truy van danh sach mon tien quyet
            $table->index(['training_program_id','subject_id']);

            // Toi uu khi truy van mon nao dang la tien quyet cua mon khac
            $table->index(['prerequisite_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_prerequisites');
    }
};

