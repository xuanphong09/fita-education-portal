<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination, Toast, WithFileUploads;

    public int $albumId;
    public int $perPage = 20;
    public array $selectedImageIds = [];
    public bool $selectPage = false;
    public ?int $selectedImageId = null;
    public int $zoomLevel = 100;
    private const MAX_UPLOAD_IMAGES = 20;

    public bool $showUploadModal = false;
    public array $uploadImages = [];
    public ?string $uploadImagesError = null;
    public ?string $caption = null;
    public bool $showCaptionModal = false;
    public bool $isBulkCaption = false;
    public ?int $captionImageId = null;
    public string $editingCaption = '';
    public array $captionEditImageIds = [];

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

    public function getMaxUploadImagesProperty(): int
    {
        return self::MAX_UPLOAD_IMAGES;
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

    public function getUploadImagePreviewsProperty(): array
    {
        $previews = [];

        foreach ($this->uploadImages as $index => $file) {
            try {
                $previews[] = [
                    'key' => 'upload-preview-' . $index,
                    'index' => $index,
                    'name' => $file->getClientOriginalName(),
                    'url' => $file->temporaryUrl(),
                ];
            } catch (\Throwable $e) {
                \Log::error('Album upload preview error: ' . $e->getMessage());
            }
        }

        return $previews;
    }

    public function updatedUploadImages(): void
    {
        $this->uploadImagesError = null;

        if (! is_array($this->uploadImages)) {
            $this->uploadImages = [];
            return;
        }

        $count = count($this->uploadImages);

        if ($count > self::MAX_UPLOAD_IMAGES) {
            $this->uploadImages = array_values(array_slice($this->uploadImages, 0, self::MAX_UPLOAD_IMAGES));

            $this->warning(
                'Bạn chọn ' . $count . ' ảnh. Hệ thống chỉ giữ ' . self::MAX_UPLOAD_IMAGES . ' ảnh đầu tiên để tải lên.'
            );
        }
    }

    public function openUploadImages(): void
    {
        $this->resetImageForm();
        $this->showUploadModal = true;
    }

    public function saveImages(): void
    {
        $this->validate([
            'uploadImages' => 'required|array|min:1|max:' . self::MAX_UPLOAD_IMAGES,
            'uploadImages.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'caption' => 'nullable|string|max:255',
        ], [
            'uploadImages.required' => 'Vui lòng chọn ít nhất 1 ảnh.',
            'uploadImages.array' => 'Danh sách ảnh không hợp lệ.',
            'uploadImages.min' => 'Vui lòng chọn ít nhất 1 ảnh.',
            'uploadImages.max' => 'Chỉ được chọn tối đa ' . self::MAX_UPLOAD_IMAGES . ' ảnh/lần tải lên.',

            'uploadImages.*.image' => 'Mỗi tệp phải là hình ảnh hợp lệ.',
            'uploadImages.*.mimes' => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
            'uploadImages.*.max' => 'Mỗi ảnh không được vượt quá 4MB.',

            'caption.max' => 'Chú thích không được vượt quá 255 ký tự.',
        ]);

        foreach ($this->uploadImages as $file) {
            $image = AlbumImage::query()->create([
                'image_path' => $file->store('uploads/albums', 'public'),
                'caption' => $this->caption,
            ]);

            // Gắn ảnh vừa upload vào album hiện tại
            $image->albums()->syncWithoutDetaching([$this->albumId]);
        }

        $uploadedCount = count($this->uploadImages);

        $this->showUploadModal = false;
        $this->resetImageForm();
        $this->resetPage();

        $this->success("Đã tải lên {$uploadedCount} ảnh vào album.");
    }

    public function removeUploadImage(int $index): void
    {
        if (! array_key_exists($index, $this->uploadImages)) {
            return;
        }

        unset($this->uploadImages[$index]);

        $this->uploadImages = array_values($this->uploadImages);
        $this->uploadImagesError = null;
    }

    public function clearUploadImages(): void
    {
        $this->uploadImages = [];
        $this->uploadImagesError = null;
        $this->resetValidation(['uploadImages', 'uploadImages.*']);
    }

    public function closeUploadModal(): void
    {
        $this->showUploadModal = false;
        $this->resetImageForm();
    }

    public function updatedShowUploadModal(bool $value): void
    {
        if (! $value) {
            $this->resetImageForm();
        }
    }

    protected function resetImageForm(): void
    {
        $this->reset([
            'uploadImages',
            'uploadImagesError',
            'caption',
        ]);

        $this->resetErrorBag();
    }

    public function openEditCaption(int $id): void
    {
        $image = AlbumImage::query()
            ->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))
            ->findOrFail($id);

        $this->isBulkCaption = false;
        $this->captionImageId = $image->id;
        $this->editingCaption = $image->caption ?? '';
        $this->showCaptionModal = true;

        $this->resetValidation(['editingCaption']);
    }

    public function openBulkCaptionEdit(): void
    {
        if (empty($this->selectedImageIds)) {
            $this->warning('Vui lòng chọn ít nhất 1 ảnh để sửa chú thích.');
            return;
        }

        $this->isBulkCaption = true;
        $this->captionImageId = null;
        $this->editingCaption = '';
        $this->showCaptionModal = true;

        $this->resetValidation(['editingCaption']);
    }

    public function saveCaption(): void
    {
        $this->validate([
            'editingCaption' => 'nullable|string|max:255',
        ], [
            'editingCaption.max' => 'Chú thích không được vượt quá 255 ký tự.',
        ]);

        if ($this->isBulkCaption) {
            $ids = array_values(array_unique(array_map('intval', $this->selectedImageIds)));

            if (empty($ids)) {
                $this->warning('Không có ảnh nào được chọn.');
                return;
            }

            $updatedCount = AlbumImage::query()
                ->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))
                ->whereKey($ids)
                ->update([
                    'caption' => blank($this->editingCaption) ? null : trim($this->editingCaption),
                ]);

            $this->clearSelection();

            $message = "Đã cập nhật chú thích cho {$updatedCount} ảnh.";
        } else {
            if (! $this->captionImageId) {
                $this->warning('Không xác định được ảnh cần sửa.');
                return;
            }

            $image = AlbumImage::query()
                ->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))
                ->findOrFail($this->captionImageId);

            $image->update([
                'caption' => blank($this->editingCaption) ? null : trim($this->editingCaption),
            ]);

            $message = 'Đã cập nhật chú thích ảnh.';
        }

        $this->closeCaptionModal();
        $this->success($message);
    }

    public function closeCaptionModal(): void
    {
        $this->showCaptionModal = false;
        $this->isBulkCaption = false;
        $this->captionImageId = null;
        $this->editingCaption = '';

        $this->resetValidation(['editingCaption']);
    }
    public function getCaptionModalImagesProperty()
    {
        if ($this->isBulkCaption) {
            $ids = array_values(array_map('intval', $this->selectedImageIds));

            if (empty($ids)) {
                return collect();
            }

            return AlbumImage::query()
                ->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))
                ->whereKey($ids)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['id', 'image_path', 'caption']);
        }

        if (! $this->captionImageId) {
            return collect();
        }

        return AlbumImage::query()
            ->whereHas('albums', fn ($query) => $query->whereKey($this->albumId))
            ->whereKey($this->captionImageId)
            ->get(['id', 'image_path', 'caption']);
    }
    public function removeImageFromCaptionEdit(int $id): void
    {
        $currentCount = $this->isBulkCaption
            ? count($this->selectedImageIds)
            : ($this->captionImageId ? 1 : 0);

        if ($currentCount <= 1) {
            $this->warning('Phải giữ lại ít nhất 1 ảnh để sửa chú thích.');
            return;
        }

        if ($this->isBulkCaption) {
            $this->selectedImageIds = array_values(array_diff(
                array_map('intval', $this->selectedImageIds),
                [(int) $id]
            ));

            return;
        }

        $this->warning('Không thể xóa ảnh duy nhất khỏi danh sách sửa.');
    }
};
?>

