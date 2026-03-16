# Hệ thống tracking lượt xem bài viết

## 📊 Tổng quan

Hệ thống đã được **tối ưu hóa** để:
- ✅ **Chống spam**: Cookie-based tracking (1 view/user/24h)
- ✅ **Chống bot**: Rate limiting (60 requests/phút)
- ✅ **Hiệu năng cao**: Database index tối ưu
- ✅ **Thread-safe**: Atomic increment

---

## 🔒 Cơ chế chống spam (3 lớp bảo vệ)

### 1. Cookie-based tracking (Lớp 1)
```php
// Lưu cookie trong 24h
cookie()->queue($cookieName, true, 1440);
```

**Chống:**
- ✅ User bình thường refresh liên tục
- ✅ User đóng/mở tab nhiều lần
- ❌ User dùng chế độ ẩn danh ❌ (cookie bị xóa mỗi lần)

---

### 2. IP + User-Agent Fingerprint (Lớp 2) ⭐
```php
// Tạo fingerprint unique từ IP + User-Agent
$fingerprint = md5(
    $this->id . 
    request()->ip() . 
    request()->userAgent()
);

// Lưu vào cache 24h
cache()->put("post_view_{$fingerprint}", true, now()->addMinutes(1440));
```

**Chống:**
- ✅ User dùng chế độ ẩn danh (Incognito/Private)
- ✅ User xóa cookie thủ công
- ✅ Bot spam từ cùng IP/device
- ❌ Chỉ fail nếu đổi IP + đổi browser

**Cách hoạt động:**
1. User A xem bài từ IP `192.168.1.100` + Chrome
2. Tạo fingerprint: `md5("1" + "192.168.1.100" + "Chrome/121...")`
3. Lưu fingerprint vào cache 24h
4. User A mở ẩn danh lại → **Cùng IP + User-Agent** → Không tăng view ✅
5. User B từ IP khác → fingerprint khác → Tăng view bình thường

---

### 3. Session-based (Lớp 3 - Fallback)
```php
// Lưu vào session
session()->put("viewed_post_{$this->id}", true);
```

**Chống:**
- ✅ User tắt cookie hoàn toàn
- ✅ Backup khi cookie/cache fail

---

### ✅ Logic kiểm tra đa lớp
```php
// Chỉ tăng view nếu CHƯA viewed qua BẤT KỲ lớp nào
if (!$hasViewedByCookie && !$hasViewedByCache && !$hasViewedBySession) {
    $this->increment('views');
    
    // Lưu vào CẢ 3 lớp
    cookie()->queue($cookieName, true, 1440);
    cache()->put($cacheKey, true, now()->addMinutes(1440));
    session()->put("viewed_post_{$this->id}", true);
}
```

**Kết quả:**
- User refresh 1000 lần → **1 view** ✅
- User mở 10 tab ẩn danh → **1 view** ✅ (cùng IP)
- User đổi IP mới + ẩn danh → **1 view mới** (hợp lệ)

---
```php
// File: routes/web.php

Route::livewire('/bai-viet/{slug}', 'pages::client.posts.show')
    ->middleware('throttle:60,1') // 60 requests/phút
    ->name('client.posts.show');
```

**Chống bot spam:**
- Giới hạn **60 requests/phút** từ cùng 1 IP
- Vượt quá → HTTP 429 Too Many Requests

---

## 🚀 Performance

### Database Index
```php
// Migration: 2026_03_10_093955_add_views_index_optimization_to_posts_table.php

// Composite index cho query "bài viết phổ biến"
$table->index(['status', 'views', 'published_at'], 'posts_popular_index');
```

**Lợi ích:**
- Query `WHERE status='published' ORDER BY views DESC` → **nhanh hơn 10-100x**
- Tránh full table scan khi có hàng triệu bài viết

---

## 📖 Cách sử dụng

### Lấy bài viết phổ biến nhất
```php
// Lấy 10 bài phổ biến nhất (tất cả thời gian)
$popularPosts = Post::popular(10)->get();

// Ví dụ: Sidebar "Bài viết được xem nhiều"
$sidebar = Post::popular(5)->get();
```

