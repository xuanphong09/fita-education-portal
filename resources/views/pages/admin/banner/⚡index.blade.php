<?php

use App\Models\Banner;
use App\Models\Page;
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
    use WithPagination, Toast, WithFileUploads;

    #[Url(as: 'search')]
    public string $search = '';
    public string $tab_create_select = 'vi';
    public string $tab_edit_select = 'vi';

    public bool $showCreate = false;
    public bool $showEdit = false;
    public ?Banner $editingBanner = null;

    public string $title_vi = '';
    public string $title_en = '';
    public string $description_vi = '';
    public string $description_en = '';
    public string $url_text_vi = '';
    public string $url_text_en = '';
    public string $url = '';
    public string $position = 'bottom center';
    public int $order = 0;
    public bool $is_active = true;

    public $image;
    public ?string $oldImagePath = null;
    public int $imageInputKey = 0;

    public array $sortBy = ['column' => 'order', 'direction' => 'asc'];
    public int $perPage = 10;

    public $autoplay = false;
    public $interval = 5000;
    public $displaySaveButton = false;

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'image', 'label' => 'Ảnh', 'sortable' => false, 'class' => 'w-60'],
            ['key' => 'title_1', 'label' => 'Tiêu đề 1', 'sortable' => false],
            ['key' => 'title_2', 'label' => 'Tiêu đề 2', 'sortable' => false],
            ['key' => 'position', 'label' => 'Vị trí nội dung', 'class' => 'w-36'],
            ['key' => 'order', 'label' => 'Thứ tự', 'class' => 'w-20'],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'sortable' => false, 'class' => 'w-24'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-32'],
        ];
    }

    public function getPositionOptionsProperty(): array
    {
        return collect(Banner::POSITIONS)
            ->map(fn(string $label, string $value) => ['id' => $value, 'name' => $label])
            ->values()
            ->toArray();
    }

    public function getBannersProperty()
    {
        $query = Banner::query();

        if (trim($this->search) !== '') {
            $this->applySearchFilter($query, trim($this->search));
        }

        $query->orderBy(...array_values($this->sortBy));

        return $query->paginate($this->perPage);
    }

    protected function applySearchFilter($query, string $search): void
    {
        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $keyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

            $query->where(function ($q) use ($keyword) {
                $q->whereRaw(
                    "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                    [$keyword]
                )
                    ->orWhereRaw(
                        "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                        [$keyword]
                    )
                    ->orWhere('position', 'like', $keyword)
                    ->orWhere('url', 'like', $keyword);
            });
        }
    }

    public function resetForm(): void
    {
        $this->reset([
            'title_vi',
            'title_en',
            'description_vi',
            'description_en',
            'url_text_vi',
            'url_text_en',
            'url',
            'position',
            'order',
            'is_active',
            'image',
            'oldImagePath',
            'editingBanner',
        ]);

        $this->position = 'bottom center';
        $this->is_active = true;
        $this->order = 0;
        $this->imageInputKey++;
        $this->resetErrorBag();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->order = (Banner::max('order') ?? 0) + 1;
        $this->showCreate = true;
        $this->showEdit = false;
    }

    public function openEdit(Banner $banner): void
    {
        $this->resetForm();

        $this->editingBanner = $banner;
        $this->title_vi = $banner->getTranslation('title', 'vi', false) ?? '';
        $this->title_en = $banner->getTranslation('title', 'en', false) ?? '';
        $this->description_vi = $banner->getTranslation('description', 'vi', false) ?? '';
        $this->description_en = $banner->getTranslation('description', 'en', false) ?? '';
        $this->url_text_vi = $banner->getTranslation('url_text', 'vi', false) ?? '';
        $this->url_text_en = $banner->getTranslation('url_text', 'en', false) ?? '';
        $this->url = $banner->url ?? '';
        $this->position = $banner->position;
        $this->order = $banner->order;
        $this->is_active = $banner->is_active;
        $this->oldImagePath = $banner->image;

        $this->showEdit = true;
        $this->showCreate = false;
    }

    public function rules()
    {
        return [
            'title_vi' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_vi' => 'nullable|string|max:2000',
            'description_en' => 'nullable|string|max:2000',
            'url_text_vi' => 'nullable|string|max:100',
            'url_text_en' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:255',
            'position' => 'required|in:' . implode(',', array_keys(Banner::POSITIONS)),
            'order' => 'required|integer|min:0',
            'image' => $this->editingBanner ? 'nullable|image|max:5120' : 'required|image|max:5120',
        ];
    }

    protected $messages = [
        'title_vi.max' => 'Tiêu đề 1 tiếng Việt không được vượt quá 255 ký tự.',
        'title_en.max' => 'Tiêu đề 1 tiếng Anh không được vượt quá 255 ký tự.',
        'description_vi.max' => 'Tiêu đề 2 tiếng Việt không được vượt quá 2000 ký tự.',
        'description_en.max' => 'Tiêu đề 2 tiếng Anh không được vượt quá 2000 ký tự.',
        'url_text_vi.max' => 'Nhãn nút tiếng Việt không được vượt quá 100 ký tự.',
        'url_text_en.max' => 'Nhãn nút tiếng Anh không được vượt quá 100 ký tự.',
        'url.url' => 'Đường dẫn phải là một URL hợp lệ.',
        'url.max' => 'Đường dẫn không được vượt quá 255 ký tự.',
        'position.required' => 'Vị trí hiển thị là bắt buộc.',
        'position.in' => 'Vị trí hiển thị không hợp lệ.',
        'order.required' => 'Thứ tự là bắt buộc.',
        'order.integer' => 'Thứ tự phải là một số nguyên.',
        'order.min' => 'Thứ tự phải lớn hơn hoặc bằng 0.',
        'image.required' => 'Ảnh banner là bắt buộc.',
        'image.image' => 'Tệp phải là một hình ảnh.',
        'image.max' => 'Kích thước ảnh không được vượt quá 5MB.',
    ];

    public function store(): void
    {
        $this->validate([
            'title_vi' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_vi' => 'nullable|string|max:2000',
            'description_en' => 'nullable|string|max:2000',
            'url_text_vi' => 'nullable|string|max:100',
            'url_text_en' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:255',
            'position' => 'required|in:' . implode(',', array_keys(Banner::POSITIONS)),
            'order' => 'required|integer|min:0',
            'image' => 'required|image|max:5120',
        ]);

        $imagePath = $this->image->store('uploads/banners', 'public');

        Banner::create([
            'title' => ['vi' => trim($this->title_vi), 'en' => trim($this->title_en)],
            'description' => ['vi' => trim($this->description_vi), 'en' => trim($this->description_en)],
            'url_text' => ['vi' => trim($this->url_text_vi), 'en' => trim($this->url_text_en)],
            'url' => trim($this->url) !== '' ? trim($this->url) : null,
            'image' => $imagePath,
            'position' => $this->position,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->showCreate = false;
        $this->success('Đã tạo banner thành công.');
    }

    public function update(): void
    {
        if (!$this->editingBanner) {
            return;
        }

        $this->validate([
            'title_vi' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_vi' => 'nullable|string|max:2000',
            'description_en' => 'nullable|string|max:2000',
            'url_text_vi' => 'nullable|string|max:100',
            'url_text_en' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:255',
            'position' => 'required|in:' . implode(',', array_keys(Banner::POSITIONS)),
            'order' => 'required|integer|min:0',
            'image' => 'nullable|image|max:5120',
        ]);

        $imagePath = $this->oldImagePath;

        if ($this->image) {
            if ($this->oldImagePath && Storage::disk('public')->exists($this->oldImagePath)) {
                Storage::disk('public')->delete($this->oldImagePath);
            }

            $imagePath = $this->image->store('uploads/banners', 'public');
        }

        $this->editingBanner->update([
            'title' => ['vi' => trim($this->title_vi), 'en' => trim($this->title_en)],
            'description' => ['vi' => trim($this->description_vi), 'en' => trim($this->description_en)],
            'url_text' => ['vi' => trim($this->url_text_vi), 'en' => trim($this->url_text_en)],
            'url' => trim($this->url) !== '' ? trim($this->url) : null,
            'image' => $imagePath,
            'position' => $this->position,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->showEdit = false;
        $this->success('Đã cập nhật banner thành công.');
    }

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa banner này?',
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
        Banner::findOrFail($id)->delete();
        $this->success('Banner đã được chuyển vào thùng rác.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['autoplay', 'interval'])) {
            $this->displaySaveButton = true;
        }
    }

    public function saveSettings()
    {
        $page = Page::updateOrCreate(
            ['slug' => 'banner'],
            ['layout' => 'banner']
        );

        $page->setTranslations('content_data', [
            'vi' => [
                'autoplay' => $this->autoplay,
                'interval' => $this->interval,
            ],
        ]);
        $page->save();
        $this->displaySaveButton = false;
        $this->success('Đã lưu cài đặt banner.');
    }

    public function mount()
    {
        $configBanner = Page::where('slug', 'banner')->first();
        if ($configBanner){
            $this->autoplay = $configBanner->getTranslation('content_data', 'vi')['autoplay'] ?? false;
            $this->interval = $configBanner->getTranslation('content_data', 'vi')['interval'] ?? 5000;
        }
     }
};
?>

<div>
    <x-slot:title>Quản lý banner</x-slot:title>

    <x-slot:breadcrumb>
        Quản lý banner
    </x-slot:breadcrumb>

    <x-header title="Danh sách banner" class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            {{--            <x-input--}}
            {{--                icon="o-magnifying-glass"--}}
            {{--                placeholder="Tìm kiếm theo tiêu đề..."--}}
            {{--                wire:model.live.debounce.300ms="search"--}}
            {{--                clearable="true"--}}
            {{--                class="w-full lg:w-96"--}}
            {{--            />--}}
            <div class="flex flex-row gap-4 items-center">
                <x-toggle label="Tự động chuyển" wire:model.live.debounce.300ms="autoplay"
                          class="toggle-primary toggle-sm" right/>
                @if($autoplay)
                    <x-input
                        placeholder="Thời gian chuyển (ms)..."
                        wire:model.live.debounce.300ms="interval"
                        class="w-full"
                        suffix="ms"
                    />
                @endif
                @if($displaySaveButton)
                    <x-button label="Lưu" class="btn-primary" wire:click="saveSettings" spinner="saveSettings"
                              :disabled="!$displaySaveButton"/>
                @endif
            </div>

        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-trash" class="btn-ghost" label="Thùng rác" link="{{ route('admin.banner.trash') }}"/>
            <x-button icon="o-plus" class="btn-primary text-white" label="Thêm mới" wire:click="openCreate"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->banners"
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
            @scope('cell_id', $banner)
            {{ ($this->banners->currentPage() - 1) * $this->banners->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_image', $banner)
            @if($banner->image && Storage::disk('public')->exists($banner->image))
                <img src="{{ Storage::url($banner->image) }}" alt="Banner"
                     class="h-18 rounded object-cover ring-1 ring-gray-200"/>
            @else
                <div class="w-12 h-12 rounded bg-gray-100 flex items-center justify-center ring-1 ring-gray-200">
                    <x-icon name="o-photo" class="w-5 h-5 text-gray-400"/>
                </div>
            @endif
            @endscope

            @scope('cell_title_1', $banner)
            <div class="font-medium line-clamp-1">{{ $banner->getTranslation('title', 'vi', false) ?: '—' }}</div>
            <div
                class="text-xs text-gray-400 line-clamp-1">{{ $banner->getTranslation('title', 'en', false) ?: '' }}</div>
            @endscope
            @scope('cell_title_2', $banner)
            <div class="font-medium line-clamp-1">{{ $banner->getTranslation('description', 'vi', false) ?: '—' }}</div>
            <div
                class="text-xs text-gray-400 line-clamp-1">{{ $banner->getTranslation('description', 'en', false) ?: '' }}</div>
            @endscope

            @scope('cell_position', $banner)
            <span
                class="whitespace-nowrap">{{ \App\Models\Banner::POSITIONS[$banner->position] ?? $banner->position }}</span>
            @endscope

            @scope('cell_is_active', $banner)
            @if($banner->is_active)
                <x-badge value="Hoạt động" class="badge-success whitespace-nowrap text-white font-medium"/>
            @else
                <x-badge value="Tắt" class="badge-warning whitespace-nowrap text-white font-medium"/>
            @endif
            @endscope

            @scope('cell_actions', $banner)
            <div class="flex gap-2">
                <x-button icon="o-pencil" class="btn-xs btn-ghost text-primary"
                          wire:click="openEdit({{ $banner->id }})"/>
                <x-button icon="o-trash" class="btn-xs btn-ghost text-error" wire:click="delete({{ $banner->id }})"/>
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-photo" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có banner nào.</p>
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

    <x-modal wire:model="showCreate" title="Thêm banner" class="modalAddBanner" separator>
        <div class="space-y-1 py-0 max-h-[70vh] overflow-y-auto pr-1">
            <div class="space-y-2">
                <label class="font-medium text-sm">Ảnh đại diện</label>
                <input
                    wire:key="create-banner-image-{{ $imageInputKey }}"
                    type="file"
                    wire:model="image"
                    accept="image/png, image/jpeg"
                    class="file-input file-input-bordered w-full"
                >
                <p class="text-xs text-gray-500">Kích thước tối ưu: 1920x550px. Vùng an toàn: khoảng 1100x550px. Dung
                    lượng tối đa: 5MB.</p>

                <div
                    class="relative min-h-32 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden">
                    <div wire:loading.flex wire:target="image"
                         class="absolute inset-0 z-10 items-center justify-center gap-2 bg-white/80">
                        <x-loading class="loading-spinner text-primary"/>
                        <span class="text-sm text-gray-600">Đang tải ảnh...</span>
                    </div>

                    <div wire:loading.remove wire:target="image" class="h-32 flex items-center justify-center">
                        @if($image)
                            <img src="{{ $image->temporaryUrl() }}" class="h-full w-full object-cover"
                                 alt="Ảnh xem trước"/>
                        @else
                            <span class="text-sm text-gray-500">Chưa chọn ảnh</span>
                        @endif
                    </div>
                </div>
            </div>

            <x-tabs wire:model="tab_create_select" active-class="text-fita! border-b-2 border-fita font-semibold">
                <x-tab name="vi" label="Tiếng Việt" class="mb-0 py-0!">
                    <x-input label="Tiêu đề 1 (Tiếng Việt)" wire:model.live.debounce.300ms="title_vi"
                             placeholder="Nhập tiêu đề 1 tiếng Việt"/>
                    <x-input label="Tiêu đề 2 (Tiếng Việt)" wire:model.live.debounce.300ms="description_vi"
                             placeholder="Nhập tiêu đề 2 tiếng Việt" class="col-span-full"/>
                    <x-input label="Nhãn nút (Tiếng Việt)" wire:model.live.debounce.300ms="url_text_vi"
                             placeholder="Ví dụ: Xem thêm"/>
                </x-tab>
                <x-tab name="en" label="Tiếng Anh" class="mb-0 py-0!">
                    <x-input label="Tiêu đề 1 (Tiếng Anh)" wire:model.live.debounce.300ms="title_en"
                             placeholder="Nhập tiêu đề 1 tiếng Anh"/>
                    <x-input label="Tiêu đề 2 (Tiếng Anh)" wire:model.live.debounce.300ms="description_en"
                             placeholder="Nhập tiêu đề 2 tiếng Anh" class="col-span-full"/>
                    <x-input label="Nhãn nút (Tiếng Anh)" wire:model.live.debounce.300ms="url_text_en"
                             placeholder="Ví dụ: Xem thêm"/>
                </x-tab>
            </x-tabs>
            <div class="grid grid-cols-2 gap-y-1 gap-x-4">
                <x-input label="Đường dãn (URL)" wire:model="url" placeholder="https://example.com"/>
                <x-select
                    label="Vị trí hiển thị nội dung"
                    wire:model="position"
                    :options="$this->positionOptions"
                    option-value="id"
                    option-label="name"
                />
                <x-input label="Thứ tự" wire:model.number="order" type="number" min="0"/>
                <div class="flex justify-end flex-col">
                    <x-checkbox label="Hoạt động" wire:model="is_active" class="checkbox-primary"/>
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showCreate = false"/>
            <x-button label="Lưu" wire:click="store" class="btn-primary" spinner="store"/>
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showEdit" title="Sửa banner" class="modalAddBanner" separator>
        <div class="space-y-1 py-0 max-h-[70vh] overflow-y-auto pr-1">
            <div class="space-y-2">
                <label class="font-medium text-sm">Ảnh đại diện</label>
                <input
                    wire:key="edit-banner-image-{{ $imageInputKey }}"
                    type="file"
                    wire:model="image"
                    accept="image/png, image/jpeg"
                    class="file-input file-input-bordered w-full"
                >
                <p class="text-xs text-gray-500">Kích thước tối ưu: 1920x550px. Vùng an toàn: khoảng 1100x550px. Dung
                    lượng tối đa: 5MB.</p>

                <div
                    class="relative min-h-32 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden">
                    <div wire:loading.flex wire:target="image"
                         class="absolute inset-0 z-10 items-center justify-center gap-2 bg-white/80">
                        <x-loading class="loading-spinner text-primary"/>
                        <span class="text-sm text-gray-600">Đang tải ảnh...</span>
                    </div>

                    <div wire:loading.remove wire:target="image" class="h-32 flex items-center justify-center">
                        @if($image)
                            <img src="{{ $image->temporaryUrl() }}" class="h-full w-full object-cover"
                                 alt="Ảnh xem trước"/>
                        @elseif($oldImagePath && Storage::disk('public')->exists($oldImagePath))
                            <img src="{{ Storage::url($oldImagePath) }}" class="h-full w-full object-cover"
                                 alt="Ảnh hiện tại"/>
                        @else
                            <span class="text-sm text-gray-500">Chưa chọn ảnh</span>
                        @endif
                    </div>
                </div>
            </div>

            <x-tabs wire:model="tab_edit_select" active-class="text-fita! border-b-2 border-fita font-semibold">
                <x-tab name="vi" label="Tiếng Việt" class="mb-0 py-0!">
                    <x-input label="Tiêu đề 1 (Tiếng Việt)" wire:model.live.debounce.300ms="title_vi"
                             placeholder="Nhập tiêu đề 1 tiếng Việt"/>
                    <x-input label="Tiêu đề 2 (Tiếng Việt)" wire:model.live.debounce.300ms="description_vi"
                             placeholder="Nhập tiêu đề 2 tiếng Việt" class="col-span-full"/>
                    <x-input label="Nhãn nút (Tiếng Việt)" wire:model.live.debounce.300ms="url_text_vi"
                             placeholder="Ví dụ: Xem thêm"/>
                </x-tab>
                <x-tab name="en" label="Tiếng Anh" class="mb-0 py-0!">
                    <x-input label="Tiêu đề 1 (Tiếng Anh)" wire:model.live.debounce.300ms="title_en"
                             placeholder="Nhập tiêu đề 1 tiếng Anh"/>
                    <x-input label="Tiêu đề 2 (Tiếng Anh)" wire:model.live.debounce.300ms="description_en"
                             placeholder="Nhập tiêu đề 2 tiếng Anh" class="col-span-full"/>
                    <x-input label="Nhãn nút (Tiếng Anh)" wire:model.live.debounce.300ms="url_text_en"
                             placeholder="Ví dụ: Xem thêm"/>
                </x-tab>
            </x-tabs>

            <div class="grid grid-cols-2 gap-y-1 gap-x-4">
                <x-input label="Đường dãn (URL)" wire:model="url" placeholder="https://example.com"/>
                <x-select
                    label="Vị trí hiển thị nội dung"
                    wire:model="position"
                    :options="$this->positionOptions"
                    option-value="id"
                    option-label="name"
                />
                <x-input label="Thứ tự" wire:model.number="order" type="number" min="0"/>
                <div class="flex justify-end flex-col">
                    <x-checkbox label="Hoạt động" wire:model="is_active" class="checkbox-primary"/>
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showEdit = false"/>
            <x-button label="Cập nhật" wire:click="update" class="btn-primary" spinner="update"/>
        </x-slot:actions>
    </x-modal>
</div>

