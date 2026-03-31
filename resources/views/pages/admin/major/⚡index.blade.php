<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Major;
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

    public function getMajorsProperty()
    {
        $q = Major::query()->withCount('students');

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
                'name_vi' => 'required|string|max:255|unique:majors,name->vi,' . $this->editingId,
                'name_en' => 'required|string|max:255|unique:majors,name->en,' . $this->editingId,
                'slug' => 'required|string|max:255|unique:majors,slug,' . $this->editingId,
                'code' => 'nullable|string|max:50',
                'order' => 'nullable|integer|min:0',
            ];
        }

        return [
            'name_vi' => 'required|string|max:255|unique:majors,name->vi',
            'name_en' => 'required|string|max:255|unique:majors,name->en',
            'slug' => 'required|string|max:255|unique:majors,slug',
            'code' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0',
        ];
    }

    protected $messages = [
        'name_vi.required' => 'Tên chuyên ngành (Tiếng Việt) không được để trống.',
        'name_en.required' => 'Tên chuyên ngành (Tiếng Anh) không được để trống.',
        'slug.required' => 'Slug không được để trống.',
        'name_vi.unique' => 'Tên chuyên ngành (Tiếng Việt) đã tồn tại.',
        'name_en.unique' => 'Tên chuyên ngành (Tiếng Anh) đã tồn tại.',
        'slug.unique' => 'Slug đã tồn tại.',
        'code.max' => 'Mã chuyên ngành không được vượt quá 50 ký tự.',
        'order.integer' => 'Thứ tự phải là một số nguyên.',
        'order.min' => 'Thứ tự phải lớn hơn hoặc bằng 0.',
        'code.string' => 'Mã chuyên ngành phải là một chuỗi ký tự.',
    ];

    public function openCreate(): void
    {
        $this->resetCreateForm();
        $this->order = Major::max('order') + 1;
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
        $this->ValidateOnly($property);
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
        $major = Major::findOrFail($id);
        $this->editingId = $major->id;
        $this->name_vi = $major->getTranslation('name', 'vi', true) ?: '';
        $this->name_en = $major->getTranslation('name', 'en', true) ?: '';
        $this->slug = $major->slug;
        $this->code = $major->code ?: '';
        $this->order = $major->order;
        $this->is_active = $major->is_active;
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

        Major::create([
            'name' => ['vi' => $this->name_vi, 'en' => $this->name_en ?: ''],
            'slug' => $this->slug ?: Str::slug($this->name_vi),
            'code' => $this->code ?: null,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->showCreate = false;
        $this->success('Tạo chuyên ngành thành công');
        $this->resetPage();
    }

    public function update(): void
    {
        if (!$this->editingId) {
            $this->error('Không tìm thấy chuyên ngành để cập nhật.');
            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin.');
            throw $e;
        }

        $major = Major::findOrFail($this->editingId);
        $major->update([
            'name' => ['vi' => $this->name_vi, 'en' => $this->name_en ?: ''],
            'slug' => $this->slug ?: Str::slug($this->name_vi),
            'code' => $this->code,
            'order' => $this->order,
            'is_active' => $this->is_active,
        ]);

        $this->showEdit = false;
        $this->success('Cập nhật chuyên ngành thành công');
        $this->resetPage();
    }

    public function headers(): array
    {
        return [
            ['key' => 'order', 'label' => 'STT', 'class' => 'w-5'],
            ['key' => 'name', 'label' => 'Tên chuyên ngành', 'class' => 'w-94'],
            ['key' => 'code', 'label' => 'Mã', 'class' => 'w-24'],
//            ['key' => 'is_active', 'label' => 'Kích hoạt', 'class' => 'w-20'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-28'],
        ];
    }

    public function delete($id)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa chuyên ngành này không?',
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
        $major = Major::withCount('students')->findOrFail($id);
        if ($major->students_count > 0) {
            $this->error('Chuyên ngành đang có sinh viên, không thể xóa.');
            return;
        }

        $major->delete();
        $this->success('Đã xóa chuyên ngành thành công.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <x-slot:title>Danh sách chuyên ngành</x-slot:title>
    <x-slot:breadcrumb>
        Quản lý chuyên ngành
    </x-slot:breadcrumb>
    <x-header title="Danh sách chuyên ngành" separator>
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
        <x-table :headers="$this->headers()" :rows="$this->majors" :sort-by="$this->sortBy" with-pagination
                 per-page="perPage"
                 class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
            "
        >

            @scope('cell_order', $major)
            {{ ($this->majors->currentPage() - 1) * $this->majors->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $major)
            <div class="font-medium">{{ $major->getTranslation('name', 'vi', true) }}</div>
            <div class="text-sm text-gray-400">{{ $major->getTranslation('name', 'en', true) }}</div>
            @endscope

            @scope('cell_code', $major)
            {{ $major->code ?? '—' }}
            @endscope

            {{--            @scope('cell_is_active', $major)--}}
            {{--            @if($major->is_active)--}}
            {{--                <x-badge value="Kích hoạt" class="badge-success"/>--}}
            {{--            @else--}}
            {{--                <x-badge value="Tắt" class="badge-error"/>--}}
            {{--            @endif--}}
            {{--            @endscope--}}

            @scope('cell_actions', $major)
            <div class="flex space-x-2">
                <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary"
                          wire:click="openEdit({{ $major->id }})"/>
                <x-button icon="o-trash" class="btn-sm btn-ghost text-danger" wire:click="delete({{ $major->id }})"/>
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-user" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có chuyên ngành nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->majors" wire:model.live="perPage"/>
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
    <x-modal wire:model="showCreate" title="Tạo chuyên ngành" separator class="modalMajor">
        <div class="space-y-3">
            <x-input label="Tên (Tiếng Việt)" wire:model.live.debounce.300ms="name_vi" required
                     placeholder="Nhập tên chuyên ngành Tiếng Việt "/>
            <x-input label="Tên (Tiếng Anh)" wire:model.live.debounce.300ms="name_en"
                     placeholder="Nhập tên chuyên ngành Tiếng Anh "/>
            <x-input label="Slug" wire:model.live.debounce.500ms="slug" placeholder="Nhập đường dẫn slug"/>
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Mã ngành" wire:model.live.debounce.300ms="code" placeholder="Nhập mã chuyên ngành"/>
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
    <x-modal wire:model="showEdit" title="Chỉnh sửa chuyên ngành" separator class="modalMajor">
        <div class="space-y-3">
            <x-input label="Tên (Tiếng Việt)" wire:model.live.debounce.300ms="name_vi" required
                     placeholder="Nhập tên chuyên ngành Tiếng Việt "/>
            <x-input label="Tên (Tiếng Anh)" wire:model.live.debounce.300ms="name_en"
                     placeholder="Nhập tên chuyên ngành Tiếng Anh "/>
            <x-input label="Slug" wire:model.live.debounce.500ms="slug" placeholder="Nhập đường dẫn slug"/>
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Mã ngành" wire:model.live.debounce.300ms="code" placeholder="Nhập mã chuyên ngành"/>
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



