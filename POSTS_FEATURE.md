# Trang Bài Viết (Client) - Tài Liệu Cập Nhật

Tài liệu này mô tả trạng thái hiện tại của phần bài viết phía client, bao gồm danh sách, chi tiết, tìm kiếm tối ưu và dữ liệu seed.

## 1) Tính năng hiện có

### 1.1 Trang danh sách bài viết (`/bai-viet`)

- Hiển thị danh sách bài viết published, có `published_at <= now()`.
- Phân trang `12` bài/trang cho phần danh sách thường.
- Có block lớn cho **bài viết nổi bật** (featured) ở phía trên.
- Block nổi bật:
  - Hiển thị các bài featured dạng slider.
  - Chỉ hiện nút điều hướng khi hover.
  - Không loop vô hạn.
  - Ẩn mũi tên ở phía đã đạt biên (đầu/cuối).
- Danh sách dưới block nổi bật sắp xếp theo `published_at desc`.
- Sidebar có:
  - Tìm kiếm.
  - Lọc danh mục cha/con.
  - Hiển thị số lượng bài theo danh mục.
- Có hỗ trợ `wire:navigate`.

### 1.2 Trang chi tiết bài viết (`/bai-viet/{slug}`)

- Hiển thị nội dung bài viết theo ngôn ngữ hiện tại.
- Meta cơ bản: tác giả, ngày xuất bản, lượt xem, danh mục.
- Tự tăng lượt xem với cơ chế chống spam đa lớp (cookie + cache fingerprint + session).
- Có bài liên quan / bài mới (theo logic hiện tại trong trang chi tiết).

### 1.3 Đa ngôn ngữ (vi/en)

- Dữ liệu bài viết dùng JSON (`Spatie Translatable`).
- Ở locale `en`, bài nào không có `content.en` sẽ bị ẩn khỏi danh sách.
- Danh mục không có tên `en` (khi ở locale `en`) cũng bị ẩn.

## 2) Routes

```php
Route::livewire('/bai-viet', 'pages::client.posts.index')->name('client.posts.index');
Route::livewire('/bai-viet/{slug}', 'pages::client.posts.show')->name('client.posts.show');
```

## 3) Tối ưu tìm kiếm (mới)

## 3.1 Search columns trong bảng `posts`

Đã thêm migration tạo các cột search chuyên dụng:

- `title_vi_search`, `title_en_search`
- `excerpt_vi_search`, `excerpt_en_search`
- `content_vi_search`, `content_en_search`
- `slug_vi_search`, `slug_en_search`

Mục tiêu: giảm phụ thuộc `JSON_EXTRACT(...)` trực tiếp trong query tìm kiếm.

File: `database/migrations/2026_03_12_120000_add_search_generated_columns_to_posts_table.php`

## 3.2 FULLTEXT index cho MySQL

Đã thêm FULLTEXT index:

- `ft_posts_vi (title_vi_search, excerpt_vi_search, content_vi_search)`
- `ft_posts_en (title_en_search, excerpt_en_search, content_en_search)`

File: `database/migrations/2026_03_12_130000_add_fulltext_indexes_to_posts_table.php`

> Ghi chú:
> - Migration FULLTEXT chỉ chạy khi DB driver là `mysql`.
> - Nếu môi trường không hỗ trợ FULLTEXT, app vẫn chạy theo fallback.

## 3.3 Luồng tìm kiếm runtime (trong `with()`)

Tại `resources/views/pages/client/posts/⚡index.blade.php`, tìm kiếm chạy theo thứ tự:

1. Nếu có FULLTEXT + từ khóa đủ điều kiện: dùng `MATCH ... AGAINST`.
2. Nếu chưa dùng FULLTEXT nhưng có search columns: dùng `LIKE` trên các cột `*_search`.
3. Nếu chưa có search columns: fallback về `JSON_EXTRACT(... ) LIKE`.
4. Luôn có nhánh kiểm tra slug (`slug`, `slug_translations`) để hỗ trợ tìm không dấu tốt hơn.

