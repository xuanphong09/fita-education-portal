<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
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


   public $images = [
       '/assets/images/Album/1.jpg',
       '/assets/images/Album/2.jpg',
       '/assets/images/Album/3.jpg',
       '/assets/images/Album/4.jpg',
       '/assets/images/Album/5.jpg',
       '/assets/images/Album/6.jpg',
       '/assets/images/Album/7.jpg',
         '/assets/images/Album/8.jpg',
       '/assets/images/Album/9.jpg',
       '/assets/images/Album/10.jpg',
       '/assets/images/Album/11.jpg',
       '/assets/images/Album/12.jpg',
       '/assets/images/Album/13.jpg',
       '/assets/images/Album/14.jpg',
       '/assets/images/Album/15.jpg',
       '/assets/images/Album/16.jpg',
       '/assets/images/Album/17.jpg',
         '/assets/images/Album/18.jpg',
       '/assets/images/Album/19.jpg',
       '/assets/images/Album/20.jpg',
       '/assets/images/Album/21.jpg',
       '/assets/images/Album/21.jpg',
       '/assets/images/Album/22.jpg',
       '/assets/images/Album/23.jpg',
       '/assets/images/Album/24.jpg',
       '/assets/images/Album/25.jpg',
       '/assets/images/Album/26.jpg',
       '/assets/images/Album/27.jpg',
         '/assets/images/Album/28.jpg',
       '/assets/images/Album/29.jpg',
       '/assets/images/Album/30.jpg',
       '/assets/images/Album/31.jpg',
       '/assets/images/Album/32.jpg',


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
            ->limit($locale === 'en' ? 12 : 3)
            ->get()
            ->filter(fn (Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();
        $featurePostsSlideImages = [];
        foreach ($featuredPosts as $post) {
            if (!empty($post->thumbnail)) {
                try {
                    if (Storage::disk('public')->exists($post->thumbnail)) {
                        $featurePostsSlideImages[] = [
                            'image' => Storage::url($post->thumbnail),
                            'day' => $post->published_at?->isoFormat('DD'),
                            'month' => $post->published_at?->isoFormat('MMMM'),
                            'url' => route('client.posts.show', $post->slug),
                        ];
                    }
                } catch (\Exception $e) {
                    // Bỏ qua nếu có lỗi kiểm tra file
                    \Log::warning("Không thể kiểm tra thumbnail cho post {$post->id}: {$e->getMessage()}");
                }
            }
        }

        $latestPosts = (clone $baseQuery)
            ->when($featuredPosts->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $featuredPosts->pluck('id')))
            ->limit($locale === 'en' ? 20 : 3)
            ->get()
            ->filter(fn (Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();

        $latestPostsSlideImages = [];
        foreach ($latestPosts as $post) {
            if (!empty($post->thumbnail)) {
                try {
                    if (Storage::disk('public')->exists($post->thumbnail)) {
                        $latestPostsSlideImages[] = [
                            'image' => Storage::url($post->thumbnail),
                            'day' => $post->published_at?->isoFormat('DD'),
                            'month' => $post->published_at?->isoFormat('MMMM'),
                            'url' => route('client.posts.show', $post->slug),
                        ];
                    }
                } catch (\Exception $e) {
                    // Bỏ qua nếu có lỗi kiểm tra file
                    \Log::warning("Không thể kiểm tra thumbnail cho post {$post->id}: {$e->getMessage()}");
                }
            }
        }

        return [
            'slides' => $slides,
            'featuredPosts' => $featuredPosts,
            'latestPosts' => $latestPosts,
            'featurePostsSlideImages' => $featurePostsSlideImages,
            'latestPostsSlideImages' => $latestPostsSlideImages,
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

    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-medium font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-4">
            {{__('News and events')}}
        </h1>
        <div class="flex h-140 w-[90%] lg:w-330 mx-auto gap-10">
            <div class="w-[50%] hidden lg:block relative">
                @php
                    $newsSlides = $tabSelected === 'tab-feature-post'
                        ? $featurePostsSlideImages
                        : $latestPostsSlideImages;
                @endphp

                <x-carousel
                    :key="'news-carousel-' . $tabSelected"
                    wire:key="news-carousel-{{ $tabSelected }}"
                    :slides="$newsSlides"
                    autoplay
                    withoutArrows="false"
                    interval="5000"
                    without-indicators
                    class="custom-carousel h-140 rounded-none"
                >
                    @scope('content', $slide)
                    <div>
                        <div
                            @class([
                                "absolute inset-0 z-[1] flex flex-col justify-start items-end text-center",
                            ])
                        >
                            <div class="bg-slate-900/45">
                                <h3 class="font-bold text-white flex flex-col justify-center items-center py-2 px-3">
                                    <span class="text-[40px]"> {{ data_get($slide, 'day') }}</span>
                                    <span class="text-[24px]">{{ data_get($slide, 'month') }}</span>
                                </h3>
                            </div>
                        </div>
                    </div>
                    @endscope
                </x-carousel>
                <div wire:loading.delay.short class="absolute inset-0 z-30 bg-white/65 backdrop-blur-[2px] transition-all duration-300">
                    <div class="flex flex-col items-center gap-2 justify-center h-full">
                        <x-loading class="text-primary loading-lg" />
{{--                        <span class="text-md font-medium text-gray-500">{{__('Loading data...')}}</span>--}}
                    </div>
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
                        <div class="flex flex-col gap-8">
                            @forelse($featuredPosts as $post)
                                <div class="flex gap-5">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover" alt="{{ $post->getTranslation('title', app()->getLocale()) }}">
                                        @else
                                            <img src="{{ asset('assets/images/noti-news.png') }}" class="w-full h-full object-cover" alt="No image">
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
                        <div class="flex flex-col gap-8">
                            @forelse($latestPosts as $post)
                                <div class="flex gap-5">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover" alt="{{ $post->getTranslation('title', app()->getLocale()) }}">
                                        @else
                                            <img src="{{ asset('assets/images/noti-news.png') }}" class="w-full h-full object-cover" alt="No image">
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
                <x-button link="{{ route('client.posts.index') }}" label="{{__('Read more')}}" icon-right="o-arrow-right" class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-105"> </x-button>
            </div>
        </div>
    </div>

    <div>
        <h1 class="mt-15 uppercase lg:text-[32px] text-[28px] text-fita font-medium font-barlow flex justify-center gap-1 items-center mt-10 lg:mt-15 mb-4"><svg fill="#0071BD" width="38px" height="38px" viewBox="0 -32 576 576" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M480 416v16c0 26.51-21.49 48-48 48H48c-26.51 0-48-21.49-48-48V176c0-26.51 21.49-48 48-48h16v48H54a6 6 0 0 0-6 6v244a6 6 0 0 0 6 6h372a6 6 0 0 0 6-6v-10h48zm42-336H150a6 6 0 0 0-6 6v244a6 6 0 0 0 6 6h372a6 6 0 0 0 6-6V86a6 6 0 0 0-6-6zm6-48c26.51 0 48 21.49 48 48v256c0 26.51-21.49 48-48 48H144c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h384zM264 144c0 22.091-17.909 40-40 40s-40-17.909-40-40 17.909-40 40-40 40 17.909 40 40zm-72 96l39.515-39.515c4.686-4.686 12.284-4.686 16.971 0L288 240l103.515-103.515c4.686-4.686 12.284-4.686 16.971 0L480 208v80H192v-48z"></path></g></svg>{{__('Photo library')}}</h1>
        <livewire:client.image-gallery :images="$images" class="h-40 rounded-box" />
    </div>
    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-medium font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-4">
            {{__('List of partners')}}
        </h1>
        <livewire:client.list-of-partners/>

    </div>
</div>
