<?php

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
use Illuminate\Support\Facades\DB;
use App\Services\PostNotificationService;

new class extends Component {
    use Toast, WithFileUploads;

    public $selectedTab = 'tab-vi';

    // Song ngữ
    public string $title_vi    = '';
    public string $title_en    = '';
    public string $content_vi  = '';
    public string $content_en  = '';
    public string $excerpt_vi  = '';
    public string $excerpt_en  = '';

    // Slug
    public string $slug = '';

    // Quan hệ
    public array $category_ids = [];

    // Trạng thái
    public string $status       = 'draft';
    public ?string $published_at = null;

    // SEO
    public string $seo_title_vi      = '';
    public string $seo_title_en      = '';
    public string $seo_description_vi = '';
    public string $seo_description_en = '';

    // Thumbnail
    public $thumbnail;

    // Nổi bật
    public bool $is_featured = false;

    // Hiển thị/ẩn metadata
    public bool $show_author = true;
    public bool $show_published_at = true;
    public bool $show_views = true;
    public bool $show_category = true;
    public bool $show_related_posts = true;

    public function canReview(): bool
    {
        return auth()->user()?->canReviewPosts() ?? false;
    }

    public function canWrite(): bool
    {
        $user = auth()->user();
        if (! $user) return false;

        return $user->can('quan_ly_bai_viet')
            || $user->can('viet_bai_viet')
            || $user->scopedPostCategoryIds('viet_bai_viet') !== [];
    }

    private function selectedCategoryIds(): array
    {
        return collect($this->category_ids)
            ->map(fn ($categoryId) => (int) $categoryId)
            ->filter(fn ($categoryId) => $categoryId > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function canReviewForSelectedCategories(): bool
    {
        return auth()->user()?->canReviewPosts($this->selectedCategoryIds()) ?? false;
    }

    public function canWriteForSelectedCategories(): bool
    {
        return auth()->user()?->canWritePosts($this->selectedCategoryIds()) ?? false;
    }

    private function validateCategoryPermissions(): void
    {
        if ($this->canWriteForSelectedCategories()) return;

        throw ValidationException::withMessages([
            'category_ids' => 'Bạn không có quyền viết bài trong danh mục đã chọn.',
        ]);
    }

    public function mount(): void
    {
        abort_unless($this->canWrite(), 403);
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
                Rule::unique('posts', 'slug')->where(function ($query) use ($primaryCategoryId) {
                    $query->whereNull('deleted_at');
                    if ($primaryCategoryId) {
                        $query->where('category_id', $primaryCategoryId);
                    } else {
                        $query->whereNull('category_id');
                    }
                }),
            ],
            'category_ids'       => 'required|array|min:1',
            'category_ids.*'     => 'integer|exists:categories,id',
            'status'             => 'required|in:draft,pending_review,published,archived', // Tạo mới không có rejected
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
        'slug.required'       => 'Đường dẫn không được để trống.',
        'slug.unique'         => 'Đường dẫn đã tồn tại, vui lòng chọn đường dẫn khác.',
        'thumbnail.image'     => 'File tải lên phải là hình ảnh.',
        'thumbnail.mimes'     => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
        'thumbnail.max'       => 'Ảnh không được vượt quá 2MB.',
        'category_ids.required' => 'Vui lòng chọn ít nhất một danh mục cho bài viết.',
    ];

    private function hasMeaningfulEditorContent(?string $html): bool
    {
        if (empty($html)) return false;
        $decoded = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags($decoded, '<img><video><iframe>');
        $plain = trim((string) preg_replace('/\x{00A0}/u', ' ', $stripped));
        return $plain !== '';
    }

    private function validateLocalizedContent(): void
    {
        $viTitle = trim($this->title_vi) !== '';
        $enTitle = trim($this->title_en) !== '';
        $viContent = $this->hasMeaningfulEditorContent($this->content_vi);
        $enContent = $this->hasMeaningfulEditorContent($this->content_en);
        $errors = [];

        if ($viTitle xor $viContent) $errors[$viTitle ? 'content_vi' : 'title_vi'] = 'Tiếng Việt cần nhập đủ cả tiêu đề và nội dung.';
        if ($enTitle xor $enContent) $errors[$enTitle ? 'content_en' : 'title_en'] = 'Tiếng Anh cần nhập đủ cả tiêu đề và nội dung.';
        if (!($viTitle && $viContent) && !($enTitle && $enContent)) {
            $errors['title_vi'] = 'Cần có ít nhất một ngôn ngữ đầy đủ (tiêu đề + nội dung).';
        }

        if (! empty($errors)) throw ValidationException::withMessages($errors);
    }

    public function updatedTitleVi($value): void
    {
        $this->slug = Str::slug($value);
        $this->validateOnly('slug');
    }

    public function updatedSlug($value): void
    {
        $this->slug = Str::slug($value);
    }

    public function updatedCategoryIds($value): void
    {
        if (empty($value)) {
            $this->category_ids = [];
            return;
        }
        if (!is_array($value)) {
            $value = [$value];
        }

        $selectedIds = array_map(fn($id) => (int) $id, $value);
        $categories = Category::all();
        $allowedMap = array_flip($this->allowedCategoryIds());
        $finalIds = [];

        foreach ($selectedIds as $id) {
            $finalIds[] = $id;
            $curr = $categories->firstWhere('id', $id);
            while ($curr && $curr->parent_id) {
                $parentId = (int) $curr->parent_id;
                if (isset($allowedMap[$parentId])) {
                    $finalIds[] = $parentId;
                }
                $curr = $categories->firstWhere('id', $parentId);
            }
        }
        $this->category_ids = array_values(array_unique($finalIds));
    }

    public function updated($property): void
    {
        $this->validateOnly($property);
    }

    public function canToggleFeatured(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        return $user->can('quan_ly_bai_viet') || $user->can('duyet_bai_viet');
    }

    public function getStatusOptionsProperty(): array
    {
        // Khi tạo mới, chỉ hiển thị Nháp và Đã đăng (nếu có quyền)
        return [
            ['id' => 'draft',     'name' => 'Nháp'],
            ['id' => 'published', 'name' => 'Đã đăng'],
        ];
    }

    public function getCategoryOptionsProperty(): array
    {
        $categories = Category::query()->orderBy('order')->get();
        $allowedCategoryIds = $this->allowedCategoryIds();
        $displayCategoryIds = array_unique(array_merge($allowedCategoryIds, $this->category_ids));

        if ($displayCategoryIds === []) return [];

        $allowedCategoryIds = array_map(fn($id) => (int) $id, $allowedCategoryIds);
        $displayCategoryIds = array_map(fn($id) => (int) $id, $displayCategoryIds);

        $allowedMap = array_flip($allowedCategoryIds);
        $visibleMap = array_flip($displayCategoryIds);

        foreach ($displayCategoryIds as $id) {
            $curr = $categories->firstWhere('id', $id);
            while ($curr && $curr->parent_id) {
                $visibleMap[(int)$curr->parent_id] = true;
                $curr = $categories->firstWhere('id', $curr->parent_id);
            }
        }
        return $this->flattenCategoryOptions($categories, null, $allowedMap, $visibleMap, 0);
    }

    private function allowedCategoryIds(): array
    {
        $user = auth()->user();
        if (! $user) return [];
        if ($user->can('quan_ly_bai_viet') || $user->can('viet_bai_viet')) {
            return Category::query()->orderBy('order')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        return $user->scopedPostCategoryIds('viet_bai_viet') ?? [];
    }

    private function flattenCategoryOptions($categories, ?int $parentId, array $allowedMap, array $visibleMap, int $depth = 0): array
    {
        $options = [];
        $prefix = $depth > 0 ? str_repeat('—', $depth) . ' ' : '';

        foreach ($categories->where('parent_id', $parentId) as $category) {
            $label = $prefix . $category->getTranslatedName();
            if (isset($visibleMap[$category->id])) {
                $options[] = [
                    'id' => $category->id,
                    'name' => $label,
                    'disabled' => !isset($allowedMap[$category->id]),
                ];
            }
            $options = array_merge($options, $this->flattenCategoryOptions($categories, (int) $category->id, $allowedMap, $visibleMap, $depth + 1));
        }
        return $options;
    }

    private function previewCacheKey(): string
    {
        return 'post_preview_new_' . auth()->id();
    }

    private function ensureFeaturedLimit(): void
    {
        if (! $this->is_featured || $this->status !== 'published' || ! $this->canReviewForSelectedCategories()) {
            return;
        }

        $featuredCount = Post::where('is_featured', true)->where('status', 'published')->count();
        if ($featuredCount >= 5) {
            throw ValidationException::withMessages(['is_featured' => 'Chỉ được tối đa 5 bài viết nổi bật.']);
        }
    }

    private function sanitizeContent(string $html): string
    {
        $html = trim($html);
        return trim((string) preg_replace('/^(?:<p[^>]*>(?:\s|&nbsp;|<br\/?\s*>)*<\/p>\s*|<br\/?\s*>\s*)+|(?:<p[^>]*>(?:\s|&nbsp;|<br\/?\s*>)*<\/p>\s*|<br\/?\s*>\s*)+$/i', '', $html));
    }

    private function enforceWriterDraftRules(): void
    {
        // Người có quyền duyệt thì có thể chọn status tùy ý (như 'published')
        if ($this->canReviewForSelectedCategories()) return;

        // Nếu chỉ là người viết, ép về trạng thái nháp
        $this->status = 'draft';
        $this->published_at = null;
        $this->is_featured = false;
    }

    public function save(): void
    {
        $this->enforceWriterDraftRules();

        try {
            $this->validate();
            $this->validateLocalizedContent();
            $this->validateCategoryPermissions();
            $this->ensureFeaturedLimit();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        DB::transaction(function () {
            $post = $this->persistPost();

            // Nếu Quản trị viên chọn tạo luôn bài viết Đã đăng
            if ($post->status === 'published') {
                PostApprovalHistory::create([
                    'post_id' => $post->id,
                    'action' => 'approved',
                    'actor_id' => auth()->id(),
                    'reviewer_id' => auth()->id(),
                    'note' => 'Đăng bài viết trực tiếp.',
                ]);
            }
        });

        $this->success('Tạo bài viết thành công!', redirectTo: route('admin.post.index'));
    }

    public function saveAndSubmitForReview(): void
    {
        $this->enforceWriterDraftRules();

        try {
            $this->validate();
            $this->validateLocalizedContent();
            $this->validateCategoryPermissions();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        DB::transaction(function () {
            $post = $this->persistPost();
            $this->submitPostForReview($post);

            app(PostNotificationService::class)->notifySubmitted(
                $post,
                auth()->user()?->name ?? '—'
            );

            // VÁ LỖI: Bắn tín hiệu Tăng số lượng Badge Bài chờ duyệt ngay lập tức
            $this->dispatch('post:pending-count-changed', delta: 1);
        });

        $this->success('Đã gửi bài viết chờ duyệt!', redirectTo: route('admin.post.index'));
    }

    public function previewDraft(): void
    {
        Cache::put($this->previewCacheKey(), [
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
            'thumbnail'       => null,
            'user_id'         => auth()->id(),
            'show_related_posts' => $this->show_related_posts,
        ], now()->addMinutes(30));

        $this->dispatch('open-preview', url: route('admin.preview.post.new'));
    }

    private function submitPostForReview(Post $post): void
    {
        $post->update([
            'status' => Post::APPROVAL_PENDING,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);

        PostApprovalHistory::create([
            'post_id' => $post->id,
            'action' => 'submitted',
            'actor_id' => auth()->id(),
            'note' => 'Tác giả gửi bài chờ duyệt.',
        ]);
    }

    private function persistPost(): Post
    {
        $thumbnailPath = null;
        if ($this->thumbnail) {
            $thumbnailPath = $this->thumbnail->store('uploads/posts', 'public');
        }

        $primaryCategoryId = $this->category_ids[0] ?? null;

        $contentImageService = app(ContentImageService::class);
        $content_vi = $contentImageService->downloadAndReplaceExternalImages($this->content_vi);
        $content_vi = $contentImageService->downloadDocuments($content_vi);
        $content_en = $contentImageService->downloadAndReplaceExternalImages($this->content_en);
        $content_en = $contentImageService->downloadDocuments($content_en);

        $content_vi = $this->sanitizeContent($content_vi);
        $content_en = $this->sanitizeContent($content_en);

        $postStatus = $this->canReviewForSelectedCategories() ? $this->status : 'draft';

        // VÁ LỖI CARBON TẠI ĐÂY: Xử lý an toàn chuỗi ngày tháng
        $safeDate = str_replace('/', '-', $this->published_at);
        $publishedAt = $postStatus === 'published' ? ($this->published_at ? Carbon::parse($safeDate) : now()) : null;

        $post = new Post();
        $post->setTranslation('title', 'vi', $this->title_vi);
        $post->setTranslation('title', 'en', $this->title_en);
        $post->setTranslation('content', 'vi', $content_vi);
        $post->setTranslation('content', 'en', $content_en);

        if ($this->excerpt_vi !== '' || $this->excerpt_en !== '') {
            $post->setTranslation('excerpt', 'vi', $this->excerpt_vi);
            $post->setTranslation('excerpt', 'en', $this->excerpt_en);
        }

        if ($this->seo_title_vi !== '' || $this->seo_title_en !== '') {
            $post->setTranslation('seo_title', 'vi', $this->seo_title_vi);
            $post->setTranslation('seo_title', 'en', $this->seo_title_en);
        }

        if ($this->seo_description_vi !== '' || $this->seo_description_en !== '') {
            $post->setTranslation('seo_description', 'vi', $this->seo_description_vi);
            $post->setTranslation('seo_description', 'en', $this->seo_description_en);
        }

        $post->fill([
            'slug' => $this->slug,
            'category_id' => $primaryCategoryId,
            'status' => $postStatus,
            'submitted_at' => null,
            'reviewed_by' => $postStatus === 'published' ? auth()->id() : null,
            'reviewed_at' => $postStatus === 'published' ? now() : null,
            'rejection_reason' => null,
            'is_featured' => $this->canReviewForSelectedCategories() ? $this->is_featured : false,
            'published_at' => $publishedAt,
            'user_id' => Auth::id(),
            'thumbnail' => $thumbnailPath ?: null,
            'show_author' => $this->show_author,
            'show_published_at' => $this->show_published_at,
            'show_views' => $this->show_views,
            'show_category' => $this->show_category,
            'show_related_posts' => $this->show_related_posts,
        ]);

        $post->save();
        $post->categories()->sync($this->category_ids);

        return $post;
    }
};
?>

<div x-data x-on:open-preview.window="window.open($event.detail.url, '_blank')">
    <x-slot:title>Tạo bài viết</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.post.index') }}" class="font-semibold text-slate-700" wire:navigate>Danh sách bài viết</a>
        <span class="mx-1">/</span>
        <span>Tạo bài viết mới</span>
    </x-slot:breadcrumb>

    <x-header title="Tạo bài viết mới" class="pb-3 mb-5! border-b border-gray-300"/>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        {{-- ===================== MAIN ===================== --}}
        <x-card class="col-span-9 flex flex-col p-3!">
            <x-tabs wire:model="selectedTab">
                {{-- TAB TIẾNG VIỆT --}}
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                    <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">Nội dung bài viết</button>
                            <div class="flex items-center gap-1"><x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/></div>
                        </div>
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input wire:model.live.debounce.400ms="title_vi" label="Tiêu đề" placeholder="VD: Thông báo tuyển sinh 2025"/>
                            <x-textarea wire:model="excerpt_vi" placeholder="Mô tả ngắn" rows="3" hint="Tối đa 500 ký tự" label="Mô tả ngắn"/>
                            <x-editor wire:model="content_vi" :config="config('tinymce')" class="h-full" label="Nội dung chi tiết" folder="uploads/posts/editor"/>
                        </div>
                    </div>
                    <div x-data="{ open: true }" class="mt-4 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">SEO</button>
                            <div class="flex items-center gap-1"><x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/></div>
                        </div>
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4 text-sm text-blue-700 space-y-1">
                                <p>💡 <strong>SEO Tiêu đề </strong> hiển thị trên tab trình duyệt và kết quả Google.</p>
                                <p>💡 <strong>SEO Mô tả</strong> là dòng mô tả hiện dưới tiêu đề trên Google.</p>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Tiêu đề</span>
                                        <button type="button" wire:click="$set('seo_title_vi', $wire.title_vi)" class="text-xs text-primary hover:underline">↖ Lấy từ tiêu đề</button>
                                    </div>
                                    <x-input wire:model="seo_title_vi" placeholder="Để trống = dùng tiêu đề bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_title_vi) }}/60 ký tự @if(mb_strlen($seo_title_vi) > 60) <span class="text-warning">— nên dưới 60</span> @endif</p>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Mô tả</span>
                                        <button type="button" wire:click="$set('seo_description_vi', $wire.excerpt_vi)" class="text-xs text-primary hover:underline">↖ Lấy từ tóm tắt</button>
                                    </div>
                                    <x-textarea wire:model="seo_description_vi" rows="2" placeholder="Để trống = dùng tóm tắt bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_description_vi) }}/160 ký tự @if(mb_strlen($seo_description_vi) > 160) <span class="text-warning">— nên dưới 160</span> @endif</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-tab>

                {{-- TAB TIẾNG ANH --}}
                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                    <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">Nội dung bài viết (Tiếng Anh)</button>
                            <div class="flex items-center gap-1"><x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/></div>
                        </div>
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input wire:model.live.debounce.400ms="title_en" label="Tiêu đề (Tiếng Anh)" placeholder="VD: Admission announcement 2025"/>
                            <x-textarea wire:model="excerpt_en" placeholder="Mô tả ngắn" rows="3" hint="Tối đa 500 ký tự" label="Mô tả ngắn (Tiếng Anh)"/>
                            <x-editor wire:model="content_en" :config="config('tinymce')" class="h-full" label="Nội dung chi tiết (Tiếng Anh)" folder="uploads/posts/editor"/>
                        </div>
                    </div>
                    <div x-data="{ open: true }" class="mt-4 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">SEO (Tiếng Anh)</button>
                            <div class="flex items-center gap-1"><x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/></div>
                        </div>
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4 text-sm text-blue-700 space-y-1">
                                <p><strong>SEO Tiêu đề</strong> hiển thị trên tab trình duyệt và kết quả Google (khác title bài viết).</p>
                                <p><strong>SEO Mô tả</strong> là mô tả dưới tiêu đề trên Google (khác short description).</p>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Tiêu đề</span>
                                        <button type="button" wire:click="$set('seo_title_en', $wire.title_en)" class="text-xs text-primary hover:underline">↖ Lấy từ Tiêu đề (Tiếng Anh)</button>
                                    </div>
                                    <x-input wire:model="seo_title_en" placeholder="Để trống = dùng title bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_title_en) }}/60 ký tự @if(mb_strlen($seo_title_en) > 60) <span class="text-warning">— nên dưới 60</span> @endif</p>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Mô tả</span>
                                        <button type="button" wire:click="$set('seo_description_en', $wire.excerpt_en)" class="text-xs text-primary hover:underline">↖ Lấy từ Mô tả ngắn (Tiếng Anh)</button>
                                    </div>
                                    <x-textarea wire:model="seo_description_en" rows="2" placeholder="Để trống = dùng short description bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_description_en) }}/160 ký tự @if(mb_strlen($seo_description_en) > 160) <span class="text-warning">— nên dưới 160</span> @endif</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-tab>
            </x-tabs>
        </x-card>

        {{-- ===================== SIDEBAR ===================== --}}
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">
            {{-- Hành động --}}
            <x-card title="Hành động" shadow separator class="p-3!">
                @if($this->canReviewForSelectedCategories())
                    {{-- Quyền Quản trị viên/Duyệt bài --}}
                    <x-button label="Lưu bài viết" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
                @else
                    {{-- Quyền Người viết thông thường --}}
                    <x-button label="Lưu bản nháp" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
                    <x-button label="Lưu & Gửi duyệt" class="bg-success text-white w-full my-1" wire:click="saveAndSubmitForReview" spinner="saveAndSubmitForReview"/>
                @endif

                <x-button label="Xem trước" class="bg-info text-white w-full my-1" wire:click="previewDraft" spinner="previewDraft"/>
            </x-card>

            {{-- Xuất bản --}}
            <x-card title="Xuất bản" shadow class="p-3!">
                <x-input label="Đường dẫn" wire:model.live.debounce.1000ms="slug" placeholder="thong-bao-tuyen-sinh-2025" required/>

                @if($this->canReviewForSelectedCategories())
                    <x-select label="Trạng thái" wire:model.live="status" :options="$this->statusOptions" option-value="id" option-label="name" class="mt-2"/>

                    <x-checkbox class="mt-3" label="Đánh dấu là bài viết nổi bật" wire:model="is_featured"/>
                    @error('is_featured') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror

                    @if($status === 'published')
                        <div class="mt-3">
                            <x-input label="Thời gian đăng" wire:model="published_at" type="datetime-local" hint="Để trống = đăng ngay bây giờ"/>
                        </div>
                    @endif
                @else
                    <div class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded p-3 mt-2">
                        Bài viết của bạn sẽ được lưu ở dạng <strong>Nháp</strong> và cần <strong>Gửi duyệt</strong> trước khi xuất bản.
                    </div>
                @endif
            </x-card>

            {{-- Danh mục --}}
            <x-card title="Danh mục" shadow class="p-3!">
                <select wire:model.live.debounce.300ms="category_ids" multiple size="8" class="select select-bordered w-full max-h-80 overflow-auto @error('category_ids') select-error @enderror [&_option:checked]:bg-blue-50 [&_option:checked]:text-blue-700 focus:outline-none">
                    @foreach($this->categoryOptions as $category)
                        <option value="{{ $category['id'] }}" @if($category['disabled']) disabled @endif>{{ $category['name'] }}</option>
                    @endforeach
                </select>
                @error('category_ids') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </x-card>

            <x-card title="Ảnh đại diện" shadow class="p-3!">
                <div x-data="{ previewUrl: null }" x-on:livewire-upload-start="previewUrl = null">
                    <x-file wire:model="thumbnail" label="Ảnh thumbnail" hint="jpg, jpeg, png, webp – tối đa 2MB" accept="image/*" x-on:change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"/>
                    <div class="mt-3">
                        <template x-if="previewUrl">
                            <img src="#" :src="previewUrl" alt="Preview" class="size-40 rounded object-cover ring-1 ring-gray-200"/>
                        </template>
                    </div>
                </div>
            </x-card>

            {{-- Ẩn/hiển thị metadata --}}
            <x-card title="Ẩn/hiển thị metadata" shadow class="p-3!">
                <x-checkbox class="mb-2" label="Hiển thị người viết" wire:model="show_author"/>
                <x-checkbox class="mb-2" label="Hiển thị ngày đăng" wire:model="show_published_at"/>
                <x-checkbox class="mb-2" label="Hiển thị lượt xem" wire:model="show_views"/>
                <x-checkbox class="mb-2" label="Hiển thị danh mục" wire:model="show_category"/>
                <x-checkbox class="mb-2" label="Hiển thị bài viết liên quan" wire:model="show_related_posts"/>
            </x-card>
        </div>
    </div>
</div>
