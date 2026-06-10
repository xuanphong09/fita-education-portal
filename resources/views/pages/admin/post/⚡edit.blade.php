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
    use App\Services\PostNotificationService;
    use App\Models\EmailTemplate;
    use App\Services\EmailTemplateService;

    new class extends Component {
        use Toast, WithFileUploads;

        public int $id;
        public string $selectedTab = 'tab-vi';
        public ?int $author_id = null;

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
        public string $searchCategory = '';

        // Trạng thái
        public string $status       = 'draft';
        public string $currentStatus= 'draft';
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
        public bool $is_removing_thumbnail = false;

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
        public int $historyLimit = 10;

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

        public function isReviewerOnly(): bool
        {
            return $this->canReview() && ! $this->canWrite();
        }

        private function postCategoryIds(Post $post): array
        {
            $categoryIds = $post->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->toArray();
            if (empty($categoryIds) && $post->category_id) {
                $categoryIds = [(int) $post->category_id];
            }
            return array_values(array_unique(array_filter($categoryIds, fn ($id) => $id > 0)));
        }

        // Lấy quyền Duyệt dựa trên Database (Cố định, không bị ảnh hưởng bởi UI)
        public function canReviewOriginalPost(): bool
        {
            $user = auth()->user();
            if (!$user) return false;
            if ($user->can('quan_ly_bai_viet') || $user->can('duyet_bai_viet')) return true;

            $reviewIds = $user->scopedPostCategoryIds('duyet_bai_viet') ?? [];
            $post = Post::find($this->id);
            if (!$post) return false;

            return count(array_intersect($this->postCategoryIds($post), $reviewIds)) > 0;
        }

        // Lấy quyền Viết dựa trên Database (Cố định, không bị ảnh hưởng bởi UI)
        public function canWriteOriginalPost(): bool
        {
            $user = auth()->user();
            if (!$user) return false;
            if ($user->can('quan_ly_bai_viet') || $user->can('viet_bai_viet')) return true;

            $writeIds = $user->scopedPostCategoryIds('viet_bai_viet') ?? [];
            $post = Post::find($this->id);
            if (!$post) return false;

            return count(array_intersect($this->postCategoryIds($post), $writeIds)) > 0;
        }

        private function validateCategoryPermissions(array $finalCategoryIds, array $originalCategoryIdsFromDB): void
        {
            if (empty($finalCategoryIds)) return;

            $user = auth()->user();
            if (!$user) return;

            if ($user->can('quan_ly_bai_viet') || $user->can('viet_bai_viet') || $user->can('duyet_bai_viet')) {
                return;
            }

            $allowedIds = $this->allowedCategoryIds();

            if (count(array_intersect($finalCategoryIds, $allowedIds)) === 0) {
                // Khôi phục mảng về trạng thái Database để giữ nguyên UI không bị mất nút
                $this->category_ids = $originalCategoryIdsFromDB;
                throw ValidationException::withMessages([
                    'category_ids' => 'Bạn phải giữ lại ít nhất 1 danh mục thuộc quyền của mình.',
                ]);
            }
        }

        protected function authorizePostAccess(Post $post): void
        {
            $user = auth()->user();
            if (!$user) abort(403);

            if ((int) $post->user_id === (int) $user->id) return;
            if ($user->can('quan_ly_bai_viet') || $user->can('viet_bai_viet') || $user->can('duyet_bai_viet')) return;

            $postCategoryIds = $this->postCategoryIds($post);

            $reviewIds = $user->scopedPostCategoryIds('duyet_bai_viet') ?? [];
            if (count(array_intersect($postCategoryIds, $reviewIds)) > 0) return;

            $writeIds = $user->scopedPostCategoryIds('viet_bai_viet') ?? [];
            if (count(array_intersect($postCategoryIds, $writeIds)) > 0) return;

            abort(403);
        }

        public function isAuthor(): bool
        {
            $userId = auth()->id();
            return $userId !== null && (int) $this->author_id === (int) $userId;
        }

        public function isScheduledPublished(): bool
        {
            if ($this->currentStatus !== 'published' || empty($this->published_at)) {
                return false;
            }

            try {
                return Carbon::parse($this->published_at)->isFuture();
            } catch (\Throwable $e) {
                return false;
            }
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
                'category_ids'       => 'required|array|min:1',
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
            'published_at.date'      => 'Ngày xuất bản không hợp lệ.',
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
                $errors['title_en'] = 'Cần có ít nhất một ngôn ngữ đầy đủ (tiêu đề + nội dung).';
            }

            if (! empty($errors)) throw ValidationException::withMessages($errors);
        }

        public function mount(int $id): void
        {
            $this->id   = $id;
            $post       = Post::findOrFail($id);
            $this->author_id = $post->user_id;
            $this->authorizePostAccess($post);

            if ($this->isReviewerOnly()) {
                $this->redirectRoute('admin.posts.review', ['id' => $id], navigate: true);
                return;
            }

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
            $this->url                = $post->client_url;
            $this->category_ids       = $post->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->toArray();
            if (empty($this->category_ids) && $post->category_id) {
                $this->category_ids = [(int) $post->category_id];
            }
            $this->status             = $post->status;
            $this->currentStatus      = $post->status;
            $this->readOnlyPublished  = $post->status === 'published' && ! $this->canReviewOriginalPost();
            $this->submitted_at       = $post->submitted_at?->format('d/m/Y H:i');
            $this->reviewed_at        = $post->reviewed_at?->format('d/m/Y H:i');
            $this->rejection_reason   = $post->rejection_reason;
            $this->is_featured        = (bool) $post->is_featured;
            $this->published_at       = $post->published_at?->format('Y-m-d\\TH:i');
            $this->currentThumbnail   = $post->thumbnail;
            $this->post_default_image_id = $post->post_default_image_id;
            $this->show_author        = (bool) $post->show_author;
            $this->show_published_at  = (bool) $post->show_published_at;
            $this->show_views         = (bool) $post->show_views;
            $this->show_category      = (bool) $post->show_category;
            $this->show_related_posts = (bool) $post->show_related_posts;
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
            $categories = Category::all(); // Lấy cây danh mục
            $allowedMap = array_flip($this->allowedCategoryIds()); // Chuyển quyền thành map O(1) để tra cứu siêu tốc
            $finalIds = [];

            foreach ($selectedIds as $id) {
                $finalIds[] = $id; // Chắc chắn giữ lại danh mục con mà user vừa click

                $curr = $categories->firstWhere('id', $id);

                // Dò ngược lên các cấp cha
                while ($curr && $curr->parent_id) {
                    $parentId = (int) $curr->parent_id;

                    // Nếu User có quyền viết ở danh mục cha này -> Tự động thêm vào mảng chọn
                    if (isset($allowedMap[$parentId])) {
                        $finalIds[] = $parentId;
                    }

                    // Tiếp tục dò lên ông nội, cụ cố...
                    $curr = $categories->firstWhere('id', $parentId);
                }
            }

            // Cập nhật lại mảng hiển thị lên UI (Loại bỏ các ID trùng lặp)
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
            $labels = [
                'draft' => 'Nháp',
                Post::APPROVAL_PENDING => 'Chờ duyệt',
                Post::APPROVAL_REJECTED => 'Từ chối',
                'published' => 'Đã đăng',
                'archived' => 'Lưu trữ',
            ];

            $post = Post::find($this->id);

            if (! $post) {
                return [
                    ['id' => $this->status, 'name' => $labels[$this->status] ?? $this->status],
                ];
            }

            $allowedStatuses = $this->allowedStatusValues($post);

            return collect($allowedStatuses)
                ->map(fn ($status) => [
                    'id' => $status,
                    'name' => $labels[$status] ?? $status,
                ])
                ->values()
                ->all();
        }

        private function allowedStatusValues(Post $post): array
        {
            // Người không có quyền duyệt thì không được đổi trạng thái bằng dropdown
            if (! $this->canReviewOriginalPost()) {
                return [$post->status];
            }

            return match ($post->status) {
                'draft' => [
                    'draft',
                    'published',
                ],

                Post::APPROVAL_PENDING => [
                    Post::APPROVAL_PENDING,
                ],

                Post::APPROVAL_REJECTED => [
                    Post::APPROVAL_REJECTED,
                    Post::APPROVAL_PENDING,
                ],

                'published' => $this->isPublishedOver24Hours($post)
                    ? [
                        'published',
                        'archived',
                    ]
                    : [
                        'published',
                        Post::APPROVAL_PENDING,
                        'archived',
                    ],

                'archived' => [
                    'archived',
                    'published',
                ],

                default => [
                    $post->status,
                ],
            };
        }

        private function isPublishedOver24Hours(Post $post): bool
        {
            if ($post->status !== 'published') {
                return false;
            }

            $publishDate = $post->published_at ?? $post->created_at;

            if (! $publishDate) {
                return false;
            }

            return Carbon::parse($publishDate)->diffInHours(now()) > 24;
        }

        public function getCategoryOptionsProperty(): array
        {
            $categories = Category::query()->orderBy('order')->get();
            $allowedCategoryIds = $this->allowedCategoryIds();

            // Đảm bảo các ID biến thành số nguyên
            $allowedCategoryIds = array_map(fn($id) => (int) $id, $allowedCategoryIds);
            $allowedMap = array_flip($allowedCategoryIds);

            $visibleIds = [];

            if (trim($this->searchCategory) !== '') {
                $searchTerm = Str::lower(Str::ascii(trim($this->searchCategory)));
                $matchedIds = [];

                // 1. Tìm các danh mục khớp từ khóa HOẶC đang được tích chọn (để không bị mất khi lọc)
                foreach ($categories as $cat) {
                    $normalizedName = Str::lower(Str::ascii($cat->getTranslatedName()));
                    if (Str::contains($normalizedName, $searchTerm) || in_array($cat->id, $this->category_ids)) {
                        $matchedIds[] = $cat->id;
                    }
                }

                // 2. Mở rộng: Tìm tất cả Cha và Con của các danh mục đã khớp
                foreach ($matchedIds as $id) {
                    $visibleIds[] = $id;

                    // 2a. Dò ngược lên: Thêm tất cả danh mục Cha
                    $curr = $categories->firstWhere('id', $id);
                    while ($curr && $curr->parent_id) {
                        $visibleIds[] = (int)$curr->parent_id;
                        $curr = $categories->firstWhere('id', $curr->parent_id);
                    }

                    // 2b. Dò đệ quy xuống: Thêm tất cả danh mục Con
                    $visibleIds = array_merge($visibleIds, $this->getAllDescendantIds($categories, $id));
                }
            } else {
                // Khi không tìm kiếm: Hiển thị (Quyền của user + Các danh mục đã lưu trong DB) và Cha của chúng
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

            // Loại bỏ ID trùng lặp và tạo Map để Flatten siêu tốc
            $visibleMap = array_flip(array_unique($visibleIds));

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
            if ($user->can('quan_ly_bai_viet') || $user->can('viet_bai_viet') || $user->can('duyet_bai_viet')) {
                return Category::query()->orderBy('order')->pluck('id')->map(fn ($id) => (int) $id)->all();
            }
            $writeIds = $user->scopedPostCategoryIds('viet_bai_viet') ?? [];
            $reviewIds = $user->scopedPostCategoryIds('duyet_bai_viet') ?? [];

            $merged = array_unique(array_merge($writeIds, $reviewIds));
            return array_map(fn($id) => (int) $id, $merged);
        }

        private function flattenCategoryOptions($categories, ?int $parentId = null, array $allowedMap = [], array $visibleMap = [], int $depth = 0): array
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

        public function fillSeoEn(): void
        {
            if (empty($this->seo_title_en)) $this->seo_title_en = $this->title_en ?: $this->title_vi;
            if (empty($this->seo_description_en)) $this->seo_description_en = $this->excerpt_en ?: $this->excerpt_vi;
        }

        public function previewDraft(): void
        {
            $cacheKey = isset($this->id)
                ? 'post_preview_' . $this->id . '_' . auth()->id()
                : 'post_preview_new_' . auth()->id();

            $thumbnailPath = null;
            $thumbnailPreviewUrl = null;

            if ($this->thumbnail) {
                $this->validateOnly('thumbnail');

                $thumbnailPreviewUrl = $this->thumbnail->temporaryUrl();
            }
            elseif (! $this->is_removing_thumbnail && ! empty($this->currentThumbnail)) {
                $thumbnailPath = $this->currentThumbnail;
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
                'thumbnail'             => $thumbnailPath,
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

        public function removeThumbnail(): void
        {
    //        if ($this->currentThumbnail) Storage::disk('public')->delete($this->currentThumbnail);
            $this->is_removing_thumbnail = true;
            $this->currentThumbnail = null;

    //        Post::where('id', $this->id)->update(['thumbnail' => null]);
    //        $this->success('Đã xóa ảnh thumbnail.');
            $this->info('Đã gỡ ảnh. Nhớ bấm "Lưu thay đổi" để áp dụng!');
        }

        private function ensureFeaturedLimitForUpdate(Post $post): void
        {
            if (! $this->canToggleFeatured()) return;
            $wasCounted = $post->is_featured && $post->status === 'published';
            $willBeCounted = $this->is_featured && $this->status === 'published';
            if (! $willBeCounted || $wasCounted) return;

            if (Post::where('is_featured', true)->where('status', 'published')->count() >= 5) {
                throw ValidationException::withMessages(['is_featured' => 'Chỉ được tối đa 5 bài viết nổi bật.']);
            }
        }

        public function cleanEmptyHtmlLines(string $html): string
        {
            $html = trim($html);
            return trim(preg_replace('/^(?:<p[^>]*>(?:\s|&nbsp;|<br\/?\s*>)*<\/p>\s*|<br\/?\s*>\s*)+|(?:<p[^>]*>(?:\s|&nbsp;|<br\/?\s*>)*<\/p>\s*|<br\/?\s*>\s*)+$/i', '', $html));
        }

        private function enforceWriterDraftRules(Post $post): void
        {
            if ($this->canReviewOriginalPost()) return;

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
                'reviewer_id' => $this->canReviewOriginalPost() ? auth()->id() : null,
                'note' => $note,
                'scheduled_publish_at' => $scheduledPublishAt,
            ]);
        }

        public function submitForReview(): void
        {
            $post = Post::findOrFail($this->id);
            $this->authorizePostAccess($post);

            if (! $this->isAuthor()) {
                $this->warning('Chỉ tác giả của bài viết mới được gửi duyệt.');
                return;
            }

            if ($this->canReviewOriginalPost()) {
                $this->warning('Bạn đang có quyền duyệt, không cần gửi chờ duyệt.');
                return;
            }

            if (! in_array($post->status, ['draft', Post::APPROVAL_REJECTED, Post::APPROVAL_PENDING], true)) {
                $this->warning('Chỉ có thể gửi duyệt bài nháp, bài bị từ chối hoặc bài đang chờ duyệt.');
                return;
            }

            $oldStatus = $post->status;
            $wasPending = $oldStatus === Post::APPROVAL_PENDING;

            $this->persistData($post);

            $action = match ($oldStatus) {
                Post::APPROVAL_REJECTED => 'resubmitted',
                Post::APPROVAL_PENDING => 'updated_pending',
                default => 'submitted',
            };

            $note = match ($action) {
                'resubmitted' => 'Tác giả gửi lại duyệt.',
                'updated_pending' => 'Tác giả cập nhật nội dung bài đang chờ duyệt.',
                default => 'Tác giả gửi chờ duyệt.',
            };

            $post->update([
                'status' => Post::APPROVAL_PENDING,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ]);

            $this->status = Post::APPROVAL_PENDING;
            $this->currentStatus = Post::APPROVAL_PENDING;
            $this->submitted_at = now()->format('d/m/Y H:i');
            $this->reviewed_at = null;
            $this->rejection_reason = null;

            $this->logApprovalHistory($post, $action, $note);

            if (in_array($oldStatus, ['draft', Post::APPROVAL_REJECTED], true)) {
                $this->sendPostNotificationOnce(
                    "submitted_{$oldStatus}",
                    $post,
                    function () use ($post) {
                        app(PostNotificationService::class)->notifySubmitted(
                            $post,
                            auth()->user()?->name ?? '—'
                        );
                    }
                );
            }

            if (! $wasPending) {
                $this->dispatch('post:pending-count-changed', delta: 1);
            }

            $this->success('Đã gửi bài viết chờ duyệt!');
        }

        public function approvePost(): void
        {
            $post = Post::findOrFail($this->id);
            $this->authorizePostAccess($post);
    //        $this->persistData($post);

            if (!$this->canReviewOriginalPost()) {
                $this->warning('Bạn không có quyền duyệt bài này.');
                return;
            }

            if ($post->status !== Post::APPROVAL_PENDING) {
                $this->warning('Chỉ có thể duyệt bài đang ở trạng thái chờ duyệt.');
                return;
            }

            $this->persistData($post);

    //        $safeDate = str_replace('/', '-', $this->published_at);
    //        $publishAt = $this->published_at ? Carbon::parse($safeDate) : now();
            $publishAt = filled($this->published_at)
                ? Carbon::parse(str_replace('/', '-', $this->published_at))
                : now();

            $post->update([
                'status' => 'published',
                'published_at' => $publishAt,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->status = 'published';
            $this->currentStatus = 'published';
            $this->reviewed_at = now()->format('d/m/Y H:i');
            $this->rejection_reason = null;

            $this->logApprovalHistory($post, 'approved', 'Duyệt bài viết.', $publishAt->toDateTimeString());
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
            $this->success($publishAt->greaterThan(now()) ? 'Đã duyệt và lên lịch đăng bài.' : 'Đã duyệt và đăng bài viết.');
        }

        public function rejectPost(): void
        {
            $this->validate(['reviewNote' => 'required|string|min:5|max:1000']);

            $post = Post::findOrFail($this->id);
            $this->authorizePostAccess($post);

            if (! $this->canReviewOriginalPost()) {
                $this->warning('Bạn không có quyền từ chối bài này.');
                return;
            }

            if ($post->status !== Post::APPROVAL_PENDING) {
                $this->warning('Chỉ có thể từ chối bài đang ở trạng thái chờ duyệt.');
                return;
            }

            $this->persistData($post);

            $rejectReason = $this->reviewNote;

            $post->update([
                'status' => Post::APPROVAL_REJECTED,
                'published_at' => null,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => $rejectReason,
            ]);

            $this->status = Post::APPROVAL_REJECTED;
            $this->currentStatus = Post::APPROVAL_REJECTED;
            $this->published_at = null;
            $this->reviewed_at = now()->format('d/m/Y H:i');
            $this->rejection_reason = $rejectReason;

            $this->logApprovalHistory($post, 'rejected', $rejectReason);

            $this->reviewNote = '';

            $this->sendPostNotificationOnce(
                'rejected',
                $post,
                function () use ($post, $rejectReason) {
                    app(PostNotificationService::class)->notifyRejected(
                        $post,
                        auth()->user()?->name ?? '—',
                        $rejectReason
                    );
                }
            );

            $this->dispatch('post:pending-count-changed', delta: -1);

            $this->warning('Đã từ chối bài viết.');
        }

        private function persistData(Post $post): void
        {
            $allowedIds = $this->allowedCategoryIds();
            $originalCategoryIdsFromDB = $post->categories()->pluck('categories.id')->map(fn($id) => (int)$id)->toArray();
            $lockedCategoryIds = array_diff($originalCategoryIdsFromDB, $allowedIds);

            $newValidCategoryIds = array_intersect($this->category_ids, $allowedIds);
            $finalCategoryIds = array_unique(array_merge($lockedCategoryIds, $newValidCategoryIds));

            $this->category_ids = $finalCategoryIds;

            $this->validate();
            $this->validateLocalizedContent();

            // KIỂM TRA QUYỀN MỚI
            $this->validateCategoryPermissions($finalCategoryIds, $originalCategoryIdsFromDB);

            $thumbnailPath = $this->currentThumbnail;
            if ($this->is_removing_thumbnail && $post->thumbnail) {
                Storage::disk('public')->delete($post->thumbnail);
                $thumbnailPath = null;
            }
            if ($this->thumbnail) {
                if ($thumbnailPath) Storage::disk('public')->delete($thumbnailPath);
                $thumbnailPath = $this->thumbnail->store('uploads/posts', 'public');
            }

            $primaryCategoryId = $this->category_ids[0] ?? null;
            $contentImageService = app(ContentImageService::class);
            $content_vi = $this->cleanEmptyHtmlLines($contentImageService->downloadDocuments($contentImageService->downloadAndReplaceExternalImages($this->content_vi)));
            $content_en = $this->cleanEmptyHtmlLines($contentImageService->downloadDocuments($contentImageService->downloadAndReplaceExternalImages($this->content_en)));

            $post->setTranslation('title', 'vi', $this->title_vi);
            $post->setTranslation('title', 'en', $this->title_en);
            $post->setTranslation('content', 'vi', $content_vi);
            $post->setTranslation('content', 'en', $content_en);

    //        if ($this->excerpt_vi !== '' || $this->excerpt_en !== '') {
                $post->setTranslation('excerpt', 'vi', $this->excerpt_vi);
                $post->setTranslation('excerpt', 'en', $this->excerpt_en);
    //        }

    //        if ($this->seo_title_vi !== '' || $this->seo_title_en !== '') {
                $post->setTranslation('seo_title', 'vi', $this->seo_title_vi);
                $post->setTranslation('seo_title', 'en', $this->seo_title_en);
    //        }

    //        if ($this->seo_description_vi !== '' || $this->seo_description_en !== '') {
                $post->setTranslation('seo_description', 'vi', $this->seo_description_vi);
                $post->setTranslation('seo_description', 'en', $this->seo_description_en);
    //        }

            $post->fill([
                'slug' => $this->slug,
                'category_id' => $primaryCategoryId,
                'is_featured' => $this->canToggleFeatured() ? $this->is_featured : $post->is_featured,
                'thumbnail' => $thumbnailPath ?: null,
                'post_default_image_id' => $this->post_default_image_id ?: null,
                'updated_by' => auth()->id(),
                'show_author' => $this->show_author,
                'show_published_at' => $this->show_published_at,
                'show_views' => $this->show_views,
                'show_category' => $this->show_category,
                'show_related_posts' => $this->show_related_posts,
            ]);

            $post->save();

            $post->categories()->sync($finalCategoryIds);
            $this->currentThumbnail = $thumbnailPath;
        }

        public function save(): void
        {
            $post = Post::findOrFail($this->id);
            $this->authorizePostAccess($post);

            if ($post->status === 'published' && ! $this->canReviewOriginalPost()) {
                $this->warning('Bạn chỉ có quyền xem bài đã đăng, không thể lưu thay đổi.');
                return;
            }

            try {
                $this->enforceWriterDraftRules($post);
                $this->ensureFeaturedLimitForUpdate($post);
            } catch (ValidationException $e) {
                $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
                throw $e;
            }

            $nextStatus = $this->canReviewOriginalPost() ? $this->status : $post->status;
            if (! in_array($nextStatus, $this->allowedStatusValues($post), true)) {
                $this->warning('Trạng thái chuyển đổi không hợp lệ theo quy trình duyệt bài.');
                return;
            }
            if ($post->status === Post::APPROVAL_PENDING && in_array($nextStatus, ['published', 'rejected'])) {
                $this->warning('Vui lòng sử dụng các nút bên trong khối "Duyệt bài viết" để thao tác chính xác!');
                return;
            }
            $this->persistData($post);

            $nextPublishedAt = null;

            if ($nextStatus === 'published') {
                $nextPublishedAt = filled($this->published_at)
                    ? Carbon::parse(str_replace('/', '-', $this->published_at))
                    : ($post->published_at ?? now());
            } elseif ($nextStatus === 'archived') {
                $nextPublishedAt = $post->published_at;
            }

            $oldStatus = $post->status;

            $updateData = [
                'status' => $nextStatus,
                'published_at' => $nextPublishedAt,
            ];

            if (
                in_array($oldStatus, ['published', Post::APPROVAL_REJECTED], true)
                && $nextStatus === Post::APPROVAL_PENDING
            ) {
                $updateData['submitted_at'] = now();
                $updateData['reviewed_by'] = null;
                $updateData['reviewed_at'] = null;
                $updateData['rejection_reason'] = null;
            }

            if ($nextStatus === 'archived') {
                $updateData['reviewed_by'] = auth()->id();
                $updateData['reviewed_at'] = now();
            }

            if ($oldStatus === 'archived' && $nextStatus === 'published') {
                $updateData['reviewed_by'] = auth()->id();
                $updateData['reviewed_at'] = now();
                $updateData['rejection_reason'] = null;
            }

            $post->update($updateData);

            if ($oldStatus !== $nextStatus) {
                $action = match (true) {
                    $nextStatus === 'archived' => 'archived',
                    $oldStatus === 'archived' && $nextStatus === 'published' => 'restored',
                    $oldStatus === 'published' && $nextStatus === Post::APPROVAL_PENDING => 'reverted_to_pending',
                    $oldStatus === Post::APPROVAL_REJECTED && $nextStatus === Post::APPROVAL_PENDING => 'restored_to_pending',
                    default => 'status_changed',
                };

                $note = match ($action) {
                    'archived' => 'Bài viết được chuyển sang lưu trữ.',
                    'restored' => 'Bài viết được khôi phục từ lưu trữ.',
                    'reverted_to_pending' => 'Bài viết đã đăng được thu hồi về trạng thái chờ duyệt.',
                    'restored_to_pending' => 'Bài viết bị từ chối được khôi phục về trạng thái chờ duyệt.',
                    default => 'Thay đổi trạng thái bài viết.',
                };

                $this->logApprovalHistory($post, $action, $note, $nextPublishedAt?->toDateTimeString());

                if ($action === 'reverted_to_pending') {
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
                }

                if ($oldStatus !== Post::APPROVAL_PENDING && $nextStatus === Post::APPROVAL_PENDING) {
                    $this->dispatch('post:pending-count-changed', delta: 1);
                }

                if ($oldStatus === Post::APPROVAL_PENDING && $nextStatus !== Post::APPROVAL_PENDING) {
                    $this->dispatch('post:pending-count-changed', delta: -1);
                }
            }

            $this->currentStatus = $nextStatus;
            $this->status = $nextStatus;
            $this->published_at = $nextPublishedAt ? Carbon::parse($nextPublishedAt)->format('Y-m-d\TH:i') : null;

            $this->success('Cập nhật bài viết thành công!');
        }

        public function withdrawToDraft(): void
        {
            $post = Post::findOrFail($this->id);
            $this->authorizePostAccess($post);

            if (! $this->isAuthor()) {
                $this->warning('Chỉ tác giả mới được rút bài viết.');
                return;
            }

            if ($post->status !== Post::APPROVAL_PENDING) {
                $this->warning('Chỉ có thể rút bài đang chờ duyệt.');
                return;
            }

            $post->update([
                'status' => 'draft',
                'submitted_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'updated_by' => auth()->id(),
            ]);

            $this->status = 'draft';
            $this->currentStatus = 'draft';
            $this->submitted_at = null;
            $this->reviewed_at = null;
            $this->rejection_reason = null;

            $this->logApprovalHistory($post, 'withdrawn', 'Tác giả rút bài về nháp.');

            $this->dispatch('post:pending-count-changed', delta: -1);

            $this->success('Đã rút bài về trạng thái nháp.');
        }

        public function getApprovalHistoriesProperty()
        {
            return PostApprovalHistory::query()->with(['actor', 'reviewer'])->where('post_id', $this->id)->latest()->limit($this->historyLimit)->get();
        }

        public function loadMoreHistories(): void
        {
            $this->historyLimit += 5;
        }

        public function getHasMoreApprovalHistoriesProperty(): bool
        {
            return PostApprovalHistory::query()
                    ->where('post_id', $this->id)
                    ->count() > $this->historyLimit;
        }

        public function selectTemplate(int $id): void
        {
            $this->post_default_image_id = $id;

            $this->thumbnail = null;

            $this->currentThumbnail = null;
            $this->is_removing_thumbnail = true;

            $this->resetErrorBag('thumbnail');
        }

        private function sendNotificationSafely(callable $callback): bool
        {
            try {
                $callback();

                return true;
            } catch (\Throwable $e) {
                report($e);

                $this->warning('Thao tác đã được lưu nhưng gửi email thông báo thất bại.');

                return false;
            }
        }

        private function notificationTemplateTypeForEvent(string $event, Post $post): ?string
        {
            $action = match (true) {
                str_starts_with($event, 'submitted_') => 'submitted',
                $event === 'approved' => 'approved',
                $event === 'rejected' => 'rejected',
                $event === 'reverted_to_pending' => 'reverted_to_pending',
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

        private function sendPostNotificationOnce(
            string $event,
            Post $post,
            callable $callback,
            int $seconds = 180
        ): void {
            $templateType = $this->notificationTemplateTypeForEvent($event, $post);

            if (! $templateType) {
                report(new \RuntimeException("Không xác định được email template cho event [{$event}] của bài viết ID {$post->id}."));
                return;
            }

            if (! EmailTemplateService::shouldSend($templateType)) {
                // Template đang tắt hoặc không tồn tại
                return;
            }

            $cacheKey = "post_notification_sent:{$event}:{$post->id}";

            if (! Cache::add($cacheKey, true, now()->addSeconds($seconds))) {
                // Đã gửi gần đây nên bỏ qua để tránh spam
                return;
            }

            $sent = $this->sendNotificationSafely($callback);

            if (! $sent) {
                Cache::forget($cacheKey);
            }
        }
    };
    ?>

    <div x-data x-on:open-preview.window="window.open($event.detail.url, '_blank')">
        <x-slot:title>Chỉnh sửa bài viết</x-slot:title>
        <x-slot:breadcrumb>
            <a href="{{ route('admin.post.index') }}" class="font-semibold text-slate-700" wire:navigate>Danh sách bài viết</a>
            <span class="mx-1">/</span><span>Chỉnh sửa bài viết</span>
        </x-slot:breadcrumb>
        <x-header title="Chỉnh sửa bài viết" class="pb-3 mb-5! border-b border-gray-300"/>

        <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
            {{-- MAIN --}}
            <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">
                <x-tabs wire:model="selectedTab">
                    <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition" @click="open = !open">Nội dung bài viết</button>
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
                                <button type="button" class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition" @click="open = !open">SEO</button>
                                <div class="flex items-center gap-1"><x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/></div>
                            </div>
                            <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                                <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4 text-md text-blue-700 space-y-1">
                                    <p>💡 <strong>SEO Tiêu đề </strong> hiển thị trên tab trình duyệt và kết quả Google.</p>
                                    <p>💡 <strong>SEO Mô tả</strong> là dòng mô tả hiện dưới tiêu đề trên Google.</p>
                                </div>
                                <div class="flex flex-col gap-3">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="fieldset-legend">SEO Tiêu đề</span>
                                            <button type="button" wire:click="$set('seo_title_vi', $wire.title_vi)" class="text-sm text-primary hover:underline">↖ Lấy từ tiêu đề</button>
                                        </div>
                                        <x-input wire:model="seo_title_vi" placeholder="Để trống = dùng tiêu đề bài viết"/>
                                        <p class="text-sm text-gray-400 mt-1">{{ mb_strlen($seo_title_vi) }}/60 ký tự @if(mb_strlen($seo_title_vi) > 60) <span class="text-warning">— nên dưới 60</span> @endif</p>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="fieldset-legend">SEO Mô tả</span>
                                            <button type="button" wire:click="$set('seo_description_vi', $wire.excerpt_vi)" class="text-sm text-primary hover:underline">↖ Lấy từ tóm tắt</button>
                                        </div>
                                        <x-textarea wire:model="seo_description_vi" rows="2" placeholder="Để trống = dùng tóm tắt bài viết"/>
                                        <p class="text-sm text-gray-400 mt-1">{{ mb_strlen($seo_description_vi) }}/160 ký tự @if(mb_strlen($seo_description_vi) > 160) <span class="text-warning">— nên dưới 160</span> @endif</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-tab>

                    <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition" @click="open = !open">Nội dung bài viết (Tiếng Anh)</button>
                                <div class="flex items-center gap-1"><x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/></div>
                            </div>
                            <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                                <x-input wire:model.live.debounce.400ms="title_en" label="Tiêu đề (Tiếng Anh)" placeholder="Ex: Admission announcement 2025"/>
                                <x-textarea wire:model="excerpt_en" placeholder="Short description" rows="3" hint="Max 500 characters" label="Mô tả ngắn (Tiếng Anh)"/>
                                <x-editor wire:model="content_en" :config="config('tinymce')" class="h-full" label="Nội dung chi tiết (Tiếng Anh)" folder="uploads/posts/editor"/>
                            </div>
                        </div>
                        <div x-data="{ open: true }" class="mt-4 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition" @click="open = !open">SEO (Tiếng Anh)</button>
                                <div class="flex items-center gap-1"><x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/></div>
                            </div>
                            <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                                <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4 text-md text-blue-700 space-y-1">
                                    <p><strong>SEO Tiêu đề</strong> hiển thị trên tab trình duyệt và kết quả Google (khác tiêu đề bài viết).</p>
                                    <p><strong>SEO Mô tả</strong> là mô tả dưới tiêu đề trên Google (khác mô tả ngắn).</p>
                                </div>
                                <div class="flex flex-col gap-3">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="fieldset-legend">SEO Tiêu đề</span>
                                            <button type="button" wire:click="$set('seo_title_en', $wire.title_en)" class="text-sm text-primary hover:underline">↖ Lấy từ Tiêu đề (Tiếng Anh)</button>
                                        </div>
                                        <x-input wire:model="seo_title_en" placeholder="Để trống = dùng title bài viết"/>
                                        <p class="text-sm text-gray-400 mt-1">{{ mb_strlen($seo_title_en) }}/60 ký tự @if(mb_strlen($seo_title_en) > 60) <span class="text-warning">— nên dưới 60</span> @endif</p>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="fieldset-legend">SEO Mô tả</span>
                                            <button type="button" wire:click="$set('seo_description_en', $wire.excerpt_en)" class="text-sm text-primary hover:underline">↖ Lấy từ Mô tả ngắn (Tiếng Anh)</button>
                                        </div>
                                        <x-textarea wire:model="seo_description_en" rows="2" placeholder="Để trống = dùng short description bài viết"/>
                                        <p class="text-sm text-gray-400 mt-1">{{ mb_strlen($seo_description_en) }}/160 ký tự @if(mb_strlen($seo_description_en) > 160) <span class="text-warning">— nên dưới 160</span> @endif</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-tab>
                </x-tabs>
                <x-card title="Lịch sử bài viết" shadow class="p-3!">
                    @forelse($this->approvalHistories as $history)
                        @php
                            $historyTitleClass = match($history->action) {
                                'approved' => 'text-md font-bold text-green-600',
                                'rejected' => 'text-md font-bold text-red-600',
                                'archived' => 'text-md font-bold text-orange-600',
                                'restored' => 'text-md font-bold text-blue-600',
                                'withdrawn' => 'text-md font-bold text-yellow-600',
                                'reverted_to_pending','restored_to_pending' => 'text-md font-bold text-warning',
                                'submitted', 'resubmitted', 'updated_pending' => 'text-md font-bold text-primary',
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

                            <div class="text-sm text-gray-500">
                                {{ $history->created_at?->format('d/m/Y H:i') }}
                                @if($history->actor)
                                    - {{ $history->actor->name }}
                                @endif
                            </div>

                            @if($history->scheduled_publish_at)
                                <div class="text-sm text-gray-500">
                                    Lên lịch: {{ $history->scheduled_publish_at->format('d/m/Y H:i') }}
                                </div>
                            @endif

                            @if($history->note)
                                <div class="text-sm text-gray-700 mt-1">
                                    <span class="text-md font-semibold">Nội dung: </span>{{ $history->note }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-md text-gray-500">Chưa có lịch sử duyệt.</div>
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

            {{-- SIDEBAR --}}
            <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">
                <x-card title="Hành động" shadow separator class="p-3!">
                    @if($this->canReviewOriginalPost())
                        @if($this->isReviewerOnly())
                            <x-button label="Chỉ duyệt (Không có quyền sửa)" class="bg-gray-400 text-white w-full my-1" disabled/>
                        @else
                            @if($currentStatus === \App\Models\Post::APPROVAL_PENDING)
                                <x-button label="Lưu thay đổi" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
                            @elseif($currentStatus === 'published')
                                <x-button label="Lưu thay đổi" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
                                @if(!$this->isScheduledPublished())
                                    <x-button label="Xem bài viết" class="bg-info text-white w-full my-1" link="{{$url}}" external="true"/>
                                @endif
                            @else
                                <x-button label="Lưu thay đổi" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
                            @endif
                        @endif
                    @elseif($this->isAuthor())
                        @if($currentStatus === 'published')
                            <x-button label="Chỉ xem (Đã xuất bản)" class="bg-gray-400 text-white w-full my-1" disabled/>
                            @if(!$this->isScheduledPublished())
                                <x-button label="Xem bài viết" class="bg-info text-white w-full my-1" link="{{$url}}" external="true"/>
                            @endif
                        @elseif($currentStatus === \App\Models\Post::APPROVAL_PENDING)
                            <x-button label="Đã gửi duyệt (Đang chờ)" class="bg-gray-400 text-white w-full my-1" disabled/>
                            <x-button :label="$currentStatus === \App\Models\Post::APPROVAL_PENDING ? 'Lưu & Gửi duyệt lại' : 'Gửi duyệt bài viết'" class="bg-success text-white w-full my-1" wire:click="submitForReview" spinner="submitForReview"/>
                            <x-button
                                label="Rút về nháp"
                                class="bg-primary text-white w-full my-1"
                                wire:click="withdrawToDraft"
                                spinner="withdrawToDraft"
                            />
                        @else
                            <x-button label="Lưu" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
                            <x-button :label="$currentStatus === \App\Models\Post::APPROVAL_REJECTED ? 'Lưu & Gửi duyệt lại' : 'Gửi duyệt bài viết'" class="bg-success text-white w-full my-1" wire:click="submitForReview" spinner="submitForReview"/>
                        @endif
                    @else
                        <x-button label="Chỉ xem (Không có quyền sửa)" class="bg-gray-400 text-white w-full my-1" disabled/>
                        @if($currentStatus === 'published')
                            @if(!$this->isScheduledPublished())
                                <x-button label="Xem bài viết" class="bg-info text-white w-full my-1" link="{{$url}}" external="true"/>
                            @endif
                        @endif
                    @endif

                    @if(($currentStatus !== 'published' && $this->isAuthor()) || $this->canReviewOriginalPost())
                        <x-button label="Xem trước" class="bg-warning text-white w-full my-1" wire:click="previewDraft" spinner="previewDraft"/>
                    @endif
                </x-card>

                @if($currentStatus === \App\Models\Post::APPROVAL_PENDING || $currentStatus === \App\Models\Post::APPROVAL_REJECTED || $currentStatus === 'published')
                    <x-card title="Duyệt bài viết" shadow class="p-3!">
                        @php
                            $approvalMap = [
                                \App\Models\Post::APPROVAL_PENDING => ['label' => 'Chờ duyệt', 'class' => 'badge-warning'],
                                \App\Models\Post::APPROVAL_REJECTED => ['label' => 'Bị từ chối', 'class' => 'badge-error'],
                                'published' => ['label' => 'Đã duyệt', 'class' => 'badge-success'],
                            ];
                            $approval = $approvalMap[$currentStatus] ?? null;
                        @endphp
                        @if($approval) <x-badge :value="$approval['label']" class="{{ $approval['class'] }} text-white font-semibold"/> @endif
                        @if($submitted_at) <p class="text-sm text-gray-500 mt-2">Gửi duyệt lúc: {{ $submitted_at }}</p> @endif
                        @if($reviewed_at) <p class="text-sm text-gray-500">Xử lý lúc: {{ $reviewed_at }}</p> @endif
                        @if($currentStatus === \App\Models\Post::APPROVAL_REJECTED && $rejection_reason)
                            <div class="mt-3 text-md bg-red-50 border border-red-200 text-red-700 rounded p-2"><strong>Lý do từ chối:</strong> {{ $rejection_reason }}</div>
                        @endif

                        @if($this->canReviewOriginalPost() && $currentStatus === \App\Models\Post::APPROVAL_PENDING)
                            <div class="mt-3 space-y-2">
                                <x-input label="Lên lịch đăng (tùy chọn)" type="datetime-local" wire:model="published_at" hint="Để trống để đăng ngay khi duyệt"/>
                                <x-textarea wire:model="reviewNote" rows="3" label="Ghi chú duyệt / lý do từ chối" placeholder="Nhập ghi chú cho tác giả..."/>
                                <x-button label="Lưu nội dung & Duyệt bài" class="bg-success text-white w-full" wire:click="approvePost" spinner="approvePost"/>
                                <x-button label="Lưu nội dung & Từ chối bài" class="bg-error text-white w-full" wire:click="rejectPost" spinner="rejectPost"/>
                            </div>
                        @endif
                    </x-card>
                @endif

                <x-card title="Xuất bản" shadow class="p-3!">
                    <x-input label="Đường dẫn" wire:model.live.debounce.1000ms="slug" placeholder="thong-bao-tuyen-sinh-2025" required/>
                    @if($this->canReviewOriginalPost())
                        <x-select label="Trạng thái" wire:model.live="status" :options="$this->statusOptions" option-value="id" option-label="name" class="mt-2"/>
                    @endif

                    @if($this->canToggleFeatured())
                        <x-checkbox class="mt-3" label="Đánh dấu là bài viết nổi bật" wire:model="is_featured"/>
                        @error('is_featured') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror
                    @endif

                    @if($this->canReviewOriginalPost() && $status === 'published')
                        <div class="mt-3">
                            <x-input label="Thời gian đăng" wire:model="published_at" type="datetime-local" hint="Để trống = đăng ngay bây giờ"/>
                        </div>
                    @endif
                </x-card>

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
                            <option wire:key="cat-edit-{{ $category['id'] }}" value="{{ $category['id'] }}" @if($category['disabled'] ?? false) disabled class="text-gray-400 bg-gray-50 italic pointer-events-none @error('category_ids') border-red-500 @enderror" title="Danh mục này được thiết lập bởi cấp trên" @endif>
                                {{ $category['name'] }}
                            </option>
                        @empty
                            <option disabled class="text-gray-800 italic p-0">Không tìm thấy danh mục nào.</option>
                        @endforelse
                    </select>
                    @error('category_ids') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror
                </x-card>

                <x-card title="Ảnh đại diện" shadow class="p-3!">
                    <div x-data="{ previewUrl: null }" x-on:livewire-upload-start="previewUrl = null">
                        @php
                            $defaultImageTemplates = \App\Models\PostDefaultImage::where('is_active', true)->orderBy('order')->get();
                            $selectedTemplate = $defaultImageTemplates->firstWhere('id', $post_default_image_id);
                            $previewTitle = trim($title_vi) !== '' ? $title_vi : (trim($title_en) !== '' ? $title_en : '');
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
                                            wire:click="selectTemplate({{ $template->id }})"
                                            x-on:click="previewUrl = null;
                                                if(document.querySelector('input[type=file]')) {
                                                        document.querySelector('input[type=file]').value = '';
                                                }
                                            "
                                            class="relative overflow-hidden rounded-lg border text-left transition {{ $isSelected ? 'border-primary ring-2 ring-primary/40' : 'border-gray-200 hover:border-primary/60' }}"
                                        >
                                            <img
                                                src="{{ Storage::url($template->image_path) }}"
                                                alt="{{ $template->name }}"
                                                class="h-20 w-full object-cover object-top"
                                            />
    {{--                                        <div class="absolute inset-x-0 bottom-0 bg-black/55 px-2 py-1 text-xs font-medium text-white line-clamp-2">--}}
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

                        <x-file
                            wire:model="thumbnail"
                            label="Tải lên ảnh đại diện"
                            hint="jpg, jpeg, png, webp – tối đa 2MB"
                            accept="image/*"
                            x-on:change="
                                    previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null;
                                    if ($event.target.files[0]) {
                                        $wire.set('post_default_image_id', null);
                                    }
                                "/>
                        <div class="mt-3 space-y-3">
                            <template x-if="previewUrl">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1">Ảnh mới (chưa lưu)</p>
                                    <div class="relative inline-block">
                                        <img src="#" :src="previewUrl" alt="Preview" class="w-full rounded object-cover ring-1 ring-gray-200"/>
                                        <button
                                            type="button"
                                            class="absolute -right-2 -top-2 btn btn-circle btn-xs btn-error text-white shadow-md hover:scale-110 transition-transform"
                                            @click="
                                                previewUrl = null;
                                                document.querySelector('input[type=file]').value = '';
                                                $wire.set('thumbnail', null);
                                            "
                                            title="Xóa ảnh tải lên"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>
                            </template>
                            @if($currentThumbnail)
                                <div x-show="!previewUrl">
                                    <p class="text-sm text-gray-500 mb-1">Ảnh hiện tại</p>
                                    <div class="relative inline-block">
                                        <img src="{{ Storage::url($currentThumbnail) }}" alt="Current thumbnail" class="w-full rounded object-cover ring-1 ring-gray-200"/>

                                        @if(($currentStatus !== 'published' && $this->isAuthor()) || $this->canReviewOriginalPost())
                                            <button
                                                type="button"
                                                class="absolute -right-2 -top-2 btn btn-circle btn-xs btn-error text-white shadow-md hover:scale-110 transition-transform"
                                                wire:click="removeThumbnail"
                                                spinner="removeThumbnail"
                                                title="Xóa ảnh hiện tại"
                                            >
                                                ✕
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-card>

                <x-card title="Ẩn/hiển thị metadata" shadow class="p-3!">
                    <x-checkbox class="mb-2" label="Hiển thị người viết" wire:model="show_author"/>
                    <x-checkbox class="mb-2" label="Hiển thị ngày đăng" wire:model="show_published_at"/>
                    <x-checkbox class="mb-2" label="Hiển thị lượt xem" wire:model="show_views"/>
                    <x-checkbox class="mb-2" label="Hiển thị danh mục" wire:model="show_category"/>
                    <x-checkbox class="mb-2" label="Hiển thị bài viết liên quan" wire:model="show_related_posts"/>
                </x-card>

                <x-card title="Thông tin" shadow class="p-3!">
                    @php $post = App\Models\Post::find($id); @endphp
                    <div class="text-md space-y-2 text-gray-600">
                        <div class="flex justify-between"><span>Lượt xem:</span><span class="font-medium">{{ number_format($post?->views ?? 0) }}</span></div>
                        <div class="flex justify-between"><span>Tác giả:</span><span class="font-medium truncate">{{ $post?->user?->name ?? '—' }}</span></div>
                        <div class="flex justify-between"><span>Ngày tạo:</span><span class="font-medium">{{ $post?->created_at?->format('H:i d/m/Y') }}</span></div>
                        <div class="flex justify-between"><span>Cập nhật:</span><span class="font-medium">{{ $post?->updated_at?->format('H:i d/m/Y') }}</span></div>
                        <div class="flex justify-between"><span>Người cập nhật:</span><span class="font-medium truncate">{{ $post?->updater?->name ?? '—' }}</span></div>
                    </div>
                </x-card>
            </div>
{{--            @once--}}
{{--                <script>--}}
{{--                    document.addEventListener('DOMContentLoaded', function () {--}}
{{--                        let lastMessage = null;--}}
{{--                        let lastToastTime = 0;--}}

{{--                        function getUploadErrorMessage(data) {--}}
{{--                            return data?.errors?.file?.[0]--}}
{{--                                || data?.message--}}
{{--                                || data?.error--}}
{{--                                || 'Đã có lỗi xảy ra khi tải ảnh lên.';--}}
{{--                        }--}}

{{--                        function showUploadError(message) {--}}
{{--                            const now = Date.now();--}}

{{--                            // Tránh hiện lặp toast liên tục cùng một lỗi--}}
{{--                            if (lastMessage === message && now - lastToastTime < 1500) {--}}
{{--                                return;--}}
{{--                            }--}}

{{--                            lastMessage = message;--}}
{{--                            lastToastTime = now;--}}

{{--                            if (window.Livewire) {--}}
{{--                                Livewire.dispatch('editor-upload-error', {--}}
{{--                                    message: message--}}
{{--                                });--}}
{{--                                return;--}}
{{--                            }--}}

{{--                            alert(message);--}}
{{--                        }--}}

{{--                        // Bắt request dùng fetch--}}
{{--                        const originalFetch = window.fetch;--}}

{{--                        window.fetch = async function (...args) {--}}
{{--                            const response = await originalFetch.apply(this, args);--}}

{{--                            const url = typeof args[0] === 'string'--}}
{{--                                ? args[0]--}}
{{--                                : (args[0]?.url || '');--}}

{{--                            if (!response.ok && response.status >= 400 && url.includes('/mary/upload')) {--}}
{{--                                response.clone().json()--}}
{{--                                    .then(data => {--}}
{{--                                        showUploadError(getUploadErrorMessage(data));--}}
{{--                                    })--}}
{{--                                    .catch(() => {--}}
{{--                                        showUploadError('Tải ảnh thất bại. Vui lòng thử lại.');--}}
{{--                                    });--}}
{{--                            }--}}

{{--                            return response;--}}
{{--                        };--}}

{{--                        // Bắt request dùng XMLHttpRequest--}}
{{--                        const originalOpen = XMLHttpRequest.prototype.open;--}}
{{--                        const originalSend = XMLHttpRequest.prototype.send;--}}

{{--                        XMLHttpRequest.prototype.open = function (method, url, ...rest) {--}}
{{--                            this._maryUploadUrl = typeof url === 'string' && url.includes('/mary/upload');--}}
{{--                            return originalOpen.call(this, method, url, ...rest);--}}
{{--                        };--}}

{{--                        XMLHttpRequest.prototype.send = function (...args) {--}}
{{--                            if (this._maryUploadUrl) {--}}
{{--                                this.addEventListener('load', function () {--}}
{{--                                    if (this.status >= 400) {--}}
{{--                                        let data = {};--}}

{{--                                        try {--}}
{{--                                            data = JSON.parse(this.responseText || '{}');--}}
{{--                                        } catch (e) {--}}
{{--                                            data = {};--}}
{{--                                        }--}}

{{--                                        showUploadError(getUploadErrorMessage(data));--}}
{{--                                    }--}}
{{--                                });--}}

{{--                                this.addEventListener('error', function () {--}}
{{--                                    showUploadError('Không thể tải ảnh lên. Vui lòng kiểm tra kết nối mạng.');--}}
{{--                                });--}}
{{--                            }--}}

{{--                            return originalSend.apply(this, args);--}}
{{--                        };--}}
{{--                    });--}}
{{--                </script>--}}
{{--            @endonce--}}
        </div>
    </div>
