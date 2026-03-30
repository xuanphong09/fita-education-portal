<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_equivalents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('equivalent_subject_id')->constrained('subjects')->restrictOnDelete();
            $table->timestamps();

            // Do not allow duplicate mapping in the same program.
            $table->unique(
                ['training_program_id', 'subject_id', 'equivalent_subject_id'],
                'uniq_subject_equivalent'
            );

            // Optimize filtering by program + source subject.
            $table->index(['training_program_id', 'subject_id'], 'idx_sub_eq_program_subject');
            // Optimize reverse lookup: which subjects are equivalent to this one.
            $table->index(['training_program_id', 'equivalent_subject_id'], 'idx_sub_eq_program_equivalent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_equivalents');
    }
};


