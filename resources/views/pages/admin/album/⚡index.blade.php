<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination, Toast;

    #[Url(as: 'search')]
    public string $search = '';

    public int $perPage = 12;

    public bool $showAlbumModal = false;
    public bool $isEditingAlbum = false;
    public ?int $editingAlbumId = null;

    public string $name = '';
    public ?string $description = null;
    public int $order = 0;
    public bool $isFeaturedHome = false;

    public function getAlbumsProperty()
    {
        return Album::query()
            ->select('albums.*')
            ->addSelect([
                'cover_image_path' => AlbumImage::query()
                    ->select('album_images.image_path')
                    ->join('album_image_album', 'album_images.id', '=', 'album_image_album.album_image_id')
                    ->whereColumn('album_image_album.album_id', 'albums.id')
                    ->whereNull('album_images.deleted_at')
                    ->orderByDesc('album_image_album.created_at')
                    ->limit(1),
            ])
            ->withCount(['images' => fn ($q) => $q->whereNull('album_images.deleted_at')])
            ->when(trim($this->search) !== '', function ($query) {
                $keyword = '%' . trim($this->search) . '%';
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('name', 'like', $keyword)
                        ->orWhere('description', 'like', $keyword);
                });
            })
            ->orderBy('order')
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateAlbum(): void
    {
        $this->resetAlbumForm();
        $this->isEditingAlbum = false;
        $this->order = (int) Album::max('order') + 1;
        $this->showAlbumModal = true;
    }

    public function openEditAlbum(int $id): void
    {
        $album = Album::query()->findOrFail($id);

        $this->isEditingAlbum = true;
        $this->editingAlbumId = $album->id;
        $this->name = $album->name;
        $this->description = $album->description;
        $this->order = (int) $album->order;
        $this->isFeaturedHome = (bool) $album->is_featured_home;
        $this->showAlbumModal = true;
    }

    public function saveAlbum(): void
    {
        $rules = [
            'description' => 'nullable|string',
            'order' => 'required|integer|min:0',
        ];

        $rules['name'] = $this->isEditingAlbum && $this->editingAlbumId
            ? 'required|string|max:255|unique:albums,name,' . $this->editingAlbumId
            : 'required|string|max:255|unique:albums,name';

        $this->validate($rules, [
            'name.required' => 'Tên album không được để trống.',
            'name.unique' => 'Tên album đã tồn tại.',
            'order.required' => 'Thứ tự không được để trống.',
            'order.integer' => 'Thứ tự phải là số nguyên.',
        ]);

        $payload = [
            'name' => $this->name,
            'description' => $this->description,
            'order' => $this->order,
            'is_featured_home' => $this->isFeaturedHome,
        ];

        $savedAlbumId = null;

        if ($this->isEditingAlbum && $this->editingAlbumId) {
            Album::query()->findOrFail($this->editingAlbumId)->update($payload);
            $savedAlbumId = $this->editingAlbumId;
            $this->success('Cập nhật album thành công.');
        } else {
            $savedAlbum = Album::query()->create($payload);
            $savedAlbumId = $savedAlbum->id;
            $this->success('Tạo album thành công.');
        }

        if ($this->isFeaturedHome && $savedAlbumId) {
            Album::query()
                ->where('id', '!=', $savedAlbumId)
                ->update(['is_featured_home' => false]);
        }

        $this->showAlbumModal = false;
        $this->resetAlbumForm();
    }

    public function goToGallery(int $id): void
    {
        $this->redirectRoute('admin.album.show', ['id' => $id], navigate: true);
    }

    public function toggleFeaturedHome(int $id): void
    {
        DB::transaction(function () use ($id) {
            $album = Album::query()->findOrFail($id);

            if ($album->is_featured_home) {
                $album->update(['is_featured_home' => false]);
                return;
            }

            Album::query()->update(['is_featured_home' => false]);
            $album->update(['is_featured_home' => true]);
        });

        $this->success('Đã cập nhật album nổi bật trang chủ.');
    }

    public function deleteAlbum(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có muốn xóa album này?',
            'icon' => 'warning',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDeleteAlbum',
            'id' => $id,
        ]);
    }

    #[On('confirmDeleteAlbum')]
    public function confirmDeleteAlbum(int $id): void
    {
        $album = Album::query()->findOrFail($id);

        // Only remove relationship, keep all image records/files intact.
//        $album->images()->detach();
        $album->delete();

        $this->success('Album đã được chuyển vào thùng rác. Ảnh vẫn được giữ lại.');
    }

    protected function resetAlbumForm(): void
    {
        $this->reset(['name', 'description', 'editingAlbumId', 'isFeaturedHome']);
        $this->order = 0;
    }
};
?>

