<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Category;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithFileUploads;

    public int $id;

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
    public ?string $currentThumbnail = null;

    protected function rules(): array
    {
        return [
            'name_vi'        => 'required|string|max:255|unique:categories,name->vi,' . $this->id,
            'name_en'        => 'nullable|string|max:255|unique:categories,name->en,' . $this->id,
            'description_vi' => 'nullable|string',
            'description_en' => 'nullable|string',
            'slug'           => 'required|string|max:255|unique:categories,slug,' . $this->id,
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

    public function mount(int $id): void
    {
        $this->id = $id;
        $category = Category::findOrFail($id);

        $this->name_vi          = $category->getTranslation('name', 'vi', false) ?? '';
        $this->name_en          = $category->getTranslation('name', 'en', false) ?? '';
        $this->description_vi   = $category->getTranslation('description', 'vi', false) ?? '';
        $this->description_en   = $category->getTranslation('description', 'en', false) ?? '';
        $this->slug             = $category->slug ?? '';
        $this->parent_id        = $category->parent_id;
        $this->order            = $category->order ?? 0;
        $this->is_active        = (bool) $category->is_active;
        $this->currentThumbnail = $category->thumbnail;
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
        $excludeIds   = $this->getDescendantIds($this->id);
        $excludeIds[] = $this->id;

        $result = [];
        $roots  = Category::whereNull('parent_id')
            ->whereNotIn('id', $excludeIds)
            ->with('childrenRecursive')
            ->orderBy('order')
            ->get();

        $this->flattenCategories($roots, $result, 0, $excludeIds);

        return $result;
    }

    private function getDescendantIds(int $id): array
    {
        $ids      = [];
        $children = Category::where('parent_id', $id)->get();
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids   = array_merge($ids, $this->getDescendantIds($child->id));
        }
        return $ids;
    }

    private function flattenCategories($categories, array &$result, int $depth, array $excludeIds = []): void
    {
        foreach ($categories as $category) {
            if (in_array($category->id, $excludeIds)) continue;
            $prefix   = str_repeat('— ', $depth);
            $result[] = ['id' => $category->id, 'name' => $prefix . $category->getTranslatedName()];
            if ($category->childrenRecursive->isNotEmpty()) {
                $this->flattenCategories($category->childrenRecursive, $result, $depth + 1, $excludeIds);
            }
        }
    }

    public function removeThumbnail(): void
    {
        if ($this->currentThumbnail) {
            Storage::disk('public')->delete($this->currentThumbnail);
        }
        $this->currentThumbnail = null;
        $this->thumbnail        = null;
        Category::where('id', $this->id)->update(['thumbnail' => null]);
        $this->success('Đã xóa ảnh thumbnail.');
    }

    public function save(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $category      = Category::findOrFail($this->id);
        $thumbnailPath = $this->currentThumbnail;

        if ($this->thumbnail) {
            if ($thumbnailPath) {
                Storage::disk('public')->delete($thumbnailPath);
            }
            $thumbnailPath = $this->thumbnail->store('uploads/categories', 'public');
        }

        $category->update([
            'name'        => ['vi' => $this->name_vi, 'en' => $this->name_en ?: ""],
            'description' => ['vi' => $this->description_vi, 'en' => $this->description_en ?: ""],
            'slug'        => $this->slug,
            'parent_id'   => $this->parent_id,
            'order'       => $this->order,
            'is_active'   => $this->is_active,
            'thumbnail'   => $thumbnailPath,
        ]);

        $this->success('Cập nhật danh mục thành công!');
    }
};
?>

<div>
    {{-- Title --}}
    <x-slot:title>Chỉnh sửa danh mục</x-slot:title>

    {{-- Breadcrumb --}}
    <x-slot:breadcrumb>
        <a href="{{ route('admin.category.index') }}" class="font-semibold text-slate-700">Danh sách danh mục</a>
        <span class="mx-1">/</span>
        <span>Chỉnh sửa danh mục</span>
    </x-slot:breadcrumb>

    {{-- Header --}}
    <x-header title="Chỉnh sửa danh mục"
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
                    hint="Chỉ gồm chữ thường, số và dấu gạch ngang."
                    required
                />
            </x-card>

            {{-- Ảnh đại diện --}}
            <x-card title="Ảnh đại diện" shadow class="p-3!">
                <div
                    x-data="{ previewUrl: null }"
                    x-on:livewire-upload-start="previewUrl = null"
                >
                    {{-- Ảnh hiện tại --}}
                    @if ($currentThumbnail)
                        <div class="mb-3" x-show="!previewUrl">
                            <img src="{{ Storage::url($currentThumbnail) }}" alt="Thumbnail hiện tại"
                                 class="size-32 rounded object-cover ring-1 ring-gray-200"/>
                            <x-button
                                label="Xóa ảnh"
                                icon="o-trash"
                                class="btn-md btn-ghost text-error mt-2"
                                wire:click="removeThumbnail"
                                spinner="removeThumbnail"
                                confirm="Bạn có chắc muốn xóa ảnh này không?"
                            />
                        </div>
                    @endif

                    <x-file
                        wire:model="thumbnail"
                        label="{{ $currentThumbnail ? 'Thay ảnh mới' : 'Ảnh thumbnail' }}"
                        hint="jpg, jpeg, png, webp – tối đa 2MB"
                        accept="image/*"
                        x-on:change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                    />

                    {{-- Preview ảnh mới --}}
                    <div class="mt-3">
                        <template x-if="previewUrl">
                            <img src="#" :src="previewUrl" alt="Preview thumbnail mới"
                                 class="size-32 rounded object-cover ring-1 ring-gray-200"/>
                        </template>
                    </div>
                </div>
            </x-card>

        </div>

        {{-- ======================== Sidebar ======================== --}}
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">

            {{-- Hành động --}}
            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button label="Lưu thay đổi" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
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

        </div>
    </div>
</div>

