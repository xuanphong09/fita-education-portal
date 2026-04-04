<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            if (Schema::hasColumn('albums', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('album_images', function (Blueprint $table) {
            if (Schema::hasColumn('album_images', 'album_id')) {
                $table->dropForeign(['album_id']);
                $table->foreignId('album_id')->nullable()->change();
                $table->foreign('album_id')->references('id')->on('albums')->nullOnDelete();
            }

            if (Schema::hasColumn('album_images', 'alt_text')) {
                $table->dropColumn('alt_text');
            }

            if (Schema::hasColumn('album_images', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('album_images', 'order')) {
                $table->dropColumn('order');
            }

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            if (!Schema::hasColumn('albums', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('order');
            }
        });

        Schema::table('album_images', function (Blueprint $table) {
            if (Schema::hasColumn('album_images', 'album_id')) {
                $table->dropForeign(['album_id']);
                $table->foreignId('album_id')->nullable(false)->change();
                $table->foreign('album_id')->references('id')->on('albums')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('album_images', 'alt_text')) {
                $table->string('alt_text')->nullable()->after('image_path');
            }

            if (!Schema::hasColumn('album_images', 'order')) {
                $table->integer('order')->default(0)->after('caption');
            }

            if (!Schema::hasColumn('album_images', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('order');
            }

            $table->dropIndex(['created_at']);
        });
    }
};

