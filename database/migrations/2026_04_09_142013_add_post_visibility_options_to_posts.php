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
            $table->boolean('show_author')->default(true)->after('views')->comment('Hiển thị người viết');
            $table->boolean('show_published_at')->default(true)->after('show_author')->comment('Hiển thị ngày đăng');
            $table->boolean('show_views')->default(true)->after('show_published_at')->comment('Hiển thị lượt xem');
            $table->boolean('show_category')->default(true)->after('show_views')->comment('Hiển thị danh mục');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['show_author', 'show_published_at', 'show_views', 'show_category']);
        });
    }
};
