<?php

use App\Models\Post;
use App\Models\User;
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
    public bool $showViewModal = false;
    public ?Post $viewingPost = null;

    // BỔ SUNG: Biến lọc tác giả
    public ?int $filterAuthor = null;

    // BỔ SUNG: Chạy khi trang vừa tải
    public function mount(): void
    {
        // "Mặc định là của mình": Gán filter mặc định bằng ID của chính user đang đăng nhập
        $this->filterAuthor = auth()->id();
    }

    public function updatedSearch(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    // BỔ SUNG: Reset trang khi đổi tác giả
    public function updatedFilterAuthor(): void
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
            $this->selected = $this->posts->pluck('id')->map(fn($id) => (int)$id)->toArray();
            return;
        }

        $this->selected = [];
    }

    public function updatedSelected(): void
    {
        $currentIds = $this->posts->pluck('id')->map(fn($id) => (int)$id)->toArray();
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
            ['key' => 'author', 'label' => 'Tác giả', 'sortable' => false, 'class' => 'w-40'], // BỔ SUNG: Cột Tác giả
            ['key' => 'slug', 'label' => 'Slug', 'class' => 'w-64'],
            ['key' => 'deleted_at', 'label' => 'Ngày xóa', 'class' => 'w-40'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-40'],
        ];
    }

    // BỔ SUNG: Lấy danh sách những user đã xóa bài để hiển thị ra Filter Dropdown
    public function getAuthorsProperty()
    {
        $user = auth()->user();
        if (!$user || !$user->can('quan_ly_bai_viet')) {
            return [];
        }

        return User::query()
            ->whereHas('posts', function ($q) {
                $q->withTrashed();
            })
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->toArray();
    }

    public function getPostsProperty()
    {
        $search = trim($this->search);
        $user = auth()->user();

        return Post::onlyTrashed()
            ->with('user') // Load quan hệ user để hiển thị tên mượt hơn (tránh N+1)
            ->when(!$user->can('quan_ly_bai_viet'), function ($query) use ($user) {
                // Nếu là user thường, ÉP BUỘC chỉ xem bài của chính mình
                $query->where('user_id', $user->id);
            })
            ->when($user->can('quan_ly_bai_viet') && $this->filterAuthor, function ($query) {
                // Nếu là Admin và có chọn filter (có thể là filter mặc định "chính mình" ở hàm mount)
                $query->where('user_id', $this->filterAuthor);
            })
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
        $user = auth()->user();
        return Post::onlyTrashed()
            ->whereIn('id', array_map('intval', $this->selected))
            ->when(!$user->can('quan_ly_bai_viet'), function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });
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
        $this->showViewModal = false;
        $this->viewingPost = null;
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
            $this->showViewModal = false;
            $this->viewingPost = null;
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

    public function viewPost(int $id): void
    {
        $this->viewingPost = Post::onlyTrashed()->with('user')->findOrFail($id);
        $this->showViewModal = true;
    }

    public function triggerRestoreFromModal(): void
    {
        if ($this->viewingPost) {
            $this->restore($this->viewingPost->id);
        }
    }

    public function triggerForceDeleteFromModal(): void
    {
        if ($this->viewingPost) {
            $this->forceDelete($this->viewingPost->id);
        }
    }
};

?>

