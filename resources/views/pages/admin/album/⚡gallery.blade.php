<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

    public bool $showCaptionModal = false;
    public bool $isBulkCaption = false;
    public ?int $captionImageId = null;
    public string $editingCaption = '';
    public array $captionEditImageIds = [];

    public function getMaxUploadImagesProperty(): int
    {
        return self::MAX_UPLOAD_IMAGES;
    }

    public function getAlbumOptionsProperty(): array
    {
        return Album::query()
            ->orderBy('order')
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->map(fn (Album $album) => [
                'id' => (int) $album->id,
                'name' => $album->name,
            ])
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

    public function getAssignmentFilterOptionsProperty(): array
    {
        return [
            ['id' => 'all', 'name' => 'Tất cả ảnh'],
            ['id' => 'assigned', 'name' => 'Đã gắn album'],
            ['id' => 'unassigned', 'name' => 'Chưa gắn album'],
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
                    'key' => 'upload-preview-' . $index,
                    'index' => $index,
                    'name' => $file->getClientOriginalName(),
                    'url' => $file->temporaryUrl(),
                ];
            } catch (\Throwable $e) {
                \Log::error('Image library upload preview error: ' . $e->getMessage());
            }
        }

        return $previews;
    }

    public function getCurrentImageProperty(): ?AlbumImage
    {
        if (! $this->selectedImageId) {
            return null;
        }

        return AlbumImage::query()
            ->with('albums:id,name')
            ->find($this->selectedImageId);
    }

    public function getSelectedImagesForMoveProperty()
    {
        if (empty($this->selectedImageIds)) {
            return collect();
        }

        return AlbumImage::query()
            ->whereKey(array_values(array_unique(array_map('intval', $this->selectedImageIds))))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'image_path', 'caption']);
    }

    public function getCaptionModalImagesProperty()
    {
        if (! $this->showCaptionModal) {
            return collect();
        }

        $ids = array_values(array_unique(array_map('intval', $this->captionEditImageIds)));

        if (empty($ids)) {
            return collect();
        }

        return AlbumImage::query()
            ->whereKey($ids)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'image_path', 'caption']);
    }

    public function updatedAssignmentFilter(): void
    {
        if (! in_array($this->assignmentFilter, ['all', 'assigned', 'unassigned'], true)) {
            $this->assignmentFilter = 'all';
        }

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

    public function updatedImages(): void
    {
        $this->uploadImagesError = null;

        if (! is_array($this->images)) {
            $this->images = [];
            return;
        }

        $count = count($this->images);

        if ($count > self::MAX_UPLOAD_IMAGES) {
            $this->images = array_values(array_slice($this->images, 0, self::MAX_UPLOAD_IMAGES));

            $this->warning(
                'Bạn chọn ' . $count . ' ảnh. Hệ thống chỉ giữ ' . self::MAX_UPLOAD_IMAGES . ' ảnh đầu tiên để tải lên.'
            );
        }
    }

    public function openUploadImages(): void
    {
        $this->resetImageForm();
        $this->uploadAlbumId = null;
        $this->showUploadModal = true;
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
            'images.array' => 'Danh sách ảnh không hợp lệ.',
            'images.min' => 'Vui lòng chọn ít nhất 1 ảnh.',
            'images.max' => 'Chỉ được chọn tối đa ' . self::MAX_UPLOAD_IMAGES . ' ảnh/lần tải lên.',
            'images.*.image' => 'Mỗi tệp phải là hình ảnh hợp lệ.',
            'images.*.mimes' => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
            'images.*.max' => 'Mỗi ảnh không được vượt quá 4MB.',
            'caption.max' => 'Chú thích không được vượt quá 255 ký tự.',
            'uploadAlbumId.exists' => 'Album được chọn không tồn tại.',
        ]);

        foreach ($this->images as $file) {
            $image = AlbumImage::query()->create([
                'image_path' => $file->store('uploads/albums', 'public'),
                'caption' => blank($this->caption) ? null : trim($this->caption),
            ]);

            if (! empty($this->uploadAlbumId)) {
                $image->albums()->syncWithoutDetaching([(int) $this->uploadAlbumId]);
            }
        }

        $uploadedCount = count($this->images);

        $this->showUploadModal = false;
        $this->resetImageForm();
        $this->resetPage('allImagesPage');

        $this->success("Đã tải lên {$uploadedCount} ảnh.");
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

    public function removeUploadImage(int $index): void
    {
        if (! array_key_exists($index, $this->images)) {
            return;
        }

        unset($this->images[$index]);

        $this->images = array_values($this->images);
        $this->uploadImagesError = null;
    }

    public function clearUploadImages(): void
    {
        $this->images = [];
        $this->uploadImagesError = null;
        $this->resetValidation(['images', 'images.*']);
    }

    protected function resetImageForm(): void
    {
        $this->reset(['images', 'caption', 'uploadAlbumId', 'uploadImagesError']);
        $this->resetErrorBag();
    }

    public function toggleSelectCurrentPage(): void
    {
        $currentPageIds = $this->currentPageImageIds;

        if (empty($currentPageIds)) {
            return;
        }

        $selectedIds = array_values(array_unique(array_map('intval', $this->selectedImageIds)));
        $allCurrentPageSelected = count(array_diff($currentPageIds, $selectedIds)) === 0;

        if ($allCurrentPageSelected) {
            $this->selectedImageIds = array_values(array_diff($selectedIds, $currentPageIds));
            return;
        }

        $this->selectedImageIds = array_values(array_unique(array_merge($selectedIds, $currentPageIds)));
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
        $ids = array_values(array_unique(array_map('intval', $this->selectedImageIds)));

        if (empty($ids)) {
            $this->warning('Không có ảnh nào để xóa.');
            return;
        }

        $images = AlbumImage::query()
            ->whereKey($ids)
            ->get(['id', 'image_path']);

        foreach ($images as $image) {
            $image->albums()->detach();

            if (! empty($image->image_path) && Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }

            $image->forceDelete();
        }

        $deletedCount = $images->count();

        $this->selectedImageIds = [];
        $this->resetPage('allImagesPage');

        $this->success("Đã xóa {$deletedCount} ảnh.");
    }

    public function deleteImage(int $id): void
    {
        $image = AlbumImage::query()->findOrFail($id);

        $image->albums()->detach();

        if (! empty($image->image_path) && Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->forceDelete();

        $this->selectedImageIds = array_values(array_diff(
            array_map('intval', $this->selectedImageIds),
            [(int) $id]
        ));

        $this->resetPage('allImagesPage');
        $this->success('Đã xóa ảnh.');
    }

    public function openMoveImage(int $id): void
    {
        $image = AlbumImage::query()->with('albums:id')->findOrFail($id);

        $this->isBulkMove = false;
        $this->selectedImageId = (int) $image->id;
        $this->moveTargetAlbumId = $image->albums->pluck('id')->map(fn ($id) => (int) $id)->first();
        $this->showMoveImageModal = true;

        $this->resetValidation(['moveTargetAlbumId']);
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

        $this->resetValidation(['moveTargetAlbumId']);
    }

    public function saveMoveImage(): void
    {
        $this->validate([
            'moveTargetAlbumId' => 'required|integer|exists:albums,id',
        ], [
            'moveTargetAlbumId.required' => 'Vui lòng chọn album đích.',
            'moveTargetAlbumId.exists' => 'Album đích không tồn tại.',
        ]);

        if ($this->isBulkMove) {
            $ids = array_values(array_unique(array_map('intval', $this->selectedImageIds)));

            if (empty($ids)) {
                $this->warning('Không có ảnh nào được chọn để chuyển.');
                return;
            }

            $images = AlbumImage::query()->whereKey($ids)->get();

            foreach ($images as $image) {
                $image->albums()->syncWithoutDetaching([(int) $this->moveTargetAlbumId]);
            }

            $count = $images->count();
            $this->selectedImageIds = [];
            $message = "Đã thêm {$count} ảnh vào album.";
        } else {
            if (! $this->selectedImageId) {
                $this->warning('Không xác định được ảnh cần thêm vào album.');
                return;
            }

            $image = AlbumImage::query()->findOrFail($this->selectedImageId);
            $image->albums()->syncWithoutDetaching([(int) $this->moveTargetAlbumId]);

            $message = 'Đã thêm ảnh vào album.';
        }

        $this->closeMoveImageModal();
        $this->resetPage('allImagesPage');
        $this->success($message);
    }

    public function closeMoveImageModal(): void
    {
        $this->showMoveImageModal = false;
        $this->selectedImageId = null;
        $this->moveTargetAlbumId = null;
        $this->isBulkMove = false;
        $this->resetErrorBag();
    }

    public function openEditCaption(int $id): void
    {
        $image = AlbumImage::query()->findOrFail($id);

        $this->isBulkCaption = false;
        $this->captionImageId = (int) $image->id;
        $this->captionEditImageIds = [(int) $image->id];
        $this->editingCaption = $image->caption ?? '';
        $this->showCaptionModal = true;

        $this->resetValidation(['editingCaption']);
    }

    public function openBulkCaptionEdit(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->selectedImageIds)));

        if (empty($ids)) {
            $this->warning('Vui lòng chọn ít nhất 1 ảnh để sửa chú thích.');
            return;
        }

        $validIds = AlbumImage::query()
            ->whereKey($ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($validIds)) {
            $this->warning('Không tìm thấy ảnh hợp lệ để sửa chú thích.');
            return;
        }

        $this->isBulkCaption = true;
        $this->captionImageId = null;
        $this->captionEditImageIds = $validIds;
        $this->editingCaption = '';
        $this->showCaptionModal = true;

        $this->resetValidation(['editingCaption']);
    }

    public function removeImageFromCaptionEdit(int $id): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->captionEditImageIds)));

        if (count($ids) <= 1) {
            $this->warning('Phải giữ lại ít nhất 1 ảnh để sửa chú thích.');
            return;
        }

        $this->captionEditImageIds = array_values(array_diff($ids, [(int) $id]));

        if ($this->isBulkCaption) {
            $this->selectedImageIds = array_values(array_diff(
                array_map('intval', $this->selectedImageIds),
                [(int) $id]
            ));
        }
    }

    public function saveCaption(): void
    {
        $this->validate([
            'editingCaption' => 'nullable|string|max:255',
        ], [
            'editingCaption.max' => 'Chú thích không được vượt quá 255 ký tự.',
        ]);

        $ids = array_values(array_unique(array_map('intval', $this->captionEditImageIds)));

        if (empty($ids)) {
            $this->warning('Không có ảnh nào để cập nhật chú thích.');
            return;
        }

        $updatedCount = AlbumImage::query()
            ->whereKey($ids)
            ->update([
                'caption' => blank($this->editingCaption) ? null : trim($this->editingCaption),
            ]);

        if ($this->isBulkCaption) {
            $this->clearImageSelection();
        }

        $message = $updatedCount > 1
            ? "Đã cập nhật chú thích cho {$updatedCount} ảnh."
            : 'Đã cập nhật chú thích ảnh.';

        $this->closeCaptionModal();
        $this->success($message);
    }

    public function closeCaptionModal(): void
    {
        $this->showCaptionModal = false;
        $this->isBulkCaption = false;
        $this->captionImageId = null;
        $this->captionEditImageIds = [];
        $this->editingCaption = '';

        $this->resetValidation(['editingCaption']);
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
            <span class="font-semibold text-primary">
                {{ count($selectedImageIds) > 0 ? 'Đã chọn: ' . count($selectedImageIds) : '' }}
            </span>

            <x-button
                label="Chọn tất cả trang"
                class="btn-ghost"
                wire:click="toggleSelectCurrentPage"
                spinner="toggleSelectCurrentPage"
            />

            <x-button
                label="Bỏ chọn"
                class="btn-ghost"
                wire:click="clearImageSelection"
                spinner="clearImageSelection"
            />

            <x-button
                label="Sửa chú thích"
                icon="o-pencil-square"
                class="btn-outline"
                wire:click="openBulkCaptionEdit"
                spinner="openBulkCaptionEdit"
                :disabled="count($selectedImageIds) === 0"
            />

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
                wire:model.live="assignmentFilter"
                :options="$this->assignmentFilterOptions"
                option-value="id"
                option-label="name"
                class="w-40"
            />

            <x-select
                wire:model.live="imagePerPage"
                :options="$this->imagePerPageOptions"
                option-value="id"
                option-label="name"
                class="w-30"
            />

            <x-button
                icon="o-arrow-up-tray"
                class="btn-primary text-white"
                label="Tải ảnh lên"
                wire:click="openUploadImages"
                spinner="openUploadImages"
            />
        </x-slot:actions>
    </x-header>

    <script>
        window.imageLibraryGallery = function () {
            return {
                lightbox: null,
                actionOverlay: null,
                lightboxKey: 'image-library-gallery',

                escapeHtml(value) {
                    const map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;',
                    };

                    return String(value ?? '').replace(/[&<>"']/g, (char) => map[char] ?? char);
                },

                getActiveImageMeta(pswp) {
                    const element = pswp?.currSlide?.data?.element;

                    return {
                        id: Number(element?.dataset?.imageId || 0) || null,
                        src: pswp?.currSlide?.data?.src || element?.href || '',
                        caption: element?.dataset?.imageCaption || '',
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
                                    ${this.escapeHtml(image.caption)}
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

                                <button type='button' data-action='move' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Thêm vào album' aria-label='Thêm vào album'>
                                    <span class='text-lg leading-none'>
                                        <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-6'>
                                            <path stroke-linecap='round' stroke-linejoin='round' d='M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12A1.5 1.5 0 0 0 9.88 3.75H4.5A2.25 2.25 0 0 0 2.25 6v12A2.25 2.25 0 0 0 4.5 20.25h15A2.25 2.25 0 0 0 21.75 18V9A2.25 2.25 0 0 0 19.5 6.75h-5.38a1.5 1.5 0 0 1-1.06-.44Z' />
                                            <path stroke-linecap='round' stroke-linejoin='round' d='M12 11.25v6m3-3H9' />
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
                        </div>
                    `;

                    overlay.style.position = 'absolute';
                    overlay.style.left = '50%';
                    overlay.style.bottom = '24px';
                    overlay.style.transform = 'translateX(-50%)';
                    overlay.style.zIndex = '60';

                    overlay.querySelector('[data-action="download"]')?.addEventListener('click', () => {
                        const image = this.getActiveImageMeta(pswp);

                        if (! image.src) {
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

                    overlay.querySelector('[data-action="caption"]')?.addEventListener('click', () => {
                        const image = this.getActiveImageMeta(pswp);

                        if (! image.id) {
                            return;
                        }

                        this.$wire.openEditCaption(image.id);
                        pswp.close();
                    });

                    overlay.querySelector('[data-action="move"]')?.addEventListener('click', () => {
                        const image = this.getActiveImageMeta(pswp);

                        if (! image.id) {
                            return;
                        }

                        this.$wire.openMoveImage(image.id);
                        pswp.close();
                    });

                    overlay.querySelector('[data-action="delete"]')?.addEventListener('click', () => {
                        const image = this.getActiveImageMeta(pswp);

                        if (! image.id) {
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

                // syncPswpSizes() {
                //     const gallery = document.getElementById('my-gallery');
                //
                //     if (! gallery) return;
                //
                //     gallery.querySelectorAll('a.pswp-item img').forEach((img) => {
                //         const link = img.closest('a.pswp-item');
                //
                //         if (! link) return;
                //
                //         const setSize = () => {
                //             if (img.naturalWidth > 0 && img.naturalHeight > 0) {
                //                 link.setAttribute('data-pswp-width', img.naturalWidth);
                //                 link.setAttribute('data-pswp-height', img.naturalHeight);
                //             }
                //         };
                //
                //         if (img.complete) {
                //             setSize();
                //         } else {
                //             img.addEventListener('load', setSize, { once: true });
                //         }
                //     });
                // },

                destroyLightbox() {
                    this.removeActionOverlay();

                    if (this.lightbox) {
                        this.lightbox.destroy();
                        this.lightbox = null;
                    }

                    if (window.__adminPhotoSwipeInstances?.[this.lightboxKey]) {
                        window.__adminPhotoSwipeInstances[this.lightboxKey].destroy();
                        delete window.__adminPhotoSwipeInstances[this.lightboxKey];
                    }
                },

                init() {
                    if (typeof PhotoSwipeLightbox === 'undefined' || typeof PhotoSwipe === 'undefined') return;

                    this.$nextTick(() => {
                        // this.syncPswpSizes();

                        window.__adminPhotoSwipeInstances ??= {};

                        if (window.__adminPhotoSwipeInstances[this.lightboxKey]) {
                            window.__adminPhotoSwipeInstances[this.lightboxKey].destroy();
                            delete window.__adminPhotoSwipeInstances[this.lightboxKey];
                        }

                        this.lightbox = new PhotoSwipeLightbox({
                            gallery: '#my-gallery',
                            children: 'a.pswp-item',
                            showHideAnimationType: 'none',
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
                        window.__adminPhotoSwipeInstances[this.lightboxKey] = this.lightbox;
                    });
                },

                destroy() {
                    this.destroyLightbox();
                }
            };
        };
    </script>

    <div
        id="my-gallery"
        wire:key="image-library-gallery-{{ $this->allImages->currentPage() }}-{{ $assignmentFilter }}-{{ $imagePerPage }}"
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
        x-data="imageLibraryGallery()"
        x-on:click.capture="
            const link = $event.target.closest('a.pswp-item');
            if (link && !lightbox) {
                $event.preventDefault();
                $event.stopPropagation();
            }
        "
        x-on:livewire:navigating.window="destroyLightbox()"
    >
        @forelse($this->allImages as $image)
            @php
                $imageUrl = Storage::url($image->image_path);

                $displayCaption = filled($image->caption)
                    ? $image->caption
                    : 'Chưa có chú thích';
            @endphp

            <div
                class="relative group/card"
                wire:key="gallery-image-{{ $image->id }}"
                x-data="{
                w: 1200,
                h: 800,
                init() {
                    this.$nextTick(() => {
                        const img = this.$refs.img;

                        if (img && img.complete && img.naturalWidth > 0) {
                            this.w = img.naturalWidth;
                            this.h = img.naturalHeight;
                        }
                    });
                }
            }"
            >
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
                        href="{{ $imageUrl }}"
                        onclick="return false;"
                        x-bind:data-pswp-width="w"
                        x-bind:data-pswp-height="h"
                        data-image-id="{{ $image->id }}"
                        data-image-url="{{ $imageUrl }}"
                        data-image-caption="{{ e($image->caption ?? '') }}"
                        aria-label="{{ e($displayCaption) }}"
                        class="pswp-item block w-full h-55 cursor-pointer group/img relative overflow-hidden rounded-lg {{ in_array($image->id, $selectedImageIds) ? 'ring-2 ring-primary ring-offset-2' : '' }}"
                    >
                        <img
                            x-ref="img"
                            x-on:load="w = $event.target.naturalWidth; h = $event.target.naturalHeight"
                            src="{{ $imageUrl }}"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover/img:scale-105"
                            loading="lazy"
                            decoding="async"
                            alt="{{ e($displayCaption) }}"
                        />

                        <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/20 transition-all duration-300"></div>

                        <div class="pointer-events-none absolute inset-x-0 bottom-0 p-3 translate-y-full group-hover/img:translate-y-0 transition-transform duration-300 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                            <p class="text-white text-sm font-semibold leading-snug line-clamp-2 {{ blank($image->caption) ? 'italic text-white/80' : '' }}">
                                {{ $displayCaption }}
                            </p>
                        </div>
                    </a>

                    <div class="absolute top-2 right-2 z-2 flex flex-col gap-2 opacity-0 scale-90 transition-all duration-300 group-hover/card:opacity-100 group-hover/card:scale-100">
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-primary shadow-md hover:bg-primary hover:text-white"
                            wire:click.stop="openEditCaption({{ $image->id }})"
                            wire:loading.attr="disabled"
                            wire:target="openEditCaption({{ $image->id }})"
                            title="Sửa chú thích"
                        >
                            <x-icon name="o-pencil-square" class="w-5 h-5" />
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-primary shadow-md hover:bg-primary hover:text-white"
                            wire:click.stop="openMoveImage({{ $image->id }})"
                            wire:loading.attr="disabled"
                            wire:target="openMoveImage({{ $image->id }})"
                            title="Thêm vào album"
                        >
                            <x-icon name="o-folder-arrow-down" class="w-5 h-5" />
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-error/90 text-white shadow-md hover:bg-error"
                            wire:click.stop="deleteImage({{ $image->id }})"
                            wire:loading.attr="disabled"
                            wire:target="deleteImage({{ $image->id }})"
                            title="Xóa ảnh"
                        >
                            <x-icon name="o-trash" class="w-5 h-5" />
                        </button>
                    </div>
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
        <div class="space-y-0">
            <x-file
                label="Ảnh"
                wire:model="images"
                multiple
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                hint="Có thể chọn tối đa 20 ảnh, mỗi ảnh tối đa 4MB."
            />

            <div wire:loading wire:target="images" class="text-sm text-primary">
                Đang tải ảnh lên để xem trước...
            </div>

            @if(!empty($uploadImagesError))
                <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                    {{ $uploadImagesError }}
                </div>
            @endif

            @error('images')
            <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                {{ $message }}
            </div>
            @enderror

            @error('images.*')
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

            <x-select
                label="Album"
                wire:model="uploadAlbumId"
                :options="$this->albumOptions"
                option-value="id"
                option-label="name"
                placeholder="Không gắn album"
                placeholder-value=""
            />

            @error('uploadAlbumId')
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

                                <div class="px-2 py-1 text-xs text-gray-600 truncate" title="{{ $preview['name'] }}">
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
                :disabled="empty($images)"
            />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showMoveImageModal" title="Thêm ảnh vào album" separator class="modalAddImage">
        <div class="space-y-0">
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

            @error('moveTargetAlbumId')
            <div class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm text-error">
                {{ $message }}
            </div>
            @enderror

            @if($this->currentImage)
                <div class="rounded-lg border border-gray-200 overflow-hidden bg-white">
                    <img
                        src="{{ Storage::url($this->currentImage->image_path) }}"
                        alt="{{ $this->currentImage->caption ?: 'image' }}"
                        class="h-56 w-full object-cover"
                        loading="lazy"
                    />
                </div>
            @elseif($isBulkMove && !empty($selectedImageIds) && $this->selectedImagesForMove->isNotEmpty())
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-90 overflow-y-auto pr-1">
                    @foreach($this->selectedImagesForMove as $selectedImage)
                        <div class="rounded-lg border border-gray-200 overflow-hidden bg-white" wire:key="move-preview-{{ $selectedImage->id }}">
                            <img
                                src="{{ Storage::url($selectedImage->image_path) }}"
                                alt="{{ $selectedImage->caption ?: 'image' }}"
                                class="h-40 w-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button
                label="Hủy"
                wire:click="closeMoveImageModal"
            />

            <x-button
                label="Lưu"
                class="btn-primary text-white"
                wire:click="saveMoveImage"
                spinner="saveMoveImage"
            />
        </x-slot:actions>
    </x-modal>

    @if($showCaptionModal)
        @php
            $captionImages = $this->captionModalImages;
            $captionImageCount = $captionImages->count();
        @endphp

        <x-modal wire:model="showCaptionModal" title="{{ $isBulkCaption ? 'Sửa chú thích nhiều ảnh' : 'Sửa chú thích ảnh' }}" separator class="modalAddImage">
            <div class="space-y-4">
                <div class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-slate-600">
                    @if($isBulkCaption)
                        Bạn đang sửa chú thích cho
                        <span class="font-semibold text-primary">{{ $captionImageCount }}</span>
                        ảnh. Chú thích mới sẽ thay thế chú thích hiện tại của các ảnh này.
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
                                        class="h-28 w-full object-cover"
                                        loading="lazy"
                                        decoding="async"
                                        fetchpriority="low"
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
                    rows="4"
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
                    :disabled="$captionImageCount === 0"
                />
            </x-slot:actions>
        </x-modal>
    @endif
</div>
