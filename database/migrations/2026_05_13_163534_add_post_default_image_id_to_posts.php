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
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('post_default_image_id')
                ->nullable()
                ->constrained('post_default_images')
                ->onDelete('set null')
                ->after('thumbnail')
                ->comment('Template ảnh mặc định được chọn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Drop the column - foreign key constraint will be automatically dropped
            $table->dropConstrainedForeignId('post_default_image_id');
        });
    }
};