<div>
    <x-slot:title>Thùng rác bài viết</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.post.index') }}" class="font-semibold text-slate-700" wire:navigate>Danh sách bài
            viết</a>
        <span class="mx-1">/</span>
        <span>Thùng rác</span>
    </x-slot:breadcrumb>

    <x-header title="Thùng rác bài viết"
              subtitle="Có thể khôi phục trước khi xóa vĩnh viễn"
              class="pb-3 mb-5! border-b border-gray-300">

        {{-- BỔ SUNG: Thanh công cụ Lọc Tác Giả & Tìm kiếm --}}
        <x-slot:middle class="justify-end!">
            <div class="flex items-center gap-2 w-full lg:w-auto">
                @if(auth()->user() && auth()->user()->can('quan_ly_bai_viet'))
                    <x-select
                        wire:model.live="filterAuthor"
                        placeholder="Tất cả tác giả"
                        placeholder-value=""
                        :options="$this->authors"
                        option-value="id"
                        option-label="name"
                        class="select-md w-48"
                    />
                @endif
                <x-input
                    icon="o-magnifying-glass"
                    placeholder="Tìm tiêu đề hoặc slug..."
                    wire:model.live.debounce.300ms="search"
                    clearable
                    class="w-full lg:w-80"
                />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button icon="o-arrow-left" class="btn-ghost" label="Quay lại" link="{{ route('admin.post.index') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <div
            class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-md">
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

            {{-- BỔ SUNG: Render tên tác giả ra bảng --}}
            @scope('cell_author', $post)
            <div class="text-sm font-medium">{{ $post->user->name ?? 'Không rõ' }}</div>
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
                    icon="o-eye"
                    class="btn-sm btn-ghost text-info"
                    tooltip="Xem chi tiết"
                    wire:click="viewPost({{ $post->id }})"
                    spinner="viewPost({{ $post->id }})"
                />
                <x-button
                    icon="o-arrow-uturn-left"
                    class="btn-sm btn-ghost text-success"
                    tooltip="Khôi phục"
                    wire:click="restore({{ $post->id }})"
                    spinner="restore({{ $post->id }})"
                />
                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-error me-3"
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

    {{-- MODAL XEM CHI TIẾT BÀI VIẾT ĐÃ XÓA --}}
    <x-modal wire:model="showViewModal" title="Chi tiết bài viết đã xóa" box-class="w-11/12 max-w-5xl">
        @if($viewingPost)
            <div class="space-y-6 max-h-[70vh] overflow-y-auto pr-2">
                {{-- Ảnh đại diện --}}
                @if($viewingPost->thumbnail)
                    <img src="{{ Storage::url($viewingPost->thumbnail) }}"
                         class="w-full max-h-80 object-cover rounded-lg ring-1 ring-gray-200" alt="Thumbnail">
                @endif

                {{-- Tiêu đề --}}
                <h2 class="text-3xl font-bold text-gray-800">
                    {{ $viewingPost->getTranslation('title', 'vi', false) ?: $viewingPost->getTranslation('title', 'en', false) }}
                </h2>

                {{-- Thông tin phụ (Tác giả, Thời gian) --}}
                <div class="flex flex-wrap gap-4 text-sm text-gray-500 border-b border-gray-200 pb-4">
                    <span class="flex items-center gap-1"><x-icon name="o-user" class="w-4 h-4"/> {{ $viewingPost->user->name ?? 'Không rõ' }}</span>
                    <span class="flex items-center gap-1"><x-icon name="o-trash" class="w-4 h-4"/> Xóa lúc: {{ $viewingPost->deleted_at->format('d/m/Y H:i') }}</span>
                    <span class="flex items-center gap-1"><x-icon name="o-tag" class="w-4 h-4"/> Trạng thái cũ: {{ $viewingPost->status }}</span>
                </div>

                {{-- Nội dung bài viết --}}
                <div class="tinymce-content max-w-none text-gray-700">
                    {!! $viewingPost->getTranslation('content', 'vi', false) ?: $viewingPost->getTranslation('content', 'en', false) !!}
                </div>
            </div>

            {{-- Các nút hành động bên trong Modal --}}
            <x-slot:actions>
                <x-button label="Khôi phục" icon="o-arrow-uturn-left" class="btn-success text-white"
                          wire:click="triggerRestoreFromModal" spinner="triggerRestoreFromModal"/>
                <x-button label="Xóa vĩnh viễn" icon="o-trash" class="btn-error text-white"
                          wire:click="triggerForceDeleteFromModal" spinner="triggerForceDeleteFromModal"/>
                <x-button label="Đóng" @click="$wire.showViewModal = false" class="btn-ghost"/>
            </x-slot:actions>
        @endif
    </x-modal>
</div>
