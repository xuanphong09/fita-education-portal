<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

new class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $uuid = '';

    // Số ảnh mỗi trang, thay đổi theo kích thước màn hình
    public int $imageLimit = 8;

    // null = Tất cả, chỉ dùng khi không có album nào active + có ảnh
    public ?int $selectedAlbumId = null;

    public function mount(int $imageLimit = 8): void
    {
        $this->uuid = 'home-gallery-' . Str::random(10);
        $this->imageLimit = $imageLimit;

        // Có album nổi bật thì chọn album nổi bật
        // Không có album nổi bật thì chọn album đầu tiên
        // Không có album nào thì giữ null => tab Tất cả
        $this->selectedAlbumId = $this->featuredAvailableAlbumId()
            ?? $this->firstAvailableAlbumId();
    }

    private function availableAlbumsQuery()
    {
        return Album::query()
            ->where('is_active', true)
            ->whereHas('images');
    }

    private function featuredAvailableAlbumId(): ?int
    {
        $id = $this->availableAlbumsQuery()
            ->where('is_featured_home', true)
            ->orderBy('order')
            ->orderByDesc('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function firstAvailableAlbumId(): ?int
    {
        $id = $this->availableAlbumsQuery()
            ->orderByDesc('is_featured_home')
            ->orderBy('order')
            ->orderByDesc('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function albumIsAvailable(int $albumId): bool
    {
        return $this->availableAlbumsQuery()
            ->whereKey($albumId)
            ->exists();
    }

    public function getAlbumOptionsProperty(): array
    {
        return $this->availableAlbumsQuery()
            ->orderByDesc('is_featured_home')
            ->orderBy('order')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_featured_home'])
            ->map(fn ($album) => [
                'id' => (int) $album->id,
                'name' => $album->name,
                'is_featured_home' => (bool) $album->is_featured_home,
            ])
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
            ->paginate($this->imageLimit, ['*'], 'homeGalleryPage');
    }

    public function setAlbum(?int $albumId): void
    {
        // Nếu đang có album thì không cho chọn Tất cả nữa
        if ($albumId === null && $this->firstAvailableAlbumId()) {
            $albumId = $this->firstAvailableAlbumId();
        }

        // Nếu album không hợp lệ thì quay về album đầu tiên
        if ($albumId && ! $this->albumIsAvailable((int) $albumId)) {
            $albumId = $this->firstAvailableAlbumId();
        }

        if ($this->selectedAlbumId === $albumId) {
            return;
        }

        $this->selectedAlbumId = $albumId;

        // Đổi album thì quay về trang đầu
        $this->resetPage('homeGalleryPage');
    }

    public function setResponsiveImageLimit(int $limit): void
    {
        $allowedLimits = [4, 6, 8, 10, 12];

        if (! in_array($limit, $allowedLimits, true)) {
            $limit = 8;
        }

        if ($this->imageLimit === $limit) {
            return;
        }

        $this->imageLimit = $limit;

        // Đổi số ảnh mỗi trang thì quay về trang đầu
        $this->resetPage('homeGalleryPage');
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
        @php
            $albums = $this->albumOptions;
            $galleryImages = $this->galleryImages;
            $currentPage = $galleryImages->currentPage();
            $galleryId = $uuid . '-' . ($selectedAlbumId ?? 'all') . '-' . $currentPage;
        @endphp

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
                    @if(empty($albums))
                        <button
                            type="button"
                            wire:click="setAlbum(null)"
                            wire:loading.attr="disabled"
                            class="shrink-0 px-4 py-2 rounded-md text-sm font-semibold border transition-all duration-300
                            bg-fita text-white border-fita shadow-md shadow-fita/20"
                        >
                            {{ __('Tất cả') }}
                        </button>
                    @else
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
                    @endif
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

        {{-- GALLERY --}}
        <div class="relative min-h-[260px]">
            <div
                wire:loading.flex
                wire:target="setAlbum,setResponsiveImageLimit,nextPage,previousPage,gotoPage"
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
                id="{{ $galleryId }}"
                wire:key="home-gallery-{{ $selectedAlbumId ?? 'all' }}-{{ $imageLimit }}-{{ $currentPage }}"
                wire:loading.class="opacity-40 pointer-events-none"
                wire:target="setAlbum,setResponsiveImageLimit,nextPage,previousPage,gotoPage"
                class="columns-2 sm:columns-3 lg:columns-4 2xl:columns-5 gap-4 lg:gap-6 mt-4 transition-opacity duration-300"
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
                @forelse($galleryImages as $image)
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
                                    src="{{ $imageUrl }}"
                                    class="w-full h-full object-cover transition-transform duration-700 group-hover/img:scale-110"
                                    onload="
                                        this.parentNode.setAttribute('data-pswp-width', this.naturalWidth);
                                        this.parentNode.setAttribute('data-pswp-height', this.naturalHeight);
                                    "
                                    loading="{{ $loop->iteration <= 2 ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                    fetchpriority="{{ $loop->iteration <= 2 ? 'high' : 'auto' }}"
                                    alt="{{ $caption ?: __('Image') }}"
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

        {{-- PHÂN TRANG XEM THÊM --}}
        @if($galleryImages->hasPages())
            <div
                class="mt-10"
                wire:loading.class="opacity-40 pointer-events-none"
                wire:target="setAlbum,setResponsiveImageLimit,nextPage,previousPage,gotoPage"
            >
                {{ $galleryImages->onEachSide(1)->links(data: ['scrollTo' => false]) }}
            </div>
        @endif

        {{-- NÚT XEM THÊM SANG TRANG THƯ VIỆN --}}
{{--        <div class="mt-10 text-center">--}}
{{--            <a--}}
{{--                href="{{ $selectedAlbumId--}}
{{--                    ? route('client.album.gallery', ['album' => $selectedAlbumId])--}}
{{--                    : route('client.album.gallery') }}"--}}
{{--                class="inline-flex items-center gap-2 px-6 py-2 rounded-md bg-fita text-white font-semibold hover:bg-fita/90 transition-all shadow-md shadow-fita/20"--}}
{{--                wire:navigate--}}
{{--            >--}}
{{--                {{ __('Read more') }}--}}
{{--                <x-icon name="o-arrow-right" class="w-5 h-5" />--}}
{{--            </a>--}}
{{--        </div>--}}
    </div>
</section>
