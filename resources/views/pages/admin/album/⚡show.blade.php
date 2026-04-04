<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination, Toast;

    public int $albumId;
    public int $perPage = 20;
    public array $selectedImageIds = [];
    public bool $selectPage = false;
    public ?int $selectedImageId = null;
    public int $zoomLevel = 100;

    public function mount(int $id): void
    {
        $this->albumId = $id;

        abort_unless(Album::query()->whereKey($id)->exists(), 404);
    }

    public function getAlbumProperty(): Album
    {
        return Album::query()->findOrFail($this->albumId);
    }

    public function getImagesProperty()
    {
        return AlbumImage::query()
            ->with('albums:id,name')
            ->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function getCurrentPageImageIdsProperty(): array
    {
        return $this->images
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function getCurrentImageProperty(): ?AlbumImage
    {
        if (!$this->selectedImageId) {
            return null;
        }

        return AlbumImage::query()
            ->with('albums:id,name')
            ->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))
            ->find($this->selectedImageId);
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
        $this->selectedImageIds = [];
        $this->selectPage = false;
    }

    public function updatedSelectedImageIds(): void
    {
        $currentIds = $this->currentPageImageIds;
        $selectedInPage = array_intersect($currentIds, array_map('intval', $this->selectedImageIds));
        $this->selectPage = count($currentIds) > 0 && count($selectedInPage) === count($currentIds);
    }

    public function toggleSelectPage(): void
    {
        $currentIds = $this->currentPageImageIds;

        if (empty($currentIds)) {
            return;
        }

        $allSelected = count(array_diff($currentIds, $this->selectedImageIds)) === 0;

        if ($allSelected) {
            $this->selectedImageIds = array_values(array_diff($this->selectedImageIds, $currentIds));
            $this->selectPage = false;
            return;
        }

        $this->selectedImageIds = array_values(array_unique(array_merge($this->selectedImageIds, $currentIds)));
        $this->selectPage = true;
    }

    public function clearSelection(): void
    {
        $this->selectedImageIds = [];
        $this->selectPage = false;
    }

    public function requestDetachImage(?int $id = null): void
    {
        if (!$id && empty($this->selectedImageIds)) {
            $this->warning('Vui lòng chọn ít nhất 1 ảnh để xóa khỏi album.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => $id ? 'Xóa ảnh khỏi album này?' : 'Xóa các ảnh đã chọn khỏi album này?',
            'icon' => 'warning',
            'confirmButtonText' => 'Xóa khỏi album',
            'cancelButtonText' => 'Hủy',
            'method' => $id ? 'confirmDetachImage' : 'confirmBulkDetachImages',
            'id' => $id,
        ]);
    }

    #[On('confirmDetachImage')]
    public function confirmDetachImage(int $id): void
    {
        $image = AlbumImage::query()->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))->findOrFail($id);

        $image->albums()->detach($this->albumId);
        $this->selectedImageIds = array_values(array_diff($this->selectedImageIds, [$id]));
        $this->success('Đã xóa ảnh khỏi album.');
    }

    #[On('confirmBulkDetachImages')]
    public function confirmBulkDetachImages(): void
    {
        $ids = array_values(array_map('intval', $this->selectedImageIds));

        if (empty($ids)) {
            $this->warning('Vui lòng chọn ít nhất 1 ảnh để xóa khỏi album.');
            return;
        }

        $images = AlbumImage::query()
            ->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))
            ->whereKey($ids)
            ->get();

        foreach ($images as $image) {
            $image->albums()->detach($this->albumId);
        }

        $this->clearSelection();
        $this->success('Đã xóa các ảnh đã chọn khỏi album.');
    }
};
?>

