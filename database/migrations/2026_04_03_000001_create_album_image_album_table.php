<?php

use App\Models\AlbumImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('album_image_album')) {
            Schema::create('album_image_album', function (Blueprint $table) {
                $table->id();
                $table->foreignId('album_id')->constrained('albums')->cascadeOnDelete();
                $table->foreignId('album_image_id')->constrained('album_images')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['album_id', 'album_image_id']);
                $table->index(['album_image_id', 'album_id']);
            });
        }

        if (Schema::hasColumn('album_images', 'album_id')) {
            $rows = AlbumImage::query()
                ->whereNotNull('album_id')
                ->get(['id', 'album_id']);

            foreach ($rows as $row) {
                DB::table('album_image_album')->updateOrInsert(
                    [
                        'album_id' => $row->album_id,
                        'album_image_id' => $row->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('album_image_album');
    }
};

