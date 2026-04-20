<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Post;
use App\Models\PostApprovalHistory;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Mary\Traits\Toast;
use App\Services\ContentImageService;

new class extends Component {
    use Toast, WithFileUploads;

    public int $id;
    public string $selectedTab = 'tab-vi';
    // Song ngữ
    public string $title_vi    = '';
    public string $title_en    = '';
    public string $content_vi  = '';
    public string $content_en  = '';
    public string $excerpt_vi  = '';
    public string $excerpt_en  = '';
    public string $url='';

    // Slug
    public string $slug = '';

    // Quan hệ
    public array $category_ids = [];

    // Trạng thái
    public string $status       = 'draft';
    public ?string $published_at = null;
    public ?string $submitted_at = null;
    public ?string $reviewed_at = null;
    public ?string $rejection_reason = null;
    public string $reviewNote = '';
    public bool $readOnlyPublished = false;

    // SEO
    public string $seo_title_vi       = '';
    public string $seo_title_en       = '';
    public string $seo_description_vi = '';
    public string $seo_description_en = '';

    // Thumbnail
    public $thumbnail;
    public ?string $currentThumbnail = null;

    // Nổi bật
    public bool $is_featured = false;

    // Hiển thị/ẩn metadata
    public bool $show_author = true;
    public bool $show_published_at = true;
    public bool $show_views = true;
    public bool $show_category = true;
    public bool $show_related_posts = true;

    public array $statusOptions = [
        ['id' => 'draft',     'name' => 'Nháp'],
        ['id' => 'pending_review', 'name' => 'Chờ duyệt'],
        ['id' => 'rejected',  'name' => 'Từ chối'],
        ['id' => 'published', 'name' => 'Đã đăng'],
        ['id' => 'archived',  'name' => 'Lưu trữ'],
    ];

    public function canReview(): bool
    {
        $user = auth()->user();

        return $user?->can('duyet_bai_viet')
            || $user?->can('quan_ly_bai_viet');
    }

    public function canWrite(): bool
    {
        $user = auth()->user();

        return $this->canReview() || $user?->can('viet_bai_viet');
    }

    protected function authorizePostAccess(Post $post): void
    {
        abort_unless($this->canWrite(), 403);

        if ($post->status === 'draft' && (int) $post->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if ($this->canReview()) {
            return;
        }

        abort_unless((int) $post->user_id === (int) auth()->id(), 403);
    }

    protected function rules(): array
    {
        $primaryCategoryId = $this->category_ids[0] ?? null;

        return [
            'title_vi'           => 'nullable|string|max:255',
            'title_en'           => 'nullable|string|max:255',
            'content_vi'         => 'nullable|string',
            'content_en'         => 'nullable|string',
            'excerpt_vi'         => 'nullable|string|max:500',
            'excerpt_en'         => 'nullable|string|max:500',
            'slug'               => [
                'required',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')
                    ->ignore($this->id)
                    ->where(function ($query) use ($primaryCategoryId) {
                        $query->whereNull('deleted_at');

                        if ($primaryCategoryId) {
                            $query->where('category_id', $primaryCategoryId);
                        } else {
                            $query->whereNull('category_id');
                        }
                    }),
            ],
            'category_ids'       => 'nullable|array',
            'category_ids.*'     => 'integer|exists:categories,id',
            'status'             => 'required|in:draft,pending_review,rejected,published,archived',
            'is_featured'        => 'boolean',
            'published_at'       => 'nullable|date',
            'show_author'        => 'boolean',
            'show_published_at'  => 'boolean',
            'show_views'         => 'boolean',
            'show_category'      => 'boolean',
            'show_related_posts' => 'boolean',
            'seo_title_vi'       => 'nullable|string|max:255',
            'seo_title_en'       => 'nullable|string|max:255',
            'seo_description_vi' => 'nullable|string|max:500',
            'seo_description_en' => 'nullable|string|max:500',
            'thumbnail'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    protected $messages = [
        'slug.required'       => 'Slug không được để trống.',
        'slug.unique'         => 'Slug đã tồn tại, vui lòng chọn slug khác.',
        'thumbnail.image'     => 'File tải lên phải là hình ảnh.',
        'thumbnail.mimes'     => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
        'thumbnail.max'       => 'Ảnh không được vượt quá 2MB.',
        'reviewNote.required' => 'Vui lòng nhập lý do từ chối.',
        'reviewNote.min'      => 'Lý do từ chối cần tối thiểu 5 ký tự.',
    ];

    private function hasMeaningfulEditorContent(?string $html): bool
    {
        $plain = trim((string) preg_replace('/\x{00A0}/u', ' ', strip_tags(html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));

        return $plain !== '';
    }

    private function validateLocalizedContent(): void
    {
        $viTitle = trim($this->title_vi) !== '';
        $enTitle = trim($this->title_en) !== '';
        $viContent = $this->hasMeaningfulEditorContent($this->content_vi);
        $enContent = $this->hasMeaningfulEditorContent($this->content_en);

        $errors = [];

        if ($viTitle xor $viContent) {
            $errors[$viTitle ? 'content_vi' : 'title_vi'] = 'Tiếng Việt cần nhập đủ cả tiêu đề và nội dung.';
        }

        if ($enTitle xor $enContent) {
            $errors[$enTitle ? 'content_en' : 'title_en'] = 'Tiếng Anh cần nhập đủ cả tiêu đề và nội dung.';
        }

        $hasVi = $viTitle && $viContent;
        $hasEn = $enTitle && $enContent;

        if (! $hasVi && ! $hasEn) {
            $errors['title_vi'] = 'Cần có ít nhất một ngôn ngữ đầy đủ (tiêu đề + nội dung).';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function mount(int $id): void
    {
        $this->id   = $id;
        $post       = Post::findOrFail($id);
        $this->authorizePostAccess($post);

        $this->title_vi           = $post->getTranslation('title',           'vi', false) ?? '';
        $this->title_en           = $post->getTranslation('title',           'en', false) ?? '';
        $this->content_vi         = $post->getTranslation('content',         'vi', false) ?? '';
        $this->content_en         = $post->getTranslation('content',         'en', false) ?? '';
        $this->excerpt_vi         = $post->getTranslation('excerpt',         'vi', false) ?? '';
        $this->excerpt_en         = $post->getTranslation('excerpt',         'en', false) ?? '';
        $this->seo_title_vi       = $post->getTranslation('seo_title',       'vi', false) ?? '';
        $this->seo_title_en       = $post->getTranslation('seo_title',       'en', false) ?? '';
        $this->seo_description_vi = $post->getTranslation('seo_description', 'vi', false) ?? '';
        $this->seo_description_en = $post->getTranslation('seo_description', 'en', false) ?? '';
        $this->slug               = $post->slug ?? '';
        $this->url = $post->client_url;
        $this->category_ids       = $post->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->toArray();
        if (empty($this->category_ids) && $post->category_id) {
            $this->category_ids = [(int) $post->category_id];
        }
        $this->status             = $post->status;
        $this->readOnlyPublished  = $post->status === 'published' && ! $this->canReview();
        $this->submitted_at       = $post->submitted_at?->format('d/m/Y H:i');
        $this->reviewed_at        = $post->reviewed_at?->format('d/m/Y H:i');
        $this->rejection_reason   = $post->rejection_reason;
        $this->is_featured        = (bool) $post->is_featured;
        $this->published_at       = $post->published_at?->format('Y-m-d\\TH:i');
        $this->currentThumbnail   = $post->thumbnail;
        $this->show_author        = (bool) $post->show_author;
        $this->show_published_at  = (bool) $post->show_published_at;
        $this->show_views         = (bool) $post->show_views;
        $this->show_category      = (bool) $post->show_category;
        $this->show_related_posts = (bool) $post->show_related_posts;
    }

    public function updatedSlug($value): void
    {
        $this->slug = Str::slug($value);
    }

    public function updated($property): void
    {
        $this->validateOnly($property);
    }

    public function getCategoryOptionsProperty(): array
    {
        $categories = Category::query()
//            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return $this->flattenCategoryOptions($categories);
    }

    private function flattenCategoryOptions($categories, ?int $parentId = null, int $depth = 0): array
    {
        $options = [];

        foreach ($categories->where('parent_id', $parentId) as $category) {
            $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';

            $options[] = [
                'id' => $category->id,
                'name' => $prefix . $category->getTranslatedName(),
            ];

            $options = array_merge(
                $options,
                $this->flattenCategoryOptions($categories, (int) $category->id, $depth + 1)
            );
        }

        return $options;
    }

    public function fillSeoEn(): void
    {
        if (empty($this->seo_title_en))
            $this->seo_title_en = $this->title_en ?: $this->title_vi;
        if (empty($this->seo_description_en))
            $this->seo_description_en = $this->excerpt_en ?: $this->excerpt_vi;
    }

    public function previewDraft(): void
    {
        $cacheKey = 'post_preview_' . $this->id . '_' . auth()->id();

        Cache::put($cacheKey, [
            'title'           => ['vi' => $this->title_vi,   'en' => $this->title_en],
            'content'         => ['vi' => $this->content_vi, 'en' => $this->content_en],
            'excerpt'         => ['vi' => $this->excerpt_vi, 'en' => $this->excerpt_en],
            'slug'            => $this->slug,
            'category_id'     => $this->category_ids[0] ?? null,
            'category_ids'    => $this->category_ids,
            'status'          => $this->status,
            'is_featured'     => $this->is_featured,
            'published_at'    => $this->published_at,
            'seo_title'       => ['vi' => $this->seo_title_vi, 'en' => $this->seo_title_en],
            'seo_description' => ['vi' => $this->seo_description_vi, 'en' => $this->seo_description_en],
            'thumbnail'       => $this->currentThumbnail,
            'user_id'         => auth()->id(),
            'show_related_posts' => $this->show_related_posts,
        ], now()->addMinutes(30));

        // Mở tab mới qua JS
        $this->dispatch('open-preview', url: route('admin.preview.post', ['id' => $this->id, 'draft' => 1]));
    }

    public function removeThumbnail(): void
    {
        if ($this->currentThumbnail) {
            Storage::disk('public')->delete($this->currentThumbnail);
        }
        $this->currentThumbnail = null;
        Post::where('id', $this->id)->update(['thumbnail' => null]);
        $this->success('Đã xóa ảnh thumbnail.');
    }

    private function ensureFeaturedLimitForUpdate(Post $post): void
    {
        if (! $this->canReview()) {
            return;
        }

        // Chỉ tính quota cho bài featured + published.
        $wasCounted = $post->is_featured && $post->status === 'published';
        $willBeCounted = $this->is_featured && $this->status === 'published';

        // Không tăng số lượng featured published thì không cần chặn.
        if (! $willBeCounted || $wasCounted) {
            return;
        }

        $featuredCount = Post::where('is_featured', true)
            ->where('status', 'published')
            ->count();

        if ($featuredCount >= 5) {
            throw ValidationException::withMessages([
                'is_featured' => 'Chỉ được tối đa 5 bài viết nổi bật trong nhóm đã đăng (published).',
            ]);
        }
    }

    public function cleanEmptyHtmlLines(string $html): string
    {
        // 1. Dọn dẹp khoảng trắng thông thường trước
        $html = trim($html);

        // 2. Biểu thức Regex dọn dẹp các thẻ p rỗng, br, hoặc chứa &nbsp; ở ĐẦU và CUỐI chuỗi
        $pattern = '/^(?:<p[^>]*>(?:\s|&nbsp;|<br\/?\s*>)*<\/p>\s*|<br\/?\s*>\s*)+|(?:<p[^>]*>(?:\s|&nbsp;|<br\/?\s*>)*<\/p>\s*|<br\/?\s*>\s*)+$/i';

        // 3. Thực thi xóa
        $cleanedHtml = preg_replace($pattern, '', $html);

        return trim($cleanedHtml);
    }

    private function enforceWriterDraftRules(Post $post): void
    {
        if ($this->canReview()) {
            return;
        }

        // Tác giả có thể lưu khi bài ở draft/pending/rejected nhưng không được đổi trạng thái.
        $this->status = $post->status;
        $this->published_at = null;
        $this->is_featured = false;
    }

    private function logApprovalHistory(Post $post, string $action, ?string $note = null, ?string $scheduledPublishAt = null): void
    {
        PostApprovalHistory::create([
            'post_id' => $post->id,
            'action' => $action,
            'actor_id' => auth()->id(),
            'reviewer_id' => $this->canReview() ? auth()->id() : null,
            'note' => $note,
            'scheduled_publish_at' => $scheduledPublishAt,
        ]);
    }

    public function submitForReview(): void
    {
        $post = Post::findOrFail($this->id);
        $this->authorizePostAccess($post);

        if ($this->canReview()) {
            $this->warning('Bạn đang có quyền duyệt, không cần gửi chờ duyệt.');
            return;
        }

//        if ($post->status === Post::APPROVAL_PENDING) {
            $this->warning('Bài viết đã ở trạng thái chờ duyệt.');
//            return;
//        }

        $this->save();

        $post->refresh();
        $isResubmitted = $post->status === Post::APPROVAL_REJECTED;

        $post->update([
            'status' => Post::APPROVAL_PENDING,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);

        $this->status = Post::APPROVAL_PENDING;
        $this->submitted_at = now()->format('d/m/Y H:i');
        $this->reviewed_at = null;
        $this->rejection_reason = null;

        $this->logApprovalHistory(
            $post,
            $isResubmitted ? 'resubmitted' : 'submitted',
            $isResubmitted ? 'Tác giả chỉnh sửa và gửi lại duyệt.' : 'Tác giả gửi bài chờ duyệt.'
        );

        $this->success('Đã gửi bài viết chờ duyệt!');
    }

    public function approvePost(): void
    {
        abort_unless($this->canReview(), 403);

        $post = Post::findOrFail($this->id);
        $this->authorizePostAccess($post);

        $publishAt = $this->published_at ? Carbon::parse($this->published_at) : now();

        $post->update([
            'status' => 'published',
            'published_at' => $publishAt,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->status = 'published';
        $this->reviewed_at = now()->format('d/m/Y H:i');
        $this->rejection_reason = null;

        $this->logApprovalHistory(
            $post,
            'approved',
            'Duyệt bài viết.',
            $publishAt->toDateTimeString()
        );

        $this->success($publishAt->greaterThan(now()) ? 'Đã duyệt và lên lịch đăng bài.' : 'Đã duyệt và đăng bài viết.');
    }

    public function rejectPost(): void
    {
        abort_unless($this->canReview(), 403);

        $this->validate([
            'reviewNote' => 'required|string|min:5|max:1000',
        ]);

        $post = Post::findOrFail($this->id);
        $this->authorizePostAccess($post);

        $post->update([
            'status' => Post::APPROVAL_REJECTED,
            'published_at' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $this->reviewNote,
        ]);

        $this->status = Post::APPROVAL_REJECTED;
        $this->published_at = null;
        $this->reviewed_at = now()->format('d/m/Y H:i');
        $this->rejection_reason = $this->reviewNote;

        $this->logApprovalHistory($post, 'rejected', $this->reviewNote);
        $this->reviewNote = '';

        $this->warning('Đã từ chối bài viết.');
    }

    public function save(): void
    {
        $post = Post::findOrFail($this->id);
        $this->authorizePostAccess($post);

        if ($post->status === 'published' && ! $this->canReview()) {
            $this->warning('Bạn chỉ có quyền xem bài đã đăng, không thể lưu thay đổi.');
            return;
        }

        try {
            $this->enforceWriterDraftRules($post);
        } catch (ValidationException $e) {
            $this->error('Bạn không thể cập nhật bài viết ở trạng thái hiện tại.');
            throw $e;
        }

        try {
            $this->validate();
            $this->validateLocalizedContent();
            $this->ensureFeaturedLimitForUpdate($post);
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $thumbnailPath = $this->currentThumbnail;

        try {
            $this->ensureFeaturedLimitForUpdate($post);
        } catch (ValidationException $e) {
            $this->error('Không thể cập nhật bài nổi bật.');
            throw $e;
        }

        if ($this->thumbnail) {
            if ($thumbnailPath) Storage::disk('public')->delete($thumbnailPath);
            $thumbnailPath = $this->thumbnail->store('uploads/posts', 'public');
        }

        $primaryCategoryId = $this->category_ids[0] ?? null;

        // Xử lý ảnh ngoài cho nội dung bài viết
        $contentImageService = app(ContentImageService::class);
        $content_vi = $contentImageService->downloadAndReplaceExternalImages($this->content_vi);
        $content_vi = $contentImageService->downloadDocuments($this->content_vi);
        $content_en = $contentImageService->downloadAndReplaceExternalImages($this->content_en);
        $content_en = $contentImageService->downloadDocuments($this->content_en);
        $content_vi = $this->cleanEmptyHtmlLines($content_vi);
        $content_en = $this->cleanEmptyHtmlLines($content_en);

        $nextStatus = $this->canReview() ? $this->status : $post->status;
        $nextPublishedAt = $nextStatus === 'published' ? ($this->published_at ?? now()) : null;

        $post->update([
            'title'   => [
                'vi' => $this->title_vi,
                'en' => $this->title_en,
            ],
            'content' => [
                'vi' => $content_vi,
                'en' => $content_en,
            ],
            'excerpt' => $this->excerpt_vi || $this->excerpt_en
                ? ['vi' => $this->excerpt_vi, 'en' => $this->excerpt_en]
                : null,
            'slug'         => $this->slug,
            // Keep legacy category_id for backward compatibility (first selected category).
            'category_id'  => $primaryCategoryId,
            'status'       => $nextStatus,
            'is_featured'  => $this->canReview() ? $this->is_featured : false,
            'published_at' => $nextPublishedAt,
            'seo_title' => $this->seo_title_vi || $this->seo_title_en
                ? ['vi' => $this->seo_title_vi, 'en' => $this->seo_title_en]
                : null,
            'seo_description' => $this->seo_description_vi || $this->seo_description_en
                ? ['vi' => $this->seo_description_vi, 'en' => $this->seo_description_en]
                : null,
            'user_id'   => $post->user_id ?? Auth::id(),
            'thumbnail' => $thumbnailPath  ? $thumbnailPath : null,
            'show_author' => $this->show_author,
            'show_published_at' => $this->show_published_at,
            'show_views' => $this->show_views,
            'show_category' => $this->show_category,
            'show_related_posts' => $this->show_related_posts,
        ]);

        $post->categories()->sync($this->category_ids);

        $this->status = $nextStatus;
        $this->published_at = $nextPublishedAt ? Carbon::parse($nextPublishedAt)->format('Y-m-d\\TH:i') : null;

        $this->success('Cập nhật bài viết thành công!');
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
};
?>

<div x-data x-on:open-preview.window="window.open($event.detail.url, '_blank')">
    <x-slot:title>Chỉnh sửa bài viết</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.post.index') }}" class="font-semibold text-slate-700">Danh sách bài viết</a>
        <span class="mx-1">/</span>
        <span>Chỉnh sửa bài viết</span>
    </x-slot:breadcrumb>

    <x-header title="Chỉnh sửa bài viết" class="pb-3 mb-5! border-b border-gray-300"/>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        {{-- ===================== MAIN ===================== --}}
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">
            <x-tabs wire:model="selectedTab">
                {{-- ================= TAB TIẾNG VIỆT ================= --}}
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Nội dung bài viết
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input wire:model.live.debounce.400ms="title_vi" label="Tiêu đề"
                                     placeholder="VD: Thông báo tuyển sinh 2025"
                            />
{{--                            <x-input label="Đường dẫn" wire:model.live.debounce.1000ms="slug"--}}
{{--                                     placeholder="thong-bao-tuyen-sinh-2025"--}}
{{--                                     hint="Tự động sinh từ tiêu đề tiếng Việt. Chỉ gồm chữ thường, số và dấu gạch ngang." required--}}
{{--                            />--}}
                            <x-textarea wire:model="excerpt_vi"
                                        placeholder="Mô tả ngắn" rows="3"
                                        hint="Tối đa 500 ký tự"
                                        label="Mô tả ngắn"
                            />
                            <x-editor
                                wire:model="content_vi"
                                :config="config('tinymce')"
                                class="h-full"
                                label="Nội dung chi tiết"
                                folder="uploads/posts/editor"
                            />
                        </div>

                    </div>
                    <div x-data="{ open: true }"
                         class="mt-4 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                SEO
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4 text-md text-blue-700 space-y-1">
                                <p>💡 <strong>SEO Tiêu đề </strong> hiển thị trên tab trình duyệt và kết quả Google — <strong>khác với tiêu đề bài viết</strong>. Nên ngắn gọn, chứa từ khóa chính, dưới 60 ký tự.</p>
                                <p>💡 <strong>SEO Mô tả</strong> là dòng mô tả hiện dưới tiêu đề trên Google — <strong>khác với tóm tắt</strong> hiển thị trên website. Nên dưới 160 ký tự.</p>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Tiêu đề</span>
                                        <button type="button"
                                                wire:click="$set('seo_title_vi', $wire.title_vi)"
                                                class="text-sm text-primary hover:underline">
                                            ↖ Lấy từ tiêu đề
                                        </button>
                                    </div>
                                    <x-input wire:model="seo_title_vi" placeholder="Để trống = dùng tiêu đề bài viết"/>
                                    <p class="text-sm text-gray-400 mt-1">
                                        {{ mb_strlen($seo_title_vi) }}/60 ký tự
                                        @if(mb_strlen($seo_title_vi) > 60) <span class="text-warning">— nên dưới 60</span> @endif
                                    </p>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Mô tả</span>
                                        <button type="button"
                                                wire:click="$set('seo_description_vi', $wire.excerpt_vi)"
                                                class="text-sm text-primary hover:underline">
                                            ↖ Lấy từ tóm tắt
                                        </button>
                                    </div>
                                    <x-textarea wire:model="seo_description_vi" rows="2"
                                                placeholder="Để trống = dùng tóm tắt bài viết"/>
                                    <p class="text-sm text-gray-400 mt-1">
                                        {{ mb_strlen($seo_description_vi) }}/160 ký tự
                                        @if(mb_strlen($seo_description_vi) > 160) <span class="text-warning">— nên dưới 160</span> @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </x-tab>

                {{-- ================= TAB TIẾNG ANH ================= --}}
                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Nội dung bài viết (Tiếng Anh)
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input wire:model.live.debounce.400ms="title_en" label="Tiêu đề (Tiếng Anh)"
                                     placeholder="Ex: Admission announcement 2025"
                            />
                            <x-textarea wire:model="excerpt_en"
                                        placeholder="Short description" rows="3"
                                        hint="Max 500 characters"
                                        label="Mô tả ngắn (Tiếng Anh)"
                            />
                            <x-editor
                                wire:model="content_en"
                                :config="config('tinymce')"
                                class="h-full"
                                label="Nội dung chi tiết (Tiếng Anh)"
                                folder="uploads/posts/editor"
                            />
                        </div>

                    </div>

                    <div x-data="{ open: true }"
                         class="mt-4 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                SEO (Tiếng Anh)
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4 text-md text-blue-700 space-y-1">
                                <p><strong>SEO Tiêu đề</strong> hiển thị trên tab trình duyệt và kết quả Google (khác tiêu đề bài viết).</p>
                                <p><strong>SEO Mô tả</strong> là mô tả dưới tiêu đề trên Google (khác mô tả ngắn).</p>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Tiêu đề</span>
                                        <button type="button"
                                                wire:click="$set('seo_title_en', $wire.title_en)"
                                                class="text-sm text-primary hover:underline">
                                            ↖ Lấy từ Tiêu đề (Tiếng Anh)
                                        </button>
                                    </div>
                                    <x-input wire:model="seo_title_en" placeholder="Để trống = dùng title bài viết"/>
                                    <p class="text-sm text-gray-400 mt-1">
                                        {{ mb_strlen($seo_title_en) }}/60 ký tự
                                        @if(mb_strlen($seo_title_en) > 60) <span class="text-warning">— nên dưới 60</span> @endif
                                    </p>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Mô tả</span>
                                        <button type="button"
                                                wire:click="$set('seo_description_en', $wire.excerpt_en)"
                                                class="text-sm text-primary hover:underline">
                                            ↖ Lấy từ Mô tả ngắn (Tiếng Anh)
                                        </button>
                                    </div>
                                    <x-textarea wire:model="seo_description_en" rows="2"
                                                placeholder="Để trống = dùng short description bài viết"/>
                                    <p class="text-sm text-gray-400 mt-1">
                                        {{ mb_strlen($seo_description_en) }}/160 ký tự
                                        @if(mb_strlen($seo_description_en) > 160) <span class="text-warning">— nên dưới 160</span> @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </x-tab>

            </x-tabs>
            <x-card title="Lịch sử duyệt bài viết" shadow class="p-3!">
                @forelse($this->approvalHistories as $history)
                    <div class="py-2 border-b border-gray-100 last:border-b-0">
                        <div class="text-md font-bold @if($history->action === 'approved') text-green-600 @elseif($history->action === 'rejected') text-red-600 @else text-gray-700 @endif">
                            {{ __(ucfirst(str_replace('_', ' ', $history->action))) }}
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ $history->created_at?->format('d/m/Y H:i') }}
                            @if($history->actor)
                                - {{ $history->actor->name }}
                            @endif
                        </div>
                        @if($history->scheduled_publish_at)
                            <div class="text-sm text-gray-500">Lên lịch: {{ $history->scheduled_publish_at->format('d/m/Y H:i') }}</div>
                        @endif
                        @if($history->note)
                            <div class="text-sm text-gray-700 mt-1"> <span class="text-md font-semibold">Nội dung: </span>{{ $history->note }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-md text-gray-500">Chưa có lịch sử duyệt.</div>
                @endforelse
            </x-card>
        </div>

        {{-- ===================== SIDEBAR ===================== --}}
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">

            {{-- Hành động --}}
            <x-card title="Hành động" shadow separator class="p-3!">
                @if($readOnlyPublished)
                    <x-button label="Chỉ xem (không có quyền lưu)" class="bg-gray-400 text-white w-full my-1" disabled/>
                @else
                    <x-button label="Lưu thay đổi" class="bg-primary text-white w-full my-1"
                              wire:click="save" spinner="save"/>
                @endif

                @if(!$this->canReview() && $status === \App\Models\Post::APPROVAL_PENDING || $status === \App\Models\Post::APPROVAL_REJECTED)
                    <x-button
                        :label="$status === \App\Models\Post::APPROVAL_REJECTED ? 'Gửi duyệt lại' : 'Gửi duyệt bài viết'"
                        class="bg-success text-white w-full my-1"
                        wire:click="submitForReview"
                        spinner="submitForReview"
                    />
                @endif
                @if($status === 'published')
                    <x-button label="Xem bài viết" class="bg-info text-white w-full my-1"
                          link="{{$url}}" external="true"/>
                @endif
                <x-button label="Xem trước" class="bg-warning text-white w-full my-1"
                          wire:click="previewDraft" spinner="previewDraft"/>
            </x-card>
            @if($status === \App\Models\Post::APPROVAL_PENDING)
                <x-card title="Duyệt bài viết" shadow class="p-3!">
                @php
                    $approvalMap = [
                        \App\Models\Post::APPROVAL_PENDING => ['label' => 'Chờ duyệt', 'class' => 'badge-warning'],
                        \App\Models\Post::APPROVAL_REJECTED => ['label' => 'Bị từ chối', 'class' => 'badge-error'],
                        'published' => ['label' => 'Đã duyệt', 'class' => 'badge-success'],
                    ];
                    $approval = $approvalMap[$status] ?? null;
                @endphp

                @if($approval)
                    <x-badge :value="$approval['label']" class="{{ $approval['class'] }} text-white font-semibold"/>
                @else
                    <span class="text-md text-gray-500">Chưa gửi duyệt</span>
                @endif

                @if($submitted_at)
                    <p class="text-sm text-gray-500 mt-2">Gửi duyệt lúc: {{ $submitted_at }}</p>
                @endif

                @if($reviewed_at)
                    <p class="text-sm text-gray-500">Xử lý lúc: {{ $reviewed_at }}</p>
                @endif

                @if($status === \App\Models\Post::APPROVAL_REJECTED && $rejection_reason)
                    <div class="mt-3 text-md bg-red-50 border border-red-200 text-red-700 rounded p-2">
                        <strong>Lý do từ chối:</strong> {{ $rejection_reason }}
                    </div>
                @endif

                @if($this->canReview())
                    <div class="mt-3 space-y-2">
                        <x-input
                            label="Lên lịch đăng (tùy chọn)"
                            type="datetime-local"
                            wire:model="published_at"
                            hint="Để trống để đăng ngay khi duyệt"
                        />

                        <x-textarea
                            wire:model="reviewNote"
                            rows="3"
                            label="Ghi chú duyệt / lý do từ chối"
                            placeholder="Nhập ghi chú cho tác giả..."
                        />

                        <x-button label="Duyệt bài" class="bg-success text-white w-full"
                                  wire:click="approvePost" spinner="approvePost"/>
                        <x-button label="Từ chối bài" class="bg-error text-white w-full"
                                  wire:click="rejectPost" spinner="rejectPost"/>
                    </div>
                @endif
            </x-card>
            @endif
            {{-- Xuất bản --}}
            <x-card title="Xuất bản" shadow class="p-3!">
                <x-input label="Đường dẫn" wire:model.live.debounce.1000ms="slug"
                         placeholder="thong-bao-tuyen-sinh-2025"
                    required
                />
                @if($this->canReview())
                    <x-select label="Trạng thái" wire:model.live="status"
                              :options="$statusOptions"
                              option-value="id" option-label="name"
                    class="mt-2"/>
                @else
{{--                    <div class="text-md text-gray-600 bg-gray-50 border border-gray-200 rounded p-3 mt-2">--}}
{{--                        Bạn chỉ có thể chỉnh sửa bản nháp và gửi chờ duyệt.--}}
{{--                    </div>--}}
                @endif

                @if($this->canReview())
                    <x-checkbox
                        class="mt-3"
                        label="Đánh dấu là bài viết nổi bật"
                        wire:model="is_featured"
                    />
                    @error('is_featured') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror
                @endif

                @if($this->canReview() && $status === 'published')
                    <div class="mt-3">
                        <x-input label="Thời gian đăng" wire:model="published_at"
                                 type="datetime-local"
                                 hint="Để trống = đăng ngay bây giờ"/>
                    </div>
                @endif
            </x-card>

            {{-- Danh mục --}}
            <x-card title="Danh mục" shadow class="p-3!">
                <select
                    wire:model="category_ids"
                    multiple
                    size="8"
                    class="select select-bordered w-full"
                >
                    @foreach($this->categoryOptions as $category)
                        <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </x-card>

{{--            ảnh đại diện--}}
            <x-card title="Ảnh đại diện" shadow class="p-3!">
                <div x-data="{ previewUrl: null }" x-on:livewire-upload-start="previewUrl = null">
                    <x-file wire:model="thumbnail" label="Ảnh thumbnail"
                            hint="jpg, jpeg, png, webp – tối đa 2MB" accept="image/*"
                            x-on:change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"/>
                    <div class="mt-3 space-y-3">
                        <template x-if="previewUrl">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Ảnh mới (chưa lưu)</p>
                                <img src="#" :src="previewUrl" alt="Preview"
                                     class="size-40 rounded object-cover ring-1 ring-gray-200"/>
                            </div>
                        </template>

                        @if($currentThumbnail)
                            <div x-show="!previewUrl">
                                <p class="text-sm text-gray-500 mb-1">Ảnh hiện tại</p>
                                <img src="{{ Storage::url($currentThumbnail) }}" alt="Current thumbnail"
                                     class="size-40 rounded object-cover ring-1 ring-gray-200"/>
                            </div>
                            <x-button
                                label="Xóa ảnh hiện tại"
                                icon="o-trash"
                                class="btn-outline btn-error btn-sm"
                                wire:click="removeThumbnail"
                                spinner="removeThumbnail"
                            />
                        @endif
                    </div>
                </div>
            </x-card>
            {{-- Ẩn/hiển thị metadata --}}
            <x-card title="Ẩn/hiển thị metadata" shadow class="p-3!">
                <x-checkbox
                    class="mb-2"
                    label="Hiển thị người viết"
                    wire:model="show_author"
                />
                <x-checkbox
                    class="mb-2"
                    label="Hiển thị ngày đăng"
                    wire:model="show_published_at"
                />
                <x-checkbox
                    class="mb-2"
                    label="Hiển thị lượt xem"
                    wire:model="show_views"
                />
                <x-checkbox
                    class="mb-2"
                    label="Hiển thị danh mục"
                    wire:model="show_category"
                />
                <x-checkbox
                    class="mb-2"
                    label="Hiển thị bài viết liên quan"
                    wire:model="show_related_posts"
                />
            </x-card>
            {{-- Thông tin --}}
{{--            <x-card title="Thông tin" shadow class="p-3!">--}}
{{--                @php $post = App\Models\Post::find($id); @endphp--}}
{{--                <div class="text-md space-y-2 text-gray-600">--}}
{{--                    <div class="flex justify-between">--}}
{{--                        <span>Lượt xem:</span>--}}
{{--                        <span class="font-medium">{{ number_format($post?->views ?? 0) }}</span>--}}
{{--                    </div>--}}
{{--                    <div class="flex justify-between">--}}
{{--                        <span>Tác giả:</span>--}}
{{--                        <span class="font-medium truncate max-w-24">{{ $post?->user?->name ?? '—' }}</span>--}}
{{--                    </div>--}}
{{--                    <div class="flex justify-between">--}}
{{--                        <span>Ngày tạo:</span>--}}
{{--                        <span class="font-medium">{{ $post?->created_at?->format('d/m/Y') }}</span>--}}
{{--                    </div>--}}
{{--                    <div class="flex justify-between">--}}
{{--                        <span>Cập nhật:</span>--}}
{{--                        <span class="font-medium">{{ $post?->updated_at?->format('d/m/Y') }}</span>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </x-card>--}}
        </div>
    </div>
</div>



