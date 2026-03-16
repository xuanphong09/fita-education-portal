<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Mary\Traits\Toast;

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
    public ?int $category_id = null;

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

    public array $statusOptions = [
        ['id' => 'draft',     'name' => 'Nháp'],
        ['id' => 'published', 'name' => 'Đã đăng'],
        ['id' => 'archived',  'name' => 'Lưu trữ'],
    ];

    protected function rules(): array
    {
        return [
            'title_vi'           => 'required|string|max:255',
            'title_en'           => 'nullable|string|max:255',
            'content_vi'         => 'required|string',
            'content_en'         => 'nullable|string',
            'excerpt_vi'         => 'nullable|string|max:500',
            'excerpt_en'         => 'nullable|string|max:500',
            'slug'               => 'required|string|max:255|unique:posts,slug',
            'category_id'        => 'nullable|exists:categories,id',
            'status'             => 'required|in:draft,published,archived',
            'is_featured'        => 'boolean',
            'published_at'       => 'nullable|date',
            'seo_title_vi'       => 'nullable|string|max:60',
            'seo_title_en'       => 'nullable|string|max:60',
            'seo_description_vi' => 'nullable|string|max:160',
            'seo_description_en' => 'nullable|string|max:160',
            'thumbnail'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    protected $messages = [
        'title_vi.required'   => 'Tiêu đề (Tiếng Việt) không được để trống.',
        'content_vi.required' => 'Nội dung (Tiếng Việt) không được để trống.',
        'slug.required'       => 'Đường dẫn không được để trống.',
        'slug.unique'         => 'Đường dẫn đã tồn tại, vui lòng chọn đường dẫn khác.',
        'thumbnail.image'     => 'File tải lên phải là hình ảnh.',
        'thumbnail.mimes'     => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
        'thumbnail.max'       => 'Ảnh không được vượt quá 2MB.',
        'status.required'       => 'Trạng thái không được để trống.',
        'status.in'             => 'Trạng thái không hợp lệ.',
        'published_at.date'     => 'Thời gian đăng phải là định dạng ngày tháng hợp lệ.',
        'seo_title_vi.max'     => 'SEO Tiêu đề (Tiếng Việt) không được vượt quá 60 ký tự.',
        'seo_title_en.max'     => 'SEO Tiêu đề (Tiếng Anh) không được vượt quá 60 ký tự.',
        'seo_description_vi.max' => 'SEO Mô tả (Tiếng Việt) không được vượt quá 160 ký tự.',
        'seo_description_en.max' => 'SEO Mô tả (Tiếng Anh) không được vượt quá 160 ký tự.',
    ];

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
        return Category::where('is_active', true)->orderBy('order')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->getTranslatedName()])
            ->toArray();
    }

    private function previewCacheKey(): string
    {
        return 'post_preview_new_' . auth()->id();
    }

    private function ensureFeaturedLimit(): void
    {
        // Chỉ giới hạn khi bài hiện tại là published và được bật nổi bật.
        if (! $this->is_featured || $this->status !== 'published') {
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

    public function save(): void
    {
        try {
            $this->validate();
            $this->ensureFeaturedLimit();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $this->persistPost();
        $this->success('Tạo bài viết thành công!', redirectTo: route('admin.post.index'));
    }

    public function previewDraft(): void
    {
        // Lưu vào cache, KHÔNG lưu DB, KHÔNG validate bắt buộc
        Cache::put($this->previewCacheKey(), [
            'title'           => ['vi' => $this->title_vi,   'en' => $this->title_en],
            'content'         => ['vi' => $this->content_vi, 'en' => $this->content_en],
            'excerpt'         => ['vi' => $this->excerpt_vi, 'en' => $this->excerpt_en],
            'slug'            => $this->slug,
            'category_id'     => $this->category_id,
            'status'          => $this->status,
            'is_featured'     => $this->is_featured,
            'published_at'    => $this->published_at,
            'seo_title'       => ['vi' => $this->seo_title_vi, 'en' => $this->seo_title_en],
            'seo_description' => ['vi' => $this->seo_description_vi, 'en' => $this->seo_description_en],
            'thumbnail'       => null,
            'user_id'         => auth()->id(),
        ], now()->addMinutes(30));

        $this->dispatch('open-preview', url: route('admin.preview.post.new'));
    }

    public function saveAndPreview(): void
    {
        try {
            $this->validate();
            $this->ensureFeaturedLimit();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $post = $this->persistPost();
        $this->redirect(route('admin.preview.post', ['id' => $post->id, 'draft' => 0]));
    }

    private function persistPost(): Post
    {
        $thumbnailPath = null;
        if ($this->thumbnail) {
            $thumbnailPath = $this->thumbnail->store('posts', 'public');
        }

        return Post::create([
            'title'   => [
                'vi' => $this->title_vi,
                'en' => $this->title_en,
            ],
            'content' => [
                'vi' => $this->content_vi,
                'en' => $this->content_en,
            ],
            'excerpt' => $this->excerpt_vi || $this->excerpt_en
                ? ['vi' => $this->excerpt_vi, 'en' => $this->excerpt_en]
                : null,
            'slug'         => $this->slug,
            'category_id'  => $this->category_id,
            'status'       => $this->status,
            'is_featured'  => $this->is_featured,
            'published_at' => $this->status === 'published'
                                ? ($this->published_at ?? now())
                                : $this->published_at,
            'seo_title' => $this->seo_title_vi || $this->seo_title_en
                ? ['vi' => $this->seo_title_vi, 'en' => $this->seo_title_en]
                : null,
            'seo_description' => $this->seo_description_vi || $this->seo_description_en
                ? ['vi' => $this->seo_description_vi, 'en' => $this->seo_description_en]
                : null,
            'user_id'   => Auth::id(),
            'thumbnail' => $thumbnailPath,
        ]);
    }
};
?>

<div x-data x-on:open-preview.window="window.open($event.detail.url, '_blank')">
    <x-slot:title>Tạo bài viết</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.post.index') }}" class="font-semibold text-slate-700">Danh sách bài viết</a>
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
                                     placeholder="VD: Thông báo tuyển sinh 2025" required
                            />
                            <x-input label="Đường dẫn" wire:model.live.debounce.1000ms="slug"
                                     placeholder="thong-bao-tuyen-sinh-2025"
                                     hint="Tự động sinh từ tiêu đề tiếng Việt. Chỉ gồm chữ thường, số và dấu gạch ngang." required
                            />
                            <x-textarea wire:model="excerpt_vi"
                                        placeholder="Mô tả ngắn" rows="3"
                                        hint="Tối đa 500 ký tự"
                                label="Mô tả ngắn"
                            />
                            <x-editor
                                wire:model.live.debounce.500ms="content_vi"
                                :config="config('tinymce')"
                                class="h-full"
                                label="Nội dung chi tiết"
                                required
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
                                Nội dung bài viết (EN)
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input wire:model.live.debounce.400ms="title_en" label="Tiêu đề (EN)"
                                     placeholder="VD: Admission announcement 2025"
                            />
                            <x-textarea wire:model="excerpt_en"
                                        placeholder="Mô tả ngắn" rows="3"
                                        hint="Tối đa 500 ký tự"
                                        label="Mô tả ngắn (EN)"
                            />
                            <x-editor
                                wire:model.live.debounce.500ms="content_en"
                                :config="config('tinymce')"
                                class="h-full"
                                label="Nội dung chi tiết (EN)"
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
                                SEO (EN)
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
                                            ↖ Lấy từ tiêu đề (EN)
                                        </button>
                                    </div>
                                    <x-input wire:model="seo_title_en" placeholder="Để trống = dùng tiêu đề (EN) bài viết"/>
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
                                            ↖ Lấy từ mô tả ngắn (EN)
                                        </button>
                                    </div>
                                    <x-textarea wire:model="seo_description_en" rows="2"
                                                placeholder="Để trống = dùng mô tả ngắn (EN) bài viết"/>
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
                <x-button label="Xem trước (chưa lưu)" icon="o-eye" class="bg-info text-white w-full my-1"
                          wire:click="previewDraft" spinner="previewDraft"/>
                <x-button label="Lưu & Xem trước" icon="o-document-check" class="bg-success text-white w-full my-1"
                          wire:click="saveAndPreview" spinner="saveAndPreview"/>
                <x-button label="Trở lại" class="bg-warning text-white w-full my-1"
                          link="{{ route('admin.post.index') }}"/>
            </x-card>

            {{-- Danh mục --}}
            <x-card title="Danh mục" shadow class="p-3!">
                <x-select label="Danh mục" wire:model="category_id"
                          :options="$this->categoryOptions"
                          placeholder="(Chưa chọn danh mục)"
                          placeholder-value=""
                          option-value="id" option-label="name"/>
            </x-card>

            {{-- Trạng thái & Thời gian đăng --}}
            <x-card title="Xuất bản" shadow class="p-3!">
                <x-select label="Trạng thái" wire:model.live="status"
                          :options="$statusOptions"
                          option-value="id" option-label="name"/>

                <x-checkbox
                    class="mt-3"
                    label="Đánh dấu là bài viết nổi bật"
                    wire:model="is_featured"
                />
                @error('is_featured') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror

                @if($status === 'published')
                <div class="mt-3">
                    <x-input label="Thời gian đăng" wire:model="published_at"
                             type="datetime-local"
                             hint="Để trống = đăng ngay bây giờ"/>
                </div>
                @endif
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
        </div>
    </div>
</div>
