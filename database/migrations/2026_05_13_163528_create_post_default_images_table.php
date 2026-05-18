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
        Schema::create('post_default_images', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Tên template ảnh (vd: "Blue Sky", "Green Forest")');
            $table->string('image_path')->comment('Đường dẫn file ảnh trong storage');
            $table->string('text_color')->default('#ffffff')->comment('Màu text của tiêu đề (hex color)');
            $table->integer('text_size')->default(48)->comment('Kích thước font tiêu đề (px)');
            $table->string('text_alignment')->default('center')->comment('Canh giữa tiêu đề (left, center, right)');
            $table->integer('text_y_offset')->default(0)->comment('Offset Y tiêu đề từ giữa (px)');
            $table->boolean('is_active')->default(true)->comment('Template có sẵn để sử dụng');
            $table->integer('order')->default(0)->comment('Thứ tự hiển thị');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_default_images');
    }
};
