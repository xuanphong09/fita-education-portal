<?php

use App\Models\ProgramMajor;
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
    public string $code = '';
    public int $order = 0;
    public bool $is_active = true;

    public function getProgramMajorsProperty()
    {
        $q = ProgramMajor::query();

        if (!empty($this->search)) {
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search) . '%';
            $q->where(function ($inner) use ($term) {
                $inner->where('slug', 'like', $term)
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$term])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$term])
                    ->orWhere('code', 'like', $term);
            });
        }

        $q->orderBy(...array_values($this->sortBy));

        return $q->paginate($this->perPage);
    }

    protected function rules(): array
    {
        if ($this->editingId) {
            return [
                'name_vi' => 'required|string|max:255|unique:program_majors,name->vi,' . $this->editingId,
                'name_en' => 'required|string|max:255|unique:program_majors,name->en,' . $this->editingId,
                'slug' => 'required|string|max:255|unique:program_majors,slug,' . $this->editingId,
                'code' => 'required|string|max:50',
                'order' => 'nullable|integer|min:0',
            ];
        }

        return [
            'name_vi' => 'required|string|max:255|unique:program_majors,name->vi',
            'name_en' => 'required|string|max:255|unique:program_majors,name->en',
            'slug' => 'required|string|max:255|unique:program_majors,slug',
            'code' => 'required|string|max:50',
            'order' => 'nullable|integer|min:0',
        ];
    }

    protected $messages = [
        'name_vi.required' => 'Tên ngành (Tiếng Việt) không được để trống.',
        'name_en.required' => 'Tên ngành (Tiếng Anh) không được để trống.',
        'slug.required' => 'Slug không được để trống.',
        'name_vi.unique' => 'Tên ngành (Tiếng Việt) đã tồn tại.',
        'name_en.unique' => 'Tên ngành (Tiếng Anh) đã tồn tại.',
        'slug.unique' => 'Slug đã tồn tại.',
        'code.max' => 'Mã ngành không được vượt quá 50 ký tự.',
        'order.integer' => 'Thứ tự phải là một số nguyên.',
        'order.min' => 'Thứ tự phải lớn hơn hoặc bằng 0.',
        'code.string' => 'Mã ngành phải là một chuỗi ký tự.',
        'name_vi.string' => 'Tên ngành (Tiếng Việt) phải là một chuỗi ký tự.',
        'name_en.string' => 'Tên ngành (Tiếng Anh) phải là một chuỗi ký tự.',
        'slug.string' => 'Slug phải là một chuỗi ký tự.',
        'code.required' => 'Mã ngành không được để trống.',
    ];

    public function openCreate(): void
    {
        $this->resetCreateForm();
        $this->order = ProgramMajor::max('order') + 1;
        $this->showCreate = true;
    }

    public function resetCreateForm(): void
    {
        $this->editingId = null;
        $this->name_vi = $this->name_en = $this->slug = $this->code = '';
        $this->order = 0;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function updated($property): void
    {
        $this->validateOnly($property);
        if ($property === 'name_vi' && !$this->slug) {
            $this->slug = Str::slug($this->name_vi);
            $this->validateOnly('slug');
        }

        if($property === 'slug') {
            $this->slug = Str::slug($this->slug);
        }
    }

    public function openEdit(int $id): void
    {
        $programMajor = ProgramMajor::findOrFail($id);
        $this->editingId = $programMajor->id;
        $this->name_vi = $programMajor->getTranslation('name', 'vi', true) ?: '';
        $this->name_en = $programMajor->getTranslation('name', 'en', true) ?: '';
        $this->slug = $programMajor->slug;
        $this->code = $programMajor->code ?: '';
        $this->order = $programMajor->order;
        $this->is_active = $programMajor->is_active;
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

        ProgramMajor::create([
            'name' => ['vi' => $this->name_vi, 'en' => $this->name_en ?: ''],
            'slug' => $this->slug ?: Str::slug($this->name_vi),
            'code' => $this->code ?: null,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->showCreate = false;
        $this->success('Tạo ngành thành công');
        $this->resetPage();
    }

    public function update(): void
    {
        if (!$this->editingId) {
            $this->error('Không tìm thấy ngành để cập nhật.');
            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin.');
            throw $e;
        }

        $programMajor = ProgramMajor::findOrFail($this->editingId);
        $programMajor->update([
            'name' => ['vi' => $this->name_vi, 'en' => $this->name_en ?: ''],
            'slug' => $this->slug ?: Str::slug($this->name_vi),
            'code' => $this->code,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->showEdit = false;
        $this->success('Cập nhật ngành thành công');
        $this->resetPage();
    }

    public function headers(): array
    {
        return [
            ['key' => 'order', 'label' => 'STT', 'class' => 'w-5'],
            ['key' => 'name', 'label' => 'Tên ngành', 'class' => 'w-94'],
            ['key' => 'code', 'label' => 'Mã', 'class' => 'w-24'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-28'],
        ];
    }

    public function delete($id)
    {
        $programMajor = ProgramMajor::query()->withCount('majors')->findOrFail($id);
        if ($programMajor->majors_count > 0) {
            $this->error('Ngành này đang có chuyên ngành phụ thuộc, không thể xóa.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa ngành này không?',
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
        $programMajor = ProgramMajor::query()->withCount('majors')->findOrFail($id);
        if ($programMajor->majors_count > 0) {
            $this->error('Ngành này đang có chuyên ngành phụ thuộc, không thể xóa.');
            return;
        }

        $programMajor->delete();
        $this->success('Đã xóa ngành thành công.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <x-slot:title>Danh sách ngành</x-slot:title>
    <x-slot:breadcrumb>
        Quản lý ngành
    </x-slot:breadcrumb>
    <x-header title="Danh sách ngành" separator>
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
        <x-table :headers="$this->headers()" :rows="$this->programMajors" :sort-by="$this->sortBy" with-pagination
                 per-page="perPage"
                 class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
            "
        >

            @scope('cell_order', $programMajor)
            {{ ($this->programMajors->currentPage() - 1) * $this->programMajors->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $programMajor)
            <div class="font-medium">{{ $programMajor->getTranslation('name', 'vi', true) }}</div>
            <div class="text-sm text-gray-400">{{ $programMajor->getTranslation('name', 'en', true) }}</div>
            @endscope

            @scope('cell_code', $programMajor)
            {{ $programMajor->code ?? '—' }}
            @endscope

            @scope('cell_actions', $programMajor)
            <div class="flex space-x-2">
                <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary"
                          wire:click="openEdit({{ $programMajor->id }})"/>
                <x-button icon="o-trash" class="btn-sm btn-ghost text-danger" wire:click="delete({{ $programMajor->id }})"/>
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-folder" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có ngành nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->programMajors" wire:model.live="perPage"/>
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
    <x-modal wire:model="showCreate" title="Tạo ngành" separator class="modalMajor">
        <div class="space-y-0">
            <x-input label="Tên (Tiếng Việt)" wire:model.live.debounce.300ms="name_vi" required
                     placeholder="Nhập tên ngành Tiếng Việt "/>
            <x-input label="Tên (Tiếng Anh)" wire:model.live.debounce.300ms="name_en"
                     placeholder="Nhập tên ngành Tiếng Anh "/>
            <x-input label="Slug" wire:model.live.debounce.500ms="slug" placeholder="Nhập đường dẫn slug"/>
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Mã ngành" wire:model.live.debounce.300ms="code" placeholder="Nhập mã ngành"/>
                <x-input label="Thứ tự" wire:model.number="order" type="number" min="0"
                         placeholder="Nhập số thứ tự hiển thị"/>
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showCreate = false"/>
            <x-button label="Lưu" class="btn-primary" wire:click="store" spinner="store"/>
        </x-slot:actions>
    </x-modal>

    {{-- Modal Edit --}}
    <x-modal wire:model="showEdit" title="Chỉnh sửa ngành" separator class="modalMajor">
        <div class="space-y-3">
            <x-input label="Tên (Tiếng Việt)" wire:model.live.debounce.300ms="name_vi" required
                     placeholder="Nhập tên ngành Tiếng Việt "/>
            <x-input label="Tên (Tiếng Anh)" wire:model.live.debounce.300ms="name_en"
                     placeholder="Nhập tên ngành Tiếng Anh "/>
            <x-input label="Slug" wire:model.live.debounce.500ms="slug" placeholder="Nhập đường dẫn slug"/>
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Mã ngành" wire:model.live.debounce.300ms="code" placeholder="Nhập mã ngành"/>
                <x-input label="Thứ tự" wire:model.number="order" type="number" min="0"
                         placeholder="Nhập số thứ tự hiển thị"/>
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showEdit = false"/>
            <x-button label="Cập nhật" class="btn-primary" wire:click="update" spinner="update"/>
        </x-slot:actions>
    </x-modal>
</div>

