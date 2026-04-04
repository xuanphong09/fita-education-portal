<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->boolean('is_featured_home')->default(false)->after('order');
            $table->index('is_featured_home');
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex(['is_featured_home']);
            $table->dropColumn('is_featured_home');
        });
    }
};

