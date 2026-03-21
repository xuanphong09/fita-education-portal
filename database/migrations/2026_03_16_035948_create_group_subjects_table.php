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
        Schema::create('group_subjects', function (Blueprint $table) {
            $table->id();

            // Ten nhom mon hoc da ngon ngu: {"vi":"...","en":"..."}
            $table->json('name');

            // Mo ta nhom mon hoc da ngon ngu neu can
            $table->json('description')->nullable();

            // Thu tu hien thi nhom mon hoc trong admin/client
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Trang thai kich hoat/de kich hoat nhom mon hoc
            $table->boolean('is_active')->default(true);

            $table->softDeletes();
            $table->timestamps();

            // Toi uu loc danh sach nhom mon hoc dang kich hoat chua xoa mem
            $table->index(['is_active', 'deleted_at']);

            // Toi uu sap xep danh sach nhom mon hoc theo thu tu
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_subjects');
    }
};
