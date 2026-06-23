<?php

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    #[Locked]
    public int $categoryId;

    #[Locked]
    public string $categorySlug = '';

    #[Locked]
    public string $categoryName = '';

    public array $sortBy = [
        'column' => 'created_at',
        'direction' => 'desc',
    ];

    public int $perPage = 10;

    #[Url(as: 'search')]
    public string $search = '';

    public function mount(string $categorySlug): void
    {
        $category = Category::query()
            ->where('slug', $categorySlug)
            ->firstOrFail();

        $this->authorizeCategoryPage($category);

        $this->categoryId = (int) $category->id;
        $this->categorySlug = $category->slug;
        $this->categoryName = $category->getTranslatedName();
    }

    public function canReview(): bool
    {
        return auth()->user()?->canReviewPosts() ?? false;
    }

    public function canWrite(): bool
    {
        return auth()->user()?->canWritePosts() ?? false;
    }

    public function isReviewerOnly(): bool
    {
        return $this->canReview() && ! $this->canWrite();
    }

    private function hasGlobalWriteAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('quan_ly_bai_viet') || $user?->can('viet_bai_viet'));
    }

    private function hasGlobalReviewAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('quan_ly_bai_viet') || $user?->can('duyet_bai_viet'));
    }

    private function categoryIdsForPermission(string $basePermission): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        if ($basePermission === 'viet_bai_viet' && $this->hasGlobalWriteAccess()) {
            return Category::query()
                ->orderBy('order')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($basePermission === 'duyet_bai_viet' && $this->hasGlobalReviewAccess()) {
            return Category::query()
                ->orderBy('order')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $user->scopedPostCategoryIds($basePermission) ?? [];
    }

    private function accessibleCategoryIds(): array
    {
        return collect([
            $this->categoryIdsForPermission('viet_bai_viet'),
            $this->categoryIdsForPermission('duyet_bai_viet'),
        ])
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Chỉ cho vào trang nếu có quyền quản lý/viết/duyệt tại danh mục này.
     * Không cấp quyền chỉ vì người dùng là tác giả của một bài viết.
     */
    private function authorizeCategoryPage(Category $category): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($this->hasGlobalWriteAccess() || $this->hasGlobalReviewAccess()) {
            return;
        }

        abort_unless(
            in_array((int) $category->id, $this->accessibleCategoryIds(), true),
            403,
            'Bạn không có quyền xem danh sách bài viết của danh mục này.'
        );
    }

    /**
     * Kiểm tra lại quyền trong mỗi lần Livewire tải dữ liệu.
     * #[Locked] ngăn categoryId bị ghi đè từ phía trình duyệt.
     */
    private function getAuthorizedCategory(): Category
    {
        $category = Category::query()->findOrFail($this->categoryId);

        $this->authorizeCategoryPage($category);

        return $category;
    }

    /**
     * Ràng buộc query vào đúng danh mục hiện tại.
     * Hỗ trợ cả dữ liệu cũ (posts.category_id) và dữ liệu mới (pivot categories).
     */
    private function applyCurrentCategoryFilter(Builder $query): void
    {
        $query->where(function (Builder $categoryQuery) {
            $categoryQuery
                ->where(function (Builder $legacyQuery) {
                    $legacyQuery
                        ->whereDoesntHave('categories')
                        ->where('category_id', $this->categoryId);
                })
                ->orWhereHas('categories', function (Builder $pivotQuery) {
                    $pivotQuery->where('categories.id', $this->categoryId);
                });
        });
    }

    /**
     * Tìm bài trong đúng danh mục đang xem để ngăn gọi thủ công Livewire action
     * với ID bài thuộc danh mục khác.
     */
    private function findPostInCurrentCategoryOrFail(int $id): Post
    {
        $this->getAuthorizedCategory();

        $query = Post::query()
            ->with(['categories', 'defaultImage'])
            ->whereKey($id);

        $this->applyCurrentCategoryFilter($query);

        return $query->firstOrFail();
    }

    /**
     * Giữ nguyên logic hiển thị bài của trang danh sách bài viết tổng:
     * - Luôn thấy bài do chính mình tạo.
     * - Người có quyền duyệt toàn cục thấy bài người khác trừ nháp.
     * - Người duyệt theo danh mục thấy bài người khác trừ nháp trong phạm vi được giao.
     */
    public function getPostsProperty()
    {
        $this->getAuthorizedCategory();

        $search = trim($this->search);
        $reviewCategoryIds = $this->categoryIdsForPermission('duyet_bai_viet');
        $isGlobalReviewer = $this->hasGlobalReviewAccess();

        $allowedSortColumns = ['id', 'created_at'];
        $sortColumn = in_array($this->sortBy['column'] ?? '', $allowedSortColumns, true)
            ? $this->sortBy['column']
            : 'created_at';

        $sortDirection = strtolower($this->sortBy['direction'] ?? 'desc') === 'asc'
            ? 'asc'
            : 'desc';

        $query = Post::query()
            ->with(['categories', 'user', 'defaultImage'])
            ->where(function (Builder $inner) use ($reviewCategoryIds, $isGlobalReviewer) {
                // Luôn thấy toàn bộ bài của chính mình.
                $inner->where('user_id', auth()->id());

                // Người có quyền duyệt toàn cục: thấy bài của người khác, trừ bản nháp.
                if ($isGlobalReviewer) {
                    $inner->orWhere('status', '!=', 'draft');

                    return;
                }

                // Người duyệt theo phạm vi danh mục: thấy bài người khác, trừ nháp,
                // miễn là bài có ít nhất một danh mục được giao duyệt.
                if (! empty($reviewCategoryIds)) {
                    $inner->orWhere(function (Builder $reviewQuery) use ($reviewCategoryIds) {
                        $reviewQuery
                            ->where('status', '!=', 'draft')
                            ->where(function (Builder $categoryQuery) use ($reviewCategoryIds) {
                                $categoryQuery
                                    ->where(function (Builder $legacyQuery) use ($reviewCategoryIds) {
                                        $legacyQuery
                                            ->whereDoesntHave('categories')
                                            ->whereIn('category_id', $reviewCategoryIds);
                                    })
                                    ->orWhereHas('categories', function (Builder $pivotQuery) use ($reviewCategoryIds) {
                                        $pivotQuery->whereIn('categories.id', $reviewCategoryIds);
                                    });
                            });
                    });
                }
            });

        // Khóa dữ liệu ở đúng danh mục của route.
        $this->applyCurrentCategoryFilter($query);

        return $query
            ->when($search !== '', fn (Builder $builder) => $this->applySearchFilter($builder, $search))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($this->perPage);
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $keyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

            $query->where(function (Builder $inner) use ($keyword) {
                $inner
                    ->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(excerpt, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(excerpt, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(content, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(content, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                    ->orWhere('slug', 'like', $keyword);
            });
        }
    }

    /**
     * Giữ nguyên hành động theo từng bài viết như trang danh sách bài viết tổng.
     */
    public function getPostActionLink(Post $post): array
    {
        $user = auth()->user();

        if (! $user) {
            return [
                'link' => '#',
                'icon' => 'o-eye',
                'tooltip' => 'Xem',
                'class' => 'text-gray-500',
            ];
        }

        $categoryIds = $post->categories
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($categoryIds) && $post->category_id) {
            $categoryIds = [(int) $post->category_id];
        }

        $isAuthor = (int) $post->user_id === (int) $user->id;

        $hasGlobalReview = $user->can('quan_ly_bai_viet') || $user->can('duyet_bai_viet');
        $reviewIds = $user->scopedPostCategoryIds('duyet_bai_viet') ?? [];
        $canReviewPost = $hasGlobalReview || count(array_intersect($categoryIds, $reviewIds)) > 0;

        $hasGlobalWrite = $user->can('quan_ly_bai_viet') || $user->can('viet_bai_viet');
        $writeIds = $user->scopedPostCategoryIds('viet_bai_viet') ?? [];
        $canWritePost = $hasGlobalWrite || count(array_intersect($categoryIds, $writeIds)) > 0;

        if ($post->status === 'published') {
            if ($isAuthor || $canWritePost) {
                return [
                    'link' => route('admin.post.edit', $post->id),
                    'icon' => 'o-pencil',
                    'tooltip' => 'Chỉnh sửa',
                    'class' => 'text-primary',
                ];
            }

            if ($canReviewPost) {
                return [
                    'link' => route('admin.posts.review', $post->id),
                    'icon' => 'o-eye',
                    'tooltip' => 'Xem chi tiết',
                    'class' => 'text-info',
                ];
            }
        }

        if ($isAuthor) {
            return [
                'link' => route('admin.post.edit', $post->id),
                'icon' => 'o-pencil',
                'tooltip' => 'Chỉnh sửa',
                'class' => 'text-primary',
            ];
        }

        if ($canReviewPost && $canWritePost) {
            return [
                'link' => route('admin.post.edit', $post->id),
                'icon' => 'o-pencil-square',
                'tooltip' => 'Sửa & Duyệt bài',
                'class' => 'text-green-500',
            ];
        }

        if ($canReviewPost && ! $canWritePost) {
            return [
                'link' => route('admin.posts.review', $post->id),
                'icon' => 'o-document-check',
                'tooltip' => 'Duyệt bài viết',
                'class' => 'text-warning',
            ];
        }

        if ($canWritePost && ! $canReviewPost) {
            return [
                'link' => route('admin.post.edit', $post->id),
                'icon' => 'o-pencil',
                'tooltip' => 'Chỉnh sửa',
                'class' => 'text-primary',
            ];
        }

        return [
            'link' => route('admin.posts.review', $post->id),
            'icon' => 'o-eye',
            'tooltip' => 'Xem chi tiết',
            'class' => 'text-gray-500',
        ];
    }

    public function canDeletePost(Post $post): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->canAny('quan_ly_bai_viet', 'duyet_bai_viet')) {
            return true;
        }

        return (int) $post->user_id === (int) $user->id
            && in_array($post->status, ['draft', 'rejected'], true);
    }

    public function delete(int $id): void
    {
        $post = $this->findPostInCurrentCategoryOrFail($id);

        if (! $this->canDeletePost($post)) {
            $this->error(
                $this->isReviewerOnly()
                    ? 'Bạn chỉ có quyền duyệt bài viết, không thể xóa.'
                    : 'Bạn chỉ có thể xóa bài nháp hoặc bài bị từ chối của chính mình.'
            );

            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa bài viết này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete(int $id): void
    {
        $post = $this->findPostInCurrentCategoryOrFail($id);

        if (! $this->canDeletePost($post)) {
            $this->error('Bạn không có quyền xóa bài viết này.');

            return;
        }

        $post->delete();
        $this->success('Đã xóa bài viết thành công!');
    }

    public function toggleFeatured(int $id): void
    {
        $post = $this->findPostInCurrentCategoryOrFail($id);

        if (! $this->canReview()) {
            $this->error('Bạn không có quyền thay đổi trạng thái nổi bật.');

            return;
        }

        if ($post->status !== 'published') {
            $this->warning('Chỉ bài viết đã đăng mới được bật/tắt nổi bật.');

            return;
        }

        if (! $post->is_featured) {
            $featuredCount = Post::query()
                ->where('is_featured', true)
                ->where('status', 'published')
                ->count();

            if ($featuredCount >= 5) {
                $this->error('Đã đủ 5 bài viết nổi bật trong nhóm published. Vui lòng bỏ nổi bật một bài đã đăng trước.');

                return;
            }
        }

        $post->update(['is_featured' => ! $post->is_featured]);

        $this->success($post->is_featured
            ? 'Đã đánh dấu bài viết nổi bật.'
            : 'Đã bỏ đánh dấu bài viết nổi bật.');
    }

    public function getDisplayTitle(Post $post): string
    {
        return $post->getTranslation('title', 'vi', false)
            ?: $post->getTranslation('title', 'en', false)
                ?: '—';
    }

    public function getStatusBadge(Post $post): array
    {
        $map = [
            'draft' => ['label' => 'Nháp', 'class' => 'badge-ghost text-black!'],
            'pending_review' => ['label' => 'Chờ duyệt', 'class' => 'badge-warning text-white'],
            'rejected' => ['label' => 'Từ chối', 'class' => 'badge-error text-white'],
            'published' => ['label' => 'Đã đăng', 'class' => 'badge-success text-white'],
            'archived' => ['label' => 'Lưu trữ', 'class' => 'badge-warning text-white'],
        ];

        return $map[$post->status] ?? $map['draft'];
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10 p-2! text-center'],
            ['key' => 'thumbnail', 'label' => 'Ảnh', 'sortable' => false, 'class' => 'w-16 p-0!'],
            ['key' => 'title', 'label' => 'Tiêu đề', 'sortable' => false, 'class' => 'min-w-44'],
//            ['key' => 'category', 'label' => 'Danh mục', 'sortable' => false, 'class' => 'w-36'],
            ['key' => 'status', 'label' => 'Trạng thái', 'sortable' => false, 'class' => 'w-28'],
            ['key' => 'featured', 'label' => 'Nổi bật', 'sortable' => false, 'class' => 'w-24'],
            ['key' => 'created_at', 'label' => 'Ngày tạo', 'class' => 'w-32'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }
};
?>

<div
    x-data="{ loading: false }"
    x-on:livewire:request.window="loading = true"
    x-on:livewire:response.window="loading = false"
    x-on:livewire:error.window="loading = false"
>
    <x-slot:title>Danh sách bài viết: {{ $this->categoryName }}</x-slot:title>

    <x-slot:breadcrumb>
        <span>Danh mục</span>
        <span class="text-gray-400">/</span>
        <span>{{ $this->categoryName }}</span>
    </x-slot:breadcrumb>

    <x-header
        :title="'Danh sách bài viết: ' . $this->categoryName"
        class="pb-3 mb-5! border-b border-gray-300"
    >
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm tiêu đề hoặc slug..."
                wire:model.live.debounce.300ms="search"
                :clearable="true"
                class="w-full lg:w-80"
            />
        </x-slot:middle>
    </x-header>

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
            <a href="{{ $this->getPostActionLink($post)['link'] }}" class="w-full relative" wire:navigate>
                @if($post->thumbnail)
                    <img src="{{ Storage::url($post->thumbnail) }}"
                         class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                         alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                         loading="lazy" decoding="async">
                @elseif($post->post_default_image_id)
                    <img src="{{ Storage::url($post->defaultImage?->image_path) }}"
                         class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                         alt="Không có ảnh" loading="lazy" decoding="async">
                @else
                    <img src="{{ asset('assets/images/post-6.jpg') }}"
                         class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                         alt="Không có ảnh" loading="lazy" decoding="async">
                @endif
            </a>
            @endscope

            @scope('cell_title', $post)
            <div class="font-medium line-clamp-1">{{ $this->getDisplayTitle($post) }}</div>
            <div class="text-sm text-gray-400 line-clamp-1">{{ $post->slug }}</div>
            @endscope

{{--            @scope('cell_category', $post)--}}
{{--            @if($post->categories->isNotEmpty())--}}
{{--                <div class="flex flex-wrap gap-1">--}}
{{--                    @foreach($post->categories->take(2) as $category)--}}
{{--                        <x-badge :value="$category->getTranslatedName()" class="badge-ghost badge-md line-clamp-1"/>--}}
{{--                    @endforeach--}}
{{--                    @if($post->categories->count() > 2)--}}
{{--                        <x-badge value="+{{ $post->categories->count() - 2 }}" class="badge-ghost"/>--}}
{{--                    @endif--}}
{{--                </div>--}}
{{--            @else--}}
{{--                <span class="text-sm text-gray-400">—</span>--}}
{{--            @endif--}}
{{--            @endscope--}}

            @scope('cell_status', $post)
            <x-badge
                :value="$this->getStatusBadge($post)['label']"
                :class="$this->getStatusBadge($post)['class'] . ' badge-md font-semibold whitespace-nowrap'"
            />
            @endscope

            @scope('cell_featured', $post)
            @if($post->is_featured)
                <x-badge value="Nổi bật" class="badge-info badge-md text-white font-semibold whitespace-nowrap"/>
            @else
                <span class="text-sm text-gray-400">—</span>
            @endif
            @endscope

            @scope('cell_created_at', $post)
            <span class="text-sm text-gray-500">{{ $post->created_at?->format('d/m/Y') ?? '—' }}</span>
            @endscope

            @scope('cell_actions', $post)
            <div class="flex gap-1">
                <x-button
                    :icon="$this->getPostActionLink($post)['icon']"
                    :class="'btn-sm btn-ghost z-5 ' . $this->getPostActionLink($post)['class']"
                    :tooltipLeft="$this->getPostActionLink($post)['tooltip']"
                    :link="$this->getPostActionLink($post)['link']"
                />

                @if($this->canReview() && $post->status === 'published')
                    <x-button
                        :icon="$post->is_featured ? 's-star' : 'o-star'"
                        class="btn-sm btn-ghost z-5 {{ $post->is_featured ? 'text-warning' : 'text-gray-500' }}"
                        :tooltipLeft="$post->is_featured ? 'Bỏ nổi bật' : 'Đánh dấu nổi bật'"
                        wire:click="toggleFeatured({{ $post->id }})"
                        spinner="toggleFeatured({{ $post->id }})"
                    />
                @endif

                @if($this->canDeletePost($post))
                    <x-button
                        icon="o-trash"
                        class="btn-sm btn-ghost text-error"
                        tooltip="Xóa"
                        wire:click="delete({{ $post->id }})"
                        spinner="delete({{ $post->id }})"
                    />
                @endif
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-8">
                    <x-icon name="o-document-text" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Chưa có bài viết nào trong danh mục này.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->posts" wire:model.live="perPage" :per-page-values="[5, 10, 20, 25, 50]"/>
        </x-table>

        <div wire:loading.flex
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md">
            <div class="flex flex-col items-center gap-2">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>
