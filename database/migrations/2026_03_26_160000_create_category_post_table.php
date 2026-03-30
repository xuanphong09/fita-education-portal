<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_post', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['category_id', 'post_id']);
            $table->index('post_id');
        });

        // Backfill from legacy posts.category_id so old data remains usable.
        DB::statement(
            "INSERT INTO category_post (category_id, post_id, created_at, updated_at)
             SELECT category_id, id, NOW(), NOW()
             FROM posts
             WHERE category_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('category_post');
    }
};

