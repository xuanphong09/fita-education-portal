<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::table('posts', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                // Generated STORED columns make JSON search cheaper and easier to optimize.
                $table->string('title_vi_search')->nullable()->storedAs("NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`title`, '$.vi'))), '')");
                $table->string('title_en_search')->nullable()->storedAs("NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`title`, '$.en'))), '')");
                $table->text('excerpt_vi_search')->nullable()->storedAs("NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`excerpt`, '$.vi'))), '')");
                $table->text('excerpt_en_search')->nullable()->storedAs("NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`excerpt`, '$.en'))), '')");
                $table->longText('content_vi_search')->nullable()->storedAs("NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`content`, '$.vi'))), '')");
                $table->longText('content_en_search')->nullable()->storedAs("NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`content`, '$.en'))), '')");
                $table->string('slug_vi_search')->nullable()->storedAs("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`slug_translations`, '$.vi'))), ''), NULLIF(TRIM(`slug`), ''))");
                $table->string('slug_en_search')->nullable()->storedAs("NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(`slug_translations`, '$.en'))), '')");
            } else {
                // Fallback columns for non-MySQL environments (e.g., sqlite tests).
                $table->string('title_vi_search')->nullable();
                $table->string('title_en_search')->nullable();
                $table->text('excerpt_vi_search')->nullable();
                $table->text('excerpt_en_search')->nullable();
                $table->longText('content_vi_search')->nullable();
                $table->longText('content_en_search')->nullable();
                $table->string('slug_vi_search')->nullable();
                $table->string('slug_en_search')->nullable();
            }

            $table->index('title_vi_search', 'posts_title_vi_search_idx');
            $table->index('title_en_search', 'posts_title_en_search_idx');
            $table->index('slug_vi_search', 'posts_slug_vi_search_idx');
            $table->index('slug_en_search', 'posts_slug_en_search_idx');
            $table->index(['status', 'is_featured', 'published_at', 'category_id'], 'posts_listing_filter_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_title_vi_search_idx');
            $table->dropIndex('posts_title_en_search_idx');
            $table->dropIndex('posts_slug_vi_search_idx');
            $table->dropIndex('posts_slug_en_search_idx');
            $table->dropIndex('posts_listing_filter_idx');

            $table->dropColumn([
                'title_vi_search',
                'title_en_search',
                'excerpt_vi_search',
                'excerpt_en_search',
                'content_vi_search',
                'content_en_search',
                'slug_vi_search',
                'slug_en_search',
            ]);
        });
    }
};

