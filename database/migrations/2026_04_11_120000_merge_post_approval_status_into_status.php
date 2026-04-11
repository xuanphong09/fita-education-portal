<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('posts', 'approval_status')) {
            DB::table('posts')
                ->whereNotNull('approval_status')
                ->update([
                    'status' => DB::raw('approval_status'),
                ]);

            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('approval_status');
            });
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE posts MODIFY status ENUM('draft','pending_review','rejected','published','archived') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('posts', 'approval_status')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->string('approval_status', 30)->nullable()->after('status')->index();
            });
        }

        DB::table('posts')
            ->whereIn('status', ['pending_review', 'rejected'])
            ->update([
                'approval_status' => DB::raw('status'),
                'status' => 'draft',
            ]);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE posts MODIFY status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft'");
        }
    }
};

