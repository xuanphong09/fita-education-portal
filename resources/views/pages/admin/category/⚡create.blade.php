<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Category;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithFileUploads;

    // Song ngữ vi/en
    public string $name_vi = '';
    public string $name_en = '';
    public string $description_vi = '';
    public string $description_en = '';

    // Slug
    public string $slug = '';

    // Quan hệ cha
    public ?int $parent_id = null;

    // Thứ tự
    public int $order = 0;

    // Trạng thái
    public bool $is_active = true;

    // Thumbnail
    public $thumbnail;

    protected function rules(): array
    {
        return [
            'name_vi'        => 'required|string|max:255|unique:categories,name->vi',
            'name_en'        => 'nullable|string|max:255|unique:categories,name->en',
            'description_vi' => 'nullable|string',
            'description_en' => 'nullable|string',
            'slug'           => 'required|string|max:255|unique:categories,slug',
            'parent_id'      => 'nullable|exists:categories,id',
            'order'          => 'nullable|integer|min:0',
            'thumbnail'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    protected $messages = [
        'name_vi.required' => 'Tên danh mục (Tiếng Việt) không được để trống.',
        'name_vi.max'      => 'Tên danh mục không được vượt quá 255 ký tự.',
        'name_vi.unique'   => 'Tên danh mục (Tiếng Việt) đã tồn tại, vui lòng chọn tên khác.',
        'name_en.max'      => 'Tên danh mục không được vượt quá 255 ký tự.',
        'name_en.unique'   => 'Tên danh mục (English) đã tồn tại, vui lòng chọn tên khác.',
        'slug.required'    => 'Đường dẫn không được để trống.',
        'slug.unique'      => 'Đường dẫn đã tồn tại, vui lòng chọn đường dẫn khác.',
        'parent_id.exists' => 'Danh mục cha không hợp lệ.',
        'thumbnail.image'  => 'File tải lên phải là hình ảnh.',
        'thumbnail.mimes'  => 'Ảnh chỉ chấp nhận định dạng jpg, jpeg, png, webp.',
        'thumbnail.max'    => 'Ảnh không được vượt quá 2MB.',
        'order.integer'    => 'Thứ tự phải là một số nguyên.',
        'order.min'        => 'Thứ tự phải là một số lớn hơn hoặc bằng 0.',
    ];

    public function updatedNameVi($value): void
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

    public function getParentOptionsProperty(): array
    {
        $result = [];
        $roots = Category::whereNull('parent_id')
            ->with('childrenRecursive')
            ->orderBy('order')
            ->get();

        $this->flattenCategories($roots, $result, 0);

        return $result;
    }

    private function flattenCategories($categories, array &$result, int $depth): void
    {
        foreach ($categories as $category) {
            $prefix = str_repeat('— ', $depth);
            $result[] = [
                'id'   => $category->id,
                'name' => $prefix . $category->getTranslatedName(),
            ];
            if ($category->childrenRecursive->isNotEmpty()) {
                $this->flattenCategories($category->childrenRecursive, $result, $depth + 1);
            }
        }
    }

    public function save(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $thumbnailPath = null;
        if ($this->thumbnail) {
            $thumbnailPath = $this->thumbnail->store('uploads/categories', 'public');
        }

        Category::create([
            'name'        => ['vi' => $this->name_vi, 'en' => $this->name_en ?: ""],
            'description' => ['vi' => $this->description_vi, 'en' => $this->description_en ?: ""],
            'slug'        => $this->slug,
            'parent_id'   => $this->parent_id,
            'order'       => $this->order,
            'is_active'   => $this->is_active,
            'thumbnail'   => $thumbnailPath,
        ]);

        $this->success('Tạo danh mục thành công!', redirectTo: route('admin.category.index'));
    }
};
?>

<div>
    {{-- Title --}}
    <x-slot:title>Tạo danh mục bài viết</x-slot:title>

    {{-- Breadcrumb --}}
    <x-slot:breadcrumb>
        <a href="{{ route('admin.category.index') }}" class="font-semibold text-slate-700">Danh sách danh mục</a>
        <span class="mx-1">/</span>
        <span>Tạo danh mục mới</span>
    </x-slot:breadcrumb>

    {{-- Header --}}
    <x-header title="Tạo danh mục bài viết"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"/>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        {{-- ======================== Main form ======================== --}}
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">

            {{-- Tên song ngữ --}}
            <x-card title="Tên danh mục" shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Tên (Tiếng Việt)"
                        wire:model.live.debounce.400ms="name_vi"
                        placeholder="VD: Tin tức khoa"
                        required
                    />
                    <x-input
                        label="Tên (Tiếng Anh)"
                        wire:model.live.debounce.400ms="name_en"
                        placeholder="VD: Faculty News"
                    />
                </div>
            </x-card>

            {{-- Mô tả song ngữ --}}
            <x-card title="Mô tả" shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-textarea
                        label="Mô tả (Tiếng Việt)"
                        wire:model="description_vi"
                        placeholder="Mô tả ngắn về danh mục..."
                        rows="4"

                    />
                    <x-textarea
                        label="Mô tả (Tiếng Anh)"
                        wire:model="description_en"
                        placeholder="Short category description..."
                        rows="4"
                    />
                </div>
            </x-card>

            {{-- Slug --}}
            <x-card title="Đường dẫn (Slug)" shadow class="p-3!">
                <x-input
                    label="Đường dẫn"
                    wire:model.live.debounce.1000ms="slug"
                    placeholder="tin-tuc-khoa"
                    hint="Tự động sinh từ tên tiếng Việt. Chỉ gồm chữ thường, số và dấu gạch ngang."
                    required
                />
            </x-card>
            <x-card title="Ảnh đại diện" shadow class="p-3!">
                <div
                    x-data="{ previewUrl: null }"
                    x-on:livewire-upload-start="previewUrl = null"
                >
                    <x-file
                        wire:model="thumbnail"
                        label="Ảnh thumbnail"
                        hint="jpg, jpeg, png, webp – tối đa 2MB"
                        accept="image/*"
                        x-on:change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                    />

                    {{-- Preview ảnh bằng Alpine, không bị wire:ignore chặn --}}
                    <div class="mt-3">
                        <template x-if="previewUrl">
                            <img src="#" :src="previewUrl" alt="Preview thumbnail"
                                 class="size-32 rounded object-cover max-h-48 ring-1 ring-gray-200"/>
                        </template>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- ======================== Sidebar ======================== --}}
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">

            {{-- Hành động --}}
            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button label="Lưu danh mục" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
            </x-card>

            {{-- Danh mục cha --}}
            <x-card title="Phân cấp" shadow class="p-3!">
                <x-select
                    label="Danh mục cha"
                    wire:model="parent_id"
                    :options="$this->parentOptions"
                    placeholder="(Không có – là danh mục gốc)"
                    placeholder-value=""
                    option-value="id"
                    option-label="name"
                />
            </x-card>

            {{-- Thứ tự & Trạng thái --}}
            <x-card title="Cài đặt" shadow class="p-3!">
                <x-input
                    label="Thứ tự hiển thị"
                    wire:model="order"
                    type="number"
                    min="0"
                    hint="Số nhỏ hơn sẽ hiển thị trước."
                />
                <div class="mt-4">
                    <x-toggle
                        label="Kích hoạt"
                        wire:model="is_active"
                        class="toggle-primary"
                    />
                </div>
            </x-card>

            {{-- Thumbnail --}}


        </div>
    </div>
</div>

