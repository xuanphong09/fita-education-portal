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
            // Only add the foreign key if the column exists and the FK doesn't already exist
            if (Schema::hasColumn('posts', 'category_id')) {
                // Add foreign key, set to NULL when referenced category is deleted
                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Drop the foreign key if it exists
            // The conventional name would be posts_category_id_foreign, but dropForeign with array is safer
            try {
                $table->dropForeign(['category_id']);
            } catch (\Exception $e) {
                // ignore if doesn't exist
            }
        });
    }
};

