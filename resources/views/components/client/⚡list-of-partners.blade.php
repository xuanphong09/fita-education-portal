<?php

use App\Models\Partner;
use Livewire\Component;
use Illuminate\Support\Str;

new class extends Component
{
    public string $uuid;

    public function mount(): void
    {
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
            // Kiểm tra an toàn: Nếu Swiper chưa tải xong thì không chạy để tránh báo lỗi đỏ
            if (typeof Swiper === 'undefined') {
                console.warn('Swiper library is not loaded!');
                return;
            }

            // Khởi tạo Swiper
            this.swiper = new Swiper(this.$refs.container, {
                slidesPerView: 1,
                spaceBetween: 20,
                centerInsufficientSlides: true,
                grid: {
                    rows: 1,
                    fill: 'row',
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
    {{-- THÊM wire:ignore VÀO ĐÂY ĐỂ TRÁNH XUNG ĐỘT DOM VỚI LIVEWIRE --}}
    <div id="{{ $uuid }}" x-ref="container" class="swiper w-[90%] lg:w-330 h-40! lg:h-50!" wire:ignore>
        <div class="swiper-wrapper">
            @foreach($partners as $partner)
                <div class="swiper-slide h-full flex items-center justify-center rounded-md overflow-hidden">

                    {{-- Tách rõ ràng 2 trường hợp có Link và Không có Link để cấu trúc HTML không bị gãy --}}
                    @if(!empty($partner->url))
                        <a href="{{ $partner->url }}" target="_blank" rel="noopener noreferrer" class="relative w-full h-full flex items-center justify-center group/img cursor-pointer">
                            <img
                                src="{{ Storage::url($partner->logo) }}"
                                class="w-[80%] h-[80%] object-contain transition-transform duration-500 group-hover/img:scale-110"
                                loading="lazy"
                                alt="Logo Đối tác"
                            />
                        </a>
                    @else
                        <div class="relative w-full h-full flex items-center justify-center group/img">
                            <img
                                src="{{ Storage::url($partner->logo) }}"
                                class="w-[80%] h-[80%] object-contain transition-transform duration-500 group-hover/img:scale-110"
                                loading="lazy"
                                alt="Logo Đối tác"
                            />
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    </div>
</div>
