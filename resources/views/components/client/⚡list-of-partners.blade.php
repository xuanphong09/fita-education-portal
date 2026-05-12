<?php

use App\Models\Partner;
use Livewire\Component;
use Illuminate\Support\Str;

new class extends Component
{
    public string $uuid;

    public function mount(): void
    {
        $this->uuid = 'partner-' . Str::random(10);
    }

    public function with(): array
    {
        $partners = Partner::query()
            ->where('is_active', true)
            ->whereNotNull('logo')
            ->orderBy('order')
            ->get();

        return [
            'partners' => $partners
        ];
    }
};
?>

<section class="pb-10 pt-2 font-sans" x-data="{
    swiper: null,
    init() {
        if (typeof Swiper === 'undefined') {
            console.warn('Swiper library is not loaded!');
            return;
        }

        // Khởi tạo Swiper với các class điều hướng giống hệt phần Cựu Sinh Viên
        this.swiper = new Swiper(this.$refs.container, {
            slidesPerView: 2,
            spaceBetween: 30,
            loop: true,
            centerInsufficientSlides: true, // Căn giữa nếu ít logo
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            },
            navigation: {
                nextEl: '.swiper-button-next-partner',
                prevEl: '.swiper-button-prev-partner',
            },
            pagination: {
                el: '.partner-pagination',
                clickable: true,
                bulletClass: 'transition-all duration-300 rounded-full w-8! h-1.5 bg-blue-300 inline-block cursor-pointer opacity-100 mx-1',
                bulletActiveClass: '!bg-fita2'
            },
            breakpoints: {
                400: { slidesPerView: 2, spaceBetween: 30 },
                640: { slidesPerView: 4, spaceBetween: 40 },
                1024: { slidesPerView: 5, spaceBetween: 40 },
                1280: { slidesPerView: 6, spaceBetween: 30 }
            }
        });
    }
}">
    <div class="container mx-auto px-4">
        <div class="relative flex items-center justify-center">

            {{-- Nút Prev --}}
            <button class="swiper-button-prev-partner absolute left-0 md:-left-8 z-10 w-10 h-10 bg-white rounded-full shadow-md hidden md:flex items-center justify-center text-gray-400 hover:text-fita transition cursor-pointer">
                <x-icon name="s-chevron-left"></x-icon>
            </button>

            <div id="{{ $uuid }}" x-ref="container" class="swiper w-full h-40 lg:h-50" wire:ignore>
                    <div class="swiper-wrapper flex items-center">
                        @foreach($partners as $partner)
                            <div class="swiper-slide h-full flex items-center justify-center rounded-md overflow-hidden">

                                {{-- Vẫn giữ nguyên hiệu ứng Grayscale xịn xò --}}
                                @php
                                    $imageClasses = "w-[80%] h-[80%] object-contain transition-all duration-500 lg:grayscale lg:opacity-90 lg:brightness-120 lg:group-hover:grayscale-0 lg:group-hover:opacity-100 lg:group-hover:scale-110 lg:group-hover:brightness-100";
                                @endphp

                                @if(!empty($partner->url))
                                    <a href="{{ $partner->url }}" target="_blank" rel="noopener noreferrer" class="group relative w-full h-full flex items-center justify-center cursor-pointer">
                                        <img src="{{ Storage::url($partner->logo) }}" class="{{ $imageClasses }}" loading="lazy" alt="Logo Đối tác" />
                                    </a>
                                @else
                                    <div class="group relative w-full h-full flex items-center justify-center">
                                        <img src="{{ Storage::url($partner->logo) }}" class="{{ $imageClasses }}" loading="lazy" alt="Logo Đối tác" />
                                    </div>
                                @endif

                            </div>
                        @endforeach
                    </div>
                </div>

            {{-- Nút Next --}}
            <button class="swiper-button-next-partner absolute right-0 md:-right-8 z-10 w-10 h-10 bg-white rounded-full shadow-md items-center justify-center text-gray-400 hover:text-fita transition cursor-pointer hidden md:flex">
                <x-icon name="s-chevron-right"></x-icon>
            </button>
        </div>

        {{-- Dots Pagination (Dấu chấm viên thuốc) --}}
        <div class="partner-pagination flex justify-center mt-8 gap-2"></div>
    </div>
</section>
