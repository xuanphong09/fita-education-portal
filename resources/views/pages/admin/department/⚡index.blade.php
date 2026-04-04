<?php

use App\Models\Department;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use WithPagination, Toast;

    public int $perPage = 10;
    #[Url('search')]
    public string $search = '';
    public array $sortBy = ['column' => 'order', 'direction' => 'asc'];

    // Modal & form state
    public bool $showCreate = false;
    public bool $showEdit = false;
    public ?int $editingId = null;

    public string $name_vi = '';
    public string $name_en = '';
    public string $slug = '';
    public int $order = 0;

    public function getDepartmentsProperty()
    {
        $q = Department::query();

        if (!empty($this->search)) {
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search) . '%';
            $q->where(function ($inner) use ($term) {
                $inner->where('slug', 'like', $term)
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$term])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$term]);
            });
        }

        $q->orderBy(...array_values($this->sortBy));

        return $q->paginate($this->perPage);
    }

    protected function rules(): array
    {
        if ($this->editingId) {
            return [
                'name_vi' => 'required|string|max:255|unique:departments,name->vi,' . $this->editingId,
                'name_en' => 'required|string|max:255|unique:departments,name->en,' . $this->editingId,
                'slug' => 'required|string|max:255|unique:departments,slug,' . $this->editingId,
                'order' => 'nullable|integer|min:0',
            ];
        }

        return [
            'name_vi' => 'required|string|max:255|unique:departments,name->vi',
            'name_en' => 'required|string|max:255|unique:departments,name->en',
            'slug' => 'required|string|max:255|unique:departments,slug',
            'order' => 'nullable|integer|min:0',
        ];
    }

    protected $messages = [
        'name_vi.required' => 'Tên bộ môn (Tiếng Việt) không được để trống.',
        'name_en.required' => 'Tên bộ môn (Tiếng Anh) không được để trống.',
        'slug.required' => 'Slug không được để trống.',
        'name_vi.unique' => 'Tên bộ môn (Tiếng Việt) đã tồn tại.',
        'name_en.unique' => 'Tên bộ môn (Tiếng Anh) đã tồn tại.',
        'slug.unique' => 'Slug đã tồn tại.',
        'order.integer' => 'Thứ tự phải là một số nguyên.',
        'order.min' => 'Thứ tự phải lớn hơn hoặc bằng 0.',
    ];

    public function openCreate(): void
    {
        $this->resetCreateForm();
        $this->order = Department::max('order') + 1;
        $this->showCreate = true;
    }

    public function resetCreateForm(): void
    {
        $this->editingId = null;
        $this->name_vi = $this->name_en = $this->slug =  '';
        $this->order = 0;
        $this->resetErrorBag();
    }

    public function updated($property): void
    {
        $this->ValidateOnly($property);
        if ($property === 'name_vi' && !$this->slug) {
            $this->slug = Str::slug($this->name_vi);
            $this->validateOnly('slug');
        }

        if ($property === 'slug') {
            $this->slug = Str::slug($this->slug);
        }
    }

    public function openEdit(int $id): void
    {
        $department = Department::findOrFail($id);
        $this->editingId = $department->id;
        $this->name_vi = $department->getTranslation('name', 'vi', true) ?: '';
        $this->name_en = $department->getTranslation('name', 'en', true) ?: '';
        $this->slug = $department->slug;
        $this->order = $department->order;
        $this->showEdit = true;
    }

    public function store(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin.');
            throw $e;
        }

        Department::create([
            'name' => ['vi' => $this->name_vi, 'en' => $this->name_en ?: ''],
            'slug' => $this->slug ?: Str::slug($this->name_vi),
            'order' => $this->order,
        ]);

        $this->showCreate = false;
        $this->success('Tạo bộ môn thành công');
        $this->resetPage();
    }

    public function update(): void
    {
        if (!$this->editingId) {
            $this->error('Không tìm thấy bộ môn để cập nhật.');
            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin.');
            throw $e;
        }

        $department = Department::findOrFail($this->editingId);
        $department->update([
            'name' => ['vi' => $this->name_vi, 'en' => $this->name_en ?: ''],
            'slug' => $this->slug ?: Str::slug($this->name_vi),
            'order' => $this->order,
        ]);

        $this->showEdit = false;
        $this->success('Cập nhật bộ môn thành công');
        $this->resetPage();
    }

    public function headers(): array
    {
        return [
            ['key' => 'order', 'label' => 'STT', 'class' => 'w-5'],
            ['key' => 'name', 'label' => 'Tên bộ môn', 'class' => 'w-94'],
//            ['key' => 'is_active', 'label' => 'Kích hoạt', 'class' => 'w-20'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-28'],
        ];
    }

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa bộ môn này không?',
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
        $department = Department::withCount('lecturer')->findOrFail($id);
        if ($department->lecturer_count > 0) {
            $this->error('Bộ môn đang có giảng viên, không thể xóa.');
            return;
        }

        $department->delete();
        $this->success('Đã xóa bộ môn thành công.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <x-slot:title>Danh sách bộ môn</x-slot:title>
    <x-slot:breadcrumb>
        Quản lý bộ môn
    </x-slot:breadcrumb>
    <x-header title="Danh sách bộ môn" separator>
        <x-slot:middle class="justify-end!">
            <x-input placeholder="Tìm tên hoặc mã..." wire:model.live.debounce.300ms="search" class="w-full lg:w-96"
                     clearable/>
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" class="btn-primary text-white" label="Tạo mới" wire:click="openCreate"
                      spinner="openCreate"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table :headers="$this->headers()" :rows="$this->Departments" :sort-by="$this->sortBy" with-pagination
                 per-page="perPage"
                 class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
            "
        >

            @scope('cell_order', $department)
            {{ ($this->Departments->currentPage() - 1) * $this->Departments->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $department)
            <div class="font-medium">{{ $department->getTranslation('name', 'vi', true) }}</div>
            <div class="text-sm text-gray-400">{{ $department->getTranslation('name', 'en', true) }}</div>
            @endscope
            {{--            @scope('cell_is_active', $department)--}}
            {{--            @if($department->is_active)--}}
            {{--                <x-badge value="Kích hoạt" class="badge-success"/>--}}
            {{--            @else--}}
            {{--                <x-badge value="Tắt" class="badge-error"/>--}}
            {{--            @endif--}}
            {{--            @endscope--}}

            @scope('cell_actions', $department)
            <div class="flex space-x-2">
                <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary"
                          wire:click="openEdit({{ $department->id }})"/>
                <x-button icon="o-trash" class="btn-sm btn-ghost text-danger" wire:click="delete({{ $department->id }})"/>
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-user" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có bộ môn nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->Departments" wire:model.live="perPage"/>
        </x-table>
        <div wire:loading.flex
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>

    {{-- Modal Create --}}
    <x-modal wire:model="showCreate" title="Tạo bộ môn" separator class="modalMajor">
        <div class="space-y-3">
            <x-input label="Tên (Tiếng Việt)" wire:model.live.debounce.300ms="name_vi" required
                     placeholder="Nhập tên bộ môn Tiếng Việt "/>
            <x-input label="Tên (Tiếng Anh)" wire:model.live.debounce.300ms="name_en"
                     placeholder="Nhập tên bộ môn Tiếng Anh "/>
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Slug" wire:model.live.debounce.500ms="slug" placeholder="Nhập đường dẫn slug"/>
                <x-input label="Thứ tự" wire:model.number="order" type="number" min="0"
                         class="Nhập số thứ tự hiển thị"/>
            </div>
            <div class="grid grid-cols-2 gap-4">
                {{--                <div class="flex items-center">--}}
                {{--                    <x-toggle label="Kích hoạt" wire:model="is_active"/>--}}
                {{--                </div>--}}
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showCreate = false"/>
            <x-button label="Lưu" class="btn-primary" wire:click="store" spinner="store"/>
        </x-slot:actions>
    </x-modal>

    {{-- Modal Edit --}}
    <x-modal wire:model="showEdit" title="Chỉnh sửa bộ môn" separator class="modalMajor">
        <div class="space-y-3">
            <x-input label="Tên (Tiếng Việt)" wire:model.live.debounce.300ms="name_vi" required
                     placeholder="Nhập tên bộ môn Tiếng Việt "/>
            <x-input label="Tên (Tiếng Anh)" wire:model.live.debounce.300ms="name_en"
                     placeholder="Nhập tên bộ môn Tiếng Anh "/>
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Slug" wire:model.live.debounce.500ms="slug" placeholder="Nhập đường dẫn slug"/>
                <x-input label="Thứ tự" wire:model.number="order" type="number" min="0"
                         class="Nhập số thứ tự hiển thị"/>
            </div>
            {{--            <div class="grid grid-cols-2 gap-4">--}}
            {{--                <div class="flex items-center">--}}
            {{--                    <x-toggle label="Kích hoạt" wire:model="is_active"/>--}}
            {{--                </div>--}}
            {{--            </div>--}}
        </div>
        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showEdit =  false"/>
            <x-button label="Cập nhật" class="btn-primary" wire:click="update" spinner="update"/>
        </x-slot:actions>
    </x-modal>
</div>



