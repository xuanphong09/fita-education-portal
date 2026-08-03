<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            if (!Schema::hasColumn('training_programs', 'elective_credits')) {
                $table->unsignedSmallInteger('elective_credits')->default(0)->after('total_credits');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            if (Schema::hasColumn('training_programs', 'elective_credits')) {
                $table->dropColumn('elective_credits');
            }
        });
    }
};