Ngoài ra, trang vẫn hỗ trợ query legacy `?q=` hoặc `?query=` và normalize vào `tim-kiem`.

## 4) Featured vs List logic

- `featuredPosts`: lấy từ cùng bộ lọc (published, locale, category, search) + `is_featured = true`.
- `listPosts`: lấy cùng bộ lọc nhưng loại bỏ ID featured để tránh trùng.
- Tổng kết quả tìm kiếm hiển thị = `featuredPosts.count + listPosts.total`.

Điều này đảm bảo khi tìm kiếm hoặc lọc danh mục, bài nổi bật vẫn được tính đúng kết quả.

## 5) Cấu trúc dữ liệu chính

### 5.1 Model `App\Models\Post`

Các trường nổi bật:

- `title` (json)
- `content` (json)
- `excerpt` (json, nullable)
- `slug` (string, canonical)
- `slug_translations` (json, nullable)
- `thumbnail` (string, nullable)
- `seo_title`, `seo_description` (json, nullable)
- `category_id`, `user_id`
- `status` (`draft|published|archived`)
- `is_featured` (bool)
- `published_at` (datetime nullable)
- `views` (unsigned big int)
- `deleted_at` (soft delete)

### 5.2 Model `App\Models\Category`

- Hỗ trợ cây danh mục cha/con qua `parent_id`.
- Có cờ `is_active` để bật/tắt danh mục.
- Dữ liệu tên/mô tả hỗ trợ đa ngôn ngữ qua JSON.

## 6) Seed dữ liệu mẫu bài viết

Seeder: `database/seeders/PostSeeder.php`

Đặc điểm dữ liệu seed hiện tại:

- Nhiều bài hơn bản cũ (không chỉ 3 bài).
- Có đủ trạng thái `published`, `draft`, `archived`.
- Có bài `is_featured = true`.
- Có bài tiếng Anh trống để test rule ẩn ở locale `en`.
- Dùng `updateOrCreate` theo `slug` để seed idempotent.

`DatabaseSeeder` đã gọi `PostSeeder` sau khi seed category mẫu.

## 7) Lệnh chạy nhanh

## 7.1 Chạy migration

```bash
php artisan migrate --force
```

## 7.2 Seed bài viết

```bash
php artisan db:seed --class=PostSeeder --force
```

## 7.3 Seed toàn bộ hệ thống

```bash
php artisan db:seed --force
```

## 8) File liên quan

### 8.1 Client pages
- `resources/views/pages/client/posts/⚡index.blade.php`
- `resources/views/pages/client/posts/⚡show.blade.php`

### 8.2 Models
- `app/Models/Post.php`
- `app/Models/Category.php`

### 8.3 Migrations
- `database/migrations/2026_03_07_031013_create_posts_table.php`
- `database/migrations/2026_03_10_100000_add_is_featured_to_posts_table.php`
- `database/migrations/2026_03_12_120000_add_search_generated_columns_to_posts_table.php`
- `database/migrations/2026_03_12_130000_add_fulltext_indexes_to_posts_table.php`

### 8.4 Seeders
- `database/seeders/PostSeeder.php`
- `database/seeders/DatabaseSeeder.php`

## 9) Lưu ý kỹ thuật

- FULLTEXT tối ưu tốt khi dữ liệu lớn, nhưng vẫn cần fallback cho môi trường không hỗ trợ.
- Từ khóa rất ngắn có thể không tối ưu bằng FULLTEXT; code đã có fallback LIKE.
- Search tiếng Việt không dấu dựa nhiều vào slug matching.
- Khi đổi logic search ở client list, cần đồng bộ với global search để UX nhất quán.

## 10) TODO đề xuất

- Đồng bộ hoàn toàn logic giữa `global-search` và trang `/bai-viet`.
- Cân nhắc thêm cơ chế ranking (ưu tiên title > excerpt > content).
- Nếu dữ liệu tăng lớn, cân nhắc search engine riêng (Meilisearch/Elasticsearch).
