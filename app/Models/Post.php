<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasTranslations, SoftDeletes;

    public const APPROVAL_PENDING = 'pending_review';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    protected const RESERVED_POST_ROUTE_SEGMENTS = [
        'admin',
        'gioi-thieu',
        'lien-he',
        'search',
        'dao-tao',
        'giang-vien',
        'login',
        'forgot-password',
        'logout',
        'auth',
        'setup-password',
        'tai-khoan',
        'doi-mat-khau',
        'test-email',
    ];

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
        'show_author',
        'show_published_at',
        'show_views',
        'show_category',
        'show_related_posts',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    public array $translatable = ['title', 'content', 'excerpt', 'seo_title', 'seo_description', 'slug_translations'];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'views' => 'integer',
        'show_author' => 'boolean',
        'show_published_at' => 'boolean',
        'show_views' => 'boolean',
        'show_category' => 'boolean',
        'show_related_posts' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvalHistories(): HasMany
    {
        return $this->hasMany(\App\Models\PostApprovalHistory::class)->latest();
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', self::APPROVAL_PENDING);
    }

    public function getPrimaryCategorySlug(): ?string
    {
        if ($this->relationLoaded('category')) {
            $legacyCategory = $this->category;
            if ($legacyCategory && $legacyCategory->is_active && !empty($legacyCategory->slug)) {
                return $this->normalizeCategorySlug($legacyCategory->slug);
            }
        }

        if ($this->category_id) {
            static $legacyCategorySlugCache = [];

            if (array_key_exists($this->category_id, $legacyCategorySlugCache)) {
                return $this->normalizeCategorySlug($legacyCategorySlugCache[$this->category_id]);
            }

            $legacyCategorySlugCache[$this->category_id] = Category::query()
                ->whereKey($this->category_id)
//                ->where('is_active', true)
                ->value('slug');

            return $this->normalizeCategorySlug($legacyCategorySlugCache[$this->category_id]);
        }

        if ($this->relationLoaded('categories')) {
            $loadedCategory = $this->categories
                ->first(fn (Category $category) => (bool) $category->is_active && !empty($category->slug));

            if ($loadedCategory) {
                return $this->normalizeCategorySlug($loadedCategory->slug);
            }
        }

        $categorySlug = $this->categories()
//            ->where('categories.is_active', true)
            ->value('categories.slug');

        if (!empty($categorySlug)) {
            return $this->normalizeCategorySlug($categorySlug);
        }

        return null;
    }

    protected function normalizeCategorySlug(?string $slug): ?string
    {
        if (empty($slug) || in_array($slug, self::RESERVED_POST_ROUTE_SEGMENTS, true)) {
            return null;
        }

        return $slug;
    }

    public function getClientRouteParameters(): array
    {
        return [
            'categorySlug' => $this->getPrimaryCategorySlug() ?: 'bai-viet',
            'slug' => $this->slug,
        ];
    }

    public function getClientUrlAttribute(): string
    {
        return route('client.posts.show', $this->getClientRouteParameters());
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

        if (!empty(trim((string) $excerpt))) {
            return $excerpt;
        }

        $content = (string) $this->getTranslation('content', $locale, true);

        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = str_replace(['</p>', '<br>', '<br/>', '<br />', '</div>', '</li>', '</h1>', '</h2>', '</h3>'], ' ', $content);
        $plain = strip_tags($content);
        $plain = preg_replace('/\s+/', ' ', $plain);
        return Str::limit(trim($plain), $limit);
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
