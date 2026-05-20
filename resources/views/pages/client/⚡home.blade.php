<?php

use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Album;
use App\Models\AlbumImage;
use App\Models\Banner;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

new
#[Layout('layouts.client')]
class extends Component {

    public $tabSelected = 'tab-feature-post';
    public bool $testimonialModal = false;

    protected function getEmptyConfig(): array
    {
        return [
            'section_titles' => [
                'news'         => '',
                'training'     => '',
                'partners'     => '',
                'testimonials' => '',
                'gallery'      => '',
            ],
            'quick_links'       => [],
            'training_programs' => [],
            'counter_stats'     => [],
            'testimonials'      => [],
        ];
    }

    protected function normalizeHomeConfig(array $config): array
    {
        $defaults = $this->getEmptyConfig();

        foreach (['quick_links', 'training_programs', 'counter_stats', 'testimonials'] as $section) {
            if (!array_key_exists($section, $config) || !is_array($config[$section])) {
                $config[$section] = [];
                continue;
            }
            $config[$section] = array_values(array_filter($config[$section], 'is_array'));
        }

        $config['section_titles'] = array_merge(
            $defaults['section_titles'],
            is_array($config['section_titles'] ?? null) ? $config['section_titles'] : []
        );

        return array_merge($defaults, $config);
    }

    protected function getHomeConfig(string $locale): array
    {
        $page = Page::where('slug', 'home3')->first();

        if (!$page) {
            return $this->getEmptyConfig();
        }

        $translations = $page->getTranslations('content_data');

        if (array_key_exists($locale, $translations)) {
            $config = $page->getTranslation('content_data', $locale, false);
            return is_array($config) ? $this->normalizeHomeConfig($config) : $this->getEmptyConfig();
        }

        if (array_key_exists('vi', $translations)) {
            $config = $page->getTranslation('content_data', 'vi', false);
            return is_array($config) ? $this->normalizeHomeConfig($config) : $this->getEmptyConfig();
        }

        return $this->getEmptyConfig();
    }

    protected function getPreviewHomeConfig(string $locale): ?array
    {
        if (!request()->boolean('preview_home3') || !auth()->check()) {
            return null;
        }

        $previewData = Cache::get('preview_home3_data_' . auth()->id());

        if (!is_array($previewData)) {
            return null;
        }

        if (array_key_exists($locale, $previewData) && is_array($previewData[$locale])) {
            return $this->normalizeHomeConfig($previewData[$locale]);
        }

        if (array_key_exists('vi', $previewData) && is_array($previewData['vi'])) {
            return $this->normalizeHomeConfig($previewData['vi']);
        }

        return null;
    }

    protected function resolveMediaUrl(?string $path, string $fallback = ''): string
    {
        $path = trim((string) $path);

        if ($path === '') return $fallback;

        if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_starts_with($path, 'assets/')) {
            return asset($path);
        }

        $path = ltrim($path, '/');
        $path = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

