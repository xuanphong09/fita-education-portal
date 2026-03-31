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
        Schema::table('majors', function (Blueprint $table) {
            if (!Schema::hasColumn('majors', 'order')) {
                $table->integer('order')->default(0)->after('slug')->index();
            }
            if (!Schema::hasColumn('majors', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('order')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('majors', function (Blueprint $table) {
            if (Schema::hasColumn('majors', 'order')) {
                $table->dropColumn('order');
            }
            if (Schema::hasColumn('majors', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};

