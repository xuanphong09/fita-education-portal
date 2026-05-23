<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public string $uuid = '';

    // Trang chủ chỉ nên lấy ít ảnh để nhẹ website
    public int $imageLimit = 8;

    // null = tất cả album
    public ?int $selectedAlbumId = null;

    public function mount(int $imageLimit = 8): void
    {
        $this->uuid = 'home-gallery-' . Str::random(10);
        $this->imageLimit = $imageLimit;
        $this->selectedAlbumId = Album::query()
            ->where('is_active', true)
            ->where('is_featured_home', true)
            ->whereHas('images')
            ->orderBy('order')
            ->orderByDesc('id')
            ->value('id');
    }

    public function getAlbumOptionsProperty(): array
    {
        return Album::query()
            ->where('is_active', true)
            ->whereHas('images')
            ->orderByDesc('is_featured_home')
            ->orderBy('order')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_featured_home'])
            ->toArray();
    }

    public function getGalleryImagesProperty()
    {
        return AlbumImage::query()
            ->select(['id', 'image_path', 'caption', 'created_at'])
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
            ->limit($this->imageLimit)
            ->get();
    }

    public function setAlbum(?int $albumId): void
    {
        if ($this->selectedAlbumId === $albumId) {
            return;
        }

        $this->selectedAlbumId = $albumId;
    }

    public function setResponsiveImageLimit(int $limit): void
    {
        $allowedLimits = [4, 6, 8, 10, 12];

        if (! in_array($limit, $allowedLimits, true)) {
            $limit = 8;
        }

        $this->imageLimit = $limit;
    }

    public function getDescriptionAlbumProperty()
    {
        if (! $this->selectedAlbumId) {
            return '';
        }

        return Album::query()
            ->where('is_active', true)
            ->whereKey($this->selectedAlbumId)
            ->value('description') ?? '';
    }
};
?>