<div>
    <x-slot:title>Album: {{ $this->album->name }}</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.album.index') }}" class="font-semibold text-slate-700" wire:navigate>Danh sách album</a>
        <span class="mx-1">/</span>
        <span>{{ $this->album->name }}</span>
    </x-slot:breadcrumb>

    <x-header :title="'Ảnh trong album: ' . $this->album->name" class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:actions>
            <span class="font-semibold text-primary">Đã chọn: {{ count($selectedImageIds) }}</span>
            <x-button label="Chọn tất cả trang" class="btn-ghost" wire:click="toggleSelectPage" spinner="toggleSelectPage"/>
            <x-button label="Bỏ chọn" class="btn-ghost" wire:click="clearSelection" spinner="clearSelection"/>
            <x-button
                label="Sửa chú thích"
                icon="o-pencil-square"
                class="btn-outline"
                wire:click="openBulkCaptionEdit"
                spinner="openBulkCaptionEdit"
                :disabled="count($selectedImageIds) === 0"
            />
            <x-button label="Xóa đã chọn khỏi album" icon="o-trash" class="btn-error text-white" wire:click="requestDetachImage" spinner="requestDetachImage" :disabled="count($selectedImageIds) === 0"/>
            <x-button icon="o-arrow-up-tray" class="btn-primary text-white" label="Tải ảnh lên" wire:click="openUploadImages" spinner/>
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

                const image = this.getActiveImageMeta(pswp);

                const overlay = document.createElement('div');
                overlay.className = 'pswp-admin-overlay';
                overlay.innerHTML = `
                    <div class='pointer-events-auto flex flex-col items-center gap-2'>
                        ${image.caption ? `
                            <div class='max-w-[80vw] rounded-xl bg-black/65 px-4 py-2 text-center text-sm font-medium text-white shadow-2xl backdrop-blur'>
                                ${image.caption}
                            </div>
                        ` : ''}

                        <div class='flex items-center gap-2 rounded-full bg-black/65 px-3 py-2 text-white shadow-2xl backdrop-blur'>
                            <button type='button' data-action='caption' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Sửa chú thích' aria-label='Sửa chú thích'>
                                <span class='text-lg leading-none'>
                                    <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-6'>
                                        <path stroke-linecap='round' stroke-linejoin='round' d='m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z' />
                                        <path stroke-linecap='round' stroke-linejoin='round' d='M19.5 7.125 16.875 4.5' />
                                    </svg>
                                </span>
                            </button>
                            <button type='button' data-action='download' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Tải ảnh xuống' aria-label='Tải ảnh xuống'>
                                <span class='text-lg leading-none'>
                                    <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-6'>
                                        <path stroke-linecap='round' stroke-linejoin='round' d='M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3' />
                                    </svg>
                                </span>
                            </button>

                            <button type='button' data-action='delete' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-error/80 transition hover:bg-error' title='Xóa khỏi album' aria-label='Xóa khỏi album'>
                                <span class='text-lg leading-none'>
                                    <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-6'>
                                        <path stroke-linecap='round' stroke-linejoin='round' d='m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0' />
                                    </svg>
                                </span>
                            </button>
                        </div>
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

                overlay.querySelector('[data-action=\'caption\']')?.addEventListener('click', () => {
                    const image = this.getActiveImageMeta(pswp);

                    if (!image.id) {
                        return;
                    }

                    this.$wire.openEditCaption(image.id);
                    pswp.close();
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
            <div class="relative group/card" wire:key="gallery-image-{{ $image->id }}"
                 x-data="{
         w: 1200,
         h: 800,
         init() {
             // Đảm bảo lấy đúng kích thước kể cả khi ảnh đã được trình duyệt cache từ trước
             this.$nextTick(() => {
                 let img = this.$refs.img;
                 if(img && img.complete && img.naturalWidth) {
                     this.w = img.naturalWidth;
                     this.h = img.naturalHeight;
                 }
             });
         }
     }">
                <label class="absolute top-2 left-2 z-2 cursor-pointer rounded-full px-2 py-1 text-white text-xs">
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
                        x-bind:data-pswp-width="w"
                        x-bind:data-pswp-height="h"
                        data-image-id="{{ $image->id }}"
                        data-image-url="{{ Storage::url($image->image_path) }}"
                        data-image-caption="{{ $image->caption }}"
                        class="pswp-item block w-full h-55 cursor-pointer group/img relative overflow-hidden rounded-lg {{ in_array($image->id, $selectedImageIds) ? 'ring-2 ring-primary ring-offset-2' : '' }}"
                    >
                        <img
                            x-ref="img"
                            x-on:load="w = $event.target.naturalWidth; h = $event.target.naturalHeight"
                            src="{{ Storage::url($image->image_path) }}"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover/img:scale-105"
                            loading="lazy"
                            alt="{{ $image->caption ?: 'image' }}"
                        />
                        <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/20 transition-all duration-300"></div>
                        @if(filled($image->caption))
                            <div class="pointer-events-none absolute inset-x-0 bottom-0 p-3 translate-y-full group-hover/img:translate-y-0 transition-transform duration-300 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                                <p class="text-white text-sm font-semibold leading-snug line-clamp-2">
                                    {{ $image->caption }}
                                </p>
                            </div>
                        @endif
                    </a>
                    <div class="absolute top-2 right-2 z-2 flex flex-col gap-2 opacity-0 scale-90 transition-all duration-300 group-hover/card:opacity-100 group-hover/card:scale-100">
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-primary shadow-md opacity-0 scale-90 transition-all duration-300 hover:bg-primary hover:text-white group-hover/card:opacity-100 group-hover/card:scale-100"
                            wire:click.stop="openEditCaption({{ $image->id }})"
                            wire:loading.attr="disabled"
                            wire:target="openEditCaption({{ $image->id }})"
                            title="Sửa chú thích"
                        >
                            <x-icon name="o-pencil-square" class="w-5 h-5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-error/90 text-white shadow-md hover:bg-error"
                            wire:click.stop="confirmDetachImage({{ $image->id }})"
                            wire:loading.attr="disabled"
                            wire:target="confirmDetachImage({{ $image->id }})"
                            title="Xóa ảnh"
                        >
                            <x-icon name="o-trash" class="w-5 h-5" />
                        </button>
                    </div>
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

    <x-modal wire:model="showUploadModal" title="Tải ảnh vào album" separator class="modalAddImage">
        <div class="space-y-0">
            <div class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-slate-600">
                Ảnh tải lên sẽ được thêm trực tiếp vào album:
                <span class="font-semibold text-primary">{{ $this->album->name }}</span>
            </div>

            <x-file
                label="Ảnh"
                wire:model="uploadImages"
                multiple
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                hint="Có thể chọn tối đa 20 ảnh, mỗi ảnh tối đa 4MB."
            />

            <div wire:loading wire:target="uploadImages" class="text-sm text-primary">
                Đang tải ảnh lên để xem trước...
            </div>

            @if(!empty($uploadImagesError))
                <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                    {{ $uploadImagesError }}
                </div>
            @endif

            @error('uploadImages')
            <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                {{ $message }}
            </div>
            @enderror

            @error('uploadImages.*')
            <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                {{ $message }}
            </div>
            @enderror

            <x-input
                label="Chú thích"
                wire:model.live.debounce.300ms="caption"
                placeholder="Nhập chú thích chung cho các ảnh nếu có..."
                maxlength="255"
            />

            @error('caption')
            <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                {{ $message }}
            </div>
            @enderror

            @if(!empty($this->uploadImagePreviews))
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <p class="text-sm font-medium">
                            Xem trước {{ count($this->uploadImagePreviews) }}/{{ $this->maxUploadImages }} ảnh
                        </p>

                        <x-button
                            label="Xóa tất cả"
                            class="btn-ghost btn-sm"
                            wire:click="clearUploadImages"
                            spinner="clearUploadImages"
                        />
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 max-h-80 overflow-y-auto pr-1">
                        @foreach($this->uploadImagePreviews as $preview)
                            <div
                                class="relative overflow-hidden rounded-lg border border-gray-200 bg-white"
                                wire:key="{{ $preview['key'] }}"
                            >
                                <button
                                    type="button"
                                    class="absolute top-2 right-2 z-2 btn btn-circle btn-xs btn-error text-white"
                                    wire:click="removeUploadImage({{ $preview['index'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="removeUploadImage"
                                    title="Xóa ảnh khỏi danh sách"
                                >
                                    ✕
                                </button>

                                <img
                                    src="{{ $preview['url'] }}"
                                    alt="{{ $preview['name'] }}"
                                    class="h-32 w-full object-cover"
                                    loading="lazy"
                                />

                                <div
                                    class="px-2 py-1 text-xs text-gray-600 truncate"
                                    title="{{ $preview['name'] }}"
                                >
                                    {{ $preview['name'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button
                label="Hủy"
                wire:click="closeUploadModal"
            />

            <x-button
                label="Tải lên"
                class="btn-primary text-white"
                wire:click="saveImages"
                spinner="saveImages"
                :disabled="empty($uploadImages)"
            />
        </x-slot:actions>
    </x-modal>
    <x-modal wire:model="showCaptionModal" title="{{ $isBulkCaption ? 'Sửa chú thích nhiều ảnh' : 'Sửa chú thích ảnh' }}" separator class="modalAddImage">
        <div class="space-y-4">
            @php
                $captionImages = $this->captionModalImages;
                $captionImageCount = $captionImages->count();
            @endphp

            <div class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-slate-600">
                @if($isBulkCaption)
                    Bạn đang sửa chú thích cho
                    <span class="font-semibold text-primary">{{ count($selectedImageIds) }}</span>
                    ảnh đã chọn.
                    Chú thích mới sẽ thay thế chú thích hiện tại của các ảnh này.
                @else
                    Bạn đang sửa chú thích cho ảnh này.
                @endif
            </div>

            @if($captionImages->isNotEmpty())
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-700">
                            Ảnh sẽ được cập nhật chú thích
                            <span class="text-primary">({{ $captionImageCount }})</span>
                        </p>

                        @if($captionImageCount <= 1)
                            <span class="text-xs text-slate-400">
                            Phải giữ lại ít nhất 1 ảnh
                        </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 max-h-72 overflow-y-auto pr-1">
                        @foreach($captionImages as $captionImage)
                            <div
                                class="relative overflow-hidden rounded-lg border border-gray-200 bg-white"
                                wire:key="caption-edit-image-{{ $captionImage->id }}"
                            >
                                <img
                                    src="{{ Storage::url($captionImage->image_path) }}"
                                    alt="{{ $captionImage->caption ?: 'image' }}"
                                    class="h-32 w-full object-cover"
                                    loading="lazy"
                                />

                                <div class="px-2 py-1 text-xs text-gray-600 line-clamp-2 min-h-8">
                                    {{ $captionImage->caption ?: 'Chưa có chú thích' }}
                                </div>

                                <button
                                    type="button"
                                    class="absolute top-2 right-2 btn btn-circle btn-xs text-white
                                    {{ $captionImageCount <= 1 ? 'btn-disabled bg-gray-400 border-gray-400' : 'btn-error' }}"
                                    wire:click="removeImageFromCaptionEdit({{ $captionImage->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="removeImageFromCaptionEdit({{ $captionImage->id }})"
                                    title="{{ $captionImageCount <= 1 ? 'Phải giữ lại ít nhất 1 ảnh' : 'Bỏ ảnh này khỏi danh sách sửa' }}"
                                    @disabled($captionImageCount <= 1)
                                >
                                    ✕
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-input
                label="Chú thích mới"
                wire:model.live.debounce.300ms="editingCaption"
                placeholder="Nhập chú thích cho ảnh..."
                maxlength="255"
            />

            @error('editingCaption')
            <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                {{ $message }}
            </div>
            @enderror
        </div>

        <x-slot:actions>
            <x-button
                label="Hủy"
                wire:click="closeCaptionModal"
            />

            <x-button
                label="Lưu chú thích"
                class="btn-primary text-white"
                wire:click="saveCaption"
                spinner="saveCaption"
                :disabled="$this->captionModalImages->count() === 0"
            />
        </x-slot:actions>
    </x-modal>
</div>

