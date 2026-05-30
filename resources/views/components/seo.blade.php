@props([
    'post' => null,
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => null,
])
@php
    use Illuminate\Support\Str;

    $locale   = app()->getLocale();
    $siteName = __('Faculty of Information Technology') . ' - VNUA';

    $toAbsoluteImageUrl = static function (?string $path): string {
        $fallback = asset('assets/images/FITA.png');

        $path = trim((string) $path);

        if ($path === '') {
            return $fallback;
        }

        // Link ngoài
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = ltrim($path, '/');

        // Nếu ảnh nằm trong public/assets
        if (Str::startsWith($path, 'assets/')) {
            return asset($path);
        }

        // Nếu DB đã lưu: storage/posts/a.jpg
        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        // Nếu DB lưu: public/posts/a.jpg
        if (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

        // Còn lại coi như ảnh trong storage/app/public
        return asset('storage/' . $path);
    };

   if ($post) {
    $rawPostTitle = strip_tags((string) $post->getTranslation('title', $locale, false));
    $rawSeoTitle  = strip_tags((string) $post->getTranslation('seo_title', $locale, false));

    $seoTitle = $title
        ?: ($rawSeoTitle ?: (Str::limit($rawPostTitle, 50, '...') . ' | ' . $siteName));

    $seoDescription = $description
        ?: ($post->getTranslation('seo_description', $locale, false)
            ?: $post->getExcerptOrAuto($locale, 160));

    $thumbnail = null;

    if (data_get($post, 'thumbnail')) {
        $thumbnail = data_get($post, 'thumbnail');
    } elseif (data_get($post, 'defaultImage.image_path')) {
        if (data_get($post, 'defaultImage.show_title')) {
            $thumbnail = 'assets/images/FITA.png';
        } else {
            $thumbnail = data_get($post, 'defaultImage.image_path');
        }
    } else {
        $thumbnail = data_get($post, 'thumbnail_path')
            ?: data_get($post, 'image')
            ?: data_get($post, 'featured_image')
            ?: $image
            ?: 'assets/images/post-6.jpg';
    }

    $ogImage = $toAbsoluteImageUrl($thumbnail);

    $canonical = url()->current();
    $ogType = $type ?: 'article';
} else {
        $rawTitle = strip_tags((string) $title);

        $seoTitle = $rawTitle
            ? Str::limit($rawTitle, 50, '...') . ' | ' . $siteName
            : $siteName;

        $seoDescription = $description
            ?: __('Faculty of Information Technology - Vietnam National University of Agriculture');

        $ogImage = $toAbsoluteImageUrl($image);
        $canonical = url()->current();
        $ogType = $type ?: 'website';
    }

    $seoDescription = Str::limit(strip_tags((string) $seoDescription), 160, '...');
@endphp

<title>{{ $seoTitle }}</title>
<meta name="title" content="{{ $seoTitle }}">
<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:type" content="{{ $ogType }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:secure_url" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ $locale === 'vi' ? 'vi_VN' : 'en_US' }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">

@if($post)
    @if($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
        <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
    @endif

    @if($post->user)
        <meta property="article:author" content="{{ $post->user->name }}">
    @endif

    @if(isset($post->categories) && $post->categories->isNotEmpty())
        <meta property="article:section" content="{{ $post->categories->first()->getTranslation('name', $locale) }}">
    @elseif(isset($post->category) && $post->category)
        <meta property="article:section" content="{{ $post->category->getTranslatedName() }}">
    @endif
@endif