<section
    class=""
    x-data="{
        currentLimit: @js($imageLimit),
        resizeTimer: null,
        resizeHandler: null,

        getImageLimit() {
            const width = window.innerWidth;

            if (width >= 1536) {
                return 10;
            }

            if (width >= 1024) {
                return 8;
            }

            if (width >= 640) {
                return 6;
            }

            return 4;
        },

        syncImageLimit() {
            const limit = this.getImageLimit();

            if (Number(this.currentLimit) === Number(limit)) {
                return;
            }

            this.currentLimit = limit;
            this.$wire.setResponsiveImageLimit(limit);
        },

        init() {
            this.$nextTick(() => {
                this.syncImageLimit();
            });

            this.resizeHandler = () => {
                clearTimeout(this.resizeTimer);

                this.resizeTimer = setTimeout(() => {
                    this.syncImageLimit();
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
>
    <div class="container mx-auto px-4">
{{--        <div class="mb-4 text-center">--}}
{{--            <p class="max-w-2xl mx-auto text-gray-600 text-[16px]">--}}
{{--                {{ $this->descriptionAlbum }}--}}
{{--            </p>--}}
{{--        </div>--}}
        {{-- FILTERABLE TABS --}}
        <div
            class="mb-4 flex items-center gap-3"
            x-data="{
                scrollLeft() {
                    this.$refs.tabs.scrollBy({
                        left: -300,
                        behavior: 'smooth'
                    });
                },

                scrollRight() {
                    this.$refs.tabs.scrollBy({
                        left: 300,
                        behavior: 'smooth'
                    });
                }
            }"
        >
            <button
                type="button"
                x-on:click="scrollLeft()"
                class="shrink-0 w-10 h-10 rounded-full bg-white text-slate-600 shadow-md border border-slate-200
                hover:bg-fita hover:text-white transition-all flex items-center justify-center"
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

                    @foreach($this->albumOptions as $album)
                        <button
                            type="button"
                            wire:click="setAlbum({{ $album['id'] }})"
                            wire:loading.attr="disabled"
                            class="shrink-0 px-4 py-2 rounded-md text-sm font-semibold border transition-all duration-300
                    {{ $selectedAlbumId === $album['id']
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
                x-on:click="scrollRight()"
                class="shrink-0 w-10 h-10 rounded-full bg-white text-slate-600 shadow-md border border-slate-200
                hover:bg-fita hover:text-white transition-all flex items-center justify-center"
            >
                <x-icon name="o-chevron-right" class="w-5 h-5" />
            </button>
        </div>
        <div class="relative min-h-[260px]">
            <div
                wire:loading.flex
                wire:target="setAlbum,setResponsiveImageLimit"
                class="absolute inset-0 z-20 bg-white/60 backdrop-blur-[1px] rounded-2xl items-center justify-center"
            >
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="loading loading-spinner loading-md text-fita"></span>
                    <span class="text-sm font-semibold text-slate-600">
                {{ __('Loading data...') }}
            </span>
                </div>
            </div>

            <div
                id="{{ $uuid }}-{{ $selectedAlbumId ?? 'all' }}"
                wire:key="home-gallery-{{ $selectedAlbumId ?? 'all' }}-{{ $imageLimit }}"
                wire:loading.class="opacity-40 pointer-events-none"
                wire:target="setAlbum,setResponsiveImageLimit"
                class="columns-2 sm:columns-3 lg:columns-4 2xl:columns-5 gap-4 lg:gap-6 mt-4"
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
                            gallery: '#{{ $uuid }}-{{ $selectedAlbumId ?? 'all' }}',
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
            >
                @forelse($this->galleryImages as $image)
                    @php
                        $imageUrl = Storage::url($image->image_path);
                        $thumbUrl = $image->thumbnail_path
                            ? Storage::url($image->thumbnail_path)
                            : $imageUrl;
                        $caption = $image->caption ?: '';

                        $aspectClass = match ($loop->iteration % 4) {
                            1 => 'aspect-[8/9]',
                            2 => 'aspect-[6/5]',
                            3 => 'aspect-[4/3]',
                            default => 'aspect-[5/4]',
                        };
                    @endphp

                    <div
                        wire:key="home-gallery-image-{{ $image->id }}"
                        class="mb-4 lg:mb-6 break-inside-avoid"
                    >
                        <div class="relative overflow-hidden rounded-2xl bg-slate-100 shadow-sm border border-slate-100 {{ $aspectClass }}">
                            <a
                                href="{{ $imageUrl }}"
                                data-pswp-width="1200"
                                data-pswp-height="800"
                                data-image-caption="{{ e($caption) }}"
                                aria-label="{{ e($caption) }}"
                                class="pswp-item relative block w-full h-full cursor-zoom-in group/img"
                            >
                                <img
                                    src="{{ $thumbUrl  }}"
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

                                {{-- Lớp phủ hover --}}
                                <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/35 transition-colors duration-300"></div>

                                {{-- Icon zoom --}}
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="w-11 h-11 rounded-full bg-white/90 text-fita flex items-center justify-center opacity-0 scale-75 group-hover/img:opacity-100 group-hover/img:scale-100 transition-all duration-300 shadow-lg">
                                        <x-icon name="o-magnifying-glass-plus" class="w-6 h-6" />
                                    </div>
                                </div>

                                {{-- Caption --}}
                                @if($caption)
                                    <div class="absolute inset-x-0 bottom-0 p-4 translate-y-full group-hover/img:translate-y-0 transition-transform duration-300 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                                        <p class="text-white text-sm font-semibold leading-snug line-clamp-2">
                                            {{ $caption }}
                                        </p>
                                    </div>
                                @endif
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="break-inside-avoid">
                        <div class="py-16 flex flex-col items-center justify-center text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                            <x-icon name="o-photo" class="w-16 h-16 mb-3 text-slate-300" />
                            <p>{{ __('Hiện tại chưa có hình ảnh nào.') }}</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- NÚT XEM THÊM --}}
        <div class="mt-10 text-center">
            <a
                href="{{ route('client.album.gallery',['album'=>$selectedAlbumId]) }}"
                class="inline-flex items-center gap-2 px-6 py-2 rounded-md bg-fita text-white font-semibold hover:bg-fita/90 transition-all shadow-md shadow-fita/20"
                wire:navigate
            >
                {{ __('Read more') }}
                <x-icon name="o-arrow-right" class="w-5 h-5" />
            </a>
        </div>
    </div>
</section>
