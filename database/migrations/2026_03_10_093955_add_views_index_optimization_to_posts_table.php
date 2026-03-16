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
            // Composite index cho query "bài viết phổ biến nhất"
            // Giúp tối ưu: WHERE status='published' ORDER BY views DESC
            $table->index(['status', 'views', 'published_at'], 'posts_popular_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_popular_index');
        });
    }
};
