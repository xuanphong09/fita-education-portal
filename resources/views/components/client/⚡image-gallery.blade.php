<?php

use Livewire\Component;
use Illuminate\Support\Str; // Nhớ import Str

new class extends Component
{
    public string $uuid;

    public function __construct(
        public array $images = [],
        public ?string $id = null,
    ) {
        // Tạo ID ngẫu nhiên cho Swiper và PhotoSwipe
        $this->uuid = 'gallery-' . Str::random(10);
    }
};
?>

{{-- Container chính chứa cả logic AlpineJS --}}
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
                    rows: 2, // Quy định 2 hàng
                    fill: 'row',
                },
                navigation: {
                    nextEl: '#next-{{ $uuid }}',
                    prevEl: '#prev-{{ $uuid }}',
                },
                breakpoints: {
                    400: { slidesPerView: 2, spaceBetween: 20, grid: { rows: 2 } },
                    768: { slidesPerView: 4, spaceBetween: 20, grid: { rows: 2 } },
                    1024: { slidesPerView: 3, spaceBetween: 20, grid: { rows: 2 } }
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
    {{-- SWIPER CONTAINER --}}
    {{-- Lưu ý: Phải set chiều cao cố định (h-[500px]) để chế độ Grid hoạt động --}}
    <div id="{{ $uuid }}" x-ref="container" class="swiper w-[90%] lg:w-330 h-100! lg:h-125! pb-10!">
        <div class="swiper-wrapper">
            @foreach($images as $image)
                {{-- SWIPER SLIDE --}}
                {{-- Chiều cao calc(...) là bắt buộc để chia đều 2 hàng --}}
                <div class="swiper-slide h-[calc((100%-20px)/2)]! md:h-[calc((100%-30px)/2)]! rounded-md overflow-hidden shadow-sm border border-gray-200">

                    {{-- Thẻ A của PhotoSwipe --}}
                    <a
                        href="{{ $image }}"
                        target="_blank"
                        data-pswp-width="1200"
                        data-pswp-height="800"
                        class="block w-full h-full cursor-pointer group/img"
                    >
                        <img
                            src="{{ $image }}"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover/img:scale-110"
                            onload="this.parentNode.setAttribute('data-pswp-width', this.naturalWidth); this.parentNode.setAttribute('data-pswp-height', this.naturalHeight)"
                            loading="lazy"
                            alt=""
                        />
                        {{-- Overlay đen mờ khi hover --}}
                        <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/20 transition-all duration-300"></div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>

    {{-- NÚT ĐIỀU HƯỚNG (NAVIGATION BUTTONS) --}}
    {{-- Nút Previous --}}
    <button id="prev-{{ $uuid }}"
            class="absolute left-10 top-4/9 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/80 hover:bg-fita text-fita hover:text-white shadow-lg flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 -ml-10 lg:-ml-5">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
    </button>

    {{-- Nút Next --}}
    <button id="next-{{ $uuid }}"
            class="absolute right-10 top-4/9 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/80 hover:bg-fita text-fita hover:text-white shadow-lg flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 -mr-10 lg:-mr-5">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>
</div>
