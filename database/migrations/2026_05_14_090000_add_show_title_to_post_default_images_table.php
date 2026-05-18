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
        Schema::table('post_default_images', function (Blueprint $table) {
            $table->boolean('show_title')
                ->default(true)
                ->after('image_path')
                ->comment('Co hien thi tieu de len anh hay khong');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_default_images', function (Blueprint $table) {
            $table->dropColumn('show_title');
        });
    }
};