<div>
    <x-slot:title>Quản lý album ảnh</x-slot:title>

    <x-slot:breadcrumb>
        <span>Quản lý album ảnh</span>
    </x-slot:breadcrumb>

    <x-header title="Danh sách album" class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo tên hoặc mô tả album..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-trash" class="btn-ghost" label="Thùng rác" link="{{ route('admin.album.trash') }}"/>
            <x-button icon="o-plus" class="btn-primary text-white" label="Thêm album" wire:click="openCreateAlbum"/>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" wire:loading.class="opacity-70" wire:target="search,perPage">
        @forelse($this->albums as $album)
            <x-card class="overflow-hidden p-0!" wire:key="album-card-{{ $album->id }}">
                <div class="h-52 bg-gray-100">
                    <a href="{{route('admin.album.show',$album->id)}}" wire:navigate>
                        <img
                            src="{{ $album->cover_image_path ? Storage::url($album->cover_image_path) : asset('assets/images/default-image.jpg') }}"
                            alt="{{ $album->name }}"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />
                    </a>
                </div>

                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-base leading-5">{{ $album->name }}</p>
                        </div>
                        <x-badge :value="$album->images_count . ' ảnh'" class="badge-outline"/>
                    </div>

                    @if($album->description)
                        <p class="text-sm text-gray-600 line-clamp-2">{{ $album->description }}</p>
                    @endif

                    <div class="flex items-center justify-between pt-1">
                        <x-button label="Xem ảnh" icon="o-photo" class="btn-sm btn-primary text-white" wire:click="goToGallery({{ $album->id }})"/>
                        <div class="flex items-center gap-1">
                            <x-button
                                :icon="$album->is_featured_home ? 's-star' : 'o-star'"
                                class="btn-xs btn-ghost text-warning"
                                wire:click="toggleFeaturedHome({{ $album->id }})"
                                tooltip="{{ $album->is_featured_home ? 'Bỏ nổi bật trang chủ' : 'Đặt nổi bật trang chủ' }}"
                            />
                            <x-button icon="o-pencil" class="btn-xs btn-ghost text-primary" wire:click="openEditAlbum({{ $album->id }})"/>
                            <x-button icon="o-trash" class="btn-xs btn-ghost text-error" wire:click="deleteAlbum({{ $album->id }})"/>
                        </div>
                    </div>
                </div>
            </x-card>

        @empty
            <div class="md:col-span-2 xl:col-span-3 py-12 text-center text-gray-500">
                Chưa có album nào.
            </div>
        @endforelse
    </div>

    @if($this->albums->hasPages())
        <div class="mt-5">
            {{ $this->albums->links() }}
        </div>
    @endif

    <x-modal wire:model="showAlbumModal" :title="$isEditingAlbum ? 'Chỉnh sửa album' : 'Thêm album'" separator>
        <div class="space-y-3">
            <x-input label="Tên album" wire:model="name" required placeholder="Nhập tên album"/>
            <x-textarea label="Mô tả" wire:model="description" rows="3" placeholder="Mô tả ngắn về album"/>
{{--            <x-input label="Thứ tự" type="number" min="0" wire:model.number="order"/>--}}
            <x-checkbox label="Đặt là album nổi bật trang chủ" wire:model="isFeaturedHome" class="checkbox-primary"/>
        </div>
        <x-slot:actions>
            <x-button label="Hủy" @click="$wire.showAlbumModal = false"/>
            <x-button label="Lưu" class="btn-primary" wire:click="saveAlbum" spinner="saveAlbum"/>
        </x-slot:actions>
    </x-modal>
</div>








