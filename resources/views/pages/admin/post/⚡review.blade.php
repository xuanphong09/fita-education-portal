<?php

use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Post;
use App\Models\PostApprovalHistory;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\Services\PostNotificationService;
use Carbon\Carbon;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public int $id;
    public string $selectedTab = 'tab-vi';

    public string $title_vi = '';
    public string $title_en = '';
    public string $content_vi = '';
    public string $content_en = '';
    public string $excerpt_vi = '';
    public string $excerpt_en = '';
    public string $slug = '';
    public string $url = '';

    public string $status = Post::APPROVAL_PENDING;
    public string $currentStatus = Post::APPROVAL_PENDING;
    public ?string $published_at = null;
    public ?string $submitted_at = null;
    public ?string $reviewed_at = null;
    public ?string $rejection_reason = null;
    public string $reviewNote = '';

    public ?int $author_id = null;
    public string $author_name = '—';
    public int $views = 0;
    public array $categories = [];
    public ?string $currentThumbnail = null;
    public bool $is_featured = false;
    public bool $show_author = true;
    public bool $show_published_at = true;
    public bool $show_views = true;
    public bool $show_category = true;
    public bool $show_related_posts = true;

    // Biến phụ để check trạng thái UI
    public bool $isScheduled = false;

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
        return $this->canReview() && !$this->canWrite();
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Nháp',
            'pending_review' => 'Chờ duyệt',
            'rejected' => 'Từ chối',
            'published' => 'Đã đăng',
            'archived' => 'Lưu trữ',
            default => $status,
        };
    }

    private function postCategoryIds(Post $post): array
    {
        $categoryIds = $post->categories()->pluck('categories.id')->map(fn($categoryId) => (int)$categoryId)->toArray();

        if (empty($categoryIds) && $post->category_id) {
            $categoryIds = [(int)$post->category_id];
        }

        return array_values(array_unique(array_filter($categoryIds, fn($categoryId) => $categoryId > 0)));
    }

    private function authorizeReviewAccess(Post $post): void
    {
        $user = auth()->user();
        if (!$user) abort(403);

        if ($user->can('quan_ly_bai_viet') || $user->can('duyet_bai_viet')) return;

        $postCategoryIds = $this->postCategoryIds($post);
        $reviewIds = $user->scopedPostCategoryIds('duyet_bai_viet') ?? [];

        if (count(array_intersect($postCategoryIds, $reviewIds)) > 0) return;

        abort(403);
    }

    public function mount(int $id): void
    {
        $this->id = $id;
        $post = Post::query()->with(['categories', 'user'])->findOrFail($id);
        $this->authorizeReviewAccess($post);

        $this->title_vi = $post->getTranslation('title', 'vi', false) ?? '';
        $this->title_en = $post->getTranslation('title', 'en', false) ?? '';
        $this->content_vi = $post->getTranslation('content', 'vi', false) ?? '';
        $this->content_en = $post->getTranslation('content', 'en', false) ?? '';
        $this->excerpt_vi = $post->getTranslation('excerpt', 'vi', false) ?? '';
        $this->excerpt_en = $post->getTranslation('excerpt', 'en', false) ?? '';
        $this->slug = $post->slug ?? '';
        $this->url = $post->client_url;
        $this->status = $post->status;
        $this->currentStatus = $post->status;

        // GIỮ NGUYÊN FORMAT NÀY CHO INPUT DATETIME-LOCAL
        $this->published_at = $post->published_at?->format('Y-m-d\TH:i');

        $this->submitted_at = $post->submitted_at?->format('d/m/Y H:i');
        $this->reviewed_at = $post->reviewed_at?->format('d/m/Y H:i');
        $this->rejection_reason = $post->rejection_reason;
        $this->author_id = $post->user_id;
        $this->author_name = $post->user?->name ?? '—';
        $this->views = (int)($post->views ?? 0);
        $this->categories = $post->categories
            ->map(fn($category) => [
                'id' => $category->id,
                'name' => $category->getTranslatedName(),
            ])
            ->values()
            ->all();
        $this->currentThumbnail = $post->thumbnail;
        $this->is_featured = (bool)$post->is_featured;
        $this->show_author = (bool)$post->show_author;
        $this->show_published_at = (bool)$post->show_published_at;
        $this->show_views = (bool)$post->show_views;
        $this->show_category = (bool)$post->show_category;
        $this->show_related_posts = (bool)$post->show_related_posts;

        $this->checkScheduleStatus();
    }

    // Hàm phụ trợ kiểm tra trạng thái lên lịch
    private function checkScheduleStatus(): void
    {
        if ($this->currentStatus === 'published' && $this->published_at) {
            $this->isScheduled = Carbon::parse($this->published_at)->isFuture();
        } else {
            $this->isScheduled = false;
        }
    }

    public function approvePost(): void
    {
        $post = Post::findOrFail($this->id);
        $this->authorizeReviewAccess($post);

        if ($post->status !== Post::APPROVAL_PENDING) {
            $this->warning('Bài viết này đã được xử lý bởi người khác.');
            return;
        }

        $publishAt = $this->published_at ? Carbon::parse($this->published_at) : now();

        $post->update([
            'status' => 'published',
            'published_at' => $publishAt,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        // SỬA LỖI: Gán lại đúng định dạng cho thẻ input
        $this->published_at = $publishAt->format('Y-m-d\TH:i');
        $this->currentStatus = 'published';
        $this->status = 'published';
        $this->reviewed_at = now()->format('d/m/Y H:i');
        $this->rejection_reason = null;

        $this->checkScheduleStatus();

        $this->success($publishAt->greaterThan(now()) ? 'Đã duyệt và lên lịch đăng bài.' : 'Đã duyệt và đăng bài viết.');

        PostApprovalHistory::create([
            'post_id' => $post->id,
            'action' => 'approved',
            'actor_id' => auth()->id(),
            'reviewer_id' => auth()->id(),
            'note' => $this->reviewNote ?: 'Duyệt bài viết.',
            'scheduled_publish_at' => $publishAt->toDateTimeString(),
        ]);

        app(PostNotificationService::class)->notifyApproved(
            $post,
            auth()->user()?->name ?? '—'
        );

        $this->reviewNote = '';
    }

    public function rejectPost(): void
    {
        $post = Post::findOrFail($this->id);
        $this->authorizeReviewAccess($post);

        if ($post->status !== Post::APPROVAL_PENDING) {
            $this->warning('Bài viết này đã được xử lý bởi người khác.');
            return;
        }

        $this->validate([
            'reviewNote' => 'required|string|min:5|max:1000',
        ], [
            'reviewNote.required' => 'Vui lòng nhập lý do từ chối để tác giả biết.',
            'reviewNote.min' => 'Lý do từ chối quá ngắn (tối thiểu 5 ký tự).'
        ]);

        $rejectNote = $this->reviewNote;

        $post->update([
            'status' => Post::APPROVAL_REJECTED,
            'published_at' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $rejectNote,
        ]);

        $this->currentStatus = Post::APPROVAL_REJECTED;
        $this->status = Post::APPROVAL_REJECTED;
        $this->published_at = null;
        $this->reviewed_at = now()->format('d/m/Y H:i');
        $this->rejection_reason = $rejectNote;
        $this->reviewNote = '';
        $this->isScheduled = false;

        $this->warning('Đã từ chối bài viết.');

        PostApprovalHistory::create([
            'post_id' => $post->id,
            'action' => 'rejected',
            'actor_id' => auth()->id(),
            'reviewer_id' => auth()->id(),
            'note' => $rejectNote,
        ]);

        app(PostNotificationService::class)->notifyRejected(
            $post,
            auth()->user()?->name ?? '—',
            $rejectNote
        );
    }

    public function getApprovalHistoriesProperty()
    {
        return PostApprovalHistory::query()
            ->with(['actor', 'reviewer'])
            ->where('post_id', $this->id)
            ->latest()
            ->limit(10)
            ->get();
    }

    public function revertToPending(): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn gỡ bài viết này xuống và chuyển về trạng thái Chờ duyệt không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmedRevertToPending',
        ]);
    }

    #[On('confirmedRevertToPending')]
    public function confirmedRevertToPending(): void
    {
        $post = Post::findOrFail($this->id);
        $this->authorizeReviewAccess($post);

        if ($post->status !== 'published') {
            $this->warning('Chỉ có thể gỡ bài khi bài viết đang ở trạng thái Đã đăng.');
            return;
        }

        $user = auth()->user();
        $isGlobalAdmin = $user->can('quan_ly_bai_viet');

        if (!$isGlobalAdmin) {
            if ($post->reviewed_by !== $user->id) {
                $this->error('Bạn không thể gỡ bài do người duyệt khác xuất bản. Vui lòng liên hệ Quản trị viên.');
                return;
            }

            if ($post->reviewed_at && $post->reviewed_at->diffInHours(now()) > 24) {
                $this->error('Đã quá 24 giờ kể từ khi duyệt bài. Bạn không thể tự gỡ bài, vui lòng báo cáo Quản trị viên.');
                return;
            }
        }

        $post->update([
            'status' => Post::APPROVAL_PENDING,
            'published_at' => null,
        ]);

        $this->currentStatus = Post::APPROVAL_PENDING;
        $this->status = Post::APPROVAL_PENDING;
        $this->published_at = null;
        $this->isScheduled = false;

        $this->warning('Đã gỡ bài và hoàn về trạng thái Chờ duyệt.');

        PostApprovalHistory::create([
            'post_id' => $post->id,
            'action' => 'rejected',
            'actor_id' => auth()->id(),
            'reviewer_id' => auth()->id(),
            'note' => 'Hủy duyệt / Gỡ bài viết khỏi hệ thống.',
        ]);

        app(PostNotificationService::class)->notifyRevertedToPending(
            $post,
            auth()->user()?->name ?? '—',
            auth()->id()
        );
    }
};
?>

