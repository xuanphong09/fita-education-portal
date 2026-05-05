<?php

use App\Models\Post;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $pendingCount = 0;

    public function mount(): void
    {
        $user = auth()->user();

        if (!$user) {
            return;
        }

        // Kiểm tra quyền (Duyệt toàn cục hoặc Duyệt theo danh mục)
        $isGlobalReviewer = $user->can('quan_ly_bai_viet') || $user->can('duyet_bai_viet');
        $reviewCategoryIds = $user->scopedPostCategoryIds('duyet_bai_viet') ?? [];

        // Nếu không có quyền duyệt nào -> Đếm = 0 và dừng luôn
        if (!$isGlobalReviewer && empty($reviewCategoryIds)) {
            $this->pendingCount = 0;
            return;
        }

        // Bắt đầu query bài viết ở trạng thái Chờ duyệt
        $query = Post::query()->where('status', Post::APPROVAL_PENDING ?? 'pending_review');

        // Áp dụng Row-Level Security: Lọc đúng các danh mục được giao
        if (!$isGlobalReviewer) {
            $query->where(function ($catQuery) use ($reviewCategoryIds) {
                $catQuery->where(function ($legacy) use ($reviewCategoryIds) {
                    $legacy->whereDoesntHave('categories')->whereIn('category_id', $reviewCategoryIds);
                })->orWhereHas('categories', function ($pivot) use ($reviewCategoryIds) {
                    $pivot->whereIn('categories.id', $reviewCategoryIds);
                });
            });
        }

        $this->pendingCount = $query->count();
    }

    #[On('post:pending-count-changed')]
    public function applyDelta(?int $delta = null): void
    {
        if ($delta === null || $delta === 0) {
            return;
        }

        $this->pendingCount = max(0, $this->pendingCount + $delta);
    }
};
?>

<x-menu-item
    title="Danh sách bài chờ duyệt"
    :link="route('admin.posts.pending')"
    :active="request()->routeIs('admin.posts.*')"
    :badge="$this->pendingCount > 0 ? $this->pendingCount : null"
    badge-color="bg-red-500 text-white"
    badgeClasses="bg-red-500 text-white rounded-full px-2 py-0 text-md absolute top-3 right-2 border-transparent"
/>