        return asset('storage/' . ltrim($path, '/'));
    }

    protected function hasMeaningfulTranslation(Post $post, string $field, string $locale): bool
    {
        $value = $post->getTranslation($field, $locale, false);

        if (!is_string($value)) return false;

        $plainText = trim(preg_replace(
            '/\x{00A0}/u',
            ' ',
            strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ) ?? '');

        return $plainText !== '';
    }

    protected function isVisibleInLocale(Post $post, string $locale): bool
    {
        if ($locale !== 'en') return true;

        return $this->hasMeaningfulTranslation($post, 'title', 'en')
            && $this->hasMeaningfulTranslation($post, 'content', 'en');
    }

    protected function isNewPost(Post $post): bool
    {
        if (!$post->published_at) return false;

        $publishedAt = $post->published_at instanceof \Illuminate\Support\Carbon
            ? $post->published_at
            : \Illuminate\Support\Carbon::parse($post->published_at);

        $now = now();
        $threshold = $now->copy()->subDays(3);

        return $publishedAt->greaterThanOrEqualTo($threshold)
            && $publishedAt->lessThanOrEqualTo($now);
    }

    public function with(): array
    {
        $locale = app()->getLocale();
        $homeConfig = $this->getPreviewHomeConfig($locale) ?? $this->getHomeConfig($locale);

        $dbSlides = Banner::query()
            ->active()
            ->orderBy('order')
            ->get()
            ->map(function (Banner $banner) use ($locale) {
                if (!$banner->image || !Storage::disk('public')->exists($banner->image)) {
                    return null;
                }

                return [
                    'image' => Storage::url($banner->image),
                    'title' => $banner->getTranslation('title', $locale, false)
                        ?: $banner->getTranslation('title', 'vi', false)
                            ?: $banner->getTranslation('title', 'en', false) ?: '',
                    'description' => $banner->getTranslation('description', $locale, false)
                        ?: $banner->getTranslation('description', 'vi', false)
                            ?: $banner->getTranslation('description', 'en', false) ?: '',
                    'url' => $banner->url,
                    'urlText' => $banner->getTranslation('url_text', $locale, false)
                        ?: $banner->getTranslation('url_text', 'vi', false)
                            ?: $banner->getTranslation('url_text', 'en', false) ?: '',
                    'position' => $banner->position ?: 'bottom center',
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        $configBanner = Page::where('slug', 'banner')->first();

        $quickLinks = collect($homeConfig['quick_links'] ?? [])
            ->map(fn($item) => array_merge($item, [
                'img_url' => $this->resolveMediaUrl($item['img'] ?? '', ''),
            ]))
            ->values()
            ->toArray();

        $trainingPrograms = collect($homeConfig['training_programs'] ?? [])
            ->map(fn($item) => array_merge($item, [
                'image_url' => $this->resolveMediaUrl($item['image'] ?? '', asset('assets/images/post-2.jpg')),
            ]))
            ->values()
            ->toArray();

        $counterStats = $homeConfig['counter_stats'] ?? [];

        $testimonials = collect($homeConfig['testimonials'] ?? [])
            ->map(fn($item) => array_merge($item, [
                'avatar_url' => $this->resolveMediaUrl($item['avatar'] ?? '', asset('assets/images/default-user-image.png')),
            ]))
            ->values()
            ->toArray();

        $sectionTitles = $homeConfig['section_titles'] ?? [];

        $baseQuery = Post::query()
            ->with(['categories' => fn($q) => $q->where('is_active', true), 'user'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');

        $featuredPosts = (clone $baseQuery)
            ->where('is_featured', true)
            ->latest('published_at')
            ->limit($locale === 'en' ? 20 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();

        $latestPosts = (clone $baseQuery)
            ->whereHas('categories', function ($query) {
                $query->where('categories.slug', 'tin-tuc')->orWhere('categories.slug', 'su-kien');
            })
            ->when($featuredPosts->isNotEmpty(), fn($q) => $q->whereNotIn('id', $featuredPosts->pluck('id')))
            ->latest('published_at')
            ->limit($locale === 'en' ? 24 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();

        $notificationPosts = (clone $baseQuery)
            ->whereHas('categories', function ($query) {
                $query->where('categories.slug', 'thong-bao');
            })
            ->when($featuredPosts->isNotEmpty(), fn($q) => $q->whereNotIn('id', $featuredPosts->pluck('id')))
            ->latest('published_at')
            ->limit($locale === 'en' ? 24 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();

        $featuredAlbum = Album::query()->featuredForHome()->orderByDesc('updated_at')->first();
        $imagesQuery = AlbumImage::query()->whereNull('album_images.deleted_at');

        if ($featuredAlbum) {
            $imagesQuery->whereHas('albums', fn($query) => $query->where('albums.id', $featuredAlbum->id))
                ->orderByDesc('album_images.created_at')
                ->orderByDesc('album_images.id');
        } else {
            $imagesQuery->orderByDesc('album_images.created_at')->orderByDesc('album_images.id')->limit(20);
        }

        $images = $imagesQuery->get()
            ->filter(fn(AlbumImage $image) => filled($image->image_path) && Storage::disk('public')->exists($image->image_path))
            ->map(fn(AlbumImage $image) => [
                'url' => Storage::url($image->image_path),
                'alt' => $image->caption,
                'caption' => $image->caption,
            ])
            ->values()
            ->toArray();

        if ($featuredAlbum && count($images) === 0) {
            $images = AlbumImage::query()
                ->whereNull('album_images.deleted_at')
                ->orderByDesc('album_images.created_at')
                ->orderByDesc('album_images.id')
                ->limit(20)
                ->get()
                ->filter(fn(AlbumImage $image) => filled($image->image_path) && Storage::disk('public')->exists($image->image_path))
                ->map(fn(AlbumImage $image) => [
                    'url' => Storage::url($image->image_path),
                    'alt' => $image->caption,
                    'caption' => $image->caption,
                ])
                ->values()
                ->toArray();
        }

        return [
            'slides' => $dbSlides,
            'quickLinks' => $quickLinks,
            'trainingPrograms' => $trainingPrograms,
            'counterStats' => $counterStats,
            'testimonials' => $testimonials,
            'sectionTitles' => $sectionTitles,
            'featuredPosts' => $featuredPosts,
            'latestPosts' => $latestPosts,
            'notificationPosts' => $notificationPosts,
            'images' => $images,
            'configBanner' => $configBanner,
        ];
    }

    public function mount(): void
    {
        $locale = app()->getLocale();

        $hasFeatured = Post::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('is_featured', true)
            ->latest('published_at')
            ->limit($locale === 'en' ? 20 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->isNotEmpty();

        if (!$hasFeatured) {
            $this->tabSelected = 'tab-new-post';
        }
    }
};
?>

<div class="bg-slate-50">
    <x-slot:title>
        {{ __('Home page') }}
    </x-slot:title>

    @if(!empty($slides))
        <div
            x-data="{
            paused: false,
            autoplay: {{ !empty($configBanner->content_data['autoplay']) ? 'true' : 'false' }},
            interval: {{ (int) ($configBanner->content_data['interval'] ?? 5000) }},
            startCustomAutoplay() {
                if (this.autoplay) {
                    setInterval(() => {
                        // Nếu chuột không nằm trong vùng banner thì mới bấm nút Next
                        if (!this.paused) {
                            let nextBtn = this.$el.querySelector(`button[aria-label='Next image']`);
                            if (nextBtn) nextBtn.click();
                        }
                    }, this.interval);
                }
            }
        }"
            x-init="startCustomAutoplay()"
            @mouseenter="paused = true"
            @mouseleave="paused = false"
            class="relative w-full home-banner-carousel"
        >
            <x-carousel
                :slides="$slides"
                class="h-[40vw] md:h-65 lg:h-91 2xl:min-h-110 rounded-none w-full [&_img]:w-full [&_img]:h-full [&_img]:object-fill"
            >
                @scope('content', $slide)
                <div
                    @class([
                        "absolute inset-0 z-[1] flex flex-col gap-1 md:gap-2 px-4 py-4 md:px-20 md:py-12",
                        "bg-gradient-to-b justify-start text-left" => data_get($slide, 'position') === 'top left',
                        "bg-gradient-to-b justify-start items-center text-center" => data_get($slide, 'position') === 'top center',
                        "bg-gradient-to-b justify-start items-end text-right" => data_get($slide, 'position') === 'top right',

                        "bg-gradient-to-t justify-center items-center text-center" => data_get($slide, 'position') === 'center center',
                        "bg-gradient-to-t justify-center items-end text-right" => data_get($slide, 'position') === 'center right',
                        "bg-gradient-to-t justify-center text-left" => data_get($slide, 'position') === 'center left',

                        "bg-gradient-to-t justify-end text-left" => data_get($slide, 'position') === 'bottom left',
                        "bg-gradient-to-t justify-end items-center text-center" => data_get($slide, 'position') === 'bottom center',
                        "bg-gradient-to-t justify-end items-end text-right" => data_get($slide, 'position') === 'bottom right',

                        "from-slate-900/45" => data_get($slide, 'urlText') || data_get($slide, 'title') || data_get($slide, 'description')
                    ])
                >
                    <h1 class="w-full md:w-[60%] text-xl md:text-2xl lg:text-[64px]/[68px] font-bold text-white">
                        {{ data_get($slide, 'title') }}
                    </h1>

                    <h5 class="w-full md:w-[60%] text-sm md:text-[16px] lg:text-[30px] font-bold text-white">
                        {{ data_get($slide, 'description') }}
                    </h5>

                    @if(data_get($slide, 'urlText'))
                        <div class="hidden md:block">
                            <x-button link="{{ data_get($slide, 'url') }}" icon-right="o-arrow-right"
                                      class="btn btn-sm lg:btn-md max-w-40 bg-fita text-white border-transparent shadow-none hover:bg-fita2 my-3 hover:scale-105">
                                {{ __(data_get($slide, 'urlText')) }}
                            </x-button>
                        </div>
                    @endif
                </div>
                @endscope
            </x-carousel>
        </div>
    @endif

    <div class="my-0.125">
        <div class="h-1.25 bg-[#F6A309] w-full"></div>
        <div class="h-1.25 bg-[#066140] w-full"></div>
        <div class="h-1.25 bg-[#4E3636] w-full shadow-[0_0_6px_#4E3636]"></div>
    </div>

    @if(!empty($quickLinks))
        <div class="relative z-20 -mt-4 mb-6 font-sans">
            <div class="container mx-auto px-3 lg:px-4 max-w-5xl"
                 x-data="{ menus: @js($quickLinks) }">
                <div class="bg-white/80 backdrop-blur-xl rounded-2xl border border-gray-100 p-2 lg:pt-2.75 lg:pb-1 lg:px-3 flex flex-wrap lg:flex-nowrap gap-2 shadow-[0_15px_35px_rgba(0,0,0,0.22)]">
                    <template x-for="(item, index) in menus" :key="index">
                        <a :href="item.link"
                           target="_blank"
                           class="relative group flex-1 min-w-[45%] md:min-w-0 flex items-center justify-center lg:justify-start gap-3 lg:gap-4 px-2 py-1 rounded-xl hover:shadow-md hover:bg-blue-100/50 transition-all duration-300 cursor-pointer hover:-translate-y-1"
                        >
                            <div x-show="item.img_url" class="w-8 h-8 lg:w-10 lg:h-10 shrink-0 transition-transform duration-300 group-hover:scale-105 group-hover:-translate-y-1">
                                <img :src="item.img_url ?? item.img" alt="App Icon" class="w-full h-full object-contain drop-shadow-sm">
                            </div>
                            <div class="text-left flex-1 min-w-0">
                                <h2 class="text-[14px] lg:text-[16px] font-bold text-gray-800 tracking-wide truncate transition-colors duration-300"
                                    {{--                                    :style="`color: ${item.color};`"--}}
                                    x-text="item.app">
                                </h2>
                            </div>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-3 px-3 py-1.5 bg-white backdrop-blur rounded-lg opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none whitespace-nowrap z-50 shadow-xl scale-95 group-hover:scale-100 origin-bottom invisible lg:visible">
                                <span class="text-[14px] font-semibold" x-text="item.desc"></span>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-[5px] border-transparent border-t-white"></div>
                            </div>
                        </a>
                    </template>
                </div>
            </div>
        </div>
    @endif

    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-0 mb-6">
            {{ !empty($sectionTitles['news']) ? $sectionTitles['news'] : __('News and events') }}
        </h1>
        <div class="relative flex flex-col lg:flex-row container px-4 lg:px-0 mx-auto gap-8 lg:gap-10">
            <div class="lg:w-[50%] w-full relative h-65 lg:h-140 rounded-2xl shadow-[0_15px_35px_rgba(0,0,0,0.22)]" wire:key="slider-{{ $tabSelected }}">
                @php
                    $currentTabPosts = match($tabSelected) {
                        'tab-feature-post' => $featuredPosts,
                        'tab-new-post' => $latestPosts,
                        default => $notificationPosts,
                    };

                    $sliderData = $currentTabPosts->map(function($post) {
                        return [
                            'url' => $post->client_url,
                            'image' => $post->thumbnail
                                ? Storage::url($post->thumbnail)
                                : ($post->post_default_image_id
                                    ? Storage::url($post->defaultImage->image_path)
                                    : asset('assets/images/post-6.jpg')),
                            'has_img_default' => !$post->thumbnail && $post->post_default_image_id && $post->defaultImage?->show_title,
                            'text_size' => $post->defaultImage?->text_size ?: 18,
                            'text_color' => $post->defaultImage?->text_color ?: '#ffffff',
                            'text_align' => $post->defaultImage?->text_alignment ?: 'center',
                            'text_y_offset' => $post->defaultImage?->text_y_offset ?: 0,
                            'is_featured' => $post->is_featured,
                            'is_new' => $this->isNewPost($post),
                            'is_notif' => $post->categories->contains(fn($cat) => $cat->slug === 'thong-bao'),
                            'day' => $post->published_at?->isoFormat('DD'),
                            'month' => app()->getLocale() === 'vi'
                                ? 'Tháng '.$post->published_at?->isoFormat('MM').'/'.$post->published_at?->isoFormat('YYYY')
                                : $post->published_at?->isoFormat('MMMM'),
                            'title' => $post->getTranslation('title', app()->getLocale()),
                        ];
                    })->values()->toArray();
                @endphp

                <div x-data="{
                        posts: @js($sliderData),
                        currentIndex: 0,
                        interval: null,
                        init() { if (this.posts.length > 1) { this.start(); } },
                        start() {
                            this.interval = setInterval(() => { this.currentIndex = (this.currentIndex + 1) % this.posts.length; }, 7000);
                        },
                        pause() { clearInterval(this.interval); }
                    }"
                     @mouseenter="pause"
                     @mouseleave="if(posts.length > 1) start()"
                     class="relative h-full w-full rounded-2xl overflow-hidden bg-slate-900 border border-base-300"
                >
                    <template x-for="(post, index) in posts" :key="index">
                        <a x-show="currentIndex === index"
                           x-transition.opacity.duration.700ms
                           :href="post.url"
                           wire:navigate
                           class="absolute inset-0 group block h-full w-full"
                        >
                            <img x-show="post.has_img_default" :src="post.image" :alt="post.title" loading="eager" class="h-full w-full object-cover object-top transition-transform duration-500 group-hover:scale-105">
                            <img x-show="!post.has_img_default" :src="post.image" :alt="post.title" loading="eager" class="h-full w-full object-cover object-top transition-transform duration-500 group-hover:scale-105">

                            <template x-if="post.is_featured">
                                <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-red-500 px-4 py-1 text-md font-bold text-white shadow-md rounded-br-2xl rounded-tl-xl">
                                    {{ __('Featured News') }}
                                </div>
                            </template>

                            @if($tabSelected === 'tab-new-post')
                                <template x-if="!post.is_featured && post.is_new">
                                    <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-[#F6A309] px-4 py-1 text-md font-bold text-white shadow-md rounded-br-2xl rounded-tl-xl">
                                        {{ __('New') }}
                                    </div>
                                </template>
                            @endif
                            @if($tabSelected === 'tab-notification-post')
                                <template x-if="!post.is_featured && post.is_notif">
                                    <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-success px-4 py-1 text-md font-bold text-white shadow-md rounded-br-2xl rounded-tl-xl">
                                        {{ __('Notification') }}
                                    </div>
                                </template>
                            @endif

                            <div class="absolute right-0 top-0 z-10 bg-black/45 px-3 py-2 text-center text-white backdrop-blur-sm">
                                <div class="text-[40px]/[34px] lg:text-[54px]/[44px] font-bold" x-text="post.day"></div>
                                <div class="text-[18px]/[30px] lg:text-[20px]/[24px] font-bold mt-0 lg:mt-3" x-text="post.month"></div>
                            </div>
                            <div x-show="!post.has_img_default" class="absolute inset-0 bg-linear-to-t from-black/60 via-black/20 to-transparent"></div>
                            <div x-show="!post.has_img_default" class="absolute bottom-0 left-0 right-0 p-6 text-white">
                                <h3 class="line-clamp-2 text-[18px]/[20px] lg:text-[20px]/[24px] font-bold" x-text="post.title"></h3>
                            </div>
                            <div x-show="post.has_img_default" class="absolute inset-0 flex items-center justify-center p-12 lg:p-5 text-center"
                                 :style="`transform: translateY(calc(${post.text_y_offset} / 1200 * 100cqw))`"
                            >
                                <p class="line-clamp-4 font-bold select-none"
                                   :style="{
                                            color: post.text_color,
                                            fontSize: `clamp(12px, calc(${post.text_size} / 1200 * 100cqw), 60px)`,
                                            lineHeight: 1.1,
                                            textAlign: post.text_align,
                                            padding: '5px',
                                        }"
                                   x-text="post.title"
                                ></p>
                            </div>
                        </a>
                    </template>
                    <div x-show="posts.length > 1" class="absolute bottom-4 right-4 z-20 flex gap-2">
                        <template x-for="(post, index) in posts" :key="'dot-'+index">
                            <button @click="currentIndex = index" class="w-2.5 h-2.5 rounded-full transition-all duration-300 shadow-sm" :class="currentIndex === index ? 'bg-white w-6' : 'bg-white/50 hover:bg-white/80'"></button>
                        </template>
                    </div>
                    <div x-show="posts.length === 0" class="flex h-full items-center justify-center bg-base-100 text-base-content/60">
                        {{ __('No posts available') }}
                    </div>
                </div>

                <div wire:loading.flex wire:target="tabSelected"
                     class="absolute inset-0 z-30 items-center justify-center bg-white/60 backdrop-blur-[1px] rounded-2xl">
                    <x-loading class="text-primary loading-lg"/>
                </div>
            </div>

            <div class="w-full lg:w-[50%]">
                <x-tabs wire:model.live="tabSelected" active-class="text-fita! border-b-4 border-fita font-semibold" label-class="font-semibold text-[20px] text-gray-700 px-4 pb-1 whitespace-nowrap font-barlow" label-div-class="border-b-[length:var(--border)] border-b-base-content/10 flex overflow-x-auto">
                    <x-tab name="tab-feature-post">
                        <x-slot:label>
                            <span class="inline-flex items-center h-6">{{ __('Featured News') }}</span>
                        </x-slot:label>
                        <div class="flex flex-col gap-5">
                            @forelse($featuredPosts as $post)
                                <div class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300 shadow-md">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative ">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover" alt="{{ $post->getTranslation('title', app()->getLocale()) }}" loading="lazy" decoding="async">
                                        @elseif($post->post_default_image_id)
                                            @if($post->defaultImage?->show_title)
                                                <div class="absolute inset-0 flex items-center justify-center p-1.25" style="container-type: inline-size;">
                                                    <p class="line-clamp-4 font-bold"
                                                       :style="{
                                                            color: '{{ $post->defaultImage?->text_color ?? '#ffffff' }}',
                                                            fontSize: 'clamp(8px, calc({{ $post->defaultImage?->text_size ?? 18 }} / 1200 * 100cqw), 60px)',
                                                            lineHeight: 1.1,
                                                            textAlign: '{{$post->defaultImage?->text_alignment ?? 'center'}}',
                                                        }"
                                                       x-text="'{{ $post->getTranslation('title', app()->getLocale()) }}'"
                                                    ></p>
                                                </div>
                                            @endif
                                            <img src="{{ Storage::url($post->defaultImage?->image_path) }}" class="w-full h-full object-cover" alt="No image" loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}" class="w-full h-full object-cover" alt="No image" loading="lazy" decoding="async">
                                        @endif
                                        @if($post->is_featured)
                                            <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-red-500 pe-2 ps-1 py-0.5 text-[9px] font-bold text-white shadow-md rounded-br-xl">
                                                {{ __('Featured News') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">{{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}</p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">{{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500">{{ __('No featured posts found.') }}</p>
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index') }}" label="{{__('Read more')}}" icon-right="o-arrow-right" class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-5 rounded-md"/>
                    </x-tab>

                    <x-tab name="tab-new-post">
                        <x-slot:label>
                            <span class="inline-flex items-center h-6">{{ __('Latest News') }}</span>
                        </x-slot:label>
                        <div class="flex flex-col gap-5">
                            @forelse($latestPosts as $post)
                                <div class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300 shadow-md">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover" alt="{{ $post->getTranslation('title', app()->getLocale()) }}" loading="lazy" decoding="async">
                                        @elseif($post->post_default_image_id)
                                            @if($post->defaultImage?->show_title)
                                                <div class="absolute inset-0 flex items-center justify-center p-1.25" style="container-type: inline-size;">
                                                    <p class="line-clamp-4 font-bold"
                                                       :style="{
                                                            color: '{{ $post->defaultImage?->text_color ?? '#ffffff' }}',
                                                            fontSize: 'clamp(8px, calc({{ $post->defaultImage?->text_size ?? 18 }} / 1200 * 100cqw), 60px)',
                                                            lineHeight: 1.1,
                                                            textAlign: '{{$post->defaultImage?->text_alignment ?? 'center'}}',
                                                        }"
                                                       x-text="'{{ $post->getTranslation('title', app()->getLocale()) }}'"
                                                    ></p>
                                                </div>
                                            @endif
                                            <img src="{{ Storage::url($post->defaultImage?->image_path) }}" class="w-full h-full object-cover" alt="No image" loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}" class="w-full h-full object-cover" alt="No image" loading="lazy" decoding="async">
                                        @endif
                                        @if($this->isNewPost($post) && !$post->is_featured)
                                            <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-[#F6A309] pe-2 ps-1 py-0.5 text-[9px] font-bold text-white shadow-md rounded-br-xl">
                                                {{ __('New') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">{{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}</p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">{{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500">{{ __('No latest posts found.') }}</p>
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index',['danh-muc' => 'tin-tuc']) }}" label="{{__('Read more')}}" icon-right="o-arrow-right" class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-5 rounded-md"/>
                    </x-tab>

                    <x-tab name="tab-notification-post">
                        <x-slot:label>
                            <span class="inline-flex items-center h-6">{{ __('Notification') }}</span>
                        </x-slot:label>
                        <div class="flex flex-col gap-5">
                            @forelse($notificationPosts as $post)
                                <div class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300 shadow-md">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover" alt="{{ $post->getTranslation('title', app()->getLocale()) }}" loading="lazy" decoding="async">
                                        @elseif($post->post_default_image_id)
                                            @if($post->defaultImage?->show_title)
                                                <div class="absolute inset-0 flex items-center justify-center p-1.25" style="container-type: inline-size;">
                                                    <p class="line-clamp-4 font-bold"
                                                       :style="{
                                                            color: '{{ $post->defaultImage?->text_color ?? '#ffffff' }}',
                                                            fontSize: 'clamp(8px, calc({{ $post->defaultImage?->text_size ?? 18 }} / 1200 * 100cqw), 60px)',
                                                            lineHeight: 1.1,
                                                            textAlign: '{{$post->defaultImage?->text_alignment ?? 'center'}}',
                                                        }"
                                                       x-text="'{{ $post->getTranslation('title', app()->getLocale()) }}'"
                                                    ></p>
                                                </div>
                                            @endif
                                            <img src="{{ Storage::url($post->defaultImage?->image_path) }}" class="w-full h-full object-cover" alt="No image" loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}" class="w-full h-full object-cover" alt="No image" loading="lazy" decoding="async">
                                        @endif
                                        <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-success pe-2 ps-1 py-0.5 text-[9px] font-bold text-white shadow-md rounded-br-xl">
                                            {{ __('Notification') }}
                                        </div>
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">{{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}</p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">{{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500">{{ __('No announcement posts found.') }}</p>
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index',['danh-muc' => 'thong-bao']) }}" label="{{__('Read more')}}" icon-right="o-arrow-right" class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-5 rounded-md"/>
                    </x-tab>
                </x-tabs>
            </div>
        </div>
    </div>

    @if(!empty($counterStats))
        <section class="mt-4 lg:mt-8 bg-slate-200/40 pb-6 pt-15 lg:pt-18 lg:pb-6">
            <div class="mx-auto container px-4 lg:px-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-8 gap-y-12 lg:gap-y-10">
                    @foreach($counterStats as $stat)
                        <div data-reveal-item class="relative pt-10 pb-2 px-4 text-center"
                             x-data="{
                                value: 0, target: {{ (int) $stat['value'] }}, suffix: '{{ $stat['suffix'] }}', started: false,
                                format(v) { return new Intl.NumberFormat('vi-VN').format(v); },
                                start() {
                                    if (this.started) return;
                                    this.started = true;
                                    const duration = 1200; const startTime = performance.now();
                                    const tick = (now) => {
                                        const progress = Math.min((now - startTime) / duration, 1);
                                        this.value = Math.floor(this.target * progress);
                                        if (progress < 1) requestAnimationFrame(tick);
                                    }; requestAnimationFrame(tick);
                                }
                            }"
                             x-intersect.threshold.40="start()"
                        >
                            <div class="absolute -top-10 left-1/2 -translate-x-1/2 h-20 w-20 rounded-full bg-[#DDE8F1] flex items-center justify-center">
                                <x-icon name="{{ $stat['icon'] }}" class="w-10 h-10 text-fita"/>
                            </div>
                            <p class="text-[30px] lg:text-[38px] leading-none font-bold text-fita mt-2">
                                <span x-text="format(value)"></span><span x-text="suffix"></span>
                            </p>
                            <p class="mt-3 text-[20px] lg:text-[18px] text-slate-700 leading-8">{{ $stat['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(!empty($trainingPrograms))
        <div>
            <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-6 lg:mb-8">
                {{ !empty($sectionTitles['training']) ? $sectionTitles['training'] : __('Training programs') }}
            </h1>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 container mx-auto px-4 lg:px-0">
                @foreach($trainingPrograms as $program)
                    <div class="flex flex-col relative rounded-2xl overflow-hidden border border-slate-300 group hover:-translate-y-1.5 hover:shadow-lg transition-all duration-300"
                         x-data="{ revealed: false }"
                         x-intersect="if (!revealed) { revealed = true; $el.classList.add('animate-fade-in-up'); }">
                        <img src="{{ data_get($program, 'image_url') ?: asset('assets/images/post-6.jpg') }}" alt="" class="w-full object-cover transition-transform duration-500 h-52" loading="lazy" decoding="async">
                        <div class="flex flex-col justify-around flex-1">
                            <div class="px-6 py-4">
                                <a href="{{ data_get($program, 'detail_url') }}" wire:navigate class="why-title text-[18px] lg:text-[22px] font-bold text-slate-900 mb-2 transition-colors uppercase line-clamp-2 group-hover:text-fita">
                                    {{ data_get($program, 'title') }}
                                </a>
                                <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed line-clamp-4">
                                    {{ data_get($program, 'description') }}
                                </p>
                            </div>
                            <div class="px-6 pb-4 pt-2 flex gap-4 justify-around flex-wrap">
                                @if(data_get($program, 'detail_url'))
                                    <x-button label="{{ app()->getLocale() === 'en' ? 'Detail program' : 'Chi tiết chương trình' }}" class="btn-outline text-fita font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-md" link="{{ data_get($program, 'detail_url') }}" />
                                @endif
                                @if(data_get($program, 'roadmap_url'))
                                    <x-button label="{{ app()->getLocale() === 'en' ? 'Roadmap' : 'Xem lộ trình' }}" icon="o-book-open" class="bg-fita text-white font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-md" link="{{ data_get($program, 'roadmap_url') }}"/>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex text-center justify-center gap-1 items-center mt-10 lg:mt-12 mb-6">
            {{ !empty($sectionTitles['partners']) ? $sectionTitles['partners'] : __('NETWORK OF BUSINESS PARTNERS') }}
        </h1>
        <livewire:client.list-of-partners/>
    </div>

    @if(!empty($testimonials))
        <section class="bg-blue-100/40 py-10 lg:py-12 font-sans" x-data="{
            activeIndex: 0,
            slides: @js($testimonials),
            isHovered: false,
            intervalId: null,

            selectedSlide: null,
            openDetailModal(slide) {
                    this.selectedSlide = slide;
                    this.isHovered = true;
                    $wire.testimonialModal = true;
                },

            next() {
                this.activeIndex = this.activeIndex === this.slides.length - 1 ? 0 : this.activeIndex + 1
            },
            prev() {
                this.activeIndex = this.activeIndex === 0 ? this.slides.length - 1 : this.activeIndex - 1
            },

            startAutoplay() {
                if (this.slides.length <= 1) return;
                this.intervalId = setInterval(() => {
                    if (!this.isHovered && !$wire.testimonialModal) {
                        this.next();
                    }
                }, 7000);
            },

            stopAutoplay() {
                if (this.intervalId) {
                    clearInterval(this.intervalId);
                }
            }
        }"
                 x-init="startAutoplay()"
                 @mouseenter="isHovered = true"
                 @mouseleave="isHovered = false"
        >
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-8">
                    <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-0">
                        {{ !empty($sectionTitles['testimonials']) ? $sectionTitles['testimonials'] : __('Perspectives from businesses and alumni') }}
                    </h1>
                </div>

                <div class="relative flex items-center justify-center">
                    <button @click="prev()" class="absolute left-0 md:-left-4 z-10 w-10 h-10 bg-white rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-fita transition">
                        <x-icon name="s-chevron-left"></x-icon>
                    </button>

                    <div class="bg-white rounded-[36px] shadow-sm p-6 md:p-10 max-w-4xl w-full mx-8 relative min-h-[360px] md:min-h-64 transition-all duration-300">
                        <template x-for="(slide, index) in slides" :key="slide.id ?? index">
                            <div x-show="activeIndex === index"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 transform translate-x-4"
                                 x-transition:enter-end="opacity-100 transform translate-x-0"
                                 class="flex flex-col md:flex-row items-center gap-8">
                                <div class="relative shrink-0">
                                    <div class="w-32 h-32 md:w-40 md:h-40 rounded-full overflow-hidden border-4 border-gray-100 shadow-inner">
                                        <img :src="slide.avatar_url ?? slide.avatar ?? '{{ asset('assets/images/default-user-image.png') }}'" alt="Avatar" class="w-full h-full object-cover">
                                    </div>
                                </div>

                                {{-- 1. Khai báo biến showButton cho từng slide và đo chiều cao tự động --}}
                                <div class="flex-1 text-center md:text-left relative"
                                     x-data="{
                                        showButton: false,

                                        checkOverflow() {
                                            this.$nextTick(() => {
                                                requestAnimationFrame(() => {
                                                    requestAnimationFrame(() => {
                                                        const el = this.$refs.desc;

                                                        if (!el || activeIndex !== index) return;

                                                        this.showButton = el.scrollHeight > el.clientHeight + 2;
                                                    });
                                                });
                                            });
                                        }
                                    }"
                                     x-init="
                                        checkOverflow();
                                        setTimeout(() => checkOverflow(), 300);
                                        setTimeout(() => checkOverflow(), 800);

                                        if (document.fonts) {
                                            document.fonts.ready.then(() => checkOverflow());
                                        }
                                    "
                                     x-effect="
                                        if (activeIndex === index) {
                                            checkOverflow();
                                            setTimeout(() => checkOverflow(), 300);
                                        }
                                    "
                                     @resize.window.debounce.200ms="checkOverflow()"
                                >
                                    <div class="hidden md:block absolute top-0 -right-2 text-6xl italic font-serif">
                                        <svg height="40px" width="40px" viewBox="0 0 512.00 512.00" fill="#000000"><g><path style="fill:#0c83d8;" d="M148.57,63.619H72.162C32.31,63.619,0,95.929,0,135.781v76.408c0,39.852,32.31,72.161,72.162,72.161h7.559 c6.338,0,12.275,3.128,15.87,8.362c3.579,5.234,4.365,11.898,2.074,17.811L54.568,422.208c-2.291,5.92-1.505,12.584,2.074,17.81 c3.595,5.234,9.532,8.362,15.87,8.362h50.738c7.157,0,13.73-3.981,17.041-10.318l61.257-117.03 c12.609-24.09,19.198-50.881,19.198-78.072v-107.18C220.748,95.929,188.422,63.619,148.57,63.619z"></path><path style="fill:#0c83d8;" d="M439.84,63.619h-76.41c-39.852,0-72.16,32.31-72.16,72.162v76.408c0,39.852,32.309,72.161,72.16,72.161h7.543 c6.338,0,12.291,3.128,15.87,8.362c3.596,5.234,4.365,11.898,2.091,17.811l-43.113,111.686c-2.291,5.92-1.505,12.584,2.09,17.81 c3.579,5.234,9.516,8.362,15.871,8.362h50.722c7.157,0,13.73-3.981,17.058-10.318l61.24-117.03 C505.411,296.942,512,270.152,512,242.96v-107.18C512,95.929,479.691,63.619,439.84,63.619z"></path></g></svg>
                                    </div>
                                    <h4 class="text-xl font-bold text-black mb-1" x-text="slide.name"></h4>
                                    <p class="text-gray-600 italic mb-4 text-sm md:text-base" x-text="slide.role"></p>

                                    {{-- 2. Đặt x-ref="desc" vào thẻ <p> này để làm mốc đo --}}
                                    <div class="relative">
                                        <p x-ref="desc"
                                           class="text-gray-700 leading-relaxed text-base md:text-lg transition-all md:line-clamp-4 line-clamp-6 pr-1 md:pr-12 text-justify wrap-anywhere"
                                           x-text="slide.content">
                                        </p>

                                        <template x-if="showButton">
                                            <button type="button"
                                                    @click="openDetailModal(slide)"
                                                    class="group absolute -right-4 bottom-1 md:right-6 md:bottom-1 bg-white text-fita font-extrabold hover:text-blue-800 transition-colors text-sm">
                                                &gt;&gt;

                                                <span
                                                    class="pointer-events-none absolute left-1/2 bottom-full z-50 mb-3 -translate-x-1/2
                                                           whitespace-nowrap rounded-xl bg-white px-4 py-2
                                                           text-sm font-medium text-gray-800 shadow-lg
                                                           opacity-0 invisible
                                                           transition-all duration-200
                                                           group-hover:opacity-100 group-hover:visible">

                                                    {{ __('View full') }}

                                                    <span
                                                        class="absolute left-1/2 top-full -translate-x-1/2
                                                               w-0 h-0
                                                               border-l-8 border-r-8 border-t-8
                                                               border-l-transparent border-r-transparent border-t-white">
                                                    </span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button @click="next()" class="absolute right-0 md:-right-4 z-10 w-10 h-10 bg-white rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-fita transition">
                        <x-icon name="s-chevron-right"></x-icon>
                    </button>
                </div>
            </div>

            <div class="flex justify-center mt-6 gap-2">
                <template x-for="(slide, index) in slides" :key="slide.id ?? index">
                    <button @click="activeIndex = index" class="h-1.5 transition-all duration-300 rounded-full" :class="activeIndex === index ? 'w-8 bg-fita2' : 'w-8 bg-blue-300'"></button>
                </template>
            </div>
            <x-modal wire:model="testimonialModal" title="{{ !empty($sectionTitles['testimonials']) ? $sectionTitles['testimonials'] : __('Perspectives from businesses and alumni') }}" class="backdrop-blur-xs modalDisplayTestimonials" box-class="max-w-3xl" separator>
                <template x-if="selectedSlide">
                    <div class="space-y-5">
                        <div class="flex flex-col md:flex-row items-center md:items-start gap-5">
                            <div class="w-28 h-28 rounded-full overflow-hidden border-4 border-gray-100 shadow-inner shrink-0">
                                <img :src="selectedSlide.avatar_url ?? selectedSlide.avatar ?? '{{ asset('assets/images/default-user-image.png') }}'"
                                     alt="Avatar"
                                     class="w-full h-full object-cover">
                            </div>

                            <div class="text-center md:text-left">
                                <h4 class="text-xl font-bold text-black" x-text="selectedSlide.name"></h4>
                                <p class="text-gray-600 italic text-sm md:text-base" x-text="selectedSlide.role"></p>
                            </div>
                        </div>

                        <div class="max-h-[40vh] md:max-h-[45vh] overflow-y-auto pr-1">
                            <p class="text-gray-700 leading-relaxed text-base md:text-lg whitespace-pre-line text-justify wrap-anywhere"
                               x-text="selectedSlide.content">
                            </p>
                        </div>
                    </div>
                </template>

                <x-slot:actions>
                    <x-button label="{{__('Close')}}" @click="$wire.testimonialModal = false; selectedSlide = null; isHovered = false" />
                </x-slot:actions>
            </x-modal>

        </section>
    @endif

    <section class="pt-10 lg:pt-12 bg-slate-50">
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mb-6 mt-0">
            {{ !empty($sectionTitles['gallery']) ? $sectionTitles['gallery'] : __('Photo library') }}
        </h1>
        <livewire:client.image-gallery :images="$images" class="h-40 rounded-box"/>
    </section>
</div>
