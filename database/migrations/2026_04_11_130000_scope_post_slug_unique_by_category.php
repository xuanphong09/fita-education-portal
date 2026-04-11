<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Drop global unique(slug) and replace with category-scoped unique.
            try {
                $table->dropUnique('posts_slug_unique');
            } catch (\Throwable $e) {
                // Ignore when index does not exist.
            }

            $table->unique(['category_id', 'slug'], 'posts_category_slug_unique');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            try {
                $table->dropUnique('posts_category_slug_unique');
            } catch (\Throwable $e) {
                // Ignore when index does not exist.
            }

            try {
                $table->dropIndex(['slug']);
            } catch (\Throwable $e) {
                // Ignore when index does not exist.
            }

            $table->unique('slug');
        });
    }
};

