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
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên đối tác
            $table->string('logo')->nullable(); // Đường dẫn ảnh logo
            $table->string('url')->nullable(); // Đường dẫn website
            $table->integer('order')->default(0); // Thứ tự hiển thị
            $table->boolean('is_active')->default(true); // Trạng thái kích hoạt
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
