<?php

use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Post;
use App\Models\PostApprovalHistory;
use Illuminate\Support\Facades\Storage;
use App\Services\PostNotificationService;
use Carbon\Carbon;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public int $id;
    public $post;
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

    public bool $isScheduled = false;

    public int $historyLimit = 10;

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

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Nháp',
            Post::APPROVAL_PENDING => 'Chờ duyệt',
            Post::APPROVAL_REJECTED => 'Từ chối',
            'published' => 'Đã đăng',
            'archived' => 'Lưu trữ',
            default => $status,
        };
    }

    private function postCategoryIds(Post $post): array
    {
        $categoryIds = $post->categories()
            ->pluck('categories.id')
            ->map(fn ($categoryId) => (int) $categoryId)
            ->toArray();

        if (empty($categoryIds) && $post->category_id) {
            $categoryIds = [(int) $post->category_id];
        }

        return array_values(array_unique(array_filter(
            $categoryIds,
            fn ($categoryId) => $categoryId > 0
        )));
    }

    private function authorizeReviewAccess(Post $post): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if ($user->can('quan_ly_bai_viet') || $user->can('duyet_bai_viet')) {
            return;
        }

        $postCategoryIds = $this->postCategoryIds($post);
        $reviewIds = $user->scopedPostCategoryIds('duyet_bai_viet') ?? [];

        if (count(array_intersect($postCategoryIds, $reviewIds)) > 0) {
            return;
        }

        abort(403);
    }

    public function mount(int $id): void
    {
        $this->id = $id;

        $this->post = Post::query()
            ->with(['categories', 'user', 'defaultImage'])
            ->findOrFail($id);

        $this->authorizeReviewAccess($this->post);

        $this->title_vi = $this->post->getTranslation('title', 'vi', false) ?? '';
        $this->title_en = $this->post->getTranslation('title', 'en', false) ?? '';
        $this->content_vi = $this->post->getTranslation('content', 'vi', false) ?? '';
        $this->content_en = $this->post->getTranslation('content', 'en', false) ?? '';
        $this->excerpt_vi = $this->post->getTranslation('excerpt', 'vi', false) ?? '';
        $this->excerpt_en = $this->post->getTranslation('excerpt', 'en', false) ?? '';

        $this->slug = $this->post->slug ?? '';
        $this->url = $this->post->client_url;

        $this->status = $this->post->status;
        $this->currentStatus = $this->post->status;

        $this->published_at = $this->post->published_at?->format('Y-m-d\TH:i');
        $this->submitted_at = $this->post->submitted_at?->format('d/m/Y H:i');
        $this->reviewed_at = $this->post->reviewed_at?->format('d/m/Y H:i');
        $this->rejection_reason = $this->post->rejection_reason;

        $this->author_id = $this->post->user_id;
        $this->author_name = $this->post->user?->name ?? '—';

        $this->views = (int) ($this->post->views ?? 0);

        $this->categories = $this->post->categories
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->getTranslatedName(),
            ])
            ->values()
            ->all();

        $this->currentThumbnail = $this->post->thumbnail;
        $this->is_featured = (bool) $this->post->is_featured;
        $this->show_author = (bool) $this->post->show_author;
        $this->show_published_at = (bool) $this->post->show_published_at;
        $this->show_views = (bool) $this->post->show_views;
        $this->show_category = (bool) $this->post->show_category;
        $this->show_related_posts = (bool) $this->post->show_related_posts;

        $this->checkScheduleStatus();
    }

    private function refreshPost(): void
    {
        $this->post = Post::query()
            ->with(['categories', 'user', 'defaultImage'])
            ->findOrFail($this->id);

        $this->url = $this->post->client_url;
        $this->currentThumbnail = $this->post->thumbnail;
    }

    private function checkScheduleStatus(): void
    {
        if ($this->currentStatus === 'published' && $this->published_at) {
            $this->isScheduled = Carbon::parse($this->published_at)->isFuture();
            return;
        }

        $this->isScheduled = false;
    }

    public function canRevertPublishedPost(Post $post): bool
    {
        $user = auth()->user();

        if (! $user || $post->status !== 'published') {
            return false;
        }

        if ($post->published_at && Carbon::parse($post->published_at)->isFuture()) {
            return $user->can('quan_ly_bai_viet')
                || (int) $post->reviewed_by === (int) $user->id;
        }

        $publishTime = $post->published_at ?? $post->reviewed_at;

        if (! $publishTime) {
            return false;
        }

        if (Carbon::parse($publishTime)->diffInHours(now()) > 24) {
            return false;
        }

        return $user->can('quan_ly_bai_viet')
            || (int) $post->reviewed_by === (int) $user->id;
    }

    public function approvePost(): void
    {
        $post = Post::findOrFail($this->id);
        $this->authorizeReviewAccess($post);

        if ($post->status !== Post::APPROVAL_PENDING) {
            $this->warning('Bài viết này đã được xử lý bởi người khác.');
            return;
        }

        $publishAt = filled($this->published_at)
            ? Carbon::parse($this->published_at)
            : now();

        $post->update([
            'status' => 'published',
            'published_at' => $publishAt,
            'updated_by' => auth()->id(),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        PostApprovalHistory::create([
            'post_id' => $post->id,
            'action' => 'approved',
            'actor_id' => auth()->id(),
            'reviewer_id' => auth()->id(),
            'note' => $this->reviewNote ?: 'Duyệt bài viết.',
            'scheduled_publish_at' => $publishAt->toDateTimeString(),
        ]);

        $this->sendPostNotificationOnce(
            'approved',
            $post,
            function () use ($post) {
                app(PostNotificationService::class)->notifyApproved(
                    $post,
                    auth()->user()?->name ?? '—'
                );
            }
        );

        $this->dispatch('post:pending-count-changed', delta: -1);

        $this->published_at = $publishAt->format('Y-m-d\TH:i');
        $this->currentStatus = 'published';
        $this->status = 'published';
        $this->reviewed_at = now()->format('d/m/Y H:i');
        $this->rejection_reason = null;
        $this->reviewNote = '';

        $this->refreshPost();
        $this->checkScheduleStatus();

        $this->success(
            $publishAt->greaterThan(now())
                ? 'Đã duyệt và lên lịch đăng bài.'
                : 'Đã duyệt và đăng bài viết.'
        );
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
            'reviewNote.min' => 'Lý do từ chối quá ngắn (tối thiểu 5 ký tự).',
        ]);

        $rejectNote = $this->reviewNote;

        $post->update([
            'status' => Post::APPROVAL_REJECTED,
            'published_at' => null,
            'updated_by' => auth()->id(),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $rejectNote,
        ]);

        PostApprovalHistory::create([
            'post_id' => $post->id,
            'action' => 'rejected',
            'actor_id' => auth()->id(),
            'reviewer_id' => auth()->id(),
            'note' => $rejectNote,
        ]);

        $this->sendPostNotificationOnce(
            'rejected',
            $post,
            function () use ($post, $rejectNote) {
                app(PostNotificationService::class)->notifyRejected(
                    $post,
                    auth()->user()?->name ?? '—',
                    $rejectNote
                );
            }
        );

        $this->dispatch('post:pending-count-changed', delta: -1);

        $this->currentStatus = Post::APPROVAL_REJECTED;
        $this->status = Post::APPROVAL_REJECTED;
        $this->published_at = null;
        $this->reviewed_at = now()->format('d/m/Y H:i');
        $this->rejection_reason = $rejectNote;
        $this->reviewNote = '';
        $this->isScheduled = false;

        $this->refreshPost();

        $this->warning('Đã từ chối bài viết.');
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

        if (! $this->canRevertPublishedPost($post)) {
            $this->error('Không thể gỡ bài: đã quá thời gian cho phép hoặc bạn không có quyền thu hồi bài này.');
            return;
        }

        $wasScheduled = $post->published_at && Carbon::parse($post->published_at)->isFuture();

        $post->update([
            'status' => Post::APPROVAL_PENDING,
            'published_at' => null,
            'submitted_at' => now(),
            'updated_by' => auth()->id(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);

        PostApprovalHistory::create([
            'post_id' => $post->id,
            'action' => 'reverted_to_pending',
            'actor_id' => auth()->id(),
            'reviewer_id' => auth()->id(),
            'note' => $wasScheduled
                ? 'Hủy lịch đăng và chuyển bài về trạng thái chờ duyệt.'
                : 'Gỡ bài đã đăng và chuyển về trạng thái chờ duyệt.',
        ]);

        $this->sendPostNotificationOnce(
            'reverted_to_pending',
            $post,
            function () use ($post) {
                app(PostNotificationService::class)->notifyRevertedToPending(
                    $post,
                    auth()->user()?->name ?? '—',
                    auth()->id()
                );
            }
        );

        $this->dispatch('post:pending-count-changed', delta: 1);

        $this->currentStatus = Post::APPROVAL_PENDING;
        $this->status = Post::APPROVAL_PENDING;
        $this->published_at = null;
        $this->submitted_at = now()->format('d/m/Y H:i');
        $this->reviewed_at = null;
        $this->rejection_reason = null;
        $this->isScheduled = false;

        $this->refreshPost();

        $this->warning(
            $wasScheduled
                ? 'Đã hủy lịch đăng và chuyển bài về trạng thái Chờ duyệt.'
                : 'Đã gỡ bài và chuyển về trạng thái Chờ duyệt.'
        );
    }

    public function loadMoreHistories(): void
    {
        $this->historyLimit += 10;
    }

    public function getHasMoreApprovalHistoriesProperty(): bool
    {
        return PostApprovalHistory::query()
                ->where('post_id', $this->id)
                ->count() > $this->historyLimit;
    }

    public function getApprovalHistoriesProperty()
    {
        return PostApprovalHistory::query()
            ->with(['actor', 'reviewer'])
            ->where('post_id', $this->id)
            ->latest()
            ->limit($this->historyLimit)
            ->get();
    }

    private function sendNotificationSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            report($e);

            $this->warning('Thao tác đã được lưu nhưng gửi email thông báo thất bại.');
        }
    }

    private function sendPostNotificationOnce(
        string $event,
        Post $post,
        callable $callback,
        int $seconds = 60
    ): void {
        $cacheKey = "post_notification_sent:{$event}:{$post->id}";

        if (! \Illuminate\Support\Facades\Cache::add($cacheKey, true, now()->addSeconds($seconds))) {
            return;
        }

        $this->sendNotificationSafely($callback);
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
            @if($currentStatus === 'published' && ! $isScheduled && $url)
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
                                \App\Models\Post::APPROVAL_PENDING => ['label' => 'Chờ duyệt', 'class' => 'badge-warning text-white'],
                                \App\Models\Post::APPROVAL_REJECTED => ['label' => 'Từ chối', 'class' => 'badge-error text-white'],
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

                <div class="mt-4">
                    <div class="font-semibold mb-2 text-sm">Ảnh đại diện:</div>

                    @if($post->thumbnail)
                        <img
                            src="{{ Storage::url($post->thumbnail) }}"
                            class="w-full rounded-lg border border-gray-200 object-cover object-top"
                            alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                            loading="lazy"
                            decoding="async"
                        >
                    @elseif($post->post_default_image_id && $post->defaultImage)
                        <div class="relative overflow-hidden rounded-lg border border-gray-200" style="container-type: inline-size;">
                            <img
                                src="{{ Storage::url($post->defaultImage->image_path) }}"
                                class="w-full object-cover object-top"
                                alt="{{ $post->defaultImage->name ?? 'Default image' }}"
                                loading="lazy"
                                decoding="async"
                            >

                            @if($post->defaultImage->show_title)
                                <div
                                    class="absolute inset-0 flex items-center justify-center p-3.5"
                                    style="transform: translateY(calc({{ $post->defaultImage->text_y_offset }} / 1200 * 100cqw));"
                                >
                                    <p
                                        class="line-clamp-3 font-bold"
                                        style="
                                            color: {{ $post->defaultImage->text_color ?? '#ffffff' }};
                                            font-size: clamp(8px, calc({{ $post->defaultImage->text_size ?? 18 }} / 1200 * 100cqw), 60px);
                                            line-height: 1.1;
                                            text-align: {{ $post->defaultImage->text_alignment ?? 'center' }};
                                        "
                                    >
                                        {{ $post->getTranslation('title', app()->getLocale()) }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-center text-gray-400">
                            Chưa có ảnh đại diện
                        </div>
                    @endif
                </div>
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
                            'reverted_to_pending' => 'text-md font-bold text-purple-600',
                            'restored_to_pending' => 'text-md font-bold text-purple-600',
                            'withdrawn' => 'text-md font-bold text-yellow-600',
                            'archived' => 'text-md font-bold text-orange-600',
                            'restored' => 'text-md font-bold text-blue-600',
                            default => 'text-md font-bold text-gray-700',
                        };

                        $historyActionLabel = match($history->action) {
                            'submitted' => 'Gửi duyệt',
                            'resubmitted' => 'Gửi duyệt lại',
                            'updated_pending' => 'Cập nhật bài chờ duyệt',
                            'approved' => 'Duyệt bài',
                            'rejected' => 'Từ chối bài',
                            'withdrawn' => 'Rút về nháp',
                            'archived' => 'Lưu trữ bài viết',
                            'restored' => 'Khôi phục bài viết',
                            'reverted_to_pending' => 'Thu hồi về chờ duyệt',
                            'restored_to_pending' => 'Khôi phục về chờ duyệt',
                            default => ucfirst(str_replace('_', ' ', $history->action)),
                        };
                    @endphp

                    <div class="py-2 border-b border-gray-100 last:border-b-0">
                        <div class="{{ $historyTitleClass }}">
                            {{ $historyActionLabel }}
                        </div>

                        <div class="text-sm text-gray-500 font-semibold">
                            {{ $history->created_at?->format('d/m/Y H:i') }}
                            @if($history->actor)
                                - {{ $history->actor->name }}
                            @endif
                        </div>

                        @if($history->scheduled_publish_at)
                            <div class="text-sm text-gray-500 font-semibold">
                                Lên lịch: {{ \Carbon\Carbon::parse($history->scheduled_publish_at)->format('H:i d/m/Y') }}
                            </div>
                        @endif

                        @if($history->note)
                            <div class="text-sm text-gray-700 mt-1">
                                <span class="text-md font-semibold">Nội dung: </span>{{ $history->note }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-md text-gray-500 font-semibold">Chưa có lịch sử duyệt.</div>
                @endforelse

                @if($this->hasMoreApprovalHistories)
                    <div class="pt-3">
                        <x-button
                            label="Xem thêm lịch sử"
                            icon="o-chevron-down"
                            class="btn-outline w-full"
                            wire:click="loadMoreHistories"
                            spinner="loadMoreHistories"
                        />
                    </div>
                @endif
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

                    <x-button
                        label="Duyệt bài"
                        class="bg-success text-white w-full mt-3"
                        wire:click="approvePost"
                        spinner="approvePost"
                    />

                    <x-button
                        label="Từ chối bài"
                        class="bg-error text-white w-full mt-2"
                        wire:click="rejectPost"
                        spinner="rejectPost"
                    />

                @elseif($currentStatus === 'published')
                    @php
                        $post = Post::findOrFail($this->id);
                        $canRevert = $this->canRevertPublishedPost($post);
                    @endphp

                    @if($isScheduled)
                        <div class="text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded p-3 mb-4">
                            Bài viết đang ở trạng thái <strong>Đã lên lịch</strong>.<br>
                            <span class="text-sm text-blue-500 mt-1 block">
                                Sẽ xuất bản lúc: {{ \Carbon\Carbon::parse($published_at)->format('H:i d/m/Y') }}
                            </span>
                        </div>
                    @else
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
                            * Không thể gỡ bài: Quá 24h kể từ lúc công khai hoặc không phải bài do bạn xuất bản.
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