### Lấy bài viết trending (xem nhiều gần đây)
```php
// Lấy 10 bài trending trong 7 ngày qua
$trendingPosts = Post::trending(7, 10)->get();

// Lấy trending tuần này
$thisWeek = Post::trending(7, 5)->get();

// Lấy trending tháng này
$thisMonth = Post::trending(30, 10)->get();
```

### Tăng lượt xem thủ công (nếu cần)
```php
// Trong controller/component
$post = Post::find($id);
$post->incrementView(); // Tự động check cookie
```

---

## 🛡️ Best Practices

### ✅ Nên làm
1. **Luôn dùng scope `popular()` hoặc `trending()`** thay vì query thủ công
2. **Cache kết quả** nếu hiển thị trên nhiều trang:
   ```php
   $popular = Cache::remember('posts.popular', 3600, function () {
       return Post::popular(10)->get();
   });
   ```
3. **Giới hạn số lượng** khi query (dùng `limit()`)

### ❌ Không nên
1. Tăng view thủ công không qua `incrementView()`
2. Query `SELECT * FROM posts ORDER BY views` (thiếu index)
3. Xóa cookie tracking để test (dùng Incognito thay vào đó)

---

## 🧪 Testing

### Test cơ chế cookie
```bash
# Lần 1: Mở bài viết → tăng view
curl http://localhost:8000/bai-viet/test-post

# Lần 2: Refresh ngay → KHÔNG tăng (có cookie)
curl http://localhost:8000/bai-viet/test-post

# Xóa cookie → Xem lại → tăng view
# (Hoặc đợi 24h)
```

### Test rate limiting
```bash
# Spam 100 requests liên tục
for i in {1..100}; do
  curl http://localhost:8000/bai-viet/test-post
done

# Kết quả: Sau request thứ 60, nhận HTTP 429
```

---

## 📊 Monitoring

### Query phân tích
```sql
-- Top 10 bài viết được xem nhiều nhất
SELECT id, JSON_EXTRACT(title, '$.vi') as title, views
FROM posts
WHERE status = 'published'
ORDER BY views DESC
LIMIT 10;

-- Bài viết trending tuần này
SELECT id, JSON_EXTRACT(title, '$.vi') as title, views, published_at
FROM posts
WHERE status = 'published'
  AND published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY views DESC
LIMIT 10;

-- Tổng views tất cả bài viết
SELECT SUM(views) as total_views FROM posts WHERE status = 'published';
```

---

## 🔧 Cấu hình nâng cao

### Thay đổi thời gian cookie (mặc định 24h)
```php
// File: app/Models/Post.php

cookie()->queue($cookieName, true, 720); // 12 giờ (720 phút)
cookie()->queue($cookieName, true, 10080); // 7 ngày
```

### Thay đổi rate limit (mặc định 60/phút)
```php
// File: routes/web.php

->middleware('throttle:120,1') // 120 requests/phút
->middleware('throttle:1000,1') // 1000 requests/phút (production)
```

### Thêm IP-based tracking (nâng cao)
```php
// Kết hợp cookie + IP + User-Agent
$fingerprint = md5(
    $this->id . 
    request()->ip() . 
    request()->userAgent()
);

if (!Cache::has("post_view_{$fingerprint}")) {
    $this->increment('views');
    Cache::put("post_view_{$fingerprint}", true, 1440); // 24h
}
```

---

## 🆘 Troubleshooting

### View không tăng?
1. ✅ Check cookie có tồn tại: DevTools → Application → Cookies
2. ✅ Kiểm tra rate limit: HTTP status code 429?
3. ✅ Verify middleware được apply: `php artisan route:list`

### View tăng quá nhanh?
1. 🔍 Bot crawl? Check access logs
2. 🔍 Cookie bị clear liên tục? 
3. 🔍 Nhiều user thật? Xem Google Analytics

### Query chậm?
1. ✅ Đã chạy migration index chưa? `php artisan migrate:status`
2. ✅ Kiểm tra EXPLAIN query:
   ```sql
   EXPLAIN SELECT * FROM posts 
   WHERE status='published' 
   ORDER BY views DESC LIMIT 10;
   ```
3. ✅ Cache kết quả popular posts

---

## 📚 Tài liệu liên quan

- [Laravel Rate Limiting](https://laravel.com/docs/11.x/routing#rate-limiting)
- [Database Indexing Best Practices](https://use-the-index-luke.com/)
- [Cookie Security](https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies)

