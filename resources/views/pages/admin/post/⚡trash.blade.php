<?php

use App\Models\Post;
use Illuminate\Database\QueryException;
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

    public int $perPage = 10;
    public bool $selectPage = false;
    public array $selected = [];

    public function updatedSearch(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatedSelectPage(bool $value): void
    {
        if ($value) {
            $this->selected = $this->posts->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            return;
        }

        $this->selected = [];
    }

    public function updatedSelected(): void
    {
        $currentIds = $this->posts->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $selectedInPage = array_intersect($currentIds, array_map('intval', $this->selected));
        $this->selectPage = count($currentIds) > 0 && count($selectedInPage) === count($currentIds);
    }

    protected function resetSelection(): void
    {
        $this->selectPage = false;
        $this->selected = [];
    }

    public function headers(): array
    {
        return [
            ['key' => 'select', 'label' => '', 'sortable' => false, 'class' => 'w-12'],
            ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
            ['key' => 'title', 'label' => 'Tiêu đề'],
            ['key' => 'slug', 'label' => 'Slug', 'class' => 'w-64'],
            ['key' => 'deleted_at', 'label' => 'Ngày xóa', 'class' => 'w-40'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-40'],
        ];
    }

    public function getPostsProperty()
    {
        $search = trim($this->search);
        return Post::onlyTrashed()
            ->when($search !== '', function ($query) use ($search) {
                $keyword = '%' . $search . '%';
                $query->where(function ($q) use ($keyword) {
                    $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.vi')) LIKE ?", [$keyword])
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) LIKE ?", [$keyword])
                        ->orWhere('slug', 'like', $keyword);
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate($this->perPage);
    }

    protected function selectedPostsQuery()
    {
        return Post::onlyTrashed()->whereIn('id', array_map('intval', $this->selected));
    }

    public function restore(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục bài viết này?',
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
        Post::onlyTrashed()->findOrFail($id)->restore();
        $this->success('Đã khôi phục bài viết thành công.');
        $this->resetSelection();
    }

    public function forceDelete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn bài viết này? Hành động không thể hoàn tác.',
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
        try {
            $post = Post::onlyTrashed()->findOrFail($id);
            if ($post->thumbnail && Storage::disk('public')->exists($post->thumbnail)) {
                Storage::disk('public')->delete($post->thumbnail);
            }
            $post->forceDelete();
            $this->success('Đã xóa vĩnh viễn bài viết.');
            $this->resetSelection();
        } catch (QueryException) {
            $this->error('Không thể xóa vĩnh viễn do dữ liệu đang được sử dụng.');
        }
    }

    public function bulkRestore(): void
    {
        if (count($this->selected) === 0) {
            $this->warning('Vui lòng chọn ít nhất 1 bài viết để khôi phục.');
            return;
        }
        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục các bài viết đã chọn?',
            'icon' => 'question',
            'confirmButtonText' => 'Khôi phục',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkRestore',
        ]);
    }

    #[On('confirmBulkRestore')]
    public function confirmBulkRestore(): void
    {
        $restored = $this->selectedPostsQuery()->restore();
        $this->resetSelection();
        $this->success("Đã khôi phục {$restored} bài viết.");
    }

    public function bulkForceDelete(): void
    {
        if (count($this->selected) === 0) {
            $this->warning('Vui lòng chọn ít nhất 1 bài viết để xóa vĩnh viễn.');
            return;
        }
        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn các bài viết đã chọn? Hành động không thể hoàn tác.',
            'icon' => 'warning',
            'confirmButtonText' => 'Xóa vĩnh viễn',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkForceDelete',
        ]);
    }

    #[On('confirmBulkForceDelete')]
    public function confirmBulkForceDelete(): void
    {
        try {
            $posts = $this->selectedPostsQuery()->get();
            foreach ($posts as $post) {
                if ($post->thumbnail && Storage::disk('public')->exists($post->thumbnail)) {
                    Storage::disk('public')->delete($post->thumbnail);
                }
            }
            $deleted = $this->selectedPostsQuery()->forceDelete();
            $this->resetSelection();
            $this->success("Đã xóa vĩnh viễn {$deleted} bài viết.");
        } catch (QueryException) {
            $this->error('Không thể xóa vĩnh viễn do dữ liệu đang được sử dụng.');
        }
    }
};

?>

<div>
    <x-slot:title>Thùng rác bài viết</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.post.index') }}" class="font-semibold text-slate-700">Danh sách bài viết</a>
        <span class="mx-1">/</span>
        <span>Thùng rác</span>
    </x-slot:breadcrumb>

    <x-header title="Thùng rác bài viết"
              subtitle="Có thể khôi phục trước khi xóa vĩnh viễn"
              class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm tiêu đề hoặc slug..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-arrow-left" class="btn-ghost" label="Quay lại" link="{{ route('admin.post.index') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-md">
            <div class="flex items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selectPage">
                    <span>Chọn tất cả trong trang</span>
                </label>
                <span class="text-sm text-gray-500">Đã chọn: {{ count($selected) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <x-button
                    icon="o-arrow-uturn-left"
                    class="btn-sm btn-ghost text-success"
                    label="Khôi phục đã chọn"
                    wire:click="bulkRestore"
                    spinner="bulkRestore"
                    :disabled="count($selected) === 0"
                />
                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-error"
                    label="Xóa vĩnh viễn đã chọn"
                    wire:click="bulkForceDelete"
                    spinner="bulkForceDelete"
                    :disabled="count($selected) === 0"
                />
            </div>
        </div>

        <x-table
            :headers="$this->headers()"
            :rows="$this->posts"
            striped
            :per-page-values="[5, 10, 20, 50]"
            per-page="perPage"
            with-pagination
            class="bg-white [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left"
        >
            @scope('cell_select', $post)
            <input
                type="checkbox"
                class="checkbox checkbox-sm"
                value="{{ $post->id }}"
                wire:model.live="selected"
            />
            @endscope

            @scope('cell_id', $post)
            {{ ($this->posts->currentPage() - 1) * $this->posts->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_title', $post)
            @php
                $title = $post->getTranslation('title', 'vi', false)
                    ?: $post->getTranslation('title', 'en', false)
                    ?: '—';
            @endphp
            <div class="font-medium line-clamp-1">{{ $title }}</div>
            @endscope

            @scope('cell_slug', $post)
            <div class="text-xs text-gray-400">{{ $post->slug }}</div>
            @endscope

            @scope('cell_deleted_at', $post)
            {{ optional($post->deleted_at)->format('d/m/Y H:i') }}
            @endscope

            @scope('cell_actions', $post)
            <div class="flex gap-2">
                <x-button
                    icon="o-arrow-uturn-left"
                    class="btn-sm btn-ghost text-success"
                    tooltip="Khôi phục"
                    wire:click="restore({{ $post->id }})"
                    spinner="restore({{ $post->id }})"
                />
                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-error"
                    tooltip="Xóa vĩnh viễn"
                    wire:click="forceDelete({{ $post->id }})"
                    spinner="forceDelete({{ $post->id }})"
                />
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-trash" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Thùng rác đang trống.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->posts" wire:model.live="perPage"/>
        </x-table>
    </div>
</div>



