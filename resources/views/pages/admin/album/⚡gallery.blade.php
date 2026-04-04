<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination, WithFileUploads, Toast;

    private const MAX_UPLOAD_IMAGES = 20;


    public string $imagePerPage = '40';
    public string $assignmentFilter = 'all';

    public bool $showUploadModal = false;
    public array $images = [];
    public ?string $caption = null;
    public ?int $uploadAlbumId = null;
    public ?string $uploadImagesError = null;

    public bool $showMoveImageModal = false;
    public ?int $selectedImageId = null;
    public ?int $moveTargetAlbumId = null;
    public array $selectedImageIds = [];
    public bool $isBulkMove = false;

    public function getAlbumOptionsProperty(): array
    {
        return Album::query()
            ->orderBy('order')
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->map(fn (Album $album) => ['id' => $album->id, 'name' => $album->name])
            ->toArray();
    }

    public function getImagePerPageOptionsProperty(): array
    {
        return [
            ['id' => '20', 'name' => '20 / trang'],
            ['id' => '40', 'name' => '40 / trang'],
            ['id' => '60', 'name' => '60 / trang'],
        ];
    }

    public function getAllImagesProperty()
    {
        $perPage = (int) $this->imagePerPage;

        return AlbumImage::query()
            ->with('albums:id,name')
            ->when($this->assignmentFilter === 'assigned', fn ($query) => $query->whereHas('albums'))
            ->when($this->assignmentFilter === 'unassigned', fn ($query) => $query->whereDoesntHave('albums'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'allImagesPage');
    }

    public function getCurrentPageImageIdsProperty(): array
    {
        return $this->allImages
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function getUploadImagePreviewsProperty(): array
    {
        $previews = [];

        foreach ($this->images as $index => $file) {
            try {
                $previews[] = [
                    'key' => 'preview-' . $index,
                    'index' => $index,
                    'name' => $file->getClientOriginalName(),
                    'url' => $file->temporaryUrl(),
                ];
            } catch (\Throwable $e) {
                \Log::error('Upload preview error: ' . $e->getMessage());
            }
        }

        return $previews;
    }

    public function getCurrentImageProperty(): ?AlbumImage
    {
        if (!$this->selectedImageId) {
            return null;
        }

        return AlbumImage::query()->with('albums:id,name')->find($this->selectedImageId);
    }

    public function getSelectedImagesForMoveProperty()
    {
        if (empty($this->selectedImageIds)) {
            return collect();
        }

        return AlbumImage::query()
            ->whereKey($this->selectedImageIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'image_path', 'caption']);
    }


    public function updatedAssignmentFilter(): void
    {
        $this->resetPage('allImagesPage');
        $this->selectedImageIds = [];
    }

    public function updatedImagePerPage($value): void
    {
        $allowed = [20, 40, 60];
        $normalized = (int) $value;

        $this->imagePerPage = in_array($normalized, $allowed, true)
            ? (string) $normalized
            : '40';

        $this->resetPage('allImagesPage');
        $this->selectedImageIds = [];
    }

    public function updatedImages($value): void
    {
        $this->uploadImagesError = null;

        if (!is_array($this->images)) {
            $this->images = [];
            return;
        }

        $count = count($this->images);

        if ($count > self::MAX_UPLOAD_IMAGES) {
//            $this->uploadImagesError = 'Bạn chọn ' . $count . ' ảnh. Hệ thống sẽ giữ ' . self::MAX_UPLOAD_IMAGES . ' ảnh đầu tiên để tải lên.';
            $this->images = array_values(array_slice($this->images, 0, self::MAX_UPLOAD_IMAGES));
            $this->warning('Bạn chọn ' . $count . ' ảnh. Hệ thống sẽ giữ ' . self::MAX_UPLOAD_IMAGES . ' ảnh đầu tiên để tải lên.');
        }
    }

    public function openUploadImages(): void
    {
        $this->resetImageForm();
        $this->uploadAlbumId = null;
        $this->showUploadModal = true;
    }

    public function toggleSelectCurrentPage(): void
    {
        $currentPageIds = $this->currentPageImageIds;

        if (empty($currentPageIds)) {
            return;
        }

        $allCurrentPageSelected = count(array_diff($currentPageIds, $this->selectedImageIds)) === 0;

        if ($allCurrentPageSelected) {
            $this->selectedImageIds = array_values(array_diff($this->selectedImageIds, $currentPageIds));
            return;
        }

        $this->selectedImageIds = array_values(array_unique(array_merge($this->selectedImageIds, $currentPageIds)));
    }

    public function clearImageSelection(): void
    {
        $this->selectedImageIds = [];
    }

    public function requestBulkDelete(): void
    {
        if (empty($this->selectedImageIds)) {
            $this->warning('Vui lòng chọn ít nhất 1 ảnh.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Xóa các ảnh đã chọn?',
            'icon' => 'warning',
            'confirmButtonText' => 'Xóa',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkDeleteImages',
        ]);
    }

    #[On('confirmBulkDeleteImages')]
    public function confirmBulkDeleteImages(): void
    {
        if (empty($this->selectedImageIds)) {
            $this->warning('Không có ảnh nào để xóa.');
            return;
        }

        $images = AlbumImage::query()
            ->whereKey($this->selectedImageIds)
            ->get(['id', 'image_path']);

        foreach ($images as $image) {
            if (!empty($image->image_path) && Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        }

        AlbumImage::query()->whereKey($this->selectedImageIds)->forceDelete();

        $deletedCount = $images->count();
        $this->selectedImageIds = [];

        $this->resetPage('allImagesPage');
        $this->success("Đã xóa {$deletedCount} ảnh.");
    }

    public function openMoveImage(int $id): void
    {
        $image = AlbumImage::query()->with('albums:id')->findOrFail($id);

        $this->isBulkMove = false;
        $this->selectedImageId = $image->id;
        $this->moveTargetAlbumId = $image->albums->pluck('id')->map(fn ($id) => (int) $id)->first();
        $this->showMoveImageModal = true;
    }

    public function openBulkMoveSelectedImages(): void
    {
        if (empty($this->selectedImageIds)) {
            $this->warning('Vui lòng chọn ít nhất 1 ảnh.');
            return;
        }

        $this->isBulkMove = true;
        $this->selectedImageId = null;
        $this->moveTargetAlbumId = null;
        $this->showMoveImageModal = true;
    }

    public function saveMoveImage(): void
    {
        $this->validate([
            'moveTargetAlbumId' => 'required|integer|exists:albums,id',
        ],
        [
            'moveTargetAlbumId.required' => 'Vui lòng chọn album đích.',
            'moveTargetAlbumId.exists' => 'Album đích không tồn tại.',
        ]
        );

        if ($this->isBulkMove) {
            if (empty($this->selectedImageIds)) {
                $this->warning('Không có ảnh nào được chọn để chuyển.');
                return;
            }

            $images = AlbumImage::query()->whereKey($this->selectedImageIds)->get();

            foreach ($images as $image) {
                $image->albums()->syncWithoutDetaching([$this->moveTargetAlbumId]);
            }

            $count = count($this->selectedImageIds);
            $this->selectedImageIds = [];
            $message = "Đã thêm {$count} ảnh vào album.";
        } else {
            if (!$this->selectedImageId) {
                $this->warning('Không xác định được ảnh cần chuyển.');
                return;
            }

            $image = AlbumImage::query()->findOrFail($this->selectedImageId);
            $image->albums()->syncWithoutDetaching([$this->moveTargetAlbumId]);

            $message = 'Đã thêm ảnh vào album.';
        }

        $this->showMoveImageModal = false;
        $this->selectedImageId = null;
        $this->moveTargetAlbumId = null;
        $this->isBulkMove = false;

        $this->resetPage('allImagesPage');
        $this->success($message);
    }

    public function saveImages(): void
    {
        $this->validate([
            'images' => 'required|array|min:1|max:' . self::MAX_UPLOAD_IMAGES,
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'caption' => 'nullable|string|max:255',
            'uploadAlbumId' => 'nullable|integer|exists:albums,id',
        ], [
            'images.required' => 'Vui lòng chọn ít nhất 1 ảnh.',
            'images.max' => 'Chỉ được chọn tối đa ' . self::MAX_UPLOAD_IMAGES . ' ảnh/lần tải lên.',
            'images.*.image' => 'Mỗi tệp phải là hình ảnh hợp lệ.',
            'images.*.mimes' => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
            'images.*.max' => 'Mỗi ảnh không được vượt quá 4MB.',
        ]);

        foreach ($this->images as $file) {
            $image = AlbumImage::query()->create([
                'image_path' => $file->store('uploads/albums', 'public'),
                'caption' => $this->caption,
            ]);

            if (!empty($this->uploadAlbumId)) {
                $image->albums()->syncWithoutDetaching([$this->uploadAlbumId]);
            }
        }

        $this->showUploadModal = false;
        $this->resetImageForm();
        $this->success('Tải ảnh lên thành công.');
    }

    public function closeUploadModal(): void
    {
        $this->showUploadModal = false;
        $this->resetImageForm();
    }

    public function removeUploadImage(int $index): void
    {
        if (!array_key_exists($index, $this->images)) {
            return;
        }

        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function clearUploadImages(): void
    {
        $this->images = [];
        $this->uploadImagesError = null;
    }

    public function closeMoveImageModal(): void
    {
        $this->showMoveImageModal = false;
        $this->selectedImageId = null;
        $this->moveTargetAlbumId = null;
        $this->isBulkMove = false;
        $this->resetErrorBag();
    }

    public function updatedShowUploadModal(bool $value): void
    {
        if (!$value) {
            $this->resetImageForm();
        }
    }

    public function deleteImage(int $id): void
    {
        $image = AlbumImage::query()->findOrFail($id);

        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->forceDelete();
        $this->selectedImageIds = array_values(array_diff($this->selectedImageIds, [$id]));
        $this->success('Đã xóa ảnh.');
    }


    protected function resetImageForm(): void
    {
        $this->reset(['images', 'caption', 'uploadAlbumId', 'uploadImagesError']);
        $this->resetErrorBag();
    }
};
?>

<div>
    <x-slot:title>Thư viện ảnh</x-slot:title>

    <x-slot:breadcrumb>
        <span>Thư viện ảnh</span>
    </x-slot:breadcrumb>

    <x-header title="Thư viện ảnh" class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:actions>
{{--            <x-button--}}
{{--                icon="o-check-circle"--}}
{{--                class="btn-outline"--}}
{{--                :label="count($selectedImageIds) > 0 ? 'Đã chọn: ' . count($selectedImageIds) : 'Chưa chọn ảnh'"--}}
{{--                disabled--}}
{{--            />--}}
            <span class="font-semibold text-primary">{{count($selectedImageIds) > 0 ? 'Đã chọn: ' . count($selectedImageIds) : ''}}</span>
            <x-button label="Chọn tất cả trang" class="btn-ghost" wire:click="toggleSelectCurrentPage" spinner="toggleSelectCurrentPage"/>
            <x-button label="Bỏ chọn" class="btn-ghost" wire:click="clearImageSelection" spinner="clearImageSelection"/>
            <x-button
                icon="o-folder-arrow-down"
                label="Thêm vào album"
                class="btn-primary text-white"
                wire:click="openBulkMoveSelectedImages"
                spinner="openBulkMoveSelectedImages"
                :disabled="count($selectedImageIds) === 0"
            />
            <x-button
                icon="o-trash"
                label="Xóa đã chọn"
                class="btn-error text-white"
                wire:click="requestBulkDelete"
                spinner="requestBulkDelete"
                :disabled="count($selectedImageIds) === 0"
            />
            <x-select
                wire:model.live="imagePerPage"
                :options="$this->imagePerPageOptions"
                option-value="id"
                option-label="name"
                class="w-30"
            />
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

                const overlay = document.createElement('div');
                overlay.className = 'pswp-admin-overlay';
                overlay.innerHTML = `
                    <div class='pointer-events-auto flex items-center gap-2 rounded-full bg-black/65 px-3 py-2 text-white shadow-2xl backdrop-blur'>
                        <button type='button' data-action='download' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Tải ảnh xuống' aria-label='Tải ảnh xuống'>
                            <span class='text-lg leading-none'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5'' stroke='currentColor' class='size-6'><path stroke-linecap='round' stroke-linejoin='round' d='M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3' /></svg></span>
                        </button>
                        <button type='button' data-action='move' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Thêm vào album' aria-label='Thêm vào album'>
                            <span class='text-lg leading-none'>
                                <svg width='24' height='24' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink'>
                                <rect width='20' height='20' fill='url(#pattern0_3342_415)'/>
                                <defs>
                                    <pattern id='pattern0_3342_415' patternContentUnits='objectBoundingBox' width='1' height='1'>
                                        <use xlink:href='#image0_3342_415' transform='scale(0.00195312)'/>
                                    </pattern>
                                    <image id='image0_3342_415' width='512' height='512' preserveAspectRatio='none' xlink:href='data:image/png;base64,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'/>
                                </defs>
                                </svg>
                            </span>
                        </button>
                        <button type='button' data-action='delete' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-error/80 transition hover:bg-error' title='Xóa ảnh' aria-label='Xóa ảnh'>
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

                overlay.querySelector('[data-action=\'move\']')?.addEventListener('click', () => {
                    const image = this.getActiveImageMeta(pswp);

                    if (!image.id) {
                        return;
                    }

                    this.$wire.openMoveImage(image.id);
                    pswp.close();
                });

                overlay.querySelector('[data-action=\'delete\']')?.addEventListener('click', () => {
                    const image = this.getActiveImageMeta(pswp);

                    if (!image.id) {
                        return;
                    }

                    this.$wire.deleteImage(image.id);
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
        @forelse($this->allImages as $image)
            <div class="relative" wire:key="gallery-image-{{ $image->id }}">
                <label class="absolute top-2 left-2 z-2 cursor-pointer rounded-full  px-2 py-1 text-white text-xs">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-md checkbox-primary checked:bg-primary! checked:text-white border-white border-2 bg-black/20"
                        value="{{ $image->id }}"
                        wire:model.live="selectedImageIds"
                    />
{{--                    <x-checkbox class="checkbox-sm checkbox-primary" value="{{ $image->id }}"--}}
{{--                                wire:model.live="selectedImageIds"></x-checkbox>--}}
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
            <div class="col-span-full py-10 text-center text-gray-500">
                Không có ảnh nào. Hãy thử tải lên một vài ảnh để bắt đầu quản lý thư viện của bạn!
            </div>
        @endforelse
    </div>

    @if($this->allImages->hasPages())
        <div class="mt-5">
            {{ $this->allImages->links() }}
        </div>
    @endif

    <x-modal wire:model="showUploadModal" title="Tải ảnh" separator class="modalAddImage">
        <div class="space-y-3">
            <x-file label="Ảnh" wire:model="images" multiple accept="image/*" hint="Có thể chọn tối đa 20 ảnh cùng lúc."/>
            @if(!empty($uploadImagesError))
                <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                    {{ $uploadImagesError }}
                </div>
            @endif
            @error('images')
                <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">{{ $message }}</div>
            @enderror
            @error('images.*')
                <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">{{ $message }}</div>
            @enderror
            @if(!empty($this->uploadImagePreviews))
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <p class="text-sm font-medium">Xem trước ({{ count($this->uploadImagePreviews) }}/20 ảnh)</p>
                        <x-button label="Xóa tất cả" class="btn-ghost btn-sm" wire:click="clearUploadImages" spinner="clearUploadImages"/>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 max-h-72 overflow-y-auto pr-1">
                        @foreach($this->uploadImagePreviews as $preview)
                            <div class="relative rounded-lg border border-gray-200 overflow-hidden bg-white" wire:key="{{ $preview['key'] }}">
                                <button
                                    type="button"
                                    class="absolute top-2 right-2 btn btn-circle btn-xs btn-error text-white"
                                    wire:click="removeUploadImage({{ $preview['index'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="removeUploadImage"
                                    title="Xóa ảnh khỏi danh sách"
                                >
                                    ✕
                                </button>
                                <img src="{{ $preview['url'] }}" alt="{{ $preview['name'] }}" class="w-full h-40 object-cover" loading="lazy"/>
                                <div class="px-2 py-1 text-xs text-gray-600 truncate" title="{{ $preview['name'] }}">{{ $preview['name'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showUploadModal=false"/>
            <x-button label="Tải lên" class="btn-primary" wire:click="saveImages" spinner="saveImages" :disabled="empty($images)"/>
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showMoveImageModal" title="Thêm ảnh vào album" separator class="modalAddImage">
        <div class="space-y-0 mt-0">
            @if($isBulkMove)
                <div class="rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-primary">
                    Đang chọn <strong>{{ count($selectedImageIds) }}</strong> ảnh.
                </div>
            @endif

            <x-select
                label="Album"
                wire:model="moveTargetAlbumId"
                :options="$this->albumOptions"
                option-value="id"
                option-label="name"
                placeholder="Chọn album"
            />

            @if($this->currentImage)
                <div class="rounded-lg border border-gray-200 overflow-hidden mt-3">
                    <img src="{{ Storage::url($this->currentImage->image_path) }}" alt="{{ $this->currentImage->caption ?: 'image' }}" class="h-56 object-cover" />
                </div>
            @elseif($isBulkMove && !empty($selectedImageIds))
                @if($this->selectedImagesForMove->isNotEmpty())
                    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-90 overflow-y-auto pr-1 mt-3">
                        @foreach($this->selectedImagesForMove as $selectedImage)
                            <div class="rounded-lg border border-gray-200 overflow-hidden bg-white" wire:key="move-preview-{{ $selectedImage->id }}">
                                <img
                                    src="{{ Storage::url($selectedImage->image_path) }}"
                                    alt="{{ $selectedImage->caption ?: 'image' }}"
                                    class="w-full h-56 object-cover"
                                    loading="lazy"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
        <x-slot:actions>
            <x-button label="Hủy" wire:click="closeMoveImageModal"/>
            <x-button label="Lưu" class="btn-primary" wire:click="saveMoveImage" spinner="saveMoveImage"/>
        </x-slot:actions>
    </x-modal>

</div>








