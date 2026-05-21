<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.client')]
class extends Component {
    use WithPagination;

    public int $imagePerPage = 25;

    #[Url(as: 'album')]
    public ?int $selectedAlbumId = null;

    public function mount(): void
    {
        if ($this->selectedAlbumId && ! $this->albumIsAvailable((int) $this->selectedAlbumId)) {
            $this->selectedAlbumId = null;
        }
    }

    private function albumIsAvailable(int $albumId): bool
    {
        return Album::query()
            ->whereKey($albumId)
            ->where('is_active', true)
            ->whereHas('images')
            ->exists();
    }

    public function getAlbumOptionsProperty(): array
    {
        return Album::query()
            ->whereHas('images')
            ->where('is_active' , true)
            ->orderBy('order')
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getAllImagesProperty()
    {
        return AlbumImage::query()
            ->when($this->selectedAlbumId, function ($query) {
                // Khi chọn album cụ thể: chỉ lấy ảnh của album đang active
                $query->whereHas('albums', function ($q) {
                    $q->where('albums.is_active', true)
                        ->where('albums.id', $this->selectedAlbumId);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->imagePerPage);
    }

    public function setAlbum(?int $albumId): void
    {
        if ($albumId && ! $this->albumIsAvailable($albumId)) {
            $this->selectedAlbumId = null;
        } else {
            $this->selectedAlbumId = $albumId;
        }

        $this->resetPage();
    }
};
?>

<div class="container mx-auto px-4 py-8 lg:py-10">
    <x-slot:title>
        {{ __('Photo library') }}
    </x-slot:title>

    <x-slot:breadcrumb>
        <span class="whitespace-nowrap font-semibold text-slate-700">
            {{ __('Photo library') }}
        </span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{ __('Photo library') }}
    </x-slot:titleBreadcrumb>

    @php
        $albums = $this->albumOptions;
        $images = $this->allImages;
        $galleryId = 'public-gallery-' . ($selectedAlbumId ?? 'all') . '-' . $images->currentPage();
    @endphp

    {{-- BỘ LỌC ALBUM --}}
{{--    @if(!empty($albums))--}}
        <div
            class="mb-8 flex items-center gap-3"
            x-data="{
                canScrollLeft: false,
                canScrollRight: false,

                init() {
                    this.$nextTick(() => {
                        this.updateScrollState();

                        this.$refs.tabs.addEventListener('scroll', () => {
                            this.updateScrollState();
                        }, { passive: true });

                        window.addEventListener('resize', () => {
                            this.updateScrollState();
                        });
                    });
                },

                updateScrollState() {
                    const el = this.$refs.tabs;

                    if (!el) return;

                    this.canScrollLeft = el.scrollLeft > 5;
                    this.canScrollRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 5;
                },

                scrollTabs(amount) {
                    this.$refs.tabs.scrollBy({
                        left: amount,
                        behavior: 'smooth'
                    });

                    setTimeout(() => this.updateScrollState(), 350);
                }
            }"
        >
            {{-- Nút trái --}}
            <button
                type="button"
                x-on:click="scrollTabs(-320)"
                x-bind:disabled="!canScrollLeft"
                x-bind:class="canScrollLeft ? 'opacity-100' : 'opacity-40 cursor-not-allowed'"
                class="shrink-0 w-10 h-10 rounded-full bg-white text-slate-600 shadow-md border border-slate-200
                       hover:bg-fita hover:text-white transition-all flex items-center justify-center"
                aria-label="Cuộn sang trái"
            >
                <x-icon name="o-chevron-left" class="w-5 h-5" />
            </button>

            {{-- Vùng tabs --}}
            <div class="min-w-0 flex-1 overflow-hidden">
                <div
                    x-ref="tabs"
                    class="flex flex-nowrap gap-2 overflow-x-auto py-1
                           [-ms-overflow-style:none] [scrollbar-width:none]
                           [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        type="button"
                        wire:click="setAlbum(null)"
                        wire:loading.attr="disabled"
                        class="shrink-0 px-4 py-2 rounded-md text-sm font-semibold border transition-all duration-300
                            {{ is_null($selectedAlbumId)
                                ? 'bg-fita text-white border-fita shadow-md shadow-fita/20'
                                : 'bg-white text-slate-600 border-slate-200 hover:border-fita hover:text-fita' }}"
                    >
                        {{ __('Tất cả') }}
                    </button>

                    @foreach($albums as $album)
                        <button
                            type="button"
                            wire:click="setAlbum({{ $album['id'] }})"
                            wire:loading.attr="disabled"
                            class="shrink-0 px-4 py-2 rounded-md text-sm font-semibold border transition-all duration-300
                                {{ (int) $selectedAlbumId === (int) $album['id']
                                    ? 'bg-fita text-white border-fita shadow-md shadow-fita/20'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-fita hover:text-fita' }}"
                        >
                            {{ $album['name'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Nút phải --}}
            <button
                type="button"
                x-on:click="scrollTabs(320)"
                x-bind:disabled="!canScrollRight"
                x-bind:class="canScrollRight ? 'opacity-100' : 'opacity-40 cursor-not-allowed'"
                class="shrink-0 w-10 h-10 rounded-full bg-white text-slate-600 shadow-md border border-slate-200
                       hover:bg-fita hover:text-white transition-all flex items-center justify-center"
                aria-label="Cuộn sang phải"
            >
                <x-icon name="o-chevron-right" class="w-5 h-5" />
            </button>
        </div>
{{--    @endif--}}

    {{-- KHU VỰC ẢNH --}}
    <div class="relative">
        {{-- Loading khi đổi album / phân trang --}}
        <div
            wire:loading.flex
            wire:target="setAlbum,nextPage,previousPage,gotoPage"
            class="absolute inset-0 z-20 bg-white/70 backdrop-blur-[1px] rounded-2xl items-center justify-center"
        >
            <div class="flex items-center gap-3 px-4 py-3">
                <span class="loading loading-spinner loading-md text-fita"></span>
                <span class="text-sm font-semibold text-slate-600">
                    {{ __('Loading data...') }}
                </span>
            </div>
        </div>

        {{-- MASONRY GRID --}}
        <div
            id="{{ $galleryId }}"
            wire:key="gallery-shell-{{ $galleryId }}"
            wire:loading.class="opacity-40 pointer-events-none"
            wire:target="setAlbum,nextPage,previousPage,gotoPage"
            class="columns-2 md:columns-3 lg:columns-4 2xl:columns-5 gap-4 lg:gap-6 transition-opacity duration-300"
            x-data="{
                lightbox: null,

                init() {
                    this.$nextTick(() => {
                        if (typeof PhotoSwipeLightbox === 'undefined' || typeof PhotoSwipe === 'undefined') {
                            return;
                        }

                        if (this.lightbox) {
                            this.lightbox.destroy();
                            this.lightbox = null;
                        }

                        this.lightbox = new PhotoSwipeLightbox({
                            gallery: '#{{ $galleryId }}',
                            children: 'a.pswp-item',
                            showHideAnimationType: 'zoom',
                            pswpModule: PhotoSwipe,
                            zoom: true,
                            arrowKeys: true,
                        });

                        this.lightbox.init();
                    });
                },

                destroy() {
                    if (this.lightbox) {
                        this.lightbox.destroy();
                        this.lightbox = null;
                    }
                }
            }"
            x-on:destroy.window="destroy()"
        >
            @forelse($images as $image)
                @php
                    $imageUrl = Storage::url($image->image_path);
                    $caption = $image->caption ?: '';
                @endphp

                <div
                    class="mb-4 lg:mb-6 break-inside-avoid"
                    wire:key="gallery-image-{{ $image->id }}"
                >
                    <figure class="relative overflow-hidden rounded-2xl bg-slate-100 shadow-sm border border-slate-100 group/img">
                        <a
                            href="{{ $imageUrl }}"
                            data-pswp-width="1200"
                            data-pswp-height="800"
                            class="pswp-item relative block w-full cursor-zoom-in"
                        >
                            <img
                                src="{{ $imageUrl }}"
                                class="w-full h-auto object-cover transition-transform duration-700 group-hover/img:scale-110"
                                onload="
                                    this.parentNode.setAttribute('data-pswp-width', this.naturalWidth);
                                    this.parentNode.setAttribute('data-pswp-height', this.naturalHeight);
                                "
                                loading="lazy"
                                decoding="async"
                                alt="{{ $caption }}"
                            />

                            {{-- Overlay --}}
                            <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/35 transition-colors duration-300"></div>

                            {{-- Icon zoom --}}
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="w-11 h-11 rounded-full bg-white/90 text-fita flex items-center justify-center opacity-0 scale-75 group-hover/img:opacity-100 group-hover/img:scale-100 transition-all duration-300 shadow-lg">
                                    <x-icon name="o-magnifying-glass-plus" class="w-6 h-6" />
                                </div>
                            </div>

                            {{-- Caption --}}
                            <figcaption class="absolute inset-x-0 bottom-0 p-4 translate-y-full group-hover/img:translate-y-0 transition-transform duration-300 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                                <p class="text-white text-sm font-semibold leading-snug line-clamp-2">
                                    {{ $caption }}
                                </p>
                            </figcaption>
                        </a>
                    </figure>
                </div>
            @empty
                <div class="break-inside-avoid">
                    <div class="py-16 flex flex-col items-center justify-center text-center text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                        <x-icon name="o-photo" class="w-16 h-16 mb-3 text-slate-300" />
                        <p>{{ __('Hiện tại chưa có hình ảnh nào trong thư mục này.') }}</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- PHÂN TRANG --}}
    @if($images->hasPages())
        <div class="mt-10">
            {{ $images->links() }}
        </div>
    @endif
</div>
