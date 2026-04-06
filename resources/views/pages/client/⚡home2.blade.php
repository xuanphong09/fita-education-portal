<?php

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

    public $tabSelected='tab-feature-post';
    public array $slides = [];
    public  $slidePosts = [
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

        $baseQuery = Post::query()
            ->with(['categories', 'user'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');

        $featuredPosts = (clone $baseQuery)
            ->where('is_featured', true)
            ->limit($locale === 'en' ? 20 : 4)
            ->get()
            ->filter(fn (Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(4)
            ->values();

        $latestPosts = (clone $baseQuery)
            ->when($featuredPosts->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $featuredPosts->pluck('id')))
            ->limit($locale === 'en' ? 24 : 4)
            ->get()
            ->filter(fn (Post $post) => $this->isVisibleInLocale($post, $locale))
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
                ->whereHas('albums', fn ($query) => $query->where('albums.id', $featuredAlbum->id))
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
            ->filter(fn (AlbumImage $image) => filled($image->image_path) && Storage::disk('public')->exists($image->image_path))
            ->map(fn (AlbumImage $image) => [
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
                ->filter(fn (AlbumImage $image) => filled($image->image_path) && Storage::disk('public')->exists($image->image_path))
                ->map(fn (AlbumImage $image) => [
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
            'images' => $images,
            'counterStats' => $counterStats,
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
    <x-carousel
        :slides="$slides"
        interval="5000"
        class="h-auto rounded-none w-full bg-cover bg-center bg-no-repeat overflow-hidden aspect-2/1 md:aspect-5/2 lg:aspect-7/2 custom-carousel"
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
                <x-button link="{{ data_get($slide, 'url') }}" icon-right="o-arrow-right" class="btn btn-sm lg:btn-md max-w-40 bg-fita text-white border-transparent shadow-none hover:bg-fita2 my-3 hover:scale-105">{{ __(data_get($slide, 'urlText')) }}</x-button>
            @endif
        </div>
        @endscope
    </x-carousel>

    {{-- Why Choose Us Section --}}
    <section class="py-12 lg:py-16 bg-linear-to-b from-blue-50 to-blue-100">
        <div class="w-[90%] lg:w-330 mx-auto">
            <div class="text-center mb-12">
{{--                <p class="text-fita font-semibold text-[14px] lg:text-[16px] uppercase tracking-wide mb-2">{{ __('Distinguishing features') }}</p>--}}
                <h2 class="text-[28px] lg:text-[36px] font-bold text-fita  font-barlow uppercase">{{ __('Why choose us?') }}</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8">
                {{-- Card 1: Đội ngũ giảng viên --}}
                <div
                    class="group relative bg-white/80 backdrop-blur-sm rounded-2xl p-6 lg:p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-blue-200/50"
                    x-data="{ revealed: false }"
                    x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
                    style="animation-delay: 0ms"
                >
                    <div class="absolute -top-8 left-6">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-blue-400 to-blue-500 blur opacity-75 group-hover:opacity-100 transition duration-300 rounded-xl"></div>
                            <div class="relative bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-3">
                                <x-icon name="o-academic-cap" class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-4 right-4 bg-blue-100 text-fita rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">01</div>

                    <div class="mt-6">
                        <h3 class="text-[18px] lg:text-[20px] font-bold text-slate-900 mb-3 group-hover:text-fita transition-colors">{{ __('Faculty of lecturers') }}</h3>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed">{{ __('Experienced instructors and industry experts with strong practical backgrounds, dedicated to supporting learners throughout their journey.') }}</p>
                        <div class="mt-4 h-1 w-12 bg-gradient-to-r from-blue-500 to-blue-600 group-hover:w-full transition-all duration-300"></div>
                    </div>
                </div>

                {{-- Card 2: Cơ sở vật chất --}}
                <div
                    class="group relative bg-white/80 backdrop-blur-sm rounded-2xl p-6 lg:p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-green-200/50"
                    x-data="{ revealed: false }"
                    x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
                    style="animation-delay: 80ms"
                >
                    <div class="absolute -top-8 left-6">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-green-400 to-green-500 blur opacity-75 group-hover:opacity-100 transition duration-300 rounded-xl"></div>
                            <div class="relative bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-3">
                                <x-icon name="o-building-office" class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-4 right-4 bg-green-100 text-green-600 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">02</div>

                    <div class="mt-6">
                        <h3 class="text-[18px] lg:text-[20px] font-bold text-slate-900 mb-3 group-hover:text-green-600 transition-colors">{{ __('Quality facilities') }}</h3>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed">{{ __('Modern lab facilities with high-performance equipment, regularly upgraded to meet learning and practice needs.') }}</p>
                        <div class="mt-4 h-1 w-12 bg-gradient-to-r from-green-500 to-green-600 group-hover:w-full transition-all duration-300"></div>
                    </div>
                </div>

                {{-- Card 3: Chương trình đào tạo --}}
                <div
                    class="group relative bg-white/80 backdrop-blur-sm rounded-2xl p-6 lg:p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-amber-200/50"
                    x-data="{ revealed: false }"
                    x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
                    style="animation-delay: 160ms"
                >
                    <div class="absolute -top-8 left-6">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-amber-400 to-amber-500 blur opacity-75 group-hover:opacity-100 transition duration-300 rounded-xl"></div>
                            <div class="relative bg-gradient-to-r from-amber-500 to-amber-600 rounded-xl p-3">
                                <x-icon name="o-book-open" class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-4 right-4 bg-amber-100 text-amber-600 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">03</div>

                    <div class="mt-6">
                        <h3 class="text-[18px] lg:text-[20px] font-bold text-slate-900 mb-3 group-hover:text-amber-600 transition-colors">{{ __('Training Programs') }}</h3>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed">{{ __('Curriculum updated to international standards, aligned with the latest technology trends to prepare learners for the job market.') }}</p>
                        <div class="mt-4 h-1 w-12 bg-gradient-to-r from-amber-500 to-amber-600 group-hover:w-full transition-all duration-300"></div>
                    </div>
                </div>

                {{-- Card 4: Phương pháp giảng dạy --}}
                <div
                    class="group relative bg-white/80 backdrop-blur-sm rounded-2xl p-6 lg:p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-purple-200/50"
                    x-data="{ revealed: false }"
                    x-intersect="
                        if (!revealed) {
                            revealed = true;
                            $el.classList.add('animate-fade-in-up');
                        }
                    "
                    style="animation-delay: 240ms"
                >
                    <div class="absolute -top-8 left-6">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-purple-400 to-purple-500 blur opacity-75 group-hover:opacity-100 transition duration-300 rounded-xl"></div>
                            <div class="relative bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-3">
                                <x-icon name="o-light-bulb" class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-4 right-4 bg-purple-100 text-purple-600 rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">04</div>

                    <div class="mt-6">
                        <h3 class="text-[18px] lg:text-[20px] font-bold text-slate-900 mb-3 group-hover:text-purple-600 transition-colors">{{ __('Teaching method') }}</h3>
                        <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed">{{ __('Practice-oriented approach through real-world projects, enabling learners to gain experience and meet IT industry demands.') }}</p>
                        <div class="mt-4 h-1 w-12 bg-gradient-to-r from-purple-500 to-purple-600 group-hover:w-full transition-all duration-300"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-4">
            {{__('News and events')}}
        </h1>
        <div class="relative flex flex-col lg:flex-row w-[90%] lg:w-330 mx-auto gap-10">
            <div class="lg:w-[50%] w-full relative h-60 lg:h-140">
                @php
                    $leftHighlightPost = $tabSelected === 'tab-feature-post'
                        ? $featuredPosts->first()
                        : $latestPosts->first();

                    // Avoid disk I/O checks in view; let browser handle missing image fallback.
                    $leftHighlightImage = $leftHighlightPost?->thumbnail
                        ? Storage::url($leftHighlightPost->thumbnail)
                        : null;
                @endphp

                @if($leftHighlightPost)
                    <a
                        href="{{ route('client.posts.show', $leftHighlightPost->slug) }}"
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
                                onerror="this.onerror=null;this.src='{{ asset('assets/images/noti-news.png') }}'"
                            >
                        @else
                            <img
                                src="{{ asset('assets/images/noti-news.png') }}"
                                alt="No image"
                                loading="eager"
                                fetchpriority="high"
                                decoding="async"
                                width="1280"
                                height="720"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            >
                        @endif

                        <div class="absolute right-0 top-0 z-10 bg-black/45 px-3 py-2 text-center text-white backdrop-blur-sm">
                            <div class="text-[30px]/[34px] lg:text-[40px]/[44px] font-bold">
                                {{ $leftHighlightPost->published_at?->isoFormat('DD') }}
                            </div>
                            <div class="text-[18px]/[30px] lg:text-[24px]/[26px] font-bold mt-0 lg:mt-3">
                                {{ app()->getLocale() === 'vi'
                                    ? 'tháng ' . $leftHighlightPost->published_at?->isoFormat('M')
                                    : $leftHighlightPost->published_at?->isoFormat('MMMM') }}
                            </div>
                        </div>

                        <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-black/15 to-transparent"></div>

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
                    <div class="flex h-140 items-center justify-center rounded-xl border border-dashed border-base-300 bg-base-100 text-base-content/60">
                        {{ __('No posts available') }}
                    </div>
                @endif

                <div wire:loading.flex wire:target="tabSelected" class="absolute inset-0 z-30 items-center justify-center bg-white/60 backdrop-blur-[1px]">
                    <x-loading class="text-primary loading-lg" />
                </div>
            </div>

            <div class="w-full lg:w-[50%]">
                <x-tabs
                    wire:model.live="tabSelected"
                    active-class="text-fita! border-b-4 border-fita font-semibold"
                    label-class="font-semibold text-[20px] text-gray-700 px-4 pb-1 whitespace-nowrap font-barlow"
                    label-div-class="border-b-[length:var(--border)] border-b-base-content/10 flex overflow-x-auto"
                >
                    <x-tab name="tab-feature-post" label="{{__('Featured News')}}" icon="">
                        <div class="flex flex-col gap-4">
                            @forelse($featuredPosts->skip(1)->take(3) as $post)
                                <div class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover" alt="{{ $post->getTranslation('title', app()->getLocale()) }}" loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/noti-news.png') }}" class="w-full h-full object-cover" alt="No image" loading="lazy" decoding="async">
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ route('client.posts.show', $post->slug) }}" wire:navigate class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:underline">
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
                                <p class="text-gray-500">{{ __('No featured posts') }}</p>
                            @endforelse
                        </div>
                    </x-tab>
                    <x-tab name="tab-new-post" label="{{__('Latest News')}}">
                        <div class="flex flex-col gap-4">
                            @forelse($latestPosts->skip(1)->take(3) as $post)
                                <div class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover" alt="{{ $post->getTranslation('title', app()->getLocale()) }}" loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/noti-news.png') }}" class="w-full h-full object-cover" alt="No image" loading="lazy" decoding="async">
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ route('client.posts.show', $post->slug) }}" wire:navigate class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:underline">
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
                                <p class="text-gray-500">{{ __('No latest posts') }}</p>
                            @endforelse
                        </div>
                    </x-tab>
                </x-tabs>
                <x-button link="{{ route('client.posts.index',['danh-muc' => 'tin-tuc']) }}" label="{{__('Read more')}}" icon-right="o-arrow-right" class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-105"> </x-button>
            </div>
        </div>
    </div>
    <section class="mt-8 lg:mt-10 bg-slate-200/40 pt-15 ">
        <div class="mx-auto w-[90%] lg:w-330 ">
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
                        <div class="absolute -top-10 left-1/2 -translate-x-1/2 h-20 w-20 rounded-full bg-[#DDE8F1] flex items-center justify-center">
                            <x-icon name="{{ $stat['icon'] }}" class="w-10 h-10 text-fita" />
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
        <h1 class="mt-10 uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center lg:mt-15 mb-4">
{{--            <svg fill="#0071BD" width="38px" height="38px" viewBox="0 -32 576 576" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M480 416v16c0 26.51-21.49 48-48 48H48c-26.51 0-48-21.49-48-48V176c0-26.51 21.49-48 48-48h16v48H54a6 6 0 0 0-6 6v244a6 6 0 0 0 6 6h372a6 6 0 0 0 6-6v-10h48zm42-336H150a6 6 0 0 0-6 6v244a6 6 0 0 0 6 6h372a6 6 0 0 0 6-6V86a6 6 0 0 0-6-6zm6-48c26.51 0 48 21.49 48 48v256c0 26.51-21.49 48-48 48H144c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h384zM264 144c0 22.091-17.909 40-40 40s-40-17.909-40-40 17.909-40 40-40 40 17.909 40 40zm-72 96l39.515-39.515c4.686-4.686 12.284-4.686 16.971 0L288 240l103.515-103.515c4.686-4.686 12.284-4.686 16.971 0L480 208v80H192v-48z"></path></g></svg>--}}
            {{__('Photo library')}}
        </h1>
        <livewire:client.image-gallery :images="$images" class="h-40 rounded-box" />
    </div>
    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-4">
            {{__('List of partners')}}
        </h1>
        <livewire:client.list-of-partners/>

    </div>
</div>
