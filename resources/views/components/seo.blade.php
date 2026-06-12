@props([
    'post' => null,
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => null,
    'keywords' => null,
])
@php
    use Illuminate\Support\Str;

    $locale   = app()->getLocale();
    $siteName = __('Faculty of Information Technology') . ' - VNUA';
    $sectionName = null;
    $fallbackDescription = __('Khoa Công nghệ thông tin thuộc Học viện Nông nghiệp Việt Nam, thành lập ngày 10/10/2005, đào tạo và nghiên cứu trong lĩnh vực công nghệ thông tin.');

    if ($post) {
        if (isset($post->categories) && $post->categories->isNotEmpty()) {
            $sectionName = $post->categories->first()->getTranslation('name', $locale);
        } elseif (isset($post->category) && $post->category) {
            $sectionName = $post->category->getTranslatedName();
        }
    }
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

    $seoTitle = $title ?: $rawSeoTitle ?: $rawPostTitle;

    $seoTitle = trim(strip_tags((string) $seoTitle));

    if ($seoTitle === '') {
        $seoTitle = $siteName;
    } elseif (! Str::contains($seoTitle, $siteName)) {
        $seoTitle = Str::limit($seoTitle, 65, '...') . ' | ' . $siteName;
    }

    $seoDescription = $description
        ?: ($post->getTranslation('seo_description', $locale, false)
            ?: ($post->getExcerptOrAuto($locale, 160) ?: $fallbackDescription));

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
       $rawTitle = trim(strip_tags((string) $title));

        $seoTitle = $rawTitle ?: $siteName;

        if ($seoTitle !== $siteName && ! Str::contains($seoTitle, $siteName)) {
            $seoTitle = Str::limit($seoTitle, 65, '...') . ' | ' . $siteName;
        }

        $seoDescription = $description ?: $fallbackDescription;

        $ogImage = $toAbsoluteImageUrl($image);
        $canonical = url()->current();
        $ogType = $type ?: 'website';
    }

    $seoDescription = Str::limit(strip_tags((string) $seoDescription), 160, '...');

    if ($seoDescription === '') {
        $seoDescription = Str::limit($fallbackDescription, 160, '...');
    }
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

<meta name="google-site-verification" content="qKDo3Apj3FQGpu7XbIth7a9H_jiNQoLKO6fX_HLSIwk" />

<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="author" content="{{ $post?->user?->name ?? $siteName }}">
<meta name="creator" content="{{ $siteName }}">
<meta name="publisher" content="{{ $siteName }}">
<meta name="application-name" content="{{ $siteName }}">
<meta name="keywords" content="{{ $keywords ?: collect([
    $sectionName,
    'Khoa Công nghệ thông tin',
    'VNUA',
    'FITA',
    'Học viện Nông nghiệp Việt Nam',
    'công nghệ thông tin',
    'đào tạo',
    'tuyển sinh',
    'sinh viên',
    'giảng viên',
])->filter()->implode(', ') }}">

@if($post)
    @if($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
        <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
    @endif

    @if($post->user)
        <meta property="article:author" content="{{ $post->user->name }}">
    @endif

    @if($sectionName)
        <meta property="article:section" content="{{ $sectionName }}">
    @endif
@endif

@php
    $schema = null;

    if ($post) {
        $authorName = data_get($post, 'user.name') ?: $siteName;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ],
            'headline' => Str::limit(strip_tags((string) ($rawPostTitle ?: $seoTitle)), 110, ''),
            'description' => $seoDescription,
            'image' => [
                $ogImage,
            ],
            'datePublished' => optional($post->published_at)->toIso8601String(),
            'dateModified' => optional($post->updated_at)->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $authorName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $siteName,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('assets/images/FITA.png'),
                ],
            ],
        ];

        if ($sectionName) {
            $schema['articleSection'] = $sectionName;
        }

        $schema = array_filter($schema, fn ($value) => ! is_null($value));
    } else {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => url('/'),
            'description' => $seoDescription,
            'publisher' => [
                '@type' => 'Organization',
                'name' => $siteName,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('assets/images/FITA.png'),
                ],
            ],
        ];
    }
@endphp

@if($schema)
    <script type="application/ld+json">
        {!! json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_APOS
            | JSON_HEX_AMP
            | JSON_HEX_QUOT
        ) !!}
    </script>
@endif
