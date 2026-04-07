<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->decimal('recaptcha_score', 3, 2)->nullable()->after('locale');
            $table->string('recaptcha_action', 50)->nullable()->after('recaptcha_score');
            $table->softDeletes();

            $table->index('deleted_at');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['sent_at']);
            $table->dropSoftDeletes();
            $table->dropColumn(['recaptcha_score', 'recaptcha_action']);
        });
    }
};

