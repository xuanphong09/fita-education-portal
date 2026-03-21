<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            // ID chuong trinh dao tao
            $table->id();

            // Ten CTDT da ngon ngu: {"vi":"...","en":"..."}
            $table->json('name');

            //hinh thuc dao tao: {"vi":"...","en":"..."}
            $table->json('type');

            // trinh do dao tao
            $table->json('level');

            // ngon ngu dao tao
            $table->json('language');

            //thoi gian dao tao
            $table->integer('duration_time');

            // Bo mon quan ly chuong trinh
//            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            // Chuyen nganh ap dung (co the null neu CTDT dung chung)
            $table->foreignId('major_id')->nullable()->constrained()->nullOnDelete();

            // Khoa tuyen sinh ap dung (vd: K68)
            $table->foreignId('intake_id')->constrained()->cascadeOnDelete();

            // Nien khoa bat dau / ket thuc (vd: 2026 - 2030)
            $table->unsignedSmallInteger('school_year_start')->nullable();
            $table->unsignedSmallInteger('school_year_end')->nullable();

            // Phien ban CTDT trong cung bo mon + nganh + khoa
            $table->string('version');

            // Tong tin chi toan chuong trinh
            $table->unsignedSmallInteger('total_credits')->default(0);

            // Trang thai CTDT: draft/published/archived
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            // Thoi diem cong bo CTDT
            $table->timestamp('published_at')->nullable();

            // Ghi chu noi bo
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Khong cho trung version trong cung pham vi nganh + khoa
            $table->unique(['major_id', 'intake_id', 'version'], 'uniq_program_scope_version');

            // Toi uu truy van theo trang thai va ngay cong bo
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};