<div x-data x-on:open-preview.window="window.open($event.detail.url, '_blank')">
    <x-slot:title>Duyệt bài viết</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.posts.pending') }}" class="font-semibold text-slate-700" wire:navigate>Bài chờ duyệt</a>
        <span class="mx-1">/</span>
        <span>Duyệt bài viết</span>
    </x-slot:breadcrumb>

    <x-header title="Duyệt bài viết" class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:actions>
            {{-- ĐÃ NÂNG CẤP: Nút Xem bài viết chỉ hiện nếu bài đã được publish VÀ không phải là đang lên lịch --}}
            @if($currentStatus === 'published' && !$isScheduled && $url)
                <x-button label="Xem bài viết" class="bg-info text-white" link="{{ $url }}" external="true"/>
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">
            <x-card title="Thông tin bài viết" shadow class="p-3!">
                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="font-semibold mb-1">Tiêu đề chính:</div>
                        <div class="text-gray-600 font-semibold">{{ $title_vi ?: $title_en }}</div>
                    </div>
                    <div>
                        <div class="font-semibold mb-1">Đường dẫn:</div>
                        <div class="text-gray-600 font-medium break-all">{{ $url ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="font-semibold mb-1">Tác giả:</div>
                        <div class="text-gray-600 font-medium">{{ $author_name }}</div>
                    </div>
                    <div>
                        <div class="font-semibold mb-1">Trạng thái:</div>
                        @php
                            $statusMap = [
                                'draft' => ['label' => 'Nháp', 'class' => 'badge-ghost'],
                                'pending_review' => ['label' => 'Chờ duyệt', 'class' => 'badge-warning text-white'],
                                'rejected' => ['label' => 'Từ chối', 'class' => 'badge-error text-white'],
                                'published' => ['label' => 'Đã đăng', 'class' => 'badge-success text-white'],
                                'archived' => ['label' => 'Lưu trữ', 'class' => 'badge-neutral text-white'],
                            ];

                            $status = $statusMap[$currentStatus] ?? $statusMap['draft'];

                            if ($isScheduled) {
                                $status = ['label' => 'Đã lên lịch', 'class' => 'badge-info text-white'];
                            }
                        @endphp
                        <x-badge :value="$status['label']" class="{{ $status['class'] }} badge-md font-medium"/>
                    </div>
                    <div>
                        <div class="font-semibold mb-1">Danh mục:</div>
                        @if($categories !== [])
                            <div class="flex flex-wrap gap-1">
                                @foreach($categories as $category)
                                    <x-badge :value="$category['name']" class="badge-ghost badge-md text-gray-600 font-medium"/>
                                @endforeach
                            </div>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </div>
                    <div>
                        <div class="font-semibold mb-1">Lịch đăng bài:</div>
                        <div class="text-gray-600 font-medium">
                            {{ $published_at ? \Carbon\Carbon::parse($published_at)->format('H:i d/m/Y') : 'Chưa có lịch đăng' }}
                        </div>
                    </div>
                </div>

                @if($currentThumbnail)
                    <div class="mt-4">
                        <div class="font-semibold mb-2 text-sm">Ảnh đại diện:</div>
                        <img src="{{ Storage::url($currentThumbnail) }}" alt="Thumbnail" class="w-full max-w-md rounded-lg border border-gray-200 object-cover"/>
                    </div>
                @endif
            </x-card>

            <x-tabs wire:model="selectedTab">
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                    <x-card title="Nội dung Tiếng Việt" shadow class="p-3!">
                        <div class="space-y-4">
                            <div>
                                <div class="text-sm text-gray-500 font-semibold mb-1">Tiêu đề</div>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 font-medium">{{ $title_vi ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 font-semibold mb-1">Mô tả ngắn</div>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">{{ $excerpt_vi ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 font-semibold mb-1">Nội dung</div>
                                <div class="rounded-lg border border-gray-200 bg-white p-4 tinymce-content max-w-none">
                                    {!! $content_vi ?: '<p class="text-gray-400">—</p>' !!}
                                </div>
                            </div>
                        </div>
                    </x-card>
                </x-tab>

                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                    <x-card title="Nội dung Tiếng Anh" shadow class="p-3!">
                        <div class="space-y-4">
                            <div>
                                <div class="text-sm text-gray-500 font-semibold mb-1">Tiêu đề</div>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 font-medium">{{ $title_en ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 font-semibold mb-1">Mô tả ngắn</div>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">{{ $excerpt_en ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 font-semibold mb-1">Nội dung</div>
                                <div class="rounded-lg border border-gray-200 bg-white p-4 prose max-w-none">
                                    {!! $content_en ?: '<p class="text-gray-400">—</p>' !!}
                                </div>
                            </div>
                        </div>
                    </x-card>
                </x-tab>
            </x-tabs>

            <x-card title="Lịch sử duyệt bài viết" shadow class="p-3!">
                @forelse($this->approvalHistories as $history)
                    @php
                        $historyTitleClass = match($history->action) {
                            'approved' => 'text-md font-bold text-green-600',
                            'rejected' => 'text-md font-bold text-red-600',
                            default => 'text-md font-bold text-gray-700',
                        };
                    @endphp
                    <div class="py-2 border-b border-gray-100 last:border-b-0">
                        <div class="{{ $historyTitleClass }}">
                            {{ __(ucfirst(str_replace('_', ' ', $history->action))) }}
                        </div>
                        <div class="text-sm text-gray-500 font-semibold">
                            {{ $history->created_at?->format('d/m/Y H:i') }}
                            @if($history->actor)
                                - {{ $history->actor->name }}
                            @endif
                        </div>
                        @if($history->scheduled_publish_at)
                            <div class="text-sm text-gray-500 font-semibold">Lên lịch: {{ \Carbon\Carbon::parse($history->scheduled_publish_at)->format('H:i d/m/Y') }}</div>
                        @endif
                        @if($history->note)
                            <div class="text-sm text-gray-700 mt-1"><span class="text-md font-semibold">Nội dung: </span>{{ $history->note }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-md text-gray-500 font-semibold">Chưa có lịch sử duyệt.</div>
                @endforelse
            </x-card>
        </div>

        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">
            <x-card title="Duyệt bài viết" shadow separator class="p-3!">
                @if($currentStatus === \App\Models\Post::APPROVAL_PENDING)
                    <x-input
                        label="Lên lịch đăng (tùy chọn)"
                        type="datetime-local"
                        wire:model="published_at"
                        hint="Để trống để đăng ngay khi duyệt"
                    />

                    <x-textarea
                        wire:model="reviewNote"
                        rows="4"
                        label="Ghi chú duyệt / lý do từ chối"
                        placeholder="Nhập ghi chú cho tác giả..."
                        class="mt-3"
                    />

                    <x-button label="Duyệt bài" class="bg-success text-white w-full mt-3" wire:click="approvePost" spinner="approvePost"/>
                    <x-button label="Từ chối bài" class="bg-error text-white w-full mt-2" wire:click="rejectPost" spinner="rejectPost"/>

                @elseif($currentStatus === 'published')
                    @php
                        $user = auth()->user();
                        $post = Post::findOrFail($this->id);
                        $isGlobalAdmin = $user->can('quan_ly_bai_viet');
                        $isMyReview = $post->reviewed_by === $user->id;
                        $isWithin24h = $post->reviewed_at && $post->reviewed_at->diffInHours(now()) <= 24;
                        $canRevert = $isGlobalAdmin || ($isMyReview && $isWithin24h);
                    @endphp

                    @if($isScheduled)
                        {{-- Hiển thị giao diện Đã lên lịch (Màu xanh dương) --}}
                        <div class="text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded p-3 mb-4">
                            Bài viết đang ở trạng thái <strong>Đã lên lịch</strong>.<br>
                            <span class="text-sm text-blue-500 mt-1 block">
                                Sẽ xuất bản lúc: {{ \Carbon\Carbon::parse($published_at)->format('H:i d/m/Y') }}
                            </span>
                        </div>
                    @else
                        {{-- Hiển thị giao diện Đã đăng (Màu xanh lá) --}}
                        <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded p-3 mb-4">
                            Bài viết đang ở trạng thái <strong>Đã đăng</strong>.<br>
                            <span class="text-sm text-green-600 mt-1 block">
                                Xuất bản lúc: {{ $published_at ? \Carbon\Carbon::parse($published_at)->format('H:i d/m/Y') : ($reviewed_at ?? '—') }}
                            </span>
                        </div>
                    @endif

                    @if($canRevert)
                        <x-button
                            label="{{ $isScheduled ? 'Hủy lịch' : 'Hủy duyệt / Gỡ bài' }}"
                            icon="o-arrow-uturn-left"
                            class="bg-warning text-white w-full"
                            wire:click="revertToPending"
                            spinner="revertToPending"
                        />
                    @else
                        <div class="text-xs text-gray-500 italic border-t border-gray-200 pt-3">
                            * Không thể gỡ bài: Quá 24h kể từ lúc duyệt hoặc không phải bài do bạn xuất bản.
                        </div>
                    @endif
                @else
                    <div class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded p-3">
                        Bài viết đang ở trạng thái <strong>{{ $this->statusLabel($currentStatus) }}</strong>. Bạn chỉ có thể xem lịch sử và nội dung bài.
                    </div>
                @endif
            </x-card>

            <x-card title="Thông tin" shadow class="p-3!">
                <div class="text-sm space-y-2 text-gray-600">
                    <div class="flex justify-between gap-3">
                        <span>Lượt xem:</span>
                        <span class="font-medium">{{ number_format($views) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>Hiển thị người viết:</span>
                        <span class="font-medium">{{ $show_author ? 'Có' : 'Không' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>Hiển thị danh mục:</span>
                        <span class="font-medium">{{ $show_category ? 'Có' : 'Không' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>Hiển thị ngày đăng:</span>
                        <span class="font-medium">{{ $show_published_at ? 'Có' : 'Không' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>Nổi bật:</span>
                        <span class="font-medium">{{ $is_featured ? 'Có' : 'Không' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span>URL xem bài:</span>
                        <span class="font-medium truncate text-right">{{ $url ?: '—' }}</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>
