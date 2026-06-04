<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('intakes', function (Blueprint $table) {
            $table->unsignedSmallInteger('year_number')->nullable()->after('name');
        });

        DB::table('intakes')->get()->each(function ($intake) {
            $number = (int) preg_replace('/[^0-9]/', '', $intake->name);

            DB::table('intakes')
                ->where('id', $intake->id)
                ->update([
                    'year_number' => $number,
                ]);
        });

        Schema::table('intakes', function (Blueprint $table) {
            $table->unsignedSmallInteger('year_number')->nullable(false)->change();
            $table->unique('year_number');
        });
    }

    public function down(): void
    {
        Schema::table('intakes', function (Blueprint $table) {
            $table->dropUnique(['year_number']);
            $table->dropColumn('year_number');
        });
    }
};
