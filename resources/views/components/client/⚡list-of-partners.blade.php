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

<div
    class="relative group"
    x-data="{
        swiper: null,
        init() {
            // KHỞI TẠO SWIPER
            this.swiper = new Swiper(this.$refs.container, {
                slidesPerView: 1,
                spaceBetween: 20,
                centerInsufficientSlides: true,
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
        }
    }"
>
    <div id="{{ $uuid }}" x-ref="container" class="swiper w-[90%] lg:w-330 h-40! lg:h-50! pb-10!">
        <div class="swiper-wrapper">
            @foreach($partners as $partner)
                {{-- SWIPER SLIDE: Thêm flex để căn giữa dọc & ngang --}}
                <div class="swiper-slide h-full flex items-center justify-center rounded-md overflow-hidden">

                    {{-- Kiểm tra nếu có URL thì bọc bằng <a>, không thì bọc bằng <div> --}}
                    @if(!empty($partner->url))
                        <a href="{{ $partner->url }}" target="_blank" class="relative w-full h-full flex items-center justify-center group/img cursor-pointer">
                            @else
                                <div class="relative w-full h-full flex items-center justify-center group/img">
                                    @endif

                                    <img
                                        src="{{ Storage::url($partner->logo) }}"
                                        class="w-[80%] h-[80%] object-contain transition-transform duration-500 group-hover/img:scale-110"
                                        loading="lazy"
                                        alt="Logo"
                                    />

                                    {{-- Overlay đen mờ khi hover (Tuỳ chọn) --}}
                                    <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/10 transition-all duration-300"></div>

                                @if(!empty($partner->url))
                        </a>
                    @else
                </div>
                @endif

        </div>
        @endforeach
    </div>
</div>
