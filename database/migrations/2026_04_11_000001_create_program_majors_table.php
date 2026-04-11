<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_majors', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('code')->nullable();
            $table->string('slug')->unique();
            $table->integer('order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('slug');
        });

        Schema::table('majors', function (Blueprint $table) {
            if (!Schema::hasColumn('majors', 'program_major_id')) {
                $table->foreignId('program_major_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('program_majors')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('majors', function (Blueprint $table) {
            if (Schema::hasColumn('majors', 'program_major_id')) {
                $table->dropConstrainedForeignId('program_major_id');
            }
        });

        Schema::dropIfExists('program_majors');
    }
};

