<?php

use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Album;
use App\Models\AlbumImage;
use App\Models\Banner;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

new
#[Layout('layouts.client')]
class extends Component {

    public $tabSelected = 'tab-feature-post';
    public array $slides = [];
    public $slidePosts = [
//        [
//            'image' => '/assets/images/img1.jpg',
//            'day' => '9',
//            'month' => 'Tháng 2',
//        ],
//        [
//            'image' => '/assets/images/img2.jpg',
//            'day' => '13',
//            'month' => 'Tháng 3',
//        ],
    ];


    protected function hasMeaningfulTranslation(Post $post, string $field, string $locale): bool
    {
        $value = $post->getTranslation($field, $locale, false);

        if (!is_string($value)) {
            return false;
        }

        $plainText = trim(preg_replace(
            '/\x{00A0}/u',
            ' ',
            strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ) ?? '');

        return $plainText !== '';
    }

    protected function isVisibleInLocale(Post $post, string $locale): bool
    {
        if ($locale !== 'en') {
            return true;
        }

        return $this->hasMeaningfulTranslation($post, 'title', 'en')
            && $this->hasMeaningfulTranslation($post, 'content', 'en');
    }

    protected function isNewPost(Post $post): bool
    {
        if (!$post->published_at) {
            return false;
        }

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

        $fallbackSlides = [
            [
                'image' => '/assets/images/banner-1.jpg',
                'title' => '9 Tháng 2',
                'description' => 'Chương trình đào tạo của Khoa Công nghệ thông tin',
                'url' => 'https://fita.vnua.edu.vn/',
                'urlText' => 'Xem thêm',
                'position' => 'bottom center',
            ],
            [
                'image' => '/assets/images/banner-2.jpg',
                'position' => 'center center',
            ],
            [
                'image' => '/assets/images/banner-3.jpg',
                'url' => 'https://vnua.edu.vn/',
                'urlText' => 'Read more',
                'position' => 'bottom right',
            ],
        ];

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
                            ?: $banner->getTranslation('title', 'en', false)
                                ?: '',
                    'description' => $banner->getTranslation('description', $locale, false)
                        ?: $banner->getTranslation('description', 'vi', false)
                            ?: $banner->getTranslation('description', 'en', false)
                                ?: '',
                    'url' => $banner->url,
                    'urlText' => $banner->getTranslation('url_text', $locale, false)
                        ?: $banner->getTranslation('url_text', 'vi', false)
                            ?: $banner->getTranslation('url_text', 'en', false)
                                ?: '',
                    'position' => $banner->position ?: 'bottom center',
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        $slides = count($dbSlides) > 0 ? $dbSlides : $fallbackSlides;
        $configBanner = Page::where('slug', 'banner')->first();

        $baseQuery = Post::query()
            ->with([
                'categories' => fn($q) => $q->where('is_active', true),
                'user'
            ])
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
            ->take(4)
            ->values();

        $latestPosts = (clone $baseQuery)
            ->whereHas('categories', function ($query) {
                $query->where('categories.slug', 'tin-tuc')
                ->orWhere('categories.slug', 'su-kien');
            })
            ->when($featuredPosts->isNotEmpty(), fn($q) => $q->whereNotIn('id', $featuredPosts->pluck('id')))
            ->latest('published_at')
            ->limit($locale === 'en' ? 24 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(4)
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
            ->take(4)
            ->values();

        $featuredAlbum = Album::query()
            ->featuredForHome()
            ->orderByDesc('updated_at')
            ->first();

        $imagesQuery = AlbumImage::query()
            ->whereNull('album_images.deleted_at');

        if ($featuredAlbum) {
            $imagesQuery
                ->whereHas('albums', fn($query) => $query->where('albums.id', $featuredAlbum->id))
                ->orderByDesc('album_images.created_at')
                ->orderByDesc('album_images.id');
        } else {
            $imagesQuery
                ->orderByDesc('album_images.created_at')
                ->orderByDesc('album_images.id')
                ->limit(20);
        }

        $images = $imagesQuery
            ->get()
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

        $counterStats = [
            [
                'label' => __('Years of Training Experience'),
                'value' => 20,
                'suffix' => '+',
                'icon' => 'o-calendar-date-range',
            ],
            [
                'label' => __('Students currently enrolled'),
                'value' => 3500,
                'suffix' => '+',
                'icon' => 'o-user-group',
            ],
            [
                'label' => __('Graduated students'),
                'value' => 12000,
                'suffix' => '+',
                'icon' => 'o-academic-cap',
            ],
            [
                'label' => __('Graduates find jobs.'),
                'value' => 96,
                'suffix' => '%',
                'icon' => 'o-briefcase',
            ],

        ];

        return [
            'slides' => $slides,
            'featuredPosts' => $featuredPosts,
            'latestPosts' => $latestPosts,
            'notificationPosts' => $notificationPosts,
            'images' => $images,
            'counterStats' => $counterStats,
            'configBanner' => $configBanner,
        ];
    }
};
?>

<div class="">
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Home page') }}
    </x-slot:title>
    {{--  end - title  --}}
    {{--    <x-carousel :slides="$slides"  interval="5000" class="custom-carousel h-65 lg:h-100 2xl:h-150 w-full aspect-[16/9] md:aspect-[3/1] overflow-hidden--}}
    {{--            bg-cover bg-center bg-no-repeat">--}}
{{--    @dd($configBanner->content_data['autoplay'])--}}
    <x-carousel
        :slides="$slides"
        :autoplay="$configBanner->content_data['autoplay'] ?? false"
        :interval="$configBanner->content_data['interval'] ?? 5000"
        class="h-[40vw] md:h-65 lg:h-91 2xl:h-110 rounded-none w-full [&_img]:w-full [&_img]:h-full [&_img]:object-fill"
    >
        @scope('content', $slide)
        <div
            @class([
                "absolute inset-0 z-[1] flex flex-col gap-2 px-20 py-12",
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

            <!-- Title 1 -->
            <h1 class="w-[60%] text-2xl lg:text-[64px]/[68px] font-bold text-white">{{ data_get($slide, 'title') }}</h1>
            <!-- Title 2 -->
            <h5 class="w-[60%] text-[16px] lg:text-[30px] font-bold text-white">{{ data_get($slide, 'description') }}</h5>


            <!-- Button-->
            @if(data_get($slide, 'urlText'))
                <div class="hidden md:block">
                    <x-button link="{{ data_get($slide, 'url') }}" icon-right="o-arrow-right"
                              class="btn btn-sm lg:btn-md max-w-40 bg-fita text-white border-transparent shadow-none hover:bg-fita2 my-3 hover:scale-105">{{ __(data_get($slide, 'urlText')) }}</x-button>
                </div>
            @endif
        </div>
        @endscope
    </x-carousel>
    <div class="mt-5">
{{--        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-4">--}}
{{--            {{__('Quick links')}}--}}
{{--        </h1>--}}
        <div class="container mx-auto"
             x-data="{
        menus: [
            { title: 'ST-Care Hỏi đáp', link: 'https://st-dse.vnua.edu.vn:6896', color: '#0961AA', btnText: 'XEM THÔNG TIN', img: 'assets/images/question-and-answer.png' },
            { title: 'Tư vấn chọn hướng chuyên sâu', link: 'https://st-dse.vnua.edu.vn:6879', color: '#F6A309', btnText: 'XEM THÔNG TIN', img: 'assets/images/health.png' },
            { title: 'Đăng ký TTNN và KLTN', link: 'https://st-dse.vnua.edu.vn:6875', color: '#066140', btnText: 'XEM THÔNG TIN', img: 'assets/images/register.png' },
            { title: 'Quản lý phòng lab', link: 'https://st-dse.vnua.edu.vn:6888', color: '#4E3636', btnText: 'CLICK HERE', img: 'assets/images/calendar.png' },
        ]
     }">

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

                <template x-for="(item, index) in menus" :key="index">
                    <a :href="item.link"
                       class="relative flex items-center justify-between h-20 px-4 overflow-hidden rounded-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl shadow-md group"
                       :style="`background: linear-gradient(135deg, ${item.color} 0%, ${item.color}CC 100%);`"
                       target="_blank"
                    >

                        <div class="z-10 text-white uppercase flex flex-col justify-center pb-1 mr-2">
                            <h2 class="text-[17px] font-semibold leading-tight drop-shadow-sm" x-text="item.title"></h2>
{{--                            <div class="mt-2 flex items-center text-[10px] font-bold tracking-wider opacity-90 group-hover:opacity-100">--}}
{{--                                <span x-text="item.btnText"></span>--}}
{{--                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1 transform group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">--}}
{{--                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />--}}
{{--                                </svg>--}}
{{--                            </div>--}}
                        </div>

                        <div class="relative z-10 w-16 h-16 object-fill p-1 shrink-0 backdrop-blur-sm group-hover:rotate-2 transition-transform duration-300">
                            <img :src="item.img" alt="Thumbnail" class="w-full h-full object-cover">
                        </div>

                        <div class="absolute inset-0 bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                </template>

            </div>
        </div>
    </div>
    <div class="container mx-auto px-4 lg:px-0">
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10">
            {{__('Faculty of Information Technology')}}
        </h1>
        <div class="flex gap-8 flex-col lg:flex-row mb-10 mt-6">

            <div class=" w-full lg:w-1/2 space-y-4">
                <div class="text-[17px]/[24px] text-justify space-y-2 leading-relaxed">
                    Khoa Công nghệ thông tin mới được thành lập từ 10/10/2005 theo QĐ số 839/QĐ – NNI của Hiệu trưởng.
                    Khoa hiện nay bao gồm 05 Bộ môn (Công nghệ phần mềm, Khoa học máy tính, Toán, Toán-Tin ứng dụng, Vật lý) và 01 Tổ Văn phòng, trong đó có một số bộ môn của Khoa đã có bề dày truyền thống như các Bộ môn Toán và Vật lý được thành lập từ ngày thành lập trường, và bộ môn CNPM và KHMT được phát triển từ Trung tâm Tin học thành lập từ đầu những năm 1980.
                </div>
                <ul class="space-y-4 mt-3">
                    <li class="flex items-center gap-3 text-[17px]/[24px] font-medium">
                        <div class="bg-fita2 rounded-full w-7 h-7 flex items-center justify-center">
                            <x-icon name="o-check" class="w-4 h-4 text-white"/>
                        </div>
                        Đào tạo cử nhân Công nghệ thông tin
                    </li>
                    <li class="flex items-center gap-3 text-[17px]/[24px] font-medium">
                        <div class="bg-fita2 rounded-full w-7 h-7 flex items-center justify-center">
                            <x-icon name="o-check" class="w-4 h-4 text-white"/>
                        </div>
                        Đào tạo cử nhân Công nghệ phần mềm
                    </li>
                    <li class="flex items-center gap-3 text-[17px]/[24px] font-medium">
                        <div class="bg-fita2 rounded-full w-7 h-7 flex items-center justify-center">
                            <x-icon name="o-check" class="w-4 h-4 text-white"/>
                        </div>
                        Đào tạo cử nhân Hệ thống thông tính
                    </li>
                    <li class="flex items-center gap-3 text-[17px]/[24px] font-medium">
                        <div class="bg-fita2 rounded-full w-7 h-7 flex items-center justify-center">
                            <x-icon name="o-check" class="w-4 h-4 text-white"/>
                        </div>
                        Đào tạo cử nhân Mạng máy tính
                    </li>
                    <li class="flex items-center gap-3 text-[17px]/[24px] font-medium">
                        <div class="bg-fita2 rounded-full w-7 h-7 flex items-center justify-center">
                            <x-icon name="o-check" class="w-4 h-4 text-white"/>
                        </div>
                        Đào tạo cử nhân Truyền thông
                    </li>
                    <li class="flex items-center gap-3 text-[17px]/[24px] font-medium">
                        <div class="bg-fita2 rounded-full w-7 h-7 flex items-center justify-center">
                            <x-icon name="o-check" class="w-4 h-4 text-white"/>
                        </div>
                        Đào tạo cử nhân Trí tuệ nhân tạo
                    </li>
                </ul>
            </div>
            <div class=" w-full lg:w-1/2">
                <img src="{{asset('assets/images/fita-info.jpg')}}" class="object-cover h-110 mx-auto rounded-lg" alt="">
            </div>
        </div>
    </div>
    <div >
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-4">
            {{__('News and events')}}
        </h1>
        <div class="relative flex flex-col lg:flex-row container px-4 lg:px-0 mx-auto gap-10">
            <div class="lg:w-[50%] w-full relative h-60 lg:h-140">
                @php
                    $leftHighlightPost = $tabSelected === 'tab-feature-post'
                        ? $featuredPosts->first()
                        : ($tabSelected === 'tab-new-post' ? $latestPosts->first(): $notificationPosts->first());

                    // Avoid disk I/O checks in view; let browser handle missing image fallback.
                    $leftHighlightImage = $leftHighlightPost?->thumbnail
                        ? Storage::url($leftHighlightPost->thumbnail)
                        : null;
                @endphp

                @if($leftHighlightPost)
                    <a
                        href="{{ $leftHighlightPost->client_url }}"
                        wire:navigate
                        class="group relative block h-full overflow-hidden border border-base-300 bg-slate-900 rounded-2xl"
                    >
                        @if($leftHighlightImage)
                            <img
                                src="{{ $leftHighlightImage }}"
                                alt="{{ $leftHighlightPost->getTranslation('title', app()->getLocale()) }}"
                                loading="eager"
                                fetchpriority="high"
                                decoding="async"
                                width="1280"
                                height="720"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                onerror="this.onerror=null;this.src='{{ asset('assets/images/post-7.jpg') }}'"
                            >
                        @else
                            <img
                                src="{{ asset('assets/images/post-7.jpg') }}"
                                alt="No image"
                                loading="eager"
                                fetchpriority="high"
                                decoding="async"
                                width="1280"
                                height="720"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            >
                        @endif

                        @if($leftHighlightPost->is_featured)
                            <div class="absolute top-3 left-3 z-10 inline-flex items-center gap-1 rounded-full bg-warning px-2.5 py-1 text-xs font-semibold text-white shadow">
                                <x-icon name="s-star" class="w-3 h-3" />
                                {{ __('Featured News') }}
                            </div>
                        @elseif($this->isNewPost($leftHighlightPost))
                            <div class="absolute top-3 left-3 z-10 inline-flex items-center gap-1 rounded-full bg-[#22c55e] px-2.5 py-1 text-xs font-semibold text-white shadow">
                                <span class="h-2 w-2 rounded-full bg-white"></span>
                                {{ __('New') }}
                            </div>
                        @endif

                        <div
                            class="absolute right-0 top-0 z-10 bg-black/45 px-3 py-2 text-center text-white backdrop-blur-sm">
                            <div class="text-[30px]/[34px] lg:text-[40px]/[44px] font-bold">
                                {{ $leftHighlightPost->published_at?->isoFormat('DD') }}
                            </div>
                            <div class="text-[18px]/[30px] lg:text-[24px]/[26px] font-bold mt-0 lg:mt-3">
                                {{ app()->getLocale() === 'vi'
                                    ? 'tháng ' . $leftHighlightPost->published_at?->isoFormat('M')
                                    : $leftHighlightPost->published_at?->isoFormat('MMMM') }}
                            </div>
                        </div>

                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>

                        <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
                            <h3 class="line-clamp-2 text-[18px]/[20px] lg:text-[20px]/[24px] font-bold">
                                {{ $leftHighlightPost->getTranslation('title', app()->getLocale()) }}
                            </h3>
                            <p class="mt-3 line-clamp-2 text-[16px]/[18px] lg:text-[18px]/[22px] text-white/90">
                                {{ $leftHighlightPost->getExcerptOrAuto(app()->getLocale(), 170) }}
                            </p>
                        </div>
                    </a>
                @else
                    <div
                        class="flex h-140 items-center justify-center rounded-xl border border-dashed border-base-300 bg-base-100 text-base-content/60">
                        {{ __('No posts available') }}
                    </div>
                @endif

                <div wire:loading.flex wire:target="tabSelected"
                     class="absolute inset-0 z-30 items-center justify-center bg-white/60 backdrop-blur-[1px]">
                    <x-loading class="text-primary loading-lg"/>
                </div>
            </div>

            <div class="w-full lg:w-[50%]">
                <x-tabs
                    wire:model.live="tabSelected"
                    active-class="text-fita! border-b-4 border-fita font-semibold"
                    label-class="font-semibold text-[20px] text-gray-700 px-4 pb-1 whitespace-nowrap font-barlow"
                    label-div-class="border-b-[length:var(--border)] border-b-base-content/10 flex overflow-x-auto"
                >
                    <x-tab name="tab-feature-post" icon="">
                        <x-slot:label>
                            <span class="relative inline-flex items-center h-6">
                                {{ __('Featured News') }}
                                @if($tabSelected !== 'tab-feature-post')
{{--                                    <span class="absolute -top-0.5 -right-4 flex h-2.5 w-2.5">--}}
{{--                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>--}}
{{--                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>--}}
{{--                                </span>--}}
                                @endif
                        </span>
                        </x-slot:label>
                        <div class="flex flex-col gap-4">
                            @forelse($featuredPosts->skip(1)->take(3) as $post)
                                <div
                                    class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                                                 loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}"
                                                 class="w-full h-full object-cover" alt="No image" loading="lazy"
                                                 decoding="async">
                                        @endif
                                        @if($leftHighlightPost->is_featured)
                                            <div class="absolute top-1 left-1 inline-flex items-center gap-1 rounded-full bg-warning px-1.5 py-0.5 text-[10px] font-semibold text-white shadow">
                                                <x-icon name="s-star" class="w-3 h-3" />
                                                {{ __('Featured News') }}
                                            </div>

                                        @elseif($this->isNewPost($post) && !$post->is_featured)
                                            <div class="absolute top-1 left-1 inline-flex items-center gap-1 rounded-full bg-[#22c55e] px-1.5 py-0.5 text-[10px] font-semibold text-white shadow">
                                                <span class="h-1 w-1 rounded-full bg-white"></span>
                                                {{ __('New') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate
                                           class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">
                                            {{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}
                                        </p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">
                                            {{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                @if($featuredPosts->isEmpty())
                                 <p class="text-gray-500">{{ __('No featured posts found.') }}</p>
                                @endif
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index',['danh-muc' => 'tin-tuc']) }}" label="{{__('Read more')}}"
                                  icon-right="o-arrow-right"
                                  class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-4">
                        </x-button>
                    </x-tab>
                    <x-tab name="tab-new-post">
                        <x-slot:label>
                            <span class="relative inline-flex items-center h-6">
                                {{ __('News & Events') }}

{{--                                <span class="absolute -top-0.5 -right-7 bg-amber-500 text-white text-[12px] font-bold px-1.5 py-1 flex items-center justify-center rounded-full shadow-sm leading-none">--}}
{{--                                    Cập nhật--}}
{{--                                </span>--}}
                                @if($tabSelected !== 'tab-new-post')
{{--                                <span class="absolute -top-0.5 -right-4 flex h-2.5 w-2.5">--}}
{{--                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>--}}
{{--                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>--}}
{{--                                </span>--}}
                                @endif
                        </span>
                        </x-slot:label>
                        <div class="flex flex-col gap-4">
                            @forelse($latestPosts->skip(1)->take(3) as $post)
                                <div
                                    class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                                                 loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}"
                                                 class="w-full h-full object-cover" alt="No image" loading="lazy"
                                                 decoding="async">
                                        @endif

                                        @if($this->isNewPost($post) && !$post->is_featured)
                                            <div class="absolute top-1 left-1 inline-flex items-center gap-1 rounded-full bg-[#22c55e] px-1.5 py-0.5 text-[10px] font-semibold text-white shadow">
                                                <span class="h-1 w-1 rounded-full bg-white"></span>
                                                {{ __('New') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate
                                           class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">
                                            {{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}
                                        </p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">
                                            {{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                @if($latestPosts->isEmpty())
                                    <p class="text-gray-500">{{ __('No latest posts found.') }}</p>
                                @endif
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index',['danh-muc' => 'tin-tuc']) }}" label="{{__('Read more')}}"
                                  icon-right="o-arrow-right"
                                  class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-4">
                        </x-button>
                    </x-tab>
                    <x-tab name="tab-notification-post">
                        <x-slot:label>
                            <span class="relative inline-flex items-center h-6">
                                {{ __('Notification') }}
                                @if($tabSelected !== 'tab-notification-post')
{{--                                    <span class="absolute -top-0.5 -right-4 flex h-2.5 w-2.5">--}}
{{--                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>--}}
{{--                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>--}}
{{--                                </span>--}}
                                @endif
                        </span>
                        </x-slot:label>
                        <div class="flex flex-col gap-4">
                            @forelse($notificationPosts->skip(1)->take(3) as $post)
                                <div
                                    class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                                                 loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}"
                                                 class="w-full h-full object-cover" alt="No image" loading="lazy"
                                                 decoding="async">
                                        @endif

                                        @if($this->isNewPost($post) && !$post->is_featured)
                                            <div class="absolute top-1 left-1 inline-flex items-center gap-1 rounded-full bg-[#22c55e] px-1.5 py-0.5 text-[10px] font-semibold text-white shadow">
                                                <span class="h-1 w-1 rounded-full bg-white"></span>
                                                {{ __('New') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate
                                           class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">
                                            {{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}
                                        </p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">
                                            {{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                @if($notificationPosts->isEmpty())
                                    <p class="text-gray-500">{{ __('No announcement posts found.') }}</p>
                                @endif
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index',['danh-muc' => 'thong-bao']) }}" label="{{__('Read more')}}"
                                  icon-right="o-arrow-right"
                                  class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-4">
                        </x-button>
                    </x-tab>
                </x-tabs>
{{--                <x-button link="{{ route('client.posts.index',['danh-muc' => 'tin-tuc']) }}" label="{{__('Read more')}}"--}}
{{--                          icon-right="o-arrow-right"--}}
{{--                          class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-105">--}}
{{--                </x-button>--}}
            </div>
        </div>
    </div>
    {{-- Why Choose Us Section --}}
    <section class="py-12 lg:py-16 bg-linear-to-b from-blue-50 to-blue-100">
        <div class="container mx-auto px-4 lg:px-0">
            <div class="text-center mb-12">
                {{--                <p class="text-fita font-semibold text-[14px] lg:text-[16px] uppercase tracking-wide mb-2">{{ __('Distinguishing features') }}</p>--}}
                {{--                <h2 class="text-[28px] lg:text-[36px] font-bold text-fita  font-barlow uppercase">{{ __('Why choose us?') }}</h2>--}}
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8">
                {{-- Card 1: Đội ngũ giảng viên --}}
                <div
                    class="why-card group relative bg-white/80 backdrop-blur-sm rounded-2xl p-6 lg:p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border"
                    x-data="{ revealed: false }"
                    x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
                    style="--why-main: var(--why-blue-main); --why-soft: var(--why-blue-soft); --why-border: var(--why-blue-border); --why-badge-bg: var(--why-blue-badge-bg); --why-badge-text: var(--why-blue-badge-text); --why-title-hover: var(--why-blue-title); animation-delay: 0ms"
                >
                    <div class="absolute -top-8 left-6">
                        <div class="relative">
                            <div
                                class="why-icon-glow absolute inset-0 blur opacity-75 group-hover:opacity-100 transition duration-300 rounded-xl"></div>
                            <div class="why-icon-bg relative rounded-xl p-3">
                                <x-icon name="o-academic-cap" class="w-6 h-6 text-white"/>
                            </div>
                        </div>
                    </div>
                    <div
                        class="why-badge absolute top-4 right-4 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">
                        01
                    </div>

                    <div class="mt-6">
                        <h3 class="why-title text-[18px] lg:text-[20px] font-bold text-slate-900 mb-3 transition-colors">{{ __('Faculty of lecturers') }}</h3>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed">{{ __('Experienced instructors and industry experts with strong practical backgrounds, dedicated to supporting learners throughout their journey.') }}</p>
                        <div class="why-accent mt-4 h-1 w-12 group-hover:w-full transition-all duration-300"></div>
                    </div>
                </div>

                {{-- Card 2: Cơ sở vật chất --}}
                <div
                    class="why-card group relative bg-white/80 backdrop-blur-sm rounded-2xl p-6 lg:p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border"
                    x-data="{ revealed: false }"
                    x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
                    style="--why-main: var(--why-yellow-main); --why-soft: var(--why-yellow-soft); --why-border: var(--why-yellow-border); --why-badge-bg: var(--why-yellow-badge-bg); --why-badge-text: var(--why-yellow-badge-text); --why-title-hover: var(--why-yellow-title); animation-delay: 80ms"
                >
                    <div class="absolute -top-8 left-6">
                        <div class="relative">
                            <div
                                class="why-icon-glow absolute inset-0 blur opacity-75 group-hover:opacity-100 transition duration-300 rounded-xl"></div>
                            <div class="why-icon-bg relative rounded-xl p-3">
                                <x-icon name="o-building-office" class="w-6 h-6 text-white"/>
                            </div>
                        </div>
                    </div>
                    <div
                        class="why-badge absolute top-4 right-4 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">
                        02
                    </div>

                    <div class="mt-6">
                        <h3 class="why-title text-[18px] lg:text-[20px] font-bold text-slate-900 mb-3 transition-colors">{{ __('Quality facilities') }}</h3>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed">{{ __('Modern lab facilities with high-performance equipment, regularly upgraded to meet learning and practice needs.') }}</p>
                        <div class="why-accent mt-4 h-1 w-12 group-hover:w-full transition-all duration-300"></div>
                    </div>
                </div>

                {{-- Card 3: Chương trình đào tạo --}}
                <div
                    class="why-card group relative bg-white/80 backdrop-blur-sm rounded-2xl p-6 lg:p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border"
                    x-data="{ revealed: false }"
                    x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
                    style="--why-main: var(--why-green-main); --why-soft: var(--why-green-soft); --why-border: var(--why-green-border); --why-badge-bg: var(--why-green-badge-bg); --why-badge-text: var(--why-green-badge-text); --why-title-hover: var(--why-green-title); animation-delay: 160ms"
                >
                    <div class="absolute -top-8 left-6">
                        <div class="relative">
                            <div
                                class="why-icon-glow absolute inset-0 blur opacity-75 group-hover:opacity-100 transition duration-300 rounded-xl"></div>
                            <div class="why-icon-bg relative rounded-xl p-3">
                                <x-icon name="o-book-open" class="w-6 h-6 text-white"/>
                            </div>
                        </div>
                    </div>
                    <div
                        class="why-badge absolute top-4 right-4 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">
                        03
                    </div>

                    <div class="mt-6">
                        <h3 class="why-title text-[18px] lg:text-[20px] font-bold text-slate-900 mb-3 transition-colors">{{ __('Training Programs') }}</h3>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed">{{ __('Curriculum updated to international standards, aligned with the latest technology trends to prepare learners for the job market.') }}</p>
                        <div class="why-accent mt-4 h-1 w-12 group-hover:w-full transition-all duration-300"></div>
                    </div>
                </div>

                {{-- Card 4: Phương pháp giảng dạy --}}
                <div
                    class="why-card group relative bg-white/80 backdrop-blur-sm rounded-2xl p-6 lg:p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border"
                    x-data="{ revealed: false }"
                    x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
                    style="--why-main: var(--why-brown-main); --why-soft: var(--why-brown-soft); --why-border: var(--why-brown-border); --why-badge-bg: var(--why-brown-badge-bg); --why-badge-text: var(--why-brown-badge-text); --why-title-hover: var(--why-brown-title); animation-delay: 240ms"
                >
                    <div class="absolute -top-8 left-6">
                        <div class="relative">
                            <div
                                class="why-icon-glow absolute inset-0 blur opacity-75 group-hover:opacity-100 transition duration-300 rounded-xl"></div>
                            <div class="why-icon-bg relative rounded-xl p-3">
                                <x-icon name="o-light-bulb" class="w-6 h-6 text-white"/>
                            </div>
                        </div>
                    </div>
                    <div
                        class="why-badge absolute top-4 right-4 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">
                        04
                    </div>

                    <div class="mt-6">
                        <h3 class="why-title text-[18px] lg:text-[20px] font-bold text-slate-900 mb-3 transition-colors">{{ __('Teaching method') }}</h3>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed">{{ __('Practice-oriented approach through real-world projects, enabling learners to gain experience and meet IT industry demands.') }}</p>
                        <div class="why-accent mt-4 h-1 w-12 group-hover:w-full transition-all duration-300"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-5">
            {{__('Training Pathways & Programs')}}
        </h1>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 container mx-auto px-4 lg:px-0">
            <div class="flex flex-col relative rounded-2xl overflow-hidden border border-slate-300 group hover:-translate-y-1.5 hover:shadow-lg transition-all duration-300"
               x-data="{ revealed: false }"
               x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
            >
                <img src="{{asset('assets/images/nganh-cntt.jpg')}}" alt=""
                     class="w-full object-cover transition-transform duration-500 h-50"
                     loading="lazy" decoding="async">
                {{--                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>--}}
                <div class="flex flex-col justify-around flex-1">
                    <div class="px-6 py-4">
                        <a
                            href="https://st-dse.vnua.edu.vn:6889/dai-hoc/cong-nghe-thong-tin" wire:navigate
                            class="why-title text-[18px] lg:text-[22px] font-bold text-slate-900 mb-2 transition-colors uppercase line-clamp-2 group-hover:text-fita">
                            Công nghệ thông tin
                        </a>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed line-clamp-4">
                            Chương trình đào tạo ngành Công nghệ thông tin (CNTT) nhằm đào tạo ra cử nhân CNTT có phẩm chất chính trị vững vàng, có đạo đức nghề nghiệp, có trách nhiệm cao và sức khỏe tốt; có kiến thức chuyên sâu và thành thạo kỹ năng nghề nghiệp; có năng lực sáng tạo, tự học, tự nghiên cứu nhằm không ngừng nâng cao trình độ; có tinh thần lập nghiệp,  hội nhập quốc tế; đóng góp nguồn nhân lực chất lượng cao trong lĩnh vực CNTT và lĩnh vực nông nghiệp hiện đại.
                        </p>
                    </div>
                    <div class="px-6 pb-4 pt-2 flex gap-4 justify-around flex-wrap">
                        <x-button label="Chi tiết chương trình"
                                  class="btn-outline text-fita font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-4xl"
                                  link="https://st-dse.vnua.edu.vn:6889/dai-hoc/cong-nghe-thong-tin"
                        >
                        </x-button>
                        <x-button label="Xem lộ trình" icon="o-book-open"
                                  class="bg-fita text-white font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-4xl"
                                    link="https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=cong-nghe-thong-tin"
                        >
                        </x-button>
                    </div>
                </div>
            </div>
            <div class="flex flex-col relative rounded-2xl overflow-hidden border border-slate-300 group hover:-translate-y-1.5 hover:shadow-lg transition-all duration-300"
               x-data="{ revealed: false }"
               x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
            >
                <img src="{{asset('assets/images/nganh-mmt.jpg')}}" alt=""
                     class="w-full object-cover transition-transform duration-500 h-50"
                     loading="lazy" decoding="async">
                {{--                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>--}}
                <div class="flex flex-col justify-around flex-1">
                    <div class="px-6 py-4">
                        <a href="https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-mang-may-tinh-va-truyen-thong-du-lieu" wire:navigate
                            class="why-title text-[18px] lg:text-[22px] font-bold text-slate-900 mb-2 transition-colors uppercase line-clamp-2 group-hover:text-fita">
                           Mạng máy tính và truyền thông dữ liệu
                        </a>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed line-clamp-4">
                            Chương trình đào tạo ngành mạng máy tính và truyền thông dữ liệu (MMT&TTDL) nhằm đào tạo cử nhân có phẩm chất chính trị vững vàng, có sức khỏe tốt; có kiến thức và kỹ năng vững vàng về lĩnh vực máy tính và công nghệ thông tin (CNTT); có khả năng tự học, tự nghiên cứu nhằm đáp ứng được yêu cầu công việc tại các cơ quan, các công ty liên quan đến lĩnh vực máy tính và CNTT.
                        </p>
                    </div>
                    <div class="px-6 pb-4 pt-2 flex gap-4 justify-around flex-wrap">
                        <x-button label="Chi tiết chương trình"
                                  class="btn-outline text-fita font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-4xl"
                            link="https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-mang-may-tinh-va-truyen-thong-du-lieu"
                        >
                        </x-button>
                        <x-button label="Xem lộ trình" icon="o-book-open"
                                  class="bg-fita text-white font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-4xl"
                         link="https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=mang-may-tinh-truyen-thong-du-lieu">
                        </x-button>
                    </div>
                </div>
            </div>
            <div
               class="flex flex-col relative rounded-2xl overflow-hidden border border-slate-300 group hover:-translate-y-1.5 hover:shadow-lg transition-all duration-300"
               x-data="{ revealed: false }"
               x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
            >
                <img src="{{asset('assets/images/nganh-khdlttnt.jpg')}}" alt=""
                     class="w-full object-cover transition-transform duration-500 h-50"
                     loading="lazy" decoding="async">
                {{--                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>--}}
                <div class="flex flex-col justify-around flex-1">
                    <div class="px-6 py-4">
                        <a  href="https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-khoa-hoc-du-lieu-va-tri-tue-nhan-tao" wire:navigate
                            class="why-title text-[18px] lg:text-[22px] font-bold text-slate-900 mb-2 transition-colors uppercase line-clamp-2 group-hover:text-fita">
                            Khoa học dữ liệu và Trí tuệ nhân tạo
                        </a>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed line-clamp-4">
                            Chương trình đào tạo ngành Khoa học dữ liệu và Trí tuệ nhân tạo (KHDL&TTNT) nhằm đào tạo ra cử nhân có phẩm chất chính trị vững vàng, có đạo đức nghề nghiệp, có trách nhiệm cao và sức khỏe tốt; có kiến thức chuyên sâu và thành thạo kỹ năng nghề nghiệp; có năng lực sáng tạo, tự học, tự nghiên cứu nhằm không ngừng nâng cao trình độ; có tinh thần lập nghiệp,  hội nhập quốc tế; đóng góp nguồn nhân lực chất lượng cao trong lĩnh vực KHDL&TTNT và lĩnh vực nông nghiệp hiện đại.
                        </p>
                    </div>
                    <div class="px-6 pb-4 pt-2 flex gap-4 justify-around flex-wrap">
                        <x-button label="Chi tiết chương trình"
                                  class="btn-outline text-fita font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-4xl"
                            link="https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-khoa-hoc-du-lieu-va-tri-tue-nhan-tao"
                        >
                        </x-button>
                        <x-button label="Xem lộ trình" icon="o-book-open"
                                  class="bg-fita text-white font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-4xl"
                            link="https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=khoa-hoc-du-lieu-va-tri-tue-nhan-tao"
                        >
                        </x-button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="mt-8 lg:mt-10 bg-slate-200/40 pt-15 ">
        <div class="mx-auto container px-4 lg:px-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-15">
                @foreach($counterStats as $stat)
                    <div
                        data-reveal-item
                        class="relative pt-12 pb-6 px-4 text-center"
                        x-data="{
                        value: 0,
                        target: {{ (int) $stat['value'] }},
                        suffix: '{{ $stat['suffix'] }}',
                        started: false,
                        format(v) { return new Intl.NumberFormat('vi-VN').format(v); },
                        start() {
                            if (this.started) return;
                            this.started = true;
                            const duration = 1200;
                            const startTime = performance.now();
                            const tick = (now) => {
                                const progress = Math.min((now - startTime) / duration, 1);
                                this.value = Math.floor(this.target * progress);
                                if (progress < 1) requestAnimationFrame(tick);
                            };
                            requestAnimationFrame(tick);
                        }
                    }"
                        x-init="
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach((entry) => {
                                if (!entry.isIntersecting) return;
                                start();
                                observer.disconnect();
                            });
                        }, { threshold: 0.4 });
                        observer.observe($el);
                    "
                    >
                        <div
                            class="absolute -top-10 left-1/2 -translate-x-1/2 h-20 w-20 rounded-full bg-[#DDE8F1] flex items-center justify-center">
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

    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-4">
            {{__('OUR PARTNERS')}}
        </h1>
        <livewire:client.list-of-partners/>
    </div>

    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mb-2">
            {{--            <svg fill="#0071BD" width="38px" height="38px" viewBox="0 -32 576 576" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M480 416v16c0 26.51-21.49 48-48 48H48c-26.51 0-48-21.49-48-48V176c0-26.51 21.49-48 48-48h16v48H54a6 6 0 0 0-6 6v244a6 6 0 0 0 6 6h372a6 6 0 0 0 6-6v-10h48zm42-336H150a6 6 0 0 0-6 6v244a6 6 0 0 0 6 6h372a6 6 0 0 0 6-6V86a6 6 0 0 0-6-6zm6-48c26.51 0 48 21.49 48 48v256c0 26.51-21.49 48-48 48H144c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h384zM264 144c0 22.091-17.909 40-40 40s-40-17.909-40-40 17.909-40 40-40 40 17.909 40 40zm-72 96l39.515-39.515c4.686-4.686 12.284-4.686 16.971 0L288 240l103.515-103.515c4.686-4.686 12.284-4.686 16.971 0L480 208v80H192v-48z"></path></g></svg>--}}
            {{__('Photo library')}}
        </h1>
        <livewire:client.image-gallery :images="$images" class="h-40 rounded-box"/>
    </div>

    <section class="bg-blue-100/40 pb-16 pt-2 font-sans" x-data="{
        activeSlide: 1,
        slides: [
            {
                id: 1,
                name: 'Nguyễn Ngọc Trường Khang',
                role: 'Cựu sinh viên Khoa Công nghệ thông tin',
                content: 'Học tại Khoa Công nghệ thông tin là hành trình đáng nhớ. Học tập, làm việc nhóm, tham gia dự án thực tế và kết nối với bạn bè – tất cả đều giúp tôi trưởng thành hơn',
                avatar: 'assets/images/avatar-dep-9.jpg'
            },
            {
                id: 2,
                name: 'Trần Thị Mỹ Linh',
                role: 'Sinh viên ngành Công nghệ thông tin',
                content: 'Môi trường năng động và các thầy cô cực kỳ tâm huyết đã giúp mình khai phá được khả năng sáng tạo của bản thân.',
                avatar: 'assets/images/avatar-dep-10.jpg'
            },
            {
                id: 3,
                name: 'Nguyễn Thanh Xuân',
                role: ' Cựu sinh viên ngành Công nghệ thông tin',
                content: 'Khoa Công nghệ thông tin không chỉ dạy mình kiến thức chuyên môn mà còn giúp mình phát triển kỹ năng mềm và tư duy phản biện.',
                avatar: 'assets/images/avatar-dep-10.jpg'
            }
        ],
        next() { this.activeSlide = this.activeSlide === this.slides.length ? 1 : this.activeSlide + 1 },
        prev() { this.activeSlide = this.activeSlide === 1 ? this.slides.length : this.activeSlide - 1 }
    }">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-6">
                <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10">
                    Mọi người nói gì về Khoa
                </h1>
            </div>

            <div class="relative flex items-center justify-center">

                <button @click="prev()" class="absolute left-0 md:-left-4 z-10 w-10 h-10 bg-white rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-fita transition">
                    <x-icon name="s-chevron-left"></x-icon>
                </button>

                <div class="bg-white rounded-[40px] shadow-sm p-8 md:p-12 max-w-4xl w-full mx-8 relative min-h-[250px]">
                    <template x-for="slide in slides" :key="slide.id">
                        <div x-show="activeSlide === slide.id"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 transform translate-x-4"
                             x-transition:enter-end="opacity-100 transform translate-x-0"
                             class="flex flex-col md:flex-row items-center gap-8">

                            <div class="relative flex-shrink-0">
                                <div class="w-32 h-32 md:w-40 md:h-40 rounded-full overflow-hidden border-4 border-gray-100 shadow-inner">
                                    <img :src="slide.avatar" alt="Avatar" class="w-full h-full object-cover">
                                </div>
                            </div>

                            <div class="flex-1 text-center md:text-left relative">
                                <div class="hidden md:block absolute top-0 -right-2  text-6xl italic font-serif">
                                    <svg height="40px" width="40px" version="1.1" id="_x32_" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512.00 512.00" xml:space="preserve" fill="#000000" stroke="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <style type="text/css"> .st0{fill:#0c83d8;} </style> <g> <path class="st0" d="M148.57,63.619H72.162C32.31,63.619,0,95.929,0,135.781v76.408c0,39.852,32.31,72.161,72.162,72.161h7.559 c6.338,0,12.275,3.128,15.87,8.362c3.579,5.234,4.365,11.898,2.074,17.811L54.568,422.208c-2.291,5.92-1.505,12.584,2.074,17.81 c3.595,5.234,9.532,8.362,15.87,8.362h50.738c7.157,0,13.73-3.981,17.041-10.318l61.257-117.03 c12.609-24.09,19.198-50.881,19.198-78.072v-107.18C220.748,95.929,188.422,63.619,148.57,63.619z"></path> <path class="st0" d="M439.84,63.619h-76.41c-39.852,0-72.16,32.31-72.16,72.162v76.408c0,39.852,32.309,72.161,72.16,72.161h7.543 c6.338,0,12.291,3.128,15.87,8.362c3.596,5.234,4.365,11.898,2.091,17.811l-43.113,111.686c-2.291,5.92-1.505,12.584,2.09,17.81 c3.579,5.234,9.516,8.362,15.871,8.362h50.722c7.157,0,13.73-3.981,17.058-10.318l61.24-117.03 C505.411,296.942,512,270.152,512,242.96v-107.18C512,95.929,479.691,63.619,439.84,63.619z"></path> </g> </g></svg>
                                </div>

                                <h4 class="text-xl font-bold text-black mb-1" x-text="slide.name"></h4>
                                <p class="text-gray-600 italic mb-6 text-sm md:text-base" x-text="slide.role"></p>
                                <p class="text-gray-700 leading-relaxed text-base md:text-lg" x-text="slide.content"></p>
                            </div>
                        </div>
                    </template>
                </div>

                <button @click="next()" class="absolute right-0 md:-right-4 z-10 w-10 h-10 bg-white rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-fita transition">
                    <x-icon name="s-chevron-right"></x-icon>
                </button>
            </div>

        </div>

        <div class="flex justify-center mt-8 gap-2">
            <template x-for="slide in slides" :key="slide.id">
                <button @click="activeSlide = slide.id"
                        class="h-1.5 transition-all duration-300 rounded-full"
                        :class="activeSlide === slide.id ? 'w-8 bg-fita2' : 'w-8 bg-blue-300'"></button>
            </template>
        </div>
    </section>
</div>
