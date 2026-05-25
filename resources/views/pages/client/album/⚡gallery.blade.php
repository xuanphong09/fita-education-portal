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

    // Số ảnh mặc định trước khi Alpine detect kích thước màn hình
    public int $imagePerPage = 12;

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
            ->where('is_active', true)
            ->orderByDesc('is_featured_home')
            ->orderBy('order')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_featured_home'])
            ->toArray();
    }

    public function getAllImagesProperty()
    {
        return AlbumImage::query()
            ->select([
                'id',
                'image_path',
                'caption',
                'created_at',
            ])
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->when($this->selectedAlbumId, function ($query) {
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

    public function setResponsiveImagePerPage(int $perPage): void
    {
        $allowedPerPages = [8, 12, 16, 20, 25];

        if (! in_array($perPage, $allowedPerPages, true)) {
            $perPage = 12;
        }

        if ($this->imagePerPage === $perPage) {
            return;
        }

        $this->imagePerPage = $perPage;
        $this->resetPage();
    }
};
?>

<div
    class="container mx-auto px-4 py-8 lg:py-10"
    x-data="{
        currentPerPage: @js($imagePerPage),
        resizeTimer: null,
        resizeHandler: null,

        getImagePerPage() {
            const width = window.innerWidth;

            if (width >= 1536) {
                return 25;
            }

            if (width >= 1280) {
                return 20;
            }

            if (width >= 1024) {
                return 16;
            }

            if (width >= 640) {
                return 12;
            }

            return 8;
        },

        syncImagePerPage() {
            const perPage = this.getImagePerPage();

            if (Number(this.currentPerPage) === Number(perPage)) {
                return;
            }

            this.currentPerPage = perPage;
            this.$wire.setResponsiveImagePerPage(perPage);
        },

        init() {
            this.$nextTick(() => {
                this.syncImagePerPage();
            });

            this.resizeHandler = () => {
                clearTimeout(this.resizeTimer);

                this.resizeTimer = setTimeout(() => {
                    this.syncImagePerPage();
                }, 350);
            };

            window.addEventListener('resize', this.resizeHandler);
        },

        destroy() {
            if (this.resizeHandler) {
                window.removeEventListener('resize', this.resizeHandler);
            }
        }
    }"
    x-on:livewire:navigating.window="destroy()"
>
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
        $galleryId = 'public-gallery-' . ($selectedAlbumId ?? 'all') . '-' . $images->currentPage() . '-' . $imagePerPage;

        $imageCount = $images->count();
    @endphp

    {{-- BỘ LỌC ALBUM --}}
    <div
        class="mb-8 flex items-center gap-3"
        x-data="{
            canScrollLeft: false,
            canScrollRight: false,
            tabsScrollHandler: null,
            tabsResizeHandler: null,

            init() {
                this.$nextTick(() => {
                    this.updateScrollState();

                    this.tabsScrollHandler = () => {
                        this.updateScrollState();
                    };

                    this.tabsResizeHandler = () => {
                        this.updateScrollState();
                    };

                    this.$refs.tabs?.addEventListener('scroll', this.tabsScrollHandler, { passive: true });
                    window.addEventListener('resize', this.tabsResizeHandler);
                });
            },

            updateScrollState() {
                const el = this.$refs.tabs;

                if (!el) {
                    return;
                }

                this.canScrollLeft = el.scrollLeft > 5;
                this.canScrollRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 5;
            },

            scrollTabs(amount) {
                this.$refs.tabs.scrollBy({
                    left: amount,
                    behavior: 'smooth'
                });

                setTimeout(() => this.updateScrollState(), 350);
            },

            destroy() {
                if (this.tabsScrollHandler) {
                    this.$refs.tabs?.removeEventListener('scroll', this.tabsScrollHandler);
                }

                if (this.tabsResizeHandler) {
                    window.removeEventListener('resize', this.tabsResizeHandler);
                }
            }
        }"
    >
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

    {{-- KHU VỰC ẢNH --}}
    <div class="relative min-h-[260px]">
        <div
            wire:loading.flex
            wire:target="setAlbum,setResponsiveImagePerPage,nextPage,previousPage,gotoPage"
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
            wire:target="setAlbum,setResponsiveImagePerPage,nextPage,previousPage,gotoPage"
            class="columns-2 sm:columns-3 lg:columns-4 2xl:columns-5 gap-4 lg:gap-6 transition-opacity duration-300"
            x-data="{
                lightbox: null,
                captionOverlay: null,

                getActiveCaption(pswp) {
                    const element = pswp?.currSlide?.data?.element;
                    return element?.dataset?.imageCaption || element?.getAttribute('aria-label') || '';
                },

                createCaptionOverlay(pswp) {
                    this.removeCaptionOverlay();

                    const caption = this.getActiveCaption(pswp);

                    if (!caption) {
                        return;
                    }

                    const overlay = document.createElement('div');
                    overlay.className = 'pswp-caption-overlay';

                    overlay.style.position = 'absolute';
                    overlay.style.left = '50%';
                    overlay.style.bottom = '28px';
                    overlay.style.transform = 'translateX(-50%)';
                    overlay.style.zIndex = '60';
                    overlay.style.maxWidth = '80vw';
                    overlay.style.pointerEvents = 'none';

                    const captionBox = document.createElement('div');
                    captionBox.className = 'rounded-xl bg-black/65 px-4 py-2 text-center text-sm font-medium text-white shadow-2xl backdrop-blur';
                    captionBox.textContent = caption;

                    overlay.appendChild(captionBox);
                    pswp.element?.appendChild(overlay);

                    this.captionOverlay = overlay;
                },

                removeCaptionOverlay() {
                    this.captionOverlay?.remove();
                    this.captionOverlay = null;
                },

                init() {
                    this.$nextTick(() => {
                        if (typeof PhotoSwipeLightbox === 'undefined' || typeof PhotoSwipe === 'undefined') {
                            return;
                        }

                        this.lightbox = new PhotoSwipeLightbox({
                            gallery: '#{{ $galleryId }}',
                            children: 'a.pswp-item',
                            showHideAnimationType: 'zoom',
                            pswpModule: PhotoSwipe,
                            zoom: true,
                            arrowKeys: true,
                        });

                        this.lightbox.on('openingAnimationEnd', () => {
                            this.createCaptionOverlay(this.lightbox.pswp);
                        });

                        this.lightbox.on('change', () => {
                            this.createCaptionOverlay(this.lightbox.pswp);
                        });

                        this.lightbox.on('close', () => {
                            this.removeCaptionOverlay();
                        });

                        this.lightbox.init();
                    });
                },

                destroy() {
                    this.removeCaptionOverlay();

                    if (this.lightbox) {
                        this.lightbox.destroy();
                        this.lightbox = null;
                    }
                }
            }"
            x-on:destroy.window="destroy()"
            x-on:livewire:navigating.window="destroy()"
        >
            @forelse($images as $image)
                @php
                    $imageUrl = Storage::url($image->image_path);
                    $caption = $image->caption ?: '';

                    $aspectClass = match ($loop->iteration % 4) {
                        1 => 'aspect-[8/9]',
                        2 => 'aspect-[6/5]',
                        3 => 'aspect-[4/3]',
                        default => 'aspect-[5/4]',
                    };
                @endphp

                <div
                    class="mb-4 lg:mb-6 break-inside-avoid"
                    wire:key="gallery-image-{{ $image->id }}"
                >
                    <figure class="relative overflow-hidden rounded-2xl bg-slate-100 shadow-sm border border-slate-100 group/img {{ $aspectClass }}">
                        <a
                            href="{{ $imageUrl }}"
                            data-pswp-width="1200"
                            data-pswp-height="800"
                            data-image-caption="{{ e($caption) }}"
                            aria-label="{{ e($caption) }}"
                            class="pswp-item relative block w-full h-full cursor-zoom-in group/img"
                        >
                            <img
                                src="{{ $imageUrl }}"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover/img:scale-110"
                                onload="
                        this.parentNode.setAttribute('data-pswp-width', this.naturalWidth);
                        this.parentNode.setAttribute('data-pswp-height', this.naturalHeight);
                    "
                                loading="{{ $loop->iteration <= 4 ? 'eager' : 'lazy' }}"
                                decoding="async"
                                fetchpriority="{{ $loop->iteration <= 2 ? 'high' : 'low' }}"
                                alt="{{ $caption }}"
                            />

                            <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/35 transition-colors duration-300"></div>

                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="w-11 h-11 rounded-full bg-white/90 text-fita flex items-center justify-center opacity-0 scale-75 group-hover/img:opacity-100 group-hover/img:scale-100 transition-all duration-300 shadow-lg">
                                    <x-icon name="o-magnifying-glass-plus" class="w-6 h-6" />
                                </div>
                            </div>

                            @if($caption)
                                <figcaption class="absolute inset-x-0 bottom-0 p-4 translate-y-full group-hover/img:translate-y-0 transition-transform duration-300 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                                    <p class="text-white text-sm font-semibold leading-snug line-clamp-2">
                                        {{ $caption }}
                                    </p>
                                </figcaption>
                            @endif
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
        <div
            class="mt-10"
            wire:loading.class="opacity-40 pointer-events-none"
            wire:target="setAlbum,setResponsiveImagePerPage,nextPage,previousPage,gotoPage"
        >
            {{ $images->links() }}
        </div>
    @endif
</div>
