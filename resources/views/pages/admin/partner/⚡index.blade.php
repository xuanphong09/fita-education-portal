<?php

use App\Models\Partner;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination, Toast, WithFileUploads;

    #[Url(as: 'search')]
    public string $search = '';

    public bool $showCreate = false;
    public bool $showEdit = false;
    public ?Partner $editingPartner = null;

    public string $name = '';
    public string $url = '';
    public int $order = 0;
    public bool $is_active = true;
    public $logo;
    public $oldLogoPath;

    public array $sortBy = ['column' => 'order', 'direction' => 'asc'];
    public int $perPage = 10;

    public function getPartnersProperty()
    {
        $q = Partner::query();

        if (!empty($this->search)) {
            $search = "%{$this->search}%";
            $q->where('name', 'like', $search);
        }

        $q->orderBy(...array_values($this->sortBy));

        return $q->paginate($this->perPage);
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'logo', 'label' => __('Logo'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'name', 'label' => 'Tên', 'class' => 'w-64'],
            ['key' => 'url', 'label' => 'Đường dẫn (URL)', 'sortable' => false, 'class' => 'w-80'],
            ['key' => 'order', 'label' => 'Thứ tự', 'class' => 'w-20'],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'sortable' => false, 'class' => 'w-24'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-32'],
        ];
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'url', 'order', 'is_active', 'logo', 'oldLogoPath', 'editingPartner']);
        $this->resetErrorBag('name');
        $this->resetErrorBag('url');
        $this->resetErrorBag('order');
        $this->resetErrorBag('logo');
        $this->is_active = true;
        $this->order = 0;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->order = Partner::max('order') + 1;
        $this->showCreate = true;
        $this->showEdit = false;
    }

    public function openEdit(Partner $partner): void
    {
        $this->resetForm();
        $this->editingPartner = $partner;
        $this->name = $partner->name;
        $this->url = $partner->url ?? '';
        $this->order = $partner->order;
        $this->is_active = $partner->is_active;
        $this->oldLogoPath = $partner->logo;
        $this->logo = null;

        $this->showEdit = true;
        $this->showCreate = false;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255|unique:partners,name',
            'url' => 'nullable|url|max:255',
            'order' => 'required|integer|min:0',
            'logo' => 'nullable|image|max:2048',
        ];

        if ($this->editingPartner) {
            $rules['name'] = 'required|string|max:255|unique:partners,name,' . $this->editingPartner->id;
        }

        return $rules;
    }

    protected $messages = [
        'name.required' => 'Tên đối tác là bắt buộc.',
        'name.string' => 'Tên đối tác phải là một chuỗi.',
        'name.max' => 'Tên đối tác không được vượt quá 255 ký tự.',
        'name.unique' => 'Tên đối tác đã tồn tại. Vui lòng chọn tên khác.',
        'url.url' => 'Đường dẫn (URL) không hợp lệ.',
        'url.max' => 'Đường dẫn (URL) không được vượt quá 255 ký tự.',
        'order.required' => 'Thứ tự là bắt buộc.',
        'order.integer' => 'Thứ tự phải là một số nguyên.',
        'order.min' => 'Thứ tự phải lớn hơn hoặc bằng 0.',
        'logo.image' => 'Logo phải là một tệp hình ảnh.',
        'logo.max' => 'Logo không được vượt quá 2MB.',
    ];

    public function store(): void
    {
        $this->validate([
            'name' => 'required|string|unique:partners|max:255',
            'url' => 'nullable|url|max:255',
            'order' => 'required|integer|min:0',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('uploads/partners', 'public');
        }

        Partner::create([
            'name' => $this->name,
            'logo' => $logoPath,
            'url' => $this->url ?: null,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->resetForm();
        $this->showCreate = false;
        $this->success('Đối tác đã được tạo thành công');
    }

    public function update(): void
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:partners,name,' . $this->editingPartner?->id,
            'url' => 'nullable|url|max:255',
            'order' => 'required|integer|min:0',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoPath = $this->oldLogoPath;
        if ($this->logo) {
            if ($this->oldLogoPath && Storage::disk('public')->exists($this->oldLogoPath)) {
                Storage::disk('public')->delete($this->oldLogoPath);
            }
            $logoPath = $this->logo->store('uploads/partners', 'public');
        }

        $this->editingPartner->update([
            'name' => $this->name,
            'logo' => $logoPath,
            'url' => $this->url ?: null,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->resetForm();
        $this->showEdit = false;
        $this->success('Đối tác đã được cập nhật thành công');
    }

    public function delete($id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa đối tác này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete($id)
    {
        $partner = Partner::findOrFail($id);
        $partner->delete();
        $this->success('Đối tác đã được chuyển vào thùng rác');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <x-slot:title>
        Danh sách đối tác
    </x-slot:title>

    <x-slot:breadcrumb>
        Danh sách đối tác
    </x-slot:breadcrumb>

    <x-header title="Danh sách đối tác" class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm kiếm theo tên đối tác..."
                wire:model.live.debounce.300ms="search"
                clearable="true"
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-trash" class="btn-ghost" label="Thùng rác" link="{{ route('admin.partner.trash') }}"/>
            <x-button icon="o-plus" class="btn-primary text-white" label="Thêm mới" wire:click="openCreate"
                      spinner="openCreate"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->partners"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[5, 10, 20, 25, 50]"
            per-page="perPage"
            with-pagination
            wire:loading.class="opacity-50 pointer-events-none select-none"
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
            "
        >

            @scope('cell_id', $partner)
            {{ ($this->partners->currentPage() - 1) * $this->partners->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_logo', $partner)
            @if($partner->logo && Storage::disk('public')->exists($partner->logo))
                <img src="{{ Storage::url($partner->logo) }}" alt="{{ $partner->name }}"
                     class="w-10 h-10 rounded object-cover ring-1 ring-gray-200"/>
            @else
                <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center ring-1 ring-gray-200">
                    <x-icon name="o-photo" class="w-5 h-5 text-gray-400"/>
                </div>
            @endif
            @endscope

            @scope('cell_name', $partner)
            <div class="font-medium">{{ $partner->name }}</div>
            @endscope

            @scope('cell_url', $partner)
            @if($partner->url)
                <a href="{{ $partner->url }}" target="_blank"
                   class="text-blue-600 hover:underline text-md truncate block">
                    {{ $partner->url }}
                </a>
            @else
                <span class="text-gray-400 text-md">—</span>
            @endif
            @endscope
            @scope('cell_is_active', $partner)
            @if($partner->is_active)
                <x-badge value="Hoạt động" class="badge-success whitespace-nowrap text-white font-medium"/>
            @else
                <x-badge value="Không hoạt động" class="badge-warning whitespace-nowrap text-white font-medium"/>
            @endif
            @endscope

            @scope('cell_actions', $partner)
            <div class="flex gap-2">
                <x-button icon="o-pencil" class="btn-xs btn-ghost text-primary"
                          wire:click="openEdit({{ $partner->id }})"/>
                <x-button icon="o-trash" class="btn-xs btn-ghost text-error" wire:click="delete({{ $partner->id }})"/>
            </div>
            @endscope
            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-briefcase" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500"> Không có đối tác nào </p>
                </div>
            </x-slot:empty>

        </x-table>
        <div wire:loading.flex
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>

    {{-- Create Modal --}}
    <x-modal wire:model="showCreate" title="Thêm đối tác" size="lg">
        <form class="space-y-1">
            <x-input label="Tên" wire:model="name" required placeholder="Nhập tên đối tác"/>
            <x-input label="Đường dẫn (URL)" wire:model="url" type="url"
                     placeholder="Nhập địa chỉ đường đẫn VD: https://example.com"/>
            <x-input label="Thứ tự" wire:model.number="order" type="number" min="0" placeholder=" Nhập thứ tự hiển thị "
                     hint="Thứ tự hiển thị (tăng dần)"/>
            <x-file label="Logo" wire:model="logo" accept="image/*" hint="{{__('Max 2MB, JPG/PNG')}}"/>
            <x-checkbox label="Hoạt động" wire:model="is_active" class="checkbox-primary checkbox-md"
                        hint="Đối tác sẽ được hiển thị trên website"/>
        </form>

        <x-slot:actions>
            <x-button label="{{__('Cancel')}}" @click="$wire.showCreate = false"/>
            <x-button label="{{__('Save')}}" wire:click="store" class="btn-primary" spinner="store"/>
        </x-slot:actions>
    </x-modal>

    {{-- Edit Modal --}}
    <x-modal wire:model="showEdit" title="Chỉnh sửa đối tác" size="lg">
        <form class="space-y-1">
            <x-input label="Tên" wire:model="name" required placeholder="Nhập tên đối tác"/>
            <x-input label="Đường dẫn (URL)" wire:model="url" type="url"
                     placeholder="Nhập địa chỉ đường đẫn VD: https://example.com"/>
            <x-input label="Thứ tự" wire:model.number="order" type="number" min="0" placeholder=" Nhập thứ tự hiển thị "
                     hint="Thứ tự hiển thị (tăng dần)"/>

            <div>
                <label class="label">
                    <span class="label-text font-semibold">{{__('Logo')}}</span>
                </label>
                @if($oldLogoPath && Storage::disk('public')->exists($oldLogoPath))
                    <div class="mb-3 p-3 bg-blue-50 rounded border border-blue-200 flex items-center gap-3">
                        <img src="{{ Storage::url($oldLogoPath) }}" alt="Current Logo"
                             class="h-12 w-12 object-cover rounded"/>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-700">Logo hiện tại</p>
                            <p class="text-xs text-gray-500">Tải lên 1 logo khác để thay thế</p>
                        </div>
                    </div>
                @endif
                <x-file wire:model="logo" accept="image/*" hint="{{__('Max 2MB, JPG/PNG')}}"/>
            </div>

            <x-checkbox label="Hoạt động" wire:model="is_active" class="checkbox-primary checkbox-md"
                        hint="Đối tác sẽ được hiển thị trên website"/>
        </form>

        <x-slot:actions>
            <x-button label="Hủy " @click="$wire.showEdit = false"/>
            <x-button label="Cập nhật" wire:click="update" class="btn-primary" spinner="update"/>
        </x-slot:actions>
    </x-modal>
</div>







