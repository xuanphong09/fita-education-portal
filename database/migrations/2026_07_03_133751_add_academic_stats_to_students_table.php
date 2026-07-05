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
        Schema::table('students', function (Blueprint $table) {
            // Thông tin đồng bộ
            $table->string('vnua_password')->nullable(); // Mật khẩu đã mã hóa

            // Thông tin điểm tích lũy
            $table->decimal('gpa_4', 4, 2)->nullable();
            $table->decimal('gpa_10', 4, 2)->nullable();
            $table->integer('total_credits_earned')->default(0);
            $table->timestamp('last_academic_stats_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['vnua_password', 'gpa_4', 'gpa_10', 'total_credits_earned']);
        });
    }
};
