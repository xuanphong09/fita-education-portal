<?php

use App\Models\PostDefaultImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination, WithFileUploads, Toast;

    #[Url(as: 'search')]
    public string $search = '';

    public array $sortBy = ['column' => 'order', 'direction' => 'asc'];
    public int $perPage = 10;

    public bool $showCreate = false;
    public bool $showEdit = false;
    public ?PostDefaultImage $editingTemplate = null;

    public string $name = '';
    public string $text_color = '#ffffff';
    public bool $show_title = false;
    public int $text_size = 48;
    public string $text_alignment = 'center';
    public int $text_y_offset = 0;
    public int $order = 0;
    public bool $is_active = true;

    public $image;
    public ?string $oldImagePath = null;
    public int $imageInputKey = 0;

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'image', 'label' => 'Ảnh', 'sortable' => false, 'class' => 'w-60'],
            ['key' => 'name', 'label' => 'Tên template'],
            ['key' => 'text_style', 'label' => 'Kiểu chữ', 'sortable' => false, 'class' => 'w-44'],
//            ['key' => 'posts_count', 'label' => 'Đang dùng', 'class' => 'w-24'],
//            ['key' => 'order', 'label' => 'Thứ tự', 'class' => 'w-20'],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'sortable' => false, 'class' => 'w-24'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-28'],
        ];
    }

    public function getAlignmentOptionsProperty(): array
    {
        return [
            ['id' => 'left', 'name' => 'Trái'],
            ['id' => 'center', 'name' => 'Giữa'],
            ['id' => 'right', 'name' => 'Phải'],
        ];
    }

    public function getTemplatesProperty()
    {
        $query = PostDefaultImage::query()->withCount('posts');

        if (trim($this->search) !== '') {
            $keyword = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', $keyword)
                    ->orWhere('text_alignment', 'like', $keyword);
            });
        }

        $query->orderBy('is_active', 'desc');
        $query->orderBy(...array_values($this->sortBy));

        return $query->paginate($this->perPage);
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'show_title' => 'boolean',
            'text_color' => ['required_if:show_title,true', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'text_size' => 'required_if:show_title,true|integer|min:10|max:100',
            'text_alignment' => 'required_if:show_title,true|in:left,center,right',
            'text_y_offset' => 'required_if:show_title,true|integer|min:-300|max:300',
            'order' => 'required|integer|min:0',
            'image' => $this->editingTemplate ? 'nullable|image|max:5120' : 'required|image|max:5120',
        ];
    }

    protected array $messages = [
        'name.required' => 'Tên template là bắt buộc.',
        'name.max' => 'Tên template không được vượt quá 255 ký tự.',
        'text_color.required_if' => 'Màu chữ là bắt buộc.',
        'text_color.regex' => 'Màu chữ phải đúng định dạng HEX, ví dụ #ffffff.',
        'text_size.required_if' => 'Cỡ chữ là bắt buộc.',
        'text_size.integer' => 'Cỡ chữ phải là số nguyên.',
        'text_size.min' => 'Cỡ chữ tối thiểu 10.',
        'text_size.max' => 'Cỡ chữ tối đa 100.',
        'text_alignment.required_if' => 'Căn lề text là bắt buộc.',
        'text_alignment.in' => 'Căn lề text không hợp lệ.',
        'text_y_offset.required_if' => 'Độ lệch Y là bắt buộc.',
        'text_y_offset.integer' => 'Độ lệch Y phải là số nguyên.',
        'text_y_offset.min' => 'Độ lệch Y tối thiểu -300.',
        'text_y_offset.max' => 'Độ lệch Y tối đa 300.',
        'order.required' => 'Thứ tự là bắt buộc.',
        'order.integer' => 'Thứ tự phải là số nguyên.',
        'order.min' => 'Thứ tự phải lớn hơn hoặc bằng 0.',
        'image.required' => 'Ảnh template là bắt buộc.',
        'image.image' => 'Tập tin phải là hình ảnh.',
        'image.max' => 'Kích thước ảnh tối đa 5MB.',
    ];

    public function resetForm(): void
    {
        $this->reset([
            'name',
            'text_color',
            'show_title',
            'text_size',
            'text_alignment',
            'text_y_offset',
            'order',
            'is_active',
            'image',
            'oldImagePath',
            'editingTemplate',
        ]);

        $this->text_color = '#ffffff';
        $this->show_title = false;
        $this->text_size = 18;
        $this->text_alignment = 'center';
        $this->text_y_offset = 0;
        $this->order = 0;
        $this->is_active = true;
        $this->imageInputKey++;
        $this->resetErrorBag();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->order = (PostDefaultImage::max('order') ?? 0) + 1;
        $this->showCreate = true;
        $this->showEdit = false;
    }

    public function openEdit(PostDefaultImage $template): void
    {
        $this->resetForm();

        $this->editingTemplate = $template;
        $this->name = $template->name;
        $this->text_color = $template->text_color;
        $this->show_title = (bool) $template->show_title;
        $this->text_size = (int) $template->text_size;
        $this->text_alignment = $template->text_alignment;
        $this->text_y_offset = (int) $template->text_y_offset;
        $this->order = (int) $template->order;
        $this->is_active = (bool) $template->is_active;
        $this->oldImagePath = $template->image_path;

        $this->showEdit = true;
        $this->showCreate = false;
    }

    public function store(): void
    {
        $this->validate();
        if (!$this->image) {
            $this->error('Có lỗi xảy ra khi tải ảnh lên. Vui lòng thử lại.');
            return;
        }
        $imagePath = $this->image->store('uploads/post-default-images', 'public');

        PostDefaultImage::create([
            'name' => trim($this->name),
            'image_path' => $imagePath,
            'show_title' => $this->show_title,
            'text_color' => $this->text_color,
            'text_size' => $this->text_size,
            'text_alignment' => $this->text_alignment,
            'text_y_offset' => $this->text_y_offset,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->showCreate = false;
        $this->success('Đã tạo ảnh mặc định thành công.');
    }

    public function update(): void
    {
        if (!$this->editingTemplate) {
            return;
        }

        $this->validate();

        $imagePath = $this->oldImagePath;
        if ($this->image) {
            if ($this->oldImagePath && Storage::disk('public')->exists($this->oldImagePath)) {
                Storage::disk('public')->delete($this->oldImagePath);
            }
            $imagePath = $this->image->store('uploads/post-default-images', 'public');
        }

        $this->editingTemplate->update([
            'name' => trim($this->name),
            'image_path' => $imagePath,
            'show_title' => $this->show_title,
            'text_color' => $this->text_color,
            'text_size' => $this->text_size,
            'text_alignment' => $this->text_alignment,
            'text_y_offset' => $this->text_y_offset,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->showEdit = false;
        $this->success('Đã cập nhật ảnh thành công.');
    }

    public function delete(int $id): void
    {
        $template = PostDefaultImage::withCount('posts')->findOrFail($id);

        // Chặn xóa nếu đang có bài viết sử dụng
        if ($template->posts_count > 0) {
            $this->error("Không thể xóa! Ảnh này đang được sử dụng cho {$template->posts_count} bài viết.");
            return;
        }
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa ảnh này?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete(int $id): void
    {
        $template = PostDefaultImage::findOrFail($id);

        if ($template->image_path && Storage::disk('public')->exists($template->image_path)) {
            Storage::disk('public')->delete($template->image_path);
        }

        $template->delete();
        $this->success('Đã xóa ảnh thành công.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <x-slot:title>Quản lý ảnh mặc định bài viết</x-slot:title>

    <x-slot:breadcrumb>
        Quản lý ảnh mặc định bài viết
    </x-slot:breadcrumb>

    <x-header title="Danh sách ảnh mặc định bài viết" class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo tên template..."
                wire:model.live.debounce.300ms="search"
                clearable="true"
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" class="btn-primary text-white" label="Thêm mới" wire:click="openCreate" spinner="openCreate"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->templates"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[5, 10, 20, 50]"
            per-page="perPage"
            with-pagination
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
            "
        >
            @scope('cell_id', $template)
            {{ ($this->templates->currentPage() - 1) * $this->templates->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_image', $template)
            @if($template->image_path && Storage::disk('public')->exists($template->image_path))
                <img src="{{ Storage::url($template->image_path) }}" alt="{{ $template->name }}" class="h-18 rounded object-cover ring-1 ring-gray-200"/>
            @else
                <div class="w-12 h-12 rounded bg-gray-100 flex items-center justify-center ring-1 ring-gray-200">
                    <x-icon name="o-photo" class="w-5 h-5 text-gray-400"/>
                </div>
            @endif
            @endscope

            @scope('cell_name', $template)
                <div class="font-medium line-clamp-1">{{ $template->name }}</div>
            @endscope

            @scope('cell_text_style', $template)
            <div class="mt-1">
            @if($template->show_title)
                <div class="text-md">{{ strtoupper((string) $template->text_color) }}</div>
                <div class="text-sm text-gray-500">{{ $template->text_alignment }} | {{ $template->text_size }}px | Y: {{ $template->text_y_offset }}</div>
                @else
                    —
                @endif
            </div>
            @endscope

{{--            @scope('cell_posts_count', $template)--}}
{{--                <x-badge :value="(string) $template->posts_count" class="badge-ghost"/>--}}
{{--            @endscope--}}

            @scope('cell_is_active', $template)
            @if($template->is_active)
                <x-badge value="Hoạt động" class="badge-success whitespace-nowrap text-white font-medium"/>
            @else
                <x-badge value="Tắt" class="badge-warning whitespace-nowrap text-white font-medium"/>
            @endif
            @endscope

            @scope('cell_actions', $template)
            <div class="flex gap-2">
                <x-button icon="o-pencil" class="btn-xs btn-ghost text-primary" wire:click="openEdit({{ $template->id }})" spinner="openEdit({{ $template->id }})"/>
                <x-button icon="o-trash" class="btn-xs btn-ghost text-error" wire:click="delete({{ $template->id }})" spinner="delete({{ $template->id }})"/>
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-photo" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có template ảnh nào.</p>
                </div>
            </x-slot:empty>
        </x-table>

        <div wire:loading.flex
             wire:target="search, sortBy, perPage"
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>

    <x-modal wire:model="showCreate" title="Thêm ảnh mặc định" separator class="modalAddBanner">
        <div class="space-y-0 py-0 max-h-[70vh] overflow-y-auto pr-1">
            <div class="space-y-2">
                <label class="font-medium text-sm">Ảnh template</label>
                <input wire:key="create-template-image-{{ $imageInputKey }}" type="file" wire:model="image" accept="image/png, image/jpeg, image/webp" class="file-input file-input-bordered w-full">
                @error('image') <span class="text-error text-sm mt-1 block">{{ $message }}</span> @enderror
                <div class="relative min-h-32 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center">
                    <div wire:loading.flex wire:target="image" class="absolute inset-0 z-10 items-center justify-center gap-2 bg-white/80">
                        <x-loading class="loading-spinner text-primary"/>
                        <span class="text-sm text-gray-600">Đang tải ảnh...</span>
                    </div>
                    <div wire:loading.remove wire:target="image" class="h-32 flex items-center justify-center">
                        @if($image)
                            <img src="{{ $image->temporaryUrl() }}" class="h-full object-cover" alt="Ảnh xem trước"/>
                        @else
                            <span class="text-sm text-gray-500">Chưa chọn ảnh</span>
                        @endif
                    </div>
                </div>
            </div>

            <x-input label="Tên template" wire:model="name" placeholder="VD: Blue Professional"/>
            <div class="grid grid-cols-2 gap-x-3 mt-2">
                <x-checkbox label="Hoạt động" wire:model="is_active" class="checkbox-primary"/>
                <x-checkbox label="Hiển thị tiêu đề" wire:model.live="show_title" class="checkbox-primary"/>
            </div>
            @if($show_title)
                <div class="grid grid-cols-2 gap-x-3">
                    <x-input type="color" label="Màu text (HEX)" wire:model="text_color" />
                    <x-input label="Cỡ text" wire:model.number="text_size" type="number" min="10" max="100"/>
                    <x-select label="Căn lề text" wire:model="text_alignment" :options="$this->alignmentOptions" option-value="id" option-label="name"/>
                    <x-input label="Độ lệch Y" wire:model.number="text_y_offset" type="number" min="-300" max="300"/>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showCreate = false"/>
            <x-button label="Lưu" wire:click="store" class="btn-primary" spinner="store"/>
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showEdit" title="Sửa ảnh mặc định" separator class="modalAddBanner">
        <div class="space-y-0 py-0 max-h-[70vh] overflow-y-auto pr-1">
            <div class="space-y-2">
                <label class="font-medium text-sm">Ảnh template</label>
                <input wire:key="edit-template-image-{{ $imageInputKey }}" type="file" wire:model="image" accept="image/png, image/jpeg, image/webp" class="file-input file-input-bordered w-full">
                @error('image') <span class="text-error text-sm mt-1 block">{{ $message }}</span> @enderror
                <div class="relative min-h-32 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden">
                    <div wire:loading.flex wire:target="image" class="absolute inset-0 z-10 items-center justify-center gap-2 bg-white/80">
                        <x-loading class="loading-spinner text-primary"/>
                        <span class="text-sm text-gray-600">Đang tải ảnh...</span>
                    </div>
                    <div wire:loading.remove wire:target="image" class="h-32 flex items-center justify-center">
                        @if($image)
                            <img src="{{ $image->temporaryUrl() }}" class="h-full object-cover" alt="Ảnh xem trước"/>
                        @elseif($oldImagePath && Storage::disk('public')->exists($oldImagePath))
                            <img src="{{ Storage::url($oldImagePath) }}" class="h-full object-cover" alt="Ảnh hiện tại"/>
                        @else
                            <span class="text-sm text-gray-500">Chưa chọn ảnh</span>
                        @endif
                    </div>
                </div>
            </div>

            <x-input label="Tên template" wire:model="name"/>
            <div class="grid grid-cols-2 gap-x-3 mt-2">
                <x-checkbox label="Hoạt động" wire:model="is_active" class="checkbox-primary"/>
                <x-checkbox label="Hiển thị tiêu đề" wire:model.live="show_title" class="checkbox-primary"/>
            </div>

            @if($show_title)
                <div class="grid grid-cols-2 gap-x-3">
                    <x-input type="color" label="Màu text (HEX)" wire:model="text_color" />
                    <x-input label="Cỡ text" wire:model.number="text_size" type="number" min="10" max="100"/>
                    <x-select label="Căn lề text" wire:model="text_alignment" :options="$this->alignmentOptions" option-value="id" option-label="name"/>
                    <x-input label="Độ lệch Y" wire:model.number="text_y_offset" type="number" min="-300" max="300"/>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showEdit = false"/>
            <x-button label="Cập nhật" wire:click="update" class="btn-primary" spinner="update"/>
        </x-slot:actions>
    </x-modal>
</div>


