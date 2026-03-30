<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'excerpt',
        'slug',
        'slug_translations',
        'thumbnail',
        'seo_title',
        'seo_description',
        'user_id',
        'category_id',
        'status',
        'is_featured',
        'published_at',
        'views',
    ];

    public array $translatable = ['title', 'content', 'excerpt', 'seo_title', 'seo_description', 'slug_translations'];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'views' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post')
            ->withTimestamps()
            ->whereNull('categories.deleted_at')
            ->orderBy('categories.order');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Bài viết phổ biến nhất (dùng index tối ưu)
     */
    public function scopePopular($query, int $limit = 10)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('views', 'desc')
            ->limit($limit);
    }

    /**
     * Scope: Bài viết được xem nhiều trong khoảng thời gian
     */
    public function scopeTrending($query, int $days = 7, int $limit = 10)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subDays($days))
            ->where('published_at', '<=', now())
            ->orderBy('views', 'desc')
            ->limit($limit);
    }

    /**
     * Tăng lượt xem bài viết (chống spam đa lớp)
     *
     * Lớp 1: Cookie (user bình thường)
     * Lớp 2: Cache fingerprint IP + User-Agent (chống ẩn danh)
     * Lớp 3: Session (fallback cho user tắt cookie)
     *
     * Chỉ tính 1 view/user/post trong 24h
     */
    public function incrementView(): void
    {
        $cookieName = 'post_viewed_' . $this->id;

        // Tạo fingerprint từ IP + User-Agent + Post ID
        // Chống spam ngay cả khi dùng ẩn danh/xóa cookie
        $fingerprint = md5(
            $this->id .
            request()->ip() .
            request()->userAgent()
        );
        $cacheKey = "post_view_{$fingerprint}";

        // Kiểm tra đa lớp
        $hasViewedByCookie = request()->cookie($cookieName);
        $hasViewedByCache = cache()->has($cacheKey);
        $hasViewedBySession = session()->has("viewed_post_{$this->id}");

        // Nếu CHƯA viewed qua bất kỳ phương thức nào
        if (!$hasViewedByCookie && !$hasViewedByCache && !$hasViewedBySession) {
            // Increment atomic để tránh race condition
            $this->increment('views');

            // Lưu tracking vào cả 3 lớp (24h = 1440 phút)
            cookie()->queue($cookieName, true, 1440);                    // Cookie
            cache()->put($cacheKey, true, now()->addMinutes(1440));     // Cache (IP-based)
            session()->put("viewed_post_{$this->id}", true);            // Session
        }
    }

    // Return excerpt or auto-generated snippet from content
    public function getExcerptOrAuto(string $locale = null, int $limit = 200): string
    {
        $locale = $locale ?? app()->getLocale();
        $excerpt = $this->getTranslation('excerpt', $locale, true);
        if (!empty($excerpt)) {
            return $excerpt;
        }

        $content = $this->getTranslation('content', $locale, true);
        $plain = trim(strip_tags($content));
        return Str::limit($plain, $limit);
    }

    // Boot logic: auto-generate slug from title for canonical slug if missing
    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            // Tự sinh slug canonical nếu trống
            if (empty($post->slug)) {
                $title = $post->getTranslation('title', 'vi', false)
                      ?: $post->getTranslation('title', 'en', false);
                if ($title) {
                    $base = Str::slug($title);
                    $slug = $base;
                    $i = 1;
                    while (self::where('slug', $slug)->where('id', '<>', $post->id ?? 0)->exists()) {
                        $slug = $base . '-' . $i++;
                    }
                    $post->slug = $slug;
                }
            }
        });
    }
}
