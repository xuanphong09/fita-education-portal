<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Post;
use App\Models\Category;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public int $perPage = 10;
    #[Url(as: 'search')]
    public string $search = '';
    public string $filterStatus = '';
    #[Url(as: 'category')]
    public int|string|null $filterCategory = null;
    public string $filterFeatured = '';
    public string $filterLanguage = '';
    public bool $pendingOnlyMode = false;
    public ?int $filterAuthor = null;

    public function mount(): void
    {
        $this->pendingOnlyMode = request()->routeIs('admin.posts.pending');

        if ($this->pendingOnlyMode && !$this->canReview()) {
            abort(403);
        }

        if ($this->pendingOnlyMode) {
            $this->filterStatus = Post::APPROVAL_PENDING;
        }
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
    public function getPostActionLink(Post $post): array
    {
        $user = auth()->user();
        if (!$user) {
            return ['link' => '#', 'icon' => 'o-eye', 'tooltip' => 'Xem', 'class' => 'text-gray-500'];
        }

        // 1. Lấy danh sách ID danh mục của bài viết
        $categoryIds = $post->categories->pluck('id')->map(fn($id) => (int)$id)->toArray();
        if (empty($categoryIds) && $post->category_id) {
            $categoryIds = [(int) $post->category_id];
        }

        // 2. Xác định các quyền (ÁP DỤNG LOGIC "CHỈ CẦN 1 DANH MỤC")
        $isAuthor  = ((int) $post->user_id === (int) $user->id);

        $hasGlobalReview = $user->can('quan_ly_bai_viet') || $user->can('duyet_bai_viet');
        $reviewIds = $user->scopedPostCategoryIds('duyet_bai_viet') ?? [];
        $canReview = $hasGlobalReview || count(array_intersect($categoryIds, $reviewIds)) > 0;

        $hasGlobalWrite = $user->can('quan_ly_bai_viet') || $user->can('viet_bai_viet');
        $writeIds = $user->scopedPostCategoryIds('viet_bai_viet') ?? [];
        $canWrite = $hasGlobalWrite || count(array_intersect($categoryIds, $writeIds)) > 0;

        // ==========================================
        // 3. XỬ LÝ RIÊNG CHO BÀI ĐÃ ĐĂNG (PUBLISHED)
        // ==========================================
        if ($post->status === 'published') {
            if ($isAuthor || $canWrite) {
                return [
                    'link' => route('admin.post.edit', $post->id),
                    'icon' => 'o-pencil',
                    'tooltip' => 'Chỉnh sửa',
                    'class' => 'text-primary' // 🔵 Xanh dương
                ];
            }
            if ($canReview) {
                return [
                    'link' => route('admin.posts.review', $post->id),
                    'icon' => 'o-eye',
                    'tooltip' => 'Xem chi tiết',
                    'class' => 'text-info' // 🩵 Xanh nhạt (Chỉ xem)
                ];
            }
        }

        // ==========================================
        // 4. CÁC TRẠNG THÁI KHÁC (Nháp, Chờ duyệt, Từ chối)
        // ==========================================

        // Tác giả bài viết -> Luôn vào trang Sửa
        if ($isAuthor) {
            return [
                'link' => route('admin.post.edit', $post->id),
                'icon' => 'o-pencil',
                'tooltip' => 'Chỉnh sửa',
                'class' => 'text-primary'
            ];
        }

        // Biên tập viên (Vừa Duyệt + Vừa Viết) -> Sửa & Duyệt
        if ($canReview && $canWrite) {
            return [
                'link' => route('admin.post.edit', $post->id),
                'icon' => 'o-pencil-square',
                'tooltip' => 'Sửa & Duyệt bài',
                'class' => 'text-green-500'
            ];
        }

        // Người kiểm duyệt (CHỈ Duyệt, KHÔNG Viết) -> Màn hình Read-only
        if ($canReview && !$canWrite) {
            return [
                'link' => route('admin.posts.review', $post->id),
                'icon' => 'o-document-check',
                'tooltip' => 'Duyệt bài viết',
                'class' => 'text-warning'
            ];
        }

        // Quản lý nội dung (CHỈ Viết, KHÔNG Duyệt)
        if ($canWrite && !$canReview) {
            return [
                'link' => route('admin.post.edit', $post->id),
                'icon' => 'o-pencil',
                'tooltip' => 'Chỉnh sửa',
                'class' => 'text-primary'
            ];
        }

        // Fallback: Không có quyền thao tác -> Chuyển vào trang Review để xem
        return [
            'link' => route('admin.posts.review', $post->id),
            'icon' => 'o-eye',
            'tooltip' => 'Xem chi tiết',
            'class' => 'text-gray-500'
        ];
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
            return Category::query()->orderBy('order')->pluck('id')->map(fn ($categoryId) => (int) $categoryId)->all();
        }

        if ($basePermission === 'duyet_bai_viet' && $this->hasGlobalReviewAccess()) {
            return Category::query()->orderBy('order')->pluck('id')->map(fn ($categoryId) => (int) $categoryId)->all();
        }

        return $user->scopedPostCategoryIds($basePermission);
    }

    private function accessibleCategoryIds(): array
    {
        return collect([
            $this->categoryIdsForPermission('viet_bai_viet'),
            $this->categoryIdsForPermission('duyet_bai_viet'),
        ])
            ->flatten()
            ->map(fn ($categoryId) => (int) $categoryId)
            ->filter(fn ($categoryId) => $categoryId > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function getPostsProperty()
    {
        $search = trim($this->search);

        // Chỉ lấy các danh mục mà user có QUYỀN DUYỆT để làm cơ sở cho việc nhìn thấy bài người khác
        $reviewCategoryIds = $this->categoryIdsForPermission('duyet_bai_viet');
        $isGlobalReviewer = $this->hasGlobalReviewAccess();

        return Post::query()
            ->with(['categories', 'user'])
            ->when($this->pendingOnlyMode, function ($query) use ($reviewCategoryIds, $isGlobalReviewer) {
                // TRANG CHỜ DUYỆT:
                $query->where('status', Post::APPROVAL_PENDING);

                if (!$isGlobalReviewer) {
                    if (empty($reviewCategoryIds)) {
                        $query->whereRaw('1 = 0'); // Không có quyền duyệt danh mục nào -> Không thấy gì
                    } else {
                        // Chỉ thấy bài chờ duyệt thuộc các danh mục mình có quyền duyệt
                        $query->where(function ($catQuery) use ($reviewCategoryIds) {
                            $catQuery->where(function ($legacy) use ($reviewCategoryIds) {
                                $legacy->whereDoesntHave('categories')->whereIn('category_id', $reviewCategoryIds);
                            })->orWhereHas('categories', function ($pivot) use ($reviewCategoryIds) {
                                $pivot->whereIn('categories.id', $reviewCategoryIds);
                            });
                        });
                    }
                }
            }, function ($query) use ($reviewCategoryIds, $isGlobalReviewer) {
                // TRANG DANH SÁCH TỔNG:
                $query->where(function ($inner) use ($reviewCategoryIds, $isGlobalReviewer) {

                    // 1. BẤT CHẤP LÀ AI: Luôn nhìn thấy tất cả bài viết của CHÍNH MÌNH
                    $inner->where('user_id', auth()->id());

                    // 2. NẾU LÀ ADMIN / TỔNG BIÊN TẬP TOÀN CỤC: Thấy toàn bộ bài của người khác (trừ bản nháp)
                    if ($isGlobalReviewer) {
                        $inner->orWhere('status', '!=', 'draft');
                    }
                    // 3. NẾU LÀ NGƯỜI DUYỆT TỪNG DANH MỤC: Thấy bài người khác (trừ nháp) TRONG DANH MỤC ĐƯỢC GIAO
                    elseif (!empty($reviewCategoryIds)) {
                        $inner->orWhere(function ($q) use ($reviewCategoryIds) {
                            $q->where('status', '!=', 'draft')
                                ->where(function ($catQuery) use ($reviewCategoryIds) {
                                    $catQuery->where(function ($legacy) use ($reviewCategoryIds) {
                                        $legacy->whereDoesntHave('categories')->whereIn('category_id', $reviewCategoryIds);
                                    })->orWhereHas('categories', function ($pivot) use ($reviewCategoryIds) {
                                        $pivot->whereIn('categories.id', $reviewCategoryIds);
                                    });
                                });
                        });
                    }
                    // Nếu CHỈ CÓ QUYỀN VIẾT: Code sẽ không chạy vào 2 nhánh orWhere trên.
                    // -> Kết quả là chỉ nhánh `where('user_id', auth()->id())` có hiệu lực!
                });
            })
            ->when($this->filterLanguage !== '', fn($q) => $this->applyLanguageFilter($q, $this->filterLanguage))
            ->when($search !== '', fn($q) => $this->applySearchFilter($q, $search, $this->filterLanguage))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when(! blank($this->filterCategory), function ($q) {
                $categoryIds = $this->selectedCategoryFilterIds();

                if (empty($categoryIds)) {
                    $q->whereRaw('1 = 0');
                    return;
                }

                $q->where(function ($catQuery) use ($categoryIds) {
                    $catQuery
                        ->where(function ($legacy) use ($categoryIds) {
                            $legacy
                                ->whereDoesntHave('categories')
                                ->whereIn('category_id', $categoryIds);
                        })
                        ->orWhereHas('categories', function ($pivot) use ($categoryIds) {
                            $pivot->whereIn('categories.id', $categoryIds);
                        });
                });
            })
            ->when($this->filterFeatured !== '', fn($q) => $q->where('is_featured', $this->filterFeatured === '1'))
            ->when($this->filterAuthor && $this->canReview(), fn($q) => $q->where('user_id', $this->filterAuthor))
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function getAuthorsProperty()
    {
        if (!$this->canReview()) {
            return [];
        }

        return User::query()
            ->whereHas('posts') // Chỉ lấy những user có bài viết
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->toArray();
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

    public function getCategoriesProperty(): array
    {
        $canSeeAllCategories = $this->pendingOnlyMode
            ? $this->hasGlobalReviewAccess()
            : ($this->hasGlobalWriteAccess() || $this->hasGlobalReviewAccess());

        $allowedCategoryIds = $this->pendingOnlyMode
            ? $this->categoryIdsForPermission('duyet_bai_viet')
            : $this->accessibleCategoryIds();

        $allowedCategoryIds = collect($allowedCategoryIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $categories = Category::query()
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        // Global quyền thì được lọc toàn bộ danh mục
        if ($canSeeAllCategories) {
            $allowedCategoryIds = $categories
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        // Không có quyền danh mục nào thì không hiện dropdown danh mục
        if (empty($allowedCategoryIds)) {
            return [];
        }

        $allowedMap = array_flip($allowedCategoryIds);
        $categoriesById = $categories->keyBy('id');

        $visibleIds = [];

        foreach ($allowedCategoryIds as $id) {
            $visibleIds[] = $id;

            $current = $categoriesById->get($id);

            // Thêm cha / ông nội để dropdown nhìn đúng cây
            while ($current && $current->parent_id) {
                $parentId = (int) $current->parent_id;

                $visibleIds[] = $parentId;
                $current = $categoriesById->get($parentId);
            }
        }

        $visibleMap = array_flip(array_unique($visibleIds));

        return $this->flattenCategorySelectOptions(
            categories: $categories,
            parentId: null,
            allowedMap: $allowedMap,
            visibleMap: $visibleMap,
            depth: 0
        );
    }

    private function flattenCategorySelectOptions(
        Collection $categories,
        ?int $parentId,
        array $allowedMap,
        array $visibleMap,
        int $depth = 0
    ): array {
        $options = [];

        $children = $parentId === null
            ? $categories->filter(fn ($category) => blank($category->parent_id) || (int) $category->parent_id === 0)
            : $categories->filter(fn ($category) => (int) $category->parent_id === $parentId);

        foreach ($children as $category) {
            $id = (int) $category->id;

            if (isset($visibleMap[$id])) {
                $prefix = $depth > 0 ? str_repeat('—', $depth) . ' ' : '';

                $options[] = [
                    'id' => $id,
                    'name' => $prefix . $category->getTranslatedName(),

                    // Cha chỉ để nhìn cây, không cho chọn nếu không có quyền trực tiếp
                    'disabled' => ! isset($allowedMap[$id]),
                ];
            }

            $options = array_merge(
                $options,
                $this->flattenCategorySelectOptions(
                    categories: $categories,
                    parentId: $id,
                    allowedMap: $allowedMap,
                    visibleMap: $visibleMap,
                    depth: $depth + 1
                )
            );
        }

        return $options;
    }

    private function selectedCategoryFilterIds(): array
    {
        if (blank($this->filterCategory)) {
            return [];
        }

        $selectedCategoryId = (int) $this->filterCategory;

        $categories = Category::query()
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $ids = [$selectedCategoryId];

        $ids = array_merge(
            $ids,
            $this->getAllDescendantCategoryIds($categories, $selectedCategoryId)
        );

        $allowedCategoryIds = $this->pendingOnlyMode
            ? $this->categoryIdsForPermission('duyet_bai_viet')
            : $this->accessibleCategoryIds();

        $allowedCategoryIds = collect($allowedCategoryIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        // Global quyền thì được lọc toàn bộ danh mục
        $canSeeAllCategories = $this->pendingOnlyMode
            ? $this->hasGlobalReviewAccess()
            : ($this->hasGlobalWriteAccess() || $this->hasGlobalReviewAccess());

        if ($canSeeAllCategories) {
            return array_values(array_unique($ids));
        }

        // User thường chỉ được lọc trong nhóm danh mục được cấp quyền
        return array_values(array_intersect(
            array_unique($ids),
            $allowedCategoryIds
        ));
    }

    private function getAllDescendantCategoryIds(Collection $categories, int $parentId): array
    {
        $ids = [];

        foreach ($categories->where('parent_id', $parentId) as $child) {
            $ids[] = (int) $child->id;

            $ids = array_merge(
                $ids,
                $this->getAllDescendantCategoryIds($categories, (int) $child->id)
            );
        }

        return $ids;
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10 p-2! text-center'],
            ['key' => 'thumbnail', 'label' => 'Ảnh', 'sortable' => false, 'class' => 'w-16 p-0!'],
            ['key' => 'title', 'label' => 'Tiêu đề', 'class' => 'min-w-44'],
            ['key' => 'category', 'label' => 'Danh mục', 'sortable' => false, 'class' => 'w-36'],
            ['key' => 'status', 'label' => 'Trạng thái', 'sortable' => false, 'class' => 'w-28'],
            ['key' => 'featured', 'label' => 'Nổi bật', 'sortable' => false, 'class' => 'w-24'],
//            ['key' => 'views', 'label' => 'Lượt xem', 'class' => 'w-24'],
            ['key' => 'created_at', 'label' => 'Ngày tạo', 'class' => 'w-32'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
    public function updatedFilterAuthor(): void
    {
        $this->resetPage();
    }
    public function updatedFilterCategory(): void
    {
        if ($this->filterCategory === '') {
            $this->filterCategory = null;
        }

        if (! blank($this->filterCategory)) {
            $this->filterCategory = (int) $this->filterCategory;
        }

        $this->resetPage();
    }

    public function updatedFilterFeatured(): void
    {
        $this->resetPage();
    }

    public function updatedFilterLanguage(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->filterCategory = null;
        $this->filterLanguage = '';
        $this->filterAuthor = null;
        $this->filterFeatured = '';
        if ($this->pendingOnlyMode) {
            $this->filterStatus = Post::APPROVAL_PENDING;
        }
        $this->resetPage();
    }

    public function getHasActiveFiltersProperty(): bool
    {
        return trim($this->search) !== ''
            || !blank($this->filterCategory)
            || ($this->pendingOnlyMode? $this->filterStatus !== Post::APPROVAL_PENDING : $this->filterStatus !== '')
            || $this->filterFeatured !== ''
            || $this->filterLanguage !== ''
            || !is_null($this->filterAuthor);
    }

    public function canDeletePost(Post $post): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->canAny('quan_ly_bai_viet', 'duyet_bai_viet')) {
            return true;
        }

        // 2. Tác giả bài viết -> Chỉ được xóa bài của CHÍNH MÌNH khi đang Nháp hoặc Bị từ chối
        if ((int) $post->user_id === (int) $user->id) {
            return in_array($post->status, ['draft', 'rejected'], true);
        }

        return false;
    }

    public function delete(int $id): void
    {
        $post = Post::findOrFail($id);

        if (!$this->canDeletePost($post)) {
            $this->error($this->isReviewerOnly() ? 'Bạn chỉ có quyền duyệt bài viết, không thể xóa.' : 'Bạn chỉ có thể xóa bài nháp của chính mình.');
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
        $post = Post::findOrFail($id);

        if (!$this->canDeletePost($post)) {
            $this->error($this->isReviewerOnly() ? 'Bạn chỉ có quyền duyệt bài viết, không thể xóa.' : 'Bạn không có quyền xóa bài viết này.');
            return;
        }

//        if ($post->thumbnail) {
//            Storage::disk('public')->delete($post->thumbnail);
//        }
        $post->delete();
        $this->success('Đã xóa bài viết thành công!');
    }

    public function toggleFeatured(int $id): void
    {
        if (!$this->canReview()) {
            $this->error('Bạn không có quyền thay đổi trạng thái nổi bật.');
            return;
        }

        $post = Post::findOrFail($id);

        if ($post->status !== 'published') {
            $this->warning('Chỉ bài viết đã đăng mới được bật/tắt nổi bật.');
            return;
        }

        // Chỉ áp quota khi đang bật nổi bật cho bài published.
        if (!$post->is_featured) {
            $featuredCount = Post::where('is_featured', true)
                ->where('status', 'published')
                ->count();

            if ($featuredCount >= 5) {
                $this->error('Đã đủ 5 bài viết nổi bật trong nhóm published. Vui lòng bỏ nổi bật một bài đã đăng trước.');
                return;
            }
        }

        $post->update(['is_featured' => !$post->is_featured]);

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
    <x-slot:title>{{ $pendingOnlyMode ? 'Danh sách bài viết chờ duyệt' : 'Danh sách bài viết' }}</x-slot:title>

    <x-slot:breadcrumb>
        <span>{{ $pendingOnlyMode ? 'Danh sách bài viết chờ duyệt' : 'Danh sách bài viết' }}</span>
    </x-slot:breadcrumb>

    <x-header :title="$pendingOnlyMode ? 'Danh sách bài viết chờ duyệt' : 'Danh sách bài viết'"
              class="pb-3 mb-5! border-b border-gray-300">
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
            @if(!$this->pendingOnlyMode && $this->canWrite())
                <x-button icon="o-trash" class="btn-ghost" label="Thùng rác" link="{{ route('admin.post.trash') }}"/>
                <x-button icon="o-plus" class="btn-primary text-white" label="Tạo bài viết"
                      link="{{ route('admin.post.create') }}"/>
            @endif
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
        @if(!$pendingOnlyMode)
        <x-select
            wire:model.live="filterStatus"
            placeholder="Tất cả trạng thái"
            placeholder-value=""
            :options="[
                ['id'=>'draft',     'name'=>'Nháp'],
                ['id'=>'pending_review', 'name'=>'Chờ duyệt'],
                ['id'=>'rejected', 'name'=>'Từ chối'],
                ['id'=>'published', 'name'=>'Đã đăng'],
                ['id'=>'archived',  'name'=>'Lưu trữ'],
            ]"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        @endif
        <x-select
            wire:model.live="filterCategory"
            placeholder="Tất cả danh mục"
            placeholder-value=""
            :options="$this->categories"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        @canany(['duyet_bai_viet', 'quan_ly_bai_viet'])
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
        @endcanany
        @if($this->canReview())
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
{{--                @if($post->thumbnail)--}}
{{--                    <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->getTranslation('title','vi',false) }}"--}}
{{--                         class="w-10 h-10 rounded object-cover ring-1 ring-gray-200"/>--}}
{{--                @else--}}
{{--                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center ring-1 ring-gray-200">--}}
{{--                        <x-icon name="o-photo" class="w-5 h-5 text-gray-400"/>--}}
{{--                    </div>--}}
{{--                @endif--}}
                <a href="{{route('admin.post.edit',$post->id)}}" class="w-full relative" wire:navigate>
                    @if($post->thumbnail)
                        <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300" alt="{{ $post->getTranslation('title', app()->getLocale()) }}" loading="lazy" decoding="async">
                    @elseif($post->post_default_image_id)
                        <img src="{{ Storage::url($post->defaultImage?->image_path) }}" class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300" alt="No image" loading="lazy" decoding="async">
                        @if($post->defaultImage?->show_title)
                            <div class="absolute inset-0 flex items-center justify-center p-5" style="container-type: inline-size;">
                                <p class="line-clamp-4 font-bold"
                                   :style="{
                                                                color: '{{ $post->defaultImage?->text_color ?? '#ffffff' }}',
                                                                fontSize: 'clamp(4px, calc({{ $post->defaultImage?->text_size ?? 18 }} / 1200 * 100cqw), 60px)',
                                                                lineHeight: 1.1,
                                                                textAlign: '{{$post->defaultImage?->text_alignment ?? 'center'}}',
                                                            }"
                                   x-text="'{{ $post->getTranslation('title', app()->getLocale()) }}'"
                                ></p>
                            </div>
                        @endif
                    @else
                        <img src="{{ asset('assets/images/post-6.jpg') }}" class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300" alt="No image" loading="lazy" decoding="async">
                    @endif
                </a>
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
            <div class="text-sm text-gray-400 line-clamp-1">{{ $post->slug }}</div>
            @endscope

            @scope('cell_category', $post)
            @if($post->categories->isNotEmpty())
                <div class="flex flex-wrap gap-1">
                    @foreach($post->categories->take(2) as $category)
                        <x-badge :value="$category->getTranslatedName()" class="badge-ghost badge-md line-clamp-1"/>
                    @endforeach
                    @if($post->categories->count() > 2)
                        <x-badge value="+{{ $post->categories->count() - 2 }}" class="badge-ghost"/>
                    @endif
                </div>
            @else
                <span class="text-sm text-gray-400">—</span>
            @endif
            @endscope

            @scope('cell_status', $post)
            @php
                $map = ['draft'=>['label'=>'Nháp','class'=>'badge-ghost text-black!'],
                        'pending_review'=>['label'=>'Chờ duyệt','class'=>'badge-warning text-white'],
                        'rejected'=>['label'=>'Từ chối','class'=>'badge-error text-white'],
                        'published'=>['label'=>'Đã đăng','class'=>'badge-success'],
                        'archived'=>['label'=>'Lưu trữ','class'=>'badge-warning']];
                $s = $map[$post->status] ?? $map['draft'];
            @endphp
            <x-badge :value="$s['label']"
                     class="{{ $s['class'] }} badge-md text-white font-semibold whitespace-nowrap"/>
            @endscope

            @scope('cell_featured', $post)
            @if($post->is_featured)
                <x-badge value="Nổi bật" class="badge-info badge-md text-white font-semibold whitespace-nowrap"/>
            @else
                <span class="text-sm text-gray-400">—</span>
            @endif
            @endscope

            @scope('cell_views', $post)
            <span class="text-sm">{{ number_format($post->views) }}</span>
            @endscope

            @scope('cell_created_at', $post)
            <span class="text-sm text-gray-500">{{ $post->created_at->format('d/m/Y') }}</span>
            @endscope

            @scope('cell_actions', $post)
            <div class="flex gap-1">
                @php
                    $action = $this->getPostActionLink($post);
                @endphp

                <x-button
                    :icon="$action['icon']"
                    class="btn-sm btn-ghost {{ $action['class'] }} z-5"
                    :tooltipLeft="$action['tooltip']"
                    link="{{ $action['link'] }}"
                />

                @if(auth()->user()?->canAny(['quan_ly_bai_viet', 'duyet_bai_viet']) && $post->status === 'published')
                    <x-button
                        :icon="$post->is_featured ? 's-star' : 'o-star'"
                        class="btn-sm btn-ghost z-5 {{ $post->is_featured ? 'text-warning' : 'text-gray-500' }}"
                        :tooltipLeft="$post->is_featured ? 'Bỏ nổi bật' : 'Đánh dấu nổi bật'"
                        wire:click="toggleFeatured({{ $post->id }})"
                        spinner="toggleFeatured({{ $post->id }})"
                    />
                @endif

                @if($this->canDeletePost($post))
                    <x-button icon="o-trash" class="btn-sm btn-ghost text-error" tooltip="Xóa"
                              wire:click="delete({{ $post->id }})" spinner="delete({{ $post->id }})"/>
                @endif
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

        <div wire:loading.flex
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>

