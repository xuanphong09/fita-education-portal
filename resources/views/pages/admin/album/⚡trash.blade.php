<?php

use App\Models\Album;
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

    public int $perPage = 10;

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
            ['key' => 'name', 'label' => 'Tên album'],
            ['key' => 'images_count', 'label' => 'Số ảnh', 'class' => 'w-24'],
            ['key' => 'deleted_at', 'label' => 'Xóa lúc', 'class' => 'w-44'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-40'],
        ];
    }

    public function getAlbumsProperty()
    {
        return Album::onlyTrashed()
            ->withCount(['images' => fn ($q) => $q->whereNull('album_images.deleted_at')])
            ->when(trim($this->search) !== '', function ($query) {
                $keyword = '%' . trim($this->search) . '%';
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('name', 'like', $keyword)
                        ->orWhere('description', 'like', $keyword);
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate($this->perPage);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function restore(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục album này?',
            'icon' => 'question',
            'confirmButtonText' => 'Khôi phục',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRestore',
            'id' => $id,
        ]);
    }

    #[On('confirmRestore')]
    public function confirmRestore(int $id): void
    {
        Album::onlyTrashed()->findOrFail($id)->restore();
        $this->success('Đã khôi phục album thành công.');
    }

    public function forceDelete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn album này? Hành động không thể hoàn tác.',
            'icon' => 'warning',
            'confirmButtonText' => 'Xóa vĩnh viễn',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmForceDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmForceDelete')]
    public function confirmForceDelete(int $id): void
    {
        $album = Album::onlyTrashed()->findOrFail($id);
        // Keep images and files, only remove album/pivot links.
        $album->images()->detach();
        $album->forceDelete();

        $this->success('Đã xóa vĩnh viễn album. Ảnh vẫn được giữ lại.');
    }
};
?>

<div>
    <x-slot:title>Thùng rác album</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.album.index') }}" class="font-semibold text-slate-700">Danh sách album</a>
        <span class="mx-1">/</span>
        <span>Thùng rác</span>
    </x-slot:breadcrumb>

    <x-header title="Thùng rác album" subtitle="Có thể khôi phục trước khi xóa vĩnh viễn" class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input icon="o-magnifying-glass" placeholder="Tìm theo tên album..." wire:model.live.debounce.300ms="search" clearable class="w-full lg:w-96"/>
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-arrow-left" class="btn-ghost" label="Quay lại" link="{{ route('admin.album.index') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative bg-white">
        <x-table
            :headers="$this->headers()"
            :rows="$this->albums"
            :per-page-values="[10, 20, 50]"
            per-page="perPage"
            with-pagination
            class="
                bg-white
                [&_table]:border-collapse [&_th]:text-left [&_th]:text-black!
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                [&_tr:hover]:bg-gray-100/70
            "
        >
            @scope('cell_id', $album)
                {{ ($this->albums->currentPage() - 1) * $this->albums->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $album)
                <p class="font-medium">{{ $album->name }}</p>
                @if($album->description)
                    <p class="text-xs text-gray-500 line-clamp-1">{{ $album->description }}</p>
                @endif
            @endscope

            @scope('cell_images_count', $album)
                <x-badge :value="$album->images_count" class="badge-outline"/>
            @endscope

            @scope('cell_deleted_at', $album)
                {{ $album->deleted_at?->format('d/m/Y H:i') }}
            @endscope

            @scope('cell_actions', $album)
                <div class="flex items-center gap-1">
                    <x-button icon="o-arrow-uturn-left" class="btn-xs btn-ghost text-success" wire:click="restore({{ $album->id }})" tooltip="Khôi phục Album"/>
                    <x-button icon="o-trash" class="btn-xs btn-ghost text-error" wire:click="forceDelete({{ $album->id }})" tooltip="Xóa vĩnh viễn"/>
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-10 text-gray-500">Không có album trong thùng rác.</div>
            </x-slot:empty>
        </x-table>
    </div>
</div>

