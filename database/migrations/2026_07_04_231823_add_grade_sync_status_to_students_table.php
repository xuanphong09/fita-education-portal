<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('grade_sync_status')->default('idle')->after('vnua_password');
            $table->text('grade_sync_message')->nullable()->after('grade_sync_status');
            $table->unsignedInteger('grade_sync_failed_count')->default(0)->after('grade_sync_message');

            $table->timestamp('grade_sync_started_at')->nullable()->after('grade_sync_failed_count');
            $table->timestamp('grade_sync_success_at')->nullable()->after('grade_sync_started_at');
            $table->timestamp('grade_sync_failed_at')->nullable()->after('grade_sync_success_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'grade_sync_status',
                'grade_sync_message',
                'grade_sync_failed_count',
                'grade_sync_started_at',
                'grade_sync_success_at',
                'grade_sync_failed_at',
            ]);
        });
    }
};
