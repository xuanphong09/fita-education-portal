<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Post;
use App\Models\Category;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public int $perPage = 10;
    public string $search = '';
    public string $filterStatus = '';
    public ?int $filterCategory = null;
    public string $filterFeatured = '';
    public string $filterLanguage = '';

    public function getPostsProperty()
    {
        $search = trim($this->search);

        return Post::query()
            ->with(['category', 'user'])
            ->when($this->filterLanguage !== '', fn ($q) => $this->applyLanguageFilter($q, $this->filterLanguage))
            ->when($search !== '', fn ($q) => $this->applySearchFilter($q, $search, $this->filterLanguage))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterCategory, fn($q) => $q->where('category_id', $this->filterCategory))
            ->when($this->filterFeatured !== '', fn($q) => $q->where('is_featured', $this->filterFeatured === '1'))
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    protected function applyLanguageFilter($query, string $locale): void
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $query->whereRaw(
            "COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(title, '$." . $locale . "'))), ''), NULL) IS NOT NULL"
        );
    }

    protected function applySearchFilter($query, string $search, string $locale = ''): void
    {
        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $keyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

            $query->where(function ($inner) use ($keyword, $locale) {
                if ($locale === 'vi' || $locale === 'en') {
                    $inner->whereRaw(
                        "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$." . $locale . "')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                        [$keyword]
                    )->orWhereRaw(
                        "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(excerpt, '$." . $locale . "')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                        [$keyword]
                    )->orWhereRaw(
                        "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(content, '$." . $locale . "')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                        [$keyword]
                    );
                } else {
                    $inner->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                        ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(excerpt, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                        ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(content, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                        ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                        ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(excerpt, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                        ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(content, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword]);
                }

                $inner->orWhere('slug', 'like', $keyword);
            });
        }
    }

    public function getCategoriesProperty()
    {
        return Category::orderBy('order')->get()->map(fn($c) => [
            'id'   => $c->id,
            'name' => $c->getTranslatedName(),
        ])->toArray();
    }

    public function headers(): array
    {
        return [
            ['key' => 'id',         'label' => '#',           'class' => 'w-10'],
            ['key' => 'thumbnail',  'label' => 'Ảnh',         'sortable' => false, 'class' => 'w-16'],
            ['key' => 'title',      'label' => 'Tiêu đề',     'class' => 'min-w-64'],
            ['key' => 'category',   'label' => 'Danh mục',    'sortable' => false, 'class' => 'w-36'],
            ['key' => 'status',     'label' => 'Trạng thái',  'sortable' => false, 'class' => 'w-28'],
            ['key' => 'featured',   'label' => 'Nổi bật',     'sortable' => false, 'class' => 'w-24'],
            ['key' => 'views',      'label' => 'Lượt xem',    'class' => 'w-24'],
            ['key' => 'created_at', 'label' => 'Ngày tạo',    'class' => 'w-32'],
            ['key' => 'actions',    'label' => 'Hành động',   'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterCategory(): void { $this->resetPage(); }
    public function updatedFilterFeatured(): void { $this->resetPage(); }
    public function updatedFilterLanguage(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->filterCategory = null;
        $this->filterLanguage = '';
        $this->filterFeatured = '';
        $this->resetPage();
    }

    public function getHasActiveFiltersProperty(): bool
    {
        return trim($this->search) !== ''
            || !is_null($this->filterCategory)
            || $this->filterStatus !== ''
            || $this->filterFeatured !== ''
            || $this->filterLanguage !== '';
    }

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title'             => 'Bạn có chắc chắn muốn xóa bài viết này không?',
            'icon'              => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText'  => 'Hủy',
            'method'            => 'confirmDelete',
            'id'                => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete(int $id): void
    {
        $post = Post::findOrFail($id);
        if ($post->thumbnail) {
            Storage::disk('public')->delete($post->thumbnail);
        }
        $post->delete();
        $this->success('Đã xóa bài viết thành công!');
    }

    public function toggleFeatured(int $id): void
    {
        $post = Post::findOrFail($id);

        if ($post->status !== 'published') {
            $this->warning('Chỉ bài viết đã đăng mới được bật/tắt nổi bật.');
            return;
        }

        // Chỉ áp quota khi đang bật nổi bật cho bài published.
        if (! $post->is_featured) {
            $featuredCount = Post::where('is_featured', true)
                ->where('status', 'published')
                ->count();

            if ($featuredCount >= 5) {
                $this->error('Đã đủ 5 bài viết nổi bật trong nhóm published. Vui lòng bỏ nổi bật một bài đã đăng trước.');
                return;
            }
        }

        $post->update(['is_featured' => ! $post->is_featured]);

        $this->success($post->is_featured ? 'Đã đánh dấu bài viết nổi bật.' : 'Đã bỏ đánh dấu bài viết nổi bật.');
    }
};
?>

<div
    x-data="{ loading: false }"
    x-on:livewire:request.window="loading = true"
    x-on:livewire:response.window="loading = false"
    x-on:livewire:error.window="loading = false"
>
    <x-slot:title>Danh sách bài viết</x-slot:title>

    <x-slot:breadcrumb>
        <span>Danh sách bài viết</span>
    </x-slot:breadcrumb>

    <x-header title="Danh sách bài viết" class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm tiêu đề hoặc slug..."
                wire:model.live.debounce.300ms="search"
                :clearable="true"
                class="w-full lg:w-80"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" class="btn-primary text-white" label="Tạo bài viết" link="{{ route('admin.post.create') }}"/>
        </x-slot:actions>
    </x-header>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <x-select
            wire:model.live="filterLanguage"
            placeholder="Tất cả ngôn ngữ"
            placeholder-value=""
            :options="[
                ['id' => 'vi', 'name' => 'Tiếng Việt'],
                ['id' => 'en', 'name' => 'Tiếng Anh'],
            ]"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        <x-select
            wire:model.live="filterStatus"
            placeholder="Tất cả trạng thái"
            placeholder-value=""
            :options="[
                ['id'=>'draft',     'name'=>'Nháp'],
                ['id'=>'published', 'name'=>'Đã đăng'],
                ['id'=>'archived',  'name'=>'Lưu trữ'],
            ]"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        <x-select
            wire:model.live="filterCategory"
            placeholder="Tất cả danh mục"
            placeholder-value=""
            :options="$this->categories"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        <x-select
            wire:model.live="filterFeatured"
            placeholder="Tất cả bài viết"
            placeholder-value=""
            :options="[
                ['id' => '1', 'name' => 'Bài nổi bật'],
                ['id' => '0', 'name' => 'Không nổi bật'],
            ]"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        @if($this->hasActiveFilters)
            <x-button
                label="Xóa bộ lọc"
                icon="o-funnel"
                class="btn-outline btn-error"
                wire:click="resetFilters"
                spinner="resetFilters"
            />
        @endif
    </div>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative"
         :class="loading && 'pointer-events-none'">

        <x-table
            :headers="$this->headers()"
            :rows="$this->posts"
            :sort-by="$this->sortBy"
            striped
            with-pagination
            :per-page-values="[5, 10, 20, 25, 50]"
            per-page="perPage"
            wire:loading.class="opacity-50 pointer-events-none select-none"
            class="bg-white
                [&_table]:border-collapse [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200
                [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!"
        >
            @scope('cell_id', $post)
                {{ ($this->posts->currentPage() - 1) * $this->posts->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_thumbnail', $post)
                @if($post->thumbnail)
                    <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->getTranslation('title','vi',false) }}"
                         class="w-10 h-10 rounded object-cover ring-1 ring-gray-200"/>
                @else
                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center ring-1 ring-gray-200">
                        <x-icon name="o-photo" class="w-5 h-5 text-gray-400"/>
                    </div>
                @endif
            @endscope

            @scope('cell_title', $post)
                @php
                    $preferredLocale = $this->filterLanguage === 'en' ? 'en' : 'vi';
                    $title = $post->getTranslation('title', $preferredLocale, false)
                        ?: $post->getTranslation('title', 'vi', false)
                        ?: $post->getTranslation('title', 'en', false)
                        ?: '—';
                @endphp
                <div class="font-medium line-clamp-1">{{ $title }}</div>
                <div class="text-xs text-gray-400">{{ $post->slug }}</div>
            @endscope

            @scope('cell_category', $post)
                @if($post->category)
                    <x-badge :value="$post->category->getTranslatedName()" class="badge-ghost badge-sm"/>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endscope

            @scope('cell_status', $post)
                @php
                    $map = ['draft'=>['label'=>'Nháp','class'=>'badge-warning'],
                            'published'=>['label'=>'Đã đăng','class'=>'badge-success'],
                            'archived'=>['label'=>'Lưu trữ','class'=>'badge-ghost']];
                    $s = $map[$post->status] ?? $map['draft'];
                @endphp
                <x-badge :value="$s['label']" class="{{ $s['class'] }} badge-sm"/>
            @endscope

            @scope('cell_featured', $post)
                @if($post->is_featured)
                    <x-badge value="Nổi bật" class="badge-info badge-sm"/>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endscope

            @scope('cell_views', $post)
                <span class="text-sm">{{ number_format($post->views) }}</span>
            @endscope

            @scope('cell_created_at', $post)
                <span class="text-xs text-gray-500">{{ $post->created_at->format('d/m/Y') }}</span>
            @endscope

            @scope('cell_actions', $post)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary" tooltip="Chỉnh sửa"
                              link="{{ route('admin.post.edit', $post->id) }}"/>

                    @if($post->status === 'published')
                        <x-button
                            :icon="$post->is_featured ? 's-star' : 'o-star'"
                            class="btn-sm btn-ghost {{ $post->is_featured ? 'text-warning' : 'text-gray-500' }}"
                            :tooltip="$post->is_featured ? 'Bỏ nổi bật' : 'Đánh dấu nổi bật'"
                            wire:click="toggleFeatured({{ $post->id }})"
                            spinner="toggleFeatured({{ $post->id }})"
                        />
                    @endif

                    <x-button icon="o-trash" class="btn-sm btn-ghost text-error" tooltip="Xóa"
                              wire:click="delete({{ $post->id }})" spinner="delete({{ $post->id }})"/>
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-8">
                    <x-icon name="o-document-text" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có bài viết nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->posts" wire:model.live="perPage" :per-page-values="[10,15,25,50]"/>
        </x-table>

        <div wire:loading.flex class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg" />
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>