<div>
    <x-slot:title>Album: {{ $this->album->name }}</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.album.index') }}" class="font-semibold text-slate-700">Danh sách album</a>
        <span class="mx-1">/</span>
        <span>{{ $this->album->name }}</span>
    </x-slot:breadcrumb>

    <x-header :title="'Ảnh trong album: ' . $this->album->name" class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:actions>
            <span class="font-semibold text-primary">Đã chọn: {{ count($selectedImageIds) }}</span>
            <x-button label="Chọn tất cả trang" class="btn-ghost" wire:click="toggleSelectPage" spinner="toggleSelectPage"/>
            <x-button label="Bỏ chọn" class="btn-ghost" wire:click="clearSelection" spinner="clearSelection"/>
            <x-button label="Xóa đã chọn khỏi album" icon="o-trash" class="btn-primary text-white" wire:click="requestDetachImage" spinner="requestDetachImage" :disabled="count($selectedImageIds) === 0"/>
        </x-slot:actions>
    </x-header>

    <div
        id="my-gallery"
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
        x-data="{
            lightbox: null,
            actionOverlay: null,
            getActiveImageMeta(pswp) {
                const element = pswp?.currSlide?.data?.element;

                return {
                    id: Number(element?.dataset?.imageId || 0) || null,
                    src: pswp?.currSlide?.data?.src || element?.href || '',
                    caption: element?.dataset?.imageCaption || element?.getAttribute('aria-label') || ''
                };
            },
            createActionOverlay(pswp) {
                this.removeActionOverlay();

                const overlay = document.createElement('div');
                overlay.className = 'pswp-admin-overlay';
                overlay.innerHTML = `
                    <div class='pointer-events-auto flex items-center gap-2 rounded-full bg-black/65 px-3 py-2 text-white shadow-2xl backdrop-blur'>
                        <button type='button' data-action='download' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Tải ảnh xuống' aria-label='Tải ảnh xuống'>
                            <span class='text-lg leading-none'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5'' stroke='currentColor' class='size-6'><path stroke-linecap='round' stroke-linejoin='round' d='M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3' /></svg></span>
                        </button>
                        <button type='button' data-action='delete' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-error/80 transition hover:bg-error' title='Xóa khỏi album' aria-label='Xóa khỏi album'>
                            <span class='text-lg leading-none'>
                                <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-6'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0' />
                                </svg>
                            </span>
                        </button>
                    </div>
                `;

                overlay.style.position = 'absolute';
                overlay.style.left = '50%';
                overlay.style.bottom = '24px';
                overlay.style.transform = 'translateX(-50%)';
                overlay.style.zIndex = '60';

                overlay.querySelector('[data-action=\'download\']')?.addEventListener('click', () => {
                    const image = this.getActiveImageMeta(pswp);

                    if (!image.src) {
                        return;
                    }

                    const link = document.createElement('a');
                    link.href = image.src;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.download = '';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                });

                overlay.querySelector('[data-action=\'delete\']')?.addEventListener('click', () => {
                    const image = this.getActiveImageMeta(pswp);

                    if (!image.id) {
                        return;
                    }

                    this.$wire.confirmDetachImage(image.id);
                    pswp.close();
                });

                pswp.element?.appendChild(overlay);
                this.actionOverlay = overlay;
            },
            removeActionOverlay() {
                this.actionOverlay?.remove();
                this.actionOverlay = null;
            },
            init() {
                if (typeof PhotoSwipeLightbox === 'undefined' || typeof PhotoSwipe === 'undefined') return;

                this.lightbox = new PhotoSwipeLightbox({
                    gallery: '#my-gallery',
                    children: 'a.pswp-item',
                    showHideAnimationType: 'fade',
                    pswpModule: PhotoSwipe,
                });

                this.lightbox.on('openingAnimationEnd', () => {
                    this.createActionOverlay(this.lightbox.pswp);
                });

                this.lightbox.on('change', () => {
                    this.removeActionOverlay();
                    this.createActionOverlay(this.lightbox.pswp);
                });

                this.lightbox.on('close', () => {
                    this.removeActionOverlay();
                });

                this.lightbox.init();
            }
        }"
    >
        @forelse($this->images as $image)
            <div class="relative" wire:key="gallery-image-{{ $image->id }}">
                <label class="absolute top-2 left-2 z-2 cursor-pointer rounded-full  px-2 py-1 text-white text-xs">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-md checkbox-primary checked:bg-primary! checked:text-white border-white border-2 bg-black/20"
                        value="{{ $image->id }}"
                        wire:model.live="selectedImageIds"
                    />
                </label>
                <div class="h-55">
                    <a
                        href="{{ Storage::url($image->image_path) }}"
                        data-pswp-width="1200"
                        data-pswp-height="800"
                        data-image-id="{{ $image->id }}"
                        data-image-url="{{ Storage::url($image->image_path) }}"
                        data-image-caption="{{ $image->caption }}"
                        class="pswp-item block w-full h-55 cursor-pointer group/img relative overflow-hidden rounded-lg {{ in_array($image->id, $selectedImageIds) ? 'ring-2 ring-primary ring-offset-2' : '' }}"
                    >
                        <img
                            src="{{ Storage::url($image->image_path) }}"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover/img:scale-105"
                            onload="this.parentNode.setAttribute('data-pswp-width', this.naturalWidth); this.parentNode.setAttribute('data-pswp-height', this.naturalHeight)"
                            loading="lazy"
                            alt="{{ $image->caption ?: 'image' }}"
                        />
                        <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/20 transition-all duration-300"></div>
                    </a>
                </div>

            </div>
        @empty
            <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4 py-12 text-center text-gray-500">
                Album chưa có ảnh nào.
            </div>
        @endforelse
    </div>

    @if($this->images->hasPages())
        <div class="mt-5">
            {{ $this->images->links() }}
        </div>
    @endif
</div>

