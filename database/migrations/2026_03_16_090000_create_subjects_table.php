<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            // ID mon hoc
            $table->id();

            // Ma mon hoc duy nhat (vd: IT202)
            $table->string('code')->unique();

            // Ten mon hoc da ngon ngu: {"vi":"...","en":"..."}
            $table->json('name');
            $table->unsignedSmallInteger('credits');//tong so tin chi
            $table->unsignedSmallInteger('credits_theory'); //tong so tin chi ly thuyet
            $table->unsignedSmallInteger('credits_practice'); //tong so tin chi thuc hanh

            //mon hoc thuoc nhom nao
            $table->foreignId('group_subject_id')->nullable()->constrained()->nullOnDelete();

            // Trang thai kich hoat/de kich hoat mon hoc
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Toi uu loc danh sach mon hoc dang kich hoat
            $table->index(['is_active', 'deleted_at']);

            // Toi uu loc mon hoc theo nhom mon hoc
            $table->index('group_subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};


