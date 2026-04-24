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
use Mary\Traits\Toast;
use App\Services\ContentImageService;

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

    public array $statusOptions = [
        ['id' => 'draft',     'name' => 'Nháp'],
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
        'slug.required'       => 'Đường dẫn không được để trống.',
        'slug.unique'         => 'Đường dẫn đã tồn tại, vui lòng chọn đường dẫn khác.',
        'thumbnail.image'     => 'File tải lên phải là hình ảnh.',
        'thumbnail.mimes'     => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
        'thumbnail.max'       => 'Ảnh không được vượt quá 2MB.',
        'status.required'       => 'Trạng thái không được để trống.',
        'status.in'             => 'Trạng thái không hợp lệ.',
        'published_at.date'     => 'Thời gian đăng phải là định dạng ngày tháng hợp lệ.',
        'seo_title_vi.max'     => 'SEO Tiêu đề (Tiếng Việt) không được vượt quá 255 ký tự.',
        'seo_title_en.max'     => 'SEO Tiêu đề (Tiếng Anh) không được vượt quá 255 ký tự.',
        'seo_description_vi.max' => 'SEO Mô tả (Tiếng Việt) không được vượt quá 500 ký tự.',
        'seo_description_en.max' => 'SEO Mô tả (Tiếng Anh) không được vượt quá 500 ký tự.',
    ];

    private function hasMeaningfulEditorContent(?string $html): bool
    {
//        $plain = trim((string) preg_replace('/\x{00A0}/u', ' ', strip_tags(html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
//
//        return $plain !== '';
        if (empty($html)) {
            return false;
        }

        // Giải mã HTML entities
        $decoded = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Xóa các thẻ HTML nhưng GIỮ LẠI img, video, iframe
        $stripped = strip_tags($decoded, '<img><video><iframe>');

        // Xóa các ký tự khoảng trắng đặc biệt (như &nbsp;)
        $plain = trim((string) preg_replace('/\x{00A0}/u', ' ', $stripped));

        // Trả về true nếu vẫn còn chữ hoặc còn thẻ ảnh/video
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
            $errors['title_en'] = 'Cần có ít nhất một ngôn ngữ đầy đủ (tiêu đề + nội dung).';
            $errors['content_vi'] = 'Cần có ít nhất một ngôn ngữ đầy đủ (tiêu đề + nội dung).';
            $errors['content_en'] = 'Cần có ít nhất một ngôn ngữ đầy đủ (tiêu đề + nội dung).';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
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

    public function updated($property): void
    {
        $this->validateOnly($property);
    }

    public function fillSeoEn(): void
    {
        if (empty($this->seo_title_en)) {
            $this->seo_title_en = $this->title_en ?: $this->title_vi;
        }
        if (empty($this->seo_description_en)) {
            $this->seo_description_en = $this->excerpt_en ?: $this->excerpt_vi;
        }
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

    private function previewCacheKey(): string
    {
        return 'post_preview_new_' . auth()->id();
    }

    private function ensureFeaturedLimit(): void
    {
        // Chỉ giới hạn khi bài hiện tại là published và được bật nổi bật.
        if (! $this->is_featured || $this->status !== 'published' || ! $this->canReview()) {
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

    private function sanitizeContent(string $html): string
    {
        $html = trim($html);
        $pattern = '/^(?:<p[^>]*>(?:\s|&nbsp;|<br\/?\s*>)*<\/p>\s*|<br\/?\s*>\s*)+|(?:<p[^>]*>(?:\s|&nbsp;|<br\/?\s*>)*<\/p>\s*|<br\/?\s*>\s*)+$/i';

        return trim((string) preg_replace($pattern, '', $html));
    }

    private function enforceWriterDraftRules(): void
    {
        if ($this->canReview()) {
            return;
        }

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
            $this->ensureFeaturedLimit();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $this->persistPost();
        $this->success('Tạo bài viết thành công!', redirectTo: route('admin.post.index'));
    }

    public function saveAndSubmitForReview(): void
    {
        $this->enforceWriterDraftRules();

        try {
            $this->validate();
            $this->validateLocalizedContent();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $post = $this->persistPost();
        $this->submitPostForReview($post, false);

        $this->success('Đã gửi bài viết chờ duyệt!', redirectTo: route('admin.post.index'));
    }

    public function previewDraft(): void
    {
        // Lưu vào cache, KHÔNG lưu DB, KHÔNG validate bắt buộc
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

    public function saveAndPreview(): void
    {
        $this->enforceWriterDraftRules();

        try {
            $this->validate();
            $this->validateLocalizedContent();
            $this->ensureFeaturedLimit();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $post = $this->persistPost();
        $this->redirect(route('admin.preview.post', ['id' => $post->id, 'draft' => 0]));
    }

    private function submitPostForReview(Post $post, bool $isResubmitted): void
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
            'action' => $isResubmitted ? 'resubmitted' : 'submitted',
            'actor_id' => auth()->id(),
            'note' => $isResubmitted ? 'Tác giả chỉnh sửa và gửi lại duyệt.' : 'Tác giả gửi bài chờ duyệt.',
        ]);
    }

    private function persistPost(): Post
    {
        $thumbnailPath = null;
        if ($this->thumbnail) {
            $thumbnailPath = $this->thumbnail->store('uploads/posts', 'public');
        }

        $primaryCategoryId = $this->category_ids[0] ?? null;

        // Xử lý ảnh ngoài cho nội dung bài viết
        $contentImageService = app(ContentImageService::class);
        $content_vi = $contentImageService->downloadAndReplaceExternalImages($this->content_vi);
        $content_vi = $contentImageService->downloadDocuments($content_vi);
        $content_en = $contentImageService->downloadAndReplaceExternalImages($this->content_en);
        $content_en = $contentImageService->downloadDocuments($content_en);

        $content_vi = $this->sanitizeContent($content_vi);
        $content_en = $this->sanitizeContent($content_en);

        $postStatus = $this->canReview() ? $this->status : 'draft';
        $publishedAt = $postStatus === 'published' ? ($this->published_at ?? now()) : null;

        $post = Post::create([
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
            'status'       => $postStatus,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'is_featured'  => $this->canReview() ? $this->is_featured : false,
            'published_at' => $publishedAt,
            'seo_title' => $this->seo_title_vi || $this->seo_title_en
                ? ['vi' => $this->seo_title_vi, 'en' => $this->seo_title_en]
                : null,
            'seo_description' => $this->seo_description_vi || $this->seo_description_en
                ? ['vi' => $this->seo_description_vi, 'en' => $this->seo_description_en]
                : null,
            'user_id'   => Auth::id(),
            'thumbnail' => $thumbnailPath  ? $thumbnailPath : null,
            'show_author' => $this->show_author,
            'show_published_at' => $this->show_published_at,
            'show_views' => $this->show_views,
            'show_category' => $this->show_category,
            'show_related_posts' => $this->show_related_posts,
        ]);

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
                {{-- ================= TAB TIẾNG VIỆT ================= --}}
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
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
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
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
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4 text-sm text-blue-700 space-y-1">
                                <p>💡 <strong>SEO Tiêu đề </strong> hiển thị trên tab trình duyệt và kết quả Google — <strong>khác với tiêu đề bài viết</strong>. Nên ngắn gọn, chứa từ khóa chính, dưới 60 ký tự.</p>
                                <p>💡 <strong>SEO Mô tả</strong> là dòng mô tả hiện dưới tiêu đề trên Google — <strong>khác với tóm tắt</strong> hiển thị trên website. Nên dưới 160 ký tự.</p>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Tiêu đề</span>
                                        <button type="button"
                                                wire:click="$set('seo_title_vi', $wire.title_vi)"
                                                class="text-xs text-primary hover:underline">
                                            ↖ Lấy từ tiêu đề
                                        </button>
                                    </div>
                                    <x-input wire:model="seo_title_vi" placeholder="Để trống = dùng tiêu đề bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ mb_strlen($seo_title_vi) }}/60 ký tự
                                        @if(mb_strlen($seo_title_vi) > 60) <span class="text-warning">— nên dưới 60</span> @endif
                                    </p>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Mô tả</span>
                                        <button type="button"
                                                wire:click="$set('seo_description_vi', $wire.excerpt_vi)"
                                                class="text-xs text-primary hover:underline">
                                            ↖ Lấy từ tóm tắt
                                        </button>
                                    </div>
                                    <x-textarea wire:model="seo_description_vi" rows="2"
                                                placeholder="Để trống = dùng tóm tắt bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">
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
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
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
                                     placeholder="VD: Admission announcement 2025"
                            />
                            <x-textarea wire:model="excerpt_en"
                                        placeholder="Mô tả ngắn" rows="3"
                                        hint="Tối đa 500 ký tự"
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
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
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
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4 text-sm text-blue-700 space-y-1">
                                <p><strong>SEO Tiêu đề</strong> hiển thị trên tab trình duyệt và kết quả Google (khác title bài viết).</p>
                                <p><strong>SEO Mô tả</strong> là mô tả dưới tiêu đề trên Google (khác short description).</p>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Tiêu đề</span>
                                        <button type="button"
                                                wire:click="$set('seo_title_en', $wire.title_en)"
                                                class="text-xs text-primary hover:underline">
                                            ↖ Lấy từ tiêu đề (Tiếng Anh)
                                        </button>
                                    </div>
                                    <x-input wire:model="seo_title_en" placeholder="Để trống = dùng tiêu đề (Tiếng Anh) bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ mb_strlen($seo_title_en) }}/60 ký tự
                                        @if(mb_strlen($seo_title_en) > 60) <span class="text-warning">— nên dưới 60</span> @endif
                                    </p>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Mô tả</span>
                                        <button type="button"
                                                wire:click="$set('seo_description_en', $wire.excerpt_en)"
                                                class="text-xs text-primary hover:underline">
                                            ↖ Lấy từ mô tả ngắn (Tiếng Anh)
                                        </button>
                                    </div>
                                    <x-textarea wire:model="seo_description_en" rows="2"
                                                placeholder="Để trống = dùng mô tả ngắn (Tiếng Anh) bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ mb_strlen($seo_description_en) }}/160 ký tự
                                        @if(mb_strlen($seo_description_en) > 160) <span class="text-warning">— nên dưới 160</span> @endif
                                    </p>
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
                <x-button label="Lưu bài viết" class="bg-primary text-white w-full my-1"
                          wire:click="save" spinner="save"/>
                @if(!$this->canReview())
                    <x-button label="Gửi duyệt bài viết" class="bg-success text-white w-full my-1"
                              wire:click="saveAndSubmitForReview" spinner="saveAndSubmitForReview"/>
                @endif
                <x-button label="Xem trước" class="bg-info text-white w-full my-1"
                          wire:click="previewDraft" spinner="previewDraft"/>
            </x-card>
            {{-- Trạng thái & Thời gian đăng --}}
            <x-card title="Xuất bản" shadow class="p-3!">
                <x-input label="Đường dẫn" wire:model.live.debounce.1000ms="slug"
                         placeholder="thong-bao-tuyen-sinh-2025" required
                />
                @if($this->canReview())
                    <x-select label="Trạng thái" wire:model.live="status"
                              :options="$statusOptions"
                              option-value="id" option-label="name"
                        class="mt-2"
                    />
                @else
                    <div class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded p-3 mt-2">
                        Bài viết của bạn cần <strong>gửi duyệt</strong>  trước khi xuất bản.
                    </div>
                @endif

                @if($this->canReview())
                    <x-checkbox
                        class="mt-3"
                        label="Đánh dấu là bài viết nổi bật"
                        wire:model="is_featured"
                    />
                    @error('is_featured') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
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
                    class="select select-bordered w-full max-h-80 overflow-auto"
                >
                    @foreach($this->categoryOptions as $category)
                        <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </x-card>

            <x-card title="Ảnh đại diện" shadow class="p-3!">
                <div x-data="{ previewUrl: null }" x-on:livewire-upload-start="previewUrl = null">
                    <x-file wire:model="thumbnail" label="Ảnh thumbnail"
                            hint="jpg, jpeg, png, webp – tối đa 2MB" accept="image/*"
                            x-on:change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"/>
                    <div class="mt-3">
                        <template x-if="previewUrl">
                            <img src="#" :src="previewUrl" alt="Preview"
                                 class="size-40 rounded object-cover ring-1 ring-gray-200"/>
                        </template>
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
        </div>
    </div>
</div>
