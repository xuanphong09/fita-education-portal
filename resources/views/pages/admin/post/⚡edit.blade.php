<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mary\Traits\Toast;

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

    // Slug
    public string $slug = '';

    // Quan hệ
    public ?int $category_id = null;

    // Trạng thái
    public string $status       = 'draft';
    public ?string $published_at = null;

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
            'slug'               => 'required|string|max:255|unique:posts,slug,' . $this->id,
            'category_id'        => 'nullable|exists:categories,id',
            'status'             => 'required|in:draft,published,archived',
            'is_featured'        => 'boolean',
            'published_at'       => 'nullable|date',
            'seo_title_vi'       => 'nullable|string|max:255',
            'seo_title_en'       => 'nullable|string|max:255',
            'seo_description_vi' => 'nullable|string|max:500',
            'seo_description_en' => 'nullable|string|max:500',
            'thumbnail'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    protected $messages = [
        'title_vi.required'   => 'Tiêu đề (Tiếng Việt) không được để trống.',
        'content_vi.required' => 'Nội dung (Tiếng Việt) không được để trống.',
        'slug.required'       => 'Slug không được để trống.',
        'slug.unique'         => 'Slug đã tồn tại, vui lòng chọn slug khác.',
        'thumbnail.image'     => 'File tải lên phải là hình ảnh.',
        'thumbnail.mimes'     => 'Ảnh chỉ chấp nhận jpg, jpeg, png, webp.',
        'thumbnail.max'       => 'Ảnh không được vượt quá 2MB.',
    ];

    public function mount(int $id): void
    {
        $this->id   = $id;
        $post       = Post::findOrFail($id);

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
        $this->category_id        = $post->category_id;
        $this->status             = $post->status;
        $this->is_featured        = (bool) $post->is_featured;
        $this->published_at       = $post->published_at?->format('Y-m-d\\TH:i');
        $this->currentThumbnail   = $post->thumbnail;
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
        return Category::where('is_active', true)->orderBy('order')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->getTranslatedName()])
            ->toArray();
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
            'category_id'     => $this->category_id,
            'status'          => $this->status,
            'is_featured'     => $this->is_featured,
            'published_at'    => $this->published_at,
            'seo_title'       => ['vi' => $this->seo_title_vi, 'en' => $this->seo_title_en],
            'seo_description' => ['vi' => $this->seo_description_vi, 'en' => $this->seo_description_en],
            'thumbnail'       => $this->currentThumbnail,
            'user_id'         => auth()->id(),
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

    public function save(): void
    {
        try {
            $this->validate();
            $post = Post::findOrFail($this->id);
            $this->ensureFeaturedLimitForUpdate($post);
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $this->dispatch('modal:confirm', [
            'title'             => 'Bạn có chắc muốn lưu thay đổi không?',
            'icon'              => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText'  => 'Hủy',
            'method'            => 'confirmSave',
        ]);
    }

    #[On('confirmSave')]
    public function confirmSave(): void
    {
        $post          = Post::findOrFail($this->id);
        $thumbnailPath = $this->currentThumbnail;

        try {
            $this->ensureFeaturedLimitForUpdate($post);
        } catch (ValidationException $e) {
            $this->error('Không thể cập nhật bài nổi bật.');
            throw $e;
        }

        if ($this->thumbnail) {
            if ($thumbnailPath) Storage::disk('public')->delete($thumbnailPath);
            $thumbnailPath = $this->thumbnail->store('posts', 'public');
        }

        $post->update([
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
            'thumbnail' => $thumbnailPath,
        ]);

        $this->success('Cập nhật bài viết thành công!', redirectTo: route('admin.post.index'));
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
                            <x-input wire:model.live.debounce.400ms="title_en" label="Title"
                                     placeholder="Ex: Admission announcement 2025"
                            />
                            <x-textarea wire:model="excerpt_en"
                                        placeholder="Short description" rows="3"
                                        hint="Max 500 characters"
                                        label="Short description"
                            />
                            <x-editor
                                wire:model.live.debounce.500ms="content_en"
                                :config="config('tinymce')"
                                class="h-full"
                                label="Content details"
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
                                <p><strong>SEO Title</strong> hiển thị trên tab trình duyệt và kết quả Google (khác title bài viết).</p>
                                <p><strong>SEO Description</strong> là mô tả dưới tiêu đề trên Google (khác short description).</p>
                            </div>
                            <div class="flex items-center justify-end mb-3">
                                <button type="button"
                                        wire:click="fillSeoEn"
                                        class="text-xs text-primary hover:underline">
                                    Điền nhanh SEO EN
                                </button>
                            </div>
                            <div class="flex flex-col gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Title</span>
                                        <button type="button"
                                                wire:click="$set('seo_title_en', $wire.title_en)"
                                                class="text-xs text-primary hover:underline">
                                            ↖ Lấy từ title EN
                                        </button>
                                    </div>
                                    <x-input wire:model="seo_title_en" placeholder="Để trống = dùng title bài viết"/>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ mb_strlen($seo_title_en) }}/60 ký tự
                                        @if(mb_strlen($seo_title_en) > 60) <span class="text-warning">— nên dưới 60</span> @endif
                                    </p>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="fieldset-legend">SEO Description</span>
                                        <button type="button"
                                                wire:click="$set('seo_description_en', $wire.excerpt_en)"
                                                class="text-xs text-primary hover:underline">
                                            ↖ Lấy từ short description EN
                                        </button>
                                    </div>
                                    <x-textarea wire:model="seo_description_en" rows="2"
                                                placeholder="Để trống = dùng short description bài viết"/>
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

        </div>

        {{-- ===================== SIDEBAR ===================== --}}
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">

            {{-- Hành động --}}
            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button label="Lưu thay đổi" class="bg-primary text-white w-full my-1"
                          wire:click="save" spinner="save"/>
                <x-button label="Xem trước" icon="o-eye" class="bg-info text-white w-full my-1"
                          wire:click="previewDraft" spinner="previewDraft"/>
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

            {{-- Xuất bản --}}
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
{{--            ảnh đại diện--}}
            <x-card title="Ảnh đại diện" shadow class="p-3!">
                <div x-data="{ previewUrl: null }" x-on:livewire-upload-start="previewUrl = null">
                    <x-file wire:model="thumbnail" label="Ảnh thumbnail"
                            hint="jpg, jpeg, png, webp – tối đa 2MB" accept="image/*"
                            x-on:change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"/>
                    <div class="mt-3 space-y-3">
                        <template x-if="previewUrl">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Ảnh mới (chưa lưu)</p>
                                <img src="#" :src="previewUrl" alt="Preview"
                                     class="size-40 rounded object-cover ring-1 ring-gray-200"/>
                            </div>
                        </template>

                        @if($currentThumbnail)
                            <div x-show="!previewUrl">
                                <p class="text-xs text-gray-500 mb-1">Ảnh hiện tại</p>
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

            {{-- Thông tin --}}
            <x-card title="Thông tin" shadow class="p-3!">
                @php $post = App\Models\Post::find($id); @endphp
                <div class="text-sm space-y-2 text-gray-600">
                    <div class="flex justify-between">
                        <span>Lượt xem:</span>
                        <span class="font-medium">{{ number_format($post?->views ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tác giả:</span>
                        <span class="font-medium truncate max-w-24">{{ $post?->user?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Ngày tạo:</span>
                        <span class="font-medium">{{ $post?->created_at?->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Cập nhật:</span>
                        <span class="font-medium">{{ $post?->updated_at?->format('d/m/Y') }}</span>
                    </div>
                </div>
            </x-card>

        </div>
    </div>
</div>

