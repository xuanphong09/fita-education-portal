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
    use Livewire\Attributes\On;
    use App\Models\EmailTemplate;
    use App\Services\EmailTemplateService;

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
        public string $searchCategory = '';
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

        // Default Image Template
        public ?int $post_default_image_id = null;

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

        #[On('editor-upload-error')]
        public function showEditorUploadError(string $message): void
        {
            $this->error(
                $message,
                position: 'toast-top toast-end',
                timeout: 6000
            );
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
                'status'             => 'required|in:draft,pending_review,published', // Tạo mới không có rejected
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
                'thumbnail'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            ];
        }

        protected $messages = [
            'slug.required'       => 'Đường dẫn không được để trống.',
            'slug.unique'         => 'Đường dẫn đã tồn tại, vui lòng chọn đường dẫn khác.',
            'thumbnail.image'     => 'File tải lên phải là hình ảnh.',
            'thumbnail.mimes'     => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
            'thumbnail.max'       => 'Ảnh không được vượt quá 10MB.',
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

            if (! $this->canReviewForSelectedCategories()) {
                $this->status = 'draft';
                $this->published_at = null;
                $this->is_featured = false;
            }
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
//            return [
//                ['id' => 'draft',     'name' => 'Nháp'],
//                ['id' => 'published', 'name' => 'Đã đăng'],
//            ];
            $options = [
                ['id' => 'draft', 'name' => 'Nháp'],
            ];

            if ($this->canReviewForSelectedCategories()) {
                $options[] = ['id' => 'published', 'name' => 'Đã đăng'];
            }

            return $options;
        }

        public function getCategoryOptionsProperty(): array
        {
            $categories = Category::query()->orderBy('order')->get();
            $allowedCategoryIds = $this->allowedCategoryIds();
            $allowedMap = array_flip($allowedCategoryIds);

            // Biến lưu trữ các ID sẽ được hiển thị lên màn hình
            $visibleIds = [];

            if (trim($this->searchCategory) !== '') {
                $searchTerm = Str::lower(Str::ascii(trim($this->searchCategory)));
                $matchedIds = [];

                // 1. Tìm các danh mục khớp từ khóa (hoặc đang được tích chọn)
                foreach ($categories as $cat) {
                    $normalizedName = Str::lower(Str::ascii($cat->getTranslatedName()));
                    if (Str::contains($normalizedName, $searchTerm) || in_array($cat->id, $this->category_ids)) {
                        $matchedIds[] = $cat->id;
                    }
                }

                // 2. Mở rộng: Tìm tất cả Cha và Con của các danh mục đã khớp
                foreach ($matchedIds as $id) {
                    $visibleIds[] = $id;

                    // 2a. Dò ngược lên: Thêm tất cả danh mục Cha, Ông nội...
                    $curr = $categories->firstWhere('id', $id);
                    while ($curr && $curr->parent_id) {
                        $visibleIds[] = (int)$curr->parent_id;
                        $curr = $categories->firstWhere('id', $curr->parent_id);
                    }

                    // 2b. Dò đệ quy xuống: Thêm tất cả danh mục Con, Cháu...
                    $visibleIds = array_merge($visibleIds, $this->getAllDescendantIds($categories, $id));
                }
            } else {
                // Khi không tìm kiếm: Hiển thị danh mục được phép + danh mục đã chọn + cha của chúng
                $displayCategoryIds = array_unique(array_merge($allowedCategoryIds, $this->category_ids));
                foreach ($displayCategoryIds as $id) {
                    $visibleIds[] = $id;
                    $curr = $categories->firstWhere('id', $id);
                    while ($curr && $curr->parent_id) {
                        $visibleIds[] = (int)$curr->parent_id;
                        $curr = $categories->firstWhere('id', $curr->parent_id);
                    }
                }
            }

            // Loại bỏ ID trùng lặp và chuyển thành Map để hàm flatten tra cứu siêu tốc O(1)
            $visibleMap = array_flip(array_unique($visibleIds));

            // Hàm flatten tự động xếp chúng thành hình cây theo Map hiển thị
            return $this->flattenCategoryOptions($categories, null, $allowedMap, $visibleMap, 0);
        }

        private function getAllDescendantIds($categories, $parentId): array
        {
            $ids = [];
            foreach ($categories->where('parent_id', $parentId) as $child) {
                $ids[] = $child->id;
                $ids = array_merge($ids, $this->getAllDescendantIds($categories, $child->id));
            }
            return $ids;
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

                $this->sendPostNotificationOnce(
                    'submitted',
                    $post,
                    function () use ($post) {
                        app(PostNotificationService::class)->notifySubmitted(
                            $post,
                            auth()->user()?->name ?? '—'
                        );
                    }
                );

                // VÁ LỖI: Bắn tín hiệu Tăng số lượng Badge Bài chờ duyệt ngay lập tức
                $this->dispatch('post:pending-count-changed', delta: 1);
            });

            $this->success('Đã gửi bài viết chờ duyệt!', redirectTo: route('admin.post.index'));
        }

        public function previewDraft(): void
        {
            $cacheKey = isset($this->id)
                ? 'post_preview_' . $this->id . '_' . auth()->id()
                : 'post_preview_new_' . auth()->id();

            $thumbnailPreviewUrl = null;

            if ($this->thumbnail) {
                $this->validateOnly('thumbnail');

                // Không lưu vào storage, chỉ lấy URL tạm để xem trước
                $thumbnailPreviewUrl = $this->thumbnail->temporaryUrl();
            } elseif (!empty($this->currentThumbnail ?? null)) {
                // Trường hợp bài viết cũ đã có ảnh thật
                $thumbnailPreviewUrl = Storage::url($this->currentThumbnail);
            }

            Cache::put($cacheKey, [
                'title'                 => ['vi' => $this->title_vi, 'en' => $this->title_en],
                'content'               => ['vi' => $this->content_vi, 'en' => $this->content_en],
                'excerpt'               => ['vi' => $this->excerpt_vi, 'en' => $this->excerpt_en],
                'slug'                  => $this->slug,
                'category_ids'          => $this->category_ids,
                'status'                => $this->status,
                'is_featured'           => $this->is_featured,
                'published_at'          => $this->published_at,

                // Lưu URL string, không lưu object TemporaryUploadedFile
                'thumbnail_url'         => $thumbnailPreviewUrl,

                'post_default_image_id' => $this->post_default_image_id ?? null,
                'show_author'           => $this->show_author ?? true,
                'show_published_at'     => $this->show_published_at ?? true,
                'show_views'            => $this->show_views ?? true,
                'show_category'         => $this->show_category ?? true,
                'show_related_posts'    => $this->show_related_posts ?? true,
                'user_id'               => auth()->id(),
            ], now()->addMinutes(10));

            $url = isset($this->id)
                ? route('admin.preview.post', ['id' => $this->id, 'draft' => 1])
                : route('admin.preview.post.new');

            $this->dispatch('open-preview', url: $url);
        }

        private function submitPostForReview(Post $post): void
        {
            $post->update([
                'status' => Post::APPROVAL_PENDING,
                'submitted_at' => now(),
                'updated_by' => auth()->id(),
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
            $publishedAt = null;

            if ($postStatus === 'published') {
                $publishedAt = filled($this->published_at)
                    ? Carbon::parse(str_replace('/', '-', $this->published_at))
                    : now();
            }

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
                'updated_by' => Auth::id(),
                'reviewed_by' => $postStatus === 'published' ? auth()->id() : null,
                'reviewed_at' => $postStatus === 'published' ? now() : null,
                'rejection_reason' => null,
                'is_featured' => $this->canReviewForSelectedCategories() ? $this->is_featured : false,
                'published_at' => $publishedAt,
                'user_id' => Auth::id(),
                'thumbnail' => $thumbnailPath ?: null,
                'post_default_image_id' => $this->post_default_image_id ?: null,
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
        public function selectTemplate(int $id): void
        {
            // 1. Chọn template
            $this->post_default_image_id = $id;

            // 2. Hủy hoàn toàn file ảnh đã tải lên trên server
            $this->thumbnail = null;
            $this->resetErrorBag('thumbnail');
        }

        private function sendNotificationSafely(callable $callback): void
        {
            try {
                $callback();
            } catch (\Throwable $e) {
                report($e);

                $this->warning('Bài viết đã được lưu nhưng gửi email thông báo thất bại.');
            }
        }

        private function sendPostNotificationOnce(
            string $event,
            Post $post,
            callable $callback,
            int $seconds = 60
        ): void {
            $templateType = $this->notificationTemplateTypeForEvent($event, $post);

            if ($templateType && ! EmailTemplateService::shouldSend($templateType)) {
                return;
            }

            $cacheKey = "post_notification_sent:{$event}:{$post->id}";

            if (! Cache::add($cacheKey, true, now()->addSeconds($seconds))) {
                return;
            }

            $this->sendNotificationSafely($callback);
        }

        private function notificationTemplateTypeForEvent(string $event, Post $post): ?string
        {
            $action = match (true) {
                str_starts_with($event, 'submitted') => 'submitted',
                $event === 'approved' => 'approved',
                default => null,
            };

            if (! $action) {
                return null;
            }

            return EmailTemplate::postStatusTemplateTypeForAction(
                $action,
                $post->published_at?->toDateTimeString()
            );
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
                    <div class="mb-3">
                        <x-input
                            icon="o-magnifying-glass"
                            placeholder="Tìm kiếm danh mục..."
                            wire:model.live.debounce.300ms="searchCategory"
                            clearable
                            class="input-md w-full"
                        />
                    </div>
                    <select wire:model.live.debounce.300ms="category_ids" multiple size="8" class="select select-bordered w-full max-h-80 overflow-auto @error('category_ids') select-error @enderror [&_option:checked]:bg-blue-50 [&_option:checked]:text-blue-700 focus:outline-none">
                        @forelse($this->categoryOptions as $category)
                            <option wire:key="cat-opt-{{ $category['id'] }}" value="{{ $category['id'] }}" @if($category['disabled']) disabled @endif>{{ $category['name'] }}</option>
                        @empty
                            <option disabled class="text-gray-800 italic p-0">Không tìm thấy danh mục nào.</option>
                        @endforelse
                    </select>
                    @error('category_ids') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </x-card>

                <x-card title="Ảnh đại diện" shadow class="p-3!">
                    <div x-data="{ previewUrl: null }" x-on:livewire-upload-start="previewUrl = null">
                        @php
                            $defaultImageTemplates = \App\Models\PostDefaultImage::where('is_active', true)->orderBy('order')->get();
                            $selectedTemplate = $defaultImageTemplates->firstWhere('id', $post_default_image_id);
                            $previewTitle = trim($title_vi) !== '' ? $title_vi : (trim($title_en) !== '' ? $title_en : '_');
                        @endphp

                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2 gap-2">
                                <p class="text-sm font-semibold text-gray-700">Chọn ảnh có sẵn</p>
                                @if($post_default_image_id)
                                    <x-button
                                        label="Bỏ chọn"
                                        icon="o-x-mark"
                                        class="btn-ghost text-red-500 btn-sm"
                                        wire:click="$set('post_default_image_id', null)"
                                        spinner="post_default_image_id"
                                    />
                                @endif
                            </div>

                            @if($defaultImageTemplates->isEmpty())
                                <p class="text-sm text-gray-500">Không có ảnh nào</p>
                            @else
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach($defaultImageTemplates as $template)
                                        @php $isSelected = (int) $post_default_image_id === (int) $template->id; @endphp
                                        <button
                                            type="button"
                                            x-on:click="previewUrl = null;
                                                if(document.querySelector('input[type=file]')) {
                                                    document.querySelector('input[type=file]').value = '';
                                                }"
                                            wire:click="selectTemplate({{ $template->id }})"
                                            class="relative overflow-hidden rounded-lg border text-left transition {{ $isSelected ? 'border-primary ring-2 ring-primary/40' : 'border-gray-200 hover:border-primary/60' }}"
                                        >
                                            <img
                                                src="{{ Storage::url($template->image_path) }}"
                                                alt="{{ $template->name }}"
                                                class="h-21 w-full object-cover object-top"
                                            />
    {{--                                        <div class="absolute inset-x-0 bottom-0 bg-black/55 px-2 py-1 text-xs font-medium text-white line-clamp-1">--}}
    {{--                                            {{ $template->name }}--}}
    {{--                                        </div>--}}
                                            @if($isSelected)
                                                <span class="absolute right-2 top-2 text-sm">
                                                    <x-icon name="o-check-circle" class="rounded-full bg-primary text-white"></x-icon>
                                                </span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if($selectedTemplate)
                            <div class="mb-4 rounded border border-blue-200 bg-blue-50 p-3">
                                <p class="mb-2 text-sm text-blue-700">Xem ảnh đã chọn</p>
                                <div class="relative overflow-hidden rounded" style="container-type: inline-size;">
                                    <img
                                        src="{{ Storage::url($selectedTemplate->image_path) }}"
                                        alt="{{ $selectedTemplate->name }}"
                                        class="w-full object-cover"
                                    />
                                    @if($selectedTemplate->show_title)
                                        <div class="absolute inset-0 flex items-center justify-center p-4 text-center"
                                            style="transform: translateY(calc({{ $selectedTemplate->text_y_offset }} / 1200 * 100cqw));"
                                        >
                                            <p class="line-clamp-4 font-bold"
                                               style="color: {{ $selectedTemplate->text_color }};
                                                font-size: clamp(12px, calc({{ $selectedTemplate->text_size }} / 1200 * 100cqw), 60px);
                                                line-height: 1.1;
                                                text-align: {{ $selectedTemplate->text_alignment }};
                                                text-wrap: balance;
                                                "
                                            >{{ $previewTitle }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <hr class="my-4"/>

                        <x-file wire:model="thumbnail"
                                label="Tải lên ảnh đại diện"
                                hint="jpg, jpeg, png, webp – tối đa 10MB"
                                accept="image/*"
                                x-on:change="
                                    previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null;
                                    if ($event.target.files[0]) {
                                        $wire.set('post_default_image_id', null);
                                    }
                                "/>
                        <div class="mt-3">
                            <template x-if="previewUrl">
                                <div class="relative inline-block">
                                    <img src="#" :src="previewUrl" alt="Preview" class="w-full rounded object-cover ring-1 ring-gray-200"/>

                                    <button
                                        type="button"
                                        class="absolute -right-2 -top-2 btn btn-circle btn-xs btn-error text-white shadow-md hover:scale-110 transition-transform"
                                        @click="
                                            previewUrl = null;
                                            if(document.querySelector('input[type=file]')) {
                                                document.querySelector('input[type=file]').value = '';
                                            }
                                            $wire.set('thumbnail', null);
                                        "
                                                    title="Xóa ảnh tải lên"
                                        >
                                        ✕
                                    </button>
                                </div>
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
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let lastMessage = null;
                let lastToastTime = 0;

                function getUploadErrorMessage(data) {
                    return data?.errors?.file?.[0]
                        || data?.message
                        || data?.error
                        || 'Đã có lỗi xảy ra khi tải ảnh lên.';
                }

                function showUploadError(message) {
                    const now = Date.now();

                    // Tránh hiện lặp toast liên tục cùng một lỗi
                    if (lastMessage === message && now - lastToastTime < 1500) {
                        return;
                    }

                    lastMessage = message;
                    lastToastTime = now;

                    if (window.Livewire) {
                        Livewire.dispatch('editor-upload-error', {
                            message: message
                        });
                        return;
                    }

                    alert(message);
                }

                // Bắt request dùng fetch
                const originalFetch = window.fetch;

                window.fetch = async function (...args) {
                    const response = await originalFetch.apply(this, args);

                    const url = typeof args[0] === 'string'
                        ? args[0]
                        : (args[0]?.url || '');

                    if (!response.ok && response.status >= 400 && url.includes('/mary/upload')) {
                        response.clone().json()
                            .then(data => {
                                showUploadError(getUploadErrorMessage(data));
                            })
                            .catch(() => {
                                showUploadError('Tải ảnh thất bại. Vui lòng thử lại.');
                            });
                    }

                    return response;
                };

                // Bắt request dùng XMLHttpRequest
                const originalOpen = XMLHttpRequest.prototype.open;
                const originalSend = XMLHttpRequest.prototype.send;

                XMLHttpRequest.prototype.open = function (method, url, ...rest) {
                    this._maryUploadUrl = typeof url === 'string' && url.includes('/mary/upload');
                    return originalOpen.call(this, method, url, ...rest);
                };

                XMLHttpRequest.prototype.send = function (...args) {
                    if (this._maryUploadUrl) {
                        this.addEventListener('load', function () {
                            if (this.status >= 400) {
                                let data = {};

                                try {
                                    data = JSON.parse(this.responseText || '{}');
                                } catch (e) {
                                    data = {};
                                }

                                showUploadError(getUploadErrorMessage(data));
                            }
                        });

                        this.addEventListener('error', function () {
                            showUploadError('Không thể tải ảnh lên. Vui lòng kiểm tra kết nối mạng.');
                        });
                    }

                    return originalSend.apply(this, args);
                };
            });
        </script>
    </div>
