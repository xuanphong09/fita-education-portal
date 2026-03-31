@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;

    // Nhận vào $post (object) hoặc $title/$description/$image riêng lẻ
    $locale      = app()->getLocale();
    $siteName    = __('Faculty of Information Technology') . ' - VNUA';

    $toAbsoluteImageUrl = static function (?string $path): string {
        if (!$path) {
            return asset('assets/images/FITA.png');
        }

        // Keep external urls as-is.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $cleanPath = preg_replace('/^\/?storage\//', '', $path);

        // 3. Gắn link tuyệt đối chuẩn xác (asset sẽ tự sinh ra https://domain/...)
        return asset('storage/' . $cleanPath);
    };

    if (isset($post) && $post) {
        $rawPostTitle = strip_tags((string) $post->getTranslation('title', $locale, false));
        $rawSeoTitle  = strip_tags((string) $post->getTranslation('seo_title', $locale, false));

        // TỐI ƯU TITLE CẮT CHỮ:
        if ($rawSeoTitle) {
            $seoTitle = $rawSeoTitle;
        } else {
            // Cắt riêng tiêu đề bài viết trước (tối đa 50 ký tự), thêm '...' nếu bị cắt
            $shortPostTitle = Str::limit($rawPostTitle, 50, '...');
            // Nối đuôi thương hiệu vào sau cùng để không bao giờ bị cắt phạm
            $seoTitle = $shortPostTitle ? $shortPostTitle . ' | ' . $siteName : $siteName;
        }

        $seoDescription = $post->getTranslation('seo_description', $locale, false)
                       ?: $post->getExcerptOrAuto($locale, 160);

        $ogImage        = $toAbsoluteImageUrl($post->thumbnail);

        $canonical      = url()->current();
        $ogType         = 'article';
    } else {
        // ---- Fallback cho trang không có bài viết ----
        $rawTitle       = isset($title) ? strip_tags((string) $title) : '';
        // Cắt title truyền vào trước, rồi mới nối siteName
        $seoTitle       = $rawTitle ? Str::limit($rawTitle, 50, '...') . ' | ' . $siteName : $siteName;

        $seoDescription = isset($description)
            ? (string) $description
            : __('Faculty of Information Technology - Vietnam National University of Agriculture');
        $ogImage        = $toAbsoluteImageUrl(isset($image) ? (string) $image : null);
        $canonical      = url()->current();
        $ogType         = 'website';
    }

    // Phần Description không có đuôi nối thêm nên cứ cắt thoải mái ở 160 ký tự
    $seoDescription = Str::limit(strip_tags((string) $seoDescription), 160, '...');
@endphp

{{-- Các thẻ Meta HTML bên dưới bạn giữ nguyên nhé --}}

{{-- ===== Primary Meta Tags ===== --}}
<title>{{ $seoTitle }}</title>
<meta name="title"       content="{{ $seoTitle }}">
<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical"    href="{{ $canonical }}">

{{-- ===== Open Graph (Facebook, Zalo, Messenger...) ===== --}}
<meta property="og:type"        content="{{ $ogType }}">
<meta property="og:url"         content="{{ $canonical }}">
<meta property="og:title"       content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image"       content="{{ $ogImage }}">
<meta property="og:site_name"   content="{{ $siteName }}">
<meta property="og:locale"      content="{{ $locale === 'vi' ? 'vi_VN' : 'en_US' }}">

{{-- ===== Twitter Card ===== --}}
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image"       content="{{ $ogImage }}">

@if(isset($post) && $post)
{{-- ===== Article specific ===== --}}
@if($post->published_at)
<meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
<meta property="article:modified_time"  content="{{ $post->updated_at->toIso8601String() }}">
@endif
@if($post->user)
<meta property="article:author" content="{{ $post->user->name }}">
@endif
@if($post->category)
<meta property="article:section" content="{{ $post->category->getTranslatedName() }}">
@endif
@endif

