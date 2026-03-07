<?php
//
//use Illuminate\Database\Migrations\Migration;
//use Illuminate\Database\Schema\Blueprint;
//use Illuminate\Support\Facades\Schema;
//
//return new class extends Migration
//{
//    /**
//     * Run the migrations.
//     */
//    public function up(): void
//    {
//        Schema::create('posts', function (Blueprint $table) {
//            $table->id();
//
//            $table->json('title');
//            $table->json('content');
//            $table->json('excerpt')->nullable();
//
//            // Slug (unique across posts)
//            $table->json('slug')->unique();
//
//            // Optional thumbnail image path
//            $table->string('thumbnail')->nullable();
//
//            // SEO / meta (translatable)
//            $table->json('seo_title')->nullable();
//            $table->json('seo_description')->nullable();
//
//            // Author (optional) -> users table
//            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
//
//            // Post status and publication timestamp
//            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
//            $table->timestamp('published_at')->nullable();
//
//            // Soft deletes and view counter
//            $table->unsignedBigInteger('views')->default(0);
//            $table->softDeletes();
//
//            $table->timestamps();
//        });
//    }
//
//    /**
//     * Reverse the migrations.
//     */
//    public function down(): void
//    {
//        Schema::dropIfExists('posts');
//    }
//};
