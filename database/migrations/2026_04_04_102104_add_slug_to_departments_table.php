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
        Schema::table('departments', function (Blueprint $table) {
            $table->string('slug')->after('name');

            if (!Schema::hasColumn('departments', 'order')) {
                $table->integer('order')->default(0)->after('slug')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');

            if (Schema::hasColumn('departments', 'order')) {
                $table->dropColumn('order');
            }
        });
    }
};
