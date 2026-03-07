<?php

use Livewire\Component;

new class extends Component
{
    public string $uuid;

    public function __construct(
        public array $images = [
            '/assets/images/LogoKhoaCNTT.png',
            '/assets/images/FITA.png',
            '/assets/images/logoST.jpg',
            '/assets/images/Logo Học viện.png',
            '/assets/images/empty-calendar.png',
            '/assets/images/logoST.jpg',
            '/assets/images/Logo Học viện.png',
            '/assets/images/empty-calendar.png',
            '/assets/images/logoST.jpg',
        ],
        public ?string $id = null,
    ) {
        // Tạo ID ngẫu nhiên cho Swiper và PhotoSwipe
        $this->uuid = 'gallery-' . Str::random(10);
    }
};
?>

<div
    class="relative group"
    x-data="{
        swiper: null,
        init() {
            // 1. KHỞI TẠO SWIPER (SLIDER LƯỚI)
            this.swiper = new Swiper(this.$refs.container, {
                slidesPerView: 1,
                spaceBetween: 20,
                grid: {
                    rows: 1,
                    fill: 'row',
                },
                navigation: {
                    nextEl: '#next-{{ $uuid }}',
                    prevEl: '#prev-{{ $uuid }}',
                },
                breakpoints: {
                    400: { slidesPerView: 2, spaceBetween: 40, grid: { rows: 1 } },
                    1024: { slidesPerView: 6, spaceBetween: 30, grid: { rows: 1 } }
                }
            });

            // 2. KHỞI TẠO PHOTOSWIPE (LIGHTBOX ZOOM ẢNH)
            const lightbox = new PhotoSwipeLightbox({
                gallery: '#{{ $uuid }}',
                children: 'a', // Chỉ định thẻ A là trigger
                showHideAnimationType: 'fade',
                pswpModule: PhotoSwipe
            });

            lightbox.init();
        }
    }"
>
    {{-- Lưu ý: Phải set chiều cao cố định (h-[500px]) để chế độ Grid hoạt động --}}
    <div id="{{ $uuid }}" x-ref="container" class="swiper w-[90%] lg:w-330 h-50! pb-10!">
        <div class="swiper-wrapper">
            @foreach($images as $image)
                {{-- SWIPER SLIDE --}}
                {{-- Chiều cao calc(...) là bắt buộc để chia đều 2 hàng --}}
                <div class="swiper-slide h-full rounded-md overflow-hidden">

                    <img
                        src="{{ $image }}"
                        class="w-full h-full object-contain transition-transform duration-500 group-hover/img:scale-110"
                        onload="this.parentNode.setAttribute('data-pswp-width', this.naturalWidth); this.parentNode.setAttribute('data-pswp-height', this.naturalHeight)"
                        loading="lazy"
                        alt=""
                    />
                        {{-- Overlay đen mờ khi hover --}}
                        <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/20 transition-all duration-300"></div>

                </div>
            @endforeach
        </div>
    </div>

    {{-- Nút Previous --}}
{{--    <button id="prev-{{ $uuid }}"--}}
{{--            class="absolute left-10 top-4/9 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/80 hover:bg-fita text-fita hover:text-white shadow-lg flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 -ml-5">--}}
{{--        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">--}}
{{--            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />--}}
{{--        </svg>--}}
{{--    </button>--}}

{{--    --}}{{-- Nút Next --}}
{{--    <button id="next-{{ $uuid }}"--}}
{{--            class="absolute right-10 top-4/9 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/80 hover:bg-fita text-fita hover:text-white shadow-lg flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 -mr-5">--}}
{{--        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">--}}
{{--            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />--}}
{{--        </svg>--}}
{{--    </button>--}}
</div>
