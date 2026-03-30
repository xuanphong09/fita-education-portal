<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\Category;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public int $perPage = 10;
    #[Url(as: 'search')]
    public string $search = '';

    public function getCategoriesProperty()
    {
        $q = Category::query()
            ->withCount('posts')
            ->with('parent');  // eager load để tránh N+1 query

        if (!empty($this->search)) {
            $search = "%{$this->search}%";
            $q->where(function ($q) use ($search) {
                $q->where('slug', 'like', $search)
                    ->orWhere('name', 'like', $search);
            });
        }

        $q->orderBy(...array_values($this->sortBy));

        return $q->paginate($this->perPage);
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'thumbnail', 'label' => 'Ảnh', 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'name', 'label' => 'Tên danh mục', 'class' => 'w-64'],
            ['key' => 'parent', 'label' => 'Danh mục cha', 'sortable' => false, 'class' => 'w-48'],
            ['key' => 'posts_count', 'label' => 'Số bài', 'class' => 'w-24'],
            ['key' => 'is_active', 'label' => 'Kích hoạt', 'class' => 'w-24'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function delete($id)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa danh mục này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete($id)
    {
        $category = Category::withCount('posts')->findOrFail($id);
        if ($category->posts_count > 0) {
            $this->error('Danh mục đang chứa bài viết, không thể xóa.');
            return;
        }

        $category->delete();
        $this->success('Đã xóa danh mục thành công');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <x-slot:title>
        {{ __('Danh sách danh mục') }}
    </x-slot:title>

    <x-slot:breadcrumb>
        <span>{{ __('Danh sách danh mục') }}</span>
    </x-slot:breadcrumb>

    <x-header title="{{ __('Danh sách danh mục') }}"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm tên hoặc slug..."
                wire:model.live.debounce.300ms="search"
                clearable="true"
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" class="btn-primary text-white" label="{{__('Create new')}}"
                      link="{{route('admin.category.create')}}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->categories"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[5, 10, 20, 25, 50]"
            per-page="perPage"
            with-pagination
            wire:loading.class="opacity-50 pointer-events-none select-none"
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
            "
        >

            @scope('cell_id', $category)
            {{ ($this->categories->currentPage() - 1) * $this->categories->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_thumbnail', $category)
            @if($category->thumbnail)
                <img src="{{ Storage::url($category->thumbnail) }}" alt="{{ $category->getTranslatedName() }}"
                     class="w-10 h-10 rounded object-cover ring-1 ring-gray-200"/>
            @else
                <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center ring-1 ring-gray-200">
                    <x-icon name="o-photo" class="w-5 h-5 text-gray-400"/>
                </div>
            @endif
            @endscope

            @scope('cell_name', $category)
            <div class="font-medium">{{ $category->getTranslatedName() }}</div>
            <div class="text-xs text-gray-400">{{ $category->slug }}</div>
            @endscope

            @scope('cell_parent', $category)
            @if($category->parent)
                <div>{{ $category->parent->getTranslatedName() }}</div>
            @else
                <div class="text-xs text-gray-400">—</div>
            @endif
            @endscope

            @scope('cell_posts_count', $category)
            <x-badge :value="$category->posts_count . ' bài'"/>
            @endscope

            @scope('cell_is_active', $category)
            @if($category->is_active)
                <x-badge value="Kích hoạt" class="badge-success badge-md text-white font-semibold"/>
            @else
                <x-badge value="Tắt" class="badge-error badge-md text-white font-semibold"/>
            @endif
            @endscope

            @scope('cell_actions', $category)
            <div class="flex space-x-2">
                <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary" tooltip="Chỉnh sửa"
                          link="{{route('admin.category.edit',$category->id)}}"/>
                <x-button icon="o-trash" class="btn-sm btn-ghost text-danger" tooltip="Xóa"
                          wire:click="delete({{ $category->id }})" spinner="delete({{ $category->id }})"/>
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-folder" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có danh mục nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->categories" wire:model.live="perPage"/>
        </x-table>

        <div wire:loading.flex
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>

