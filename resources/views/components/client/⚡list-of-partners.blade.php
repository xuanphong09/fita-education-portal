<?php

use App\Models\Partner;
use Livewire\Component;
use Illuminate\Support\Str;

new class extends Component
{
    public string $uuid;

    public function __construct(
        public ?string $id = null,
    ) {
        $this->uuid = 'gallery-' . Str::random(10);
    }

    public function with(): array
    {
        $images = Partner::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->pluck('logo')
            ->filter()
            ->values()
            ->all();

        return [
            'images' => $images
        ];
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
                    400: { slidesPerView: 3, spaceBetween: 40, grid: { rows: 1 } },
                    640: { slidesPerView: 4, spaceBetween: 40, grid: { rows: 1 } },
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
    <div id="{{ $uuid }}" x-ref="container" class="swiper w-[90%] lg:w-330 h-40! lg:h-50! pb-10!">
        <div class="swiper-wrapper">
            @foreach($images as $image)
                {{-- SWIPER SLIDE --}}
                {{-- Chiều cao calc(...) là bắt buộc để chia đều 2 hàng --}}
                <div class="swiper-slide h-full rounded-md overflow-hidden">

                    <img
                        src="{{Storage::url($image) }}"
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
</div>
