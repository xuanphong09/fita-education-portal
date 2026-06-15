<?php

use App\Models\Intake;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public int $perPage = 10;

    #[Url('search')]
    public string $search = '';

    public array $sortBy = [
        'column' => 'year_number',
        'direction' => 'asc',
    ];

    public bool $showCreate = false;
    public bool $showEdit = false;

    public ?int $editingId = null;

    public ?int $year_number = null;

    public function getIntakesProperty()
    {
        $query = Intake::query()->withCount('students');

        if (filled($this->search)) {
            $term = '%' . str_replace(
                    ['\\', '%', '_'],
                    ['\\\\', '\\%', '\\_'],
                    trim($this->search)
                ) . '%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('year_number', 'like', $term);
            });
        }

        $allowedSortColumns = [
            'name',
            'year_number',
            'created_at',
        ];

        $column = $this->sortBy['column'] ?? 'year_number';
        $direction = $this->sortBy['direction'] ?? 'asc';

        if (!in_array($column, $allowedSortColumns, true)) {
            $column = 'year_number';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        return $query
            ->orderBy($column, $direction)
            ->paginate($this->perPage);
    }

    protected function rules(): array
    {
        return [
            'year_number' => [
                'required',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('intakes', 'year_number')->ignore($this->editingId),
            ],
        ];
    }

    protected array $messages = [
        'year_number.required' => 'Số khóa không được để trống.',
        'year_number.integer' => 'Số khóa phải là số nguyên.',
        'year_number.min' => 'Số khóa không hợp lệ.',
        'year_number.max' => 'Số khóa không hợp lệ.',
        'year_number.unique' => 'Số khóa đã tồn tại, vui lòng chọn số khác.',
    ];

    public function updated($property): void
    {
        if ($property === 'year_number') {
            $this->validateOnly('year_number');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function getGeneratedNameProperty(): string
    {
        return $this->makeName($this->year_number);
    }

    private function makeName($yearNumber): string
    {
        if (blank($yearNumber)) {
            return '';
        }

        return 'K' . (int) $yearNumber;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showCreate = true;
    }

    public function openEdit(int $id): void
    {
        $intake = Intake::findOrFail($id);

        $this->editingId = $intake->id;
        $this->year_number = $intake->year_number;

        $this->resetErrorBag();
        $this->resetValidation();

        $this->showEdit = true;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->year_number = null;

        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function store(): void
    {
        try {
            $data = $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin.');
            throw $e;
        }

        $yearNumber = (int) $data['year_number'];

        Intake::create([
            'year_number' => $yearNumber,
            'name' => $this->makeName($yearNumber),
        ]);

        $this->showCreate = false;
        $this->resetForm();
        $this->resetPage();

        $this->success('Tạo khóa thành công.');
    }

    public function update(): void
    {
        if (!$this->editingId) {
            $this->error('Không tìm thấy khóa để cập nhật.');
            return;
        }

        try {
            $data = $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin.');
            throw $e;
        }

        $yearNumber = (int) $data['year_number'];

        $intake = Intake::findOrFail($this->editingId);

        $intake->update([
            'year_number' => $yearNumber,
            'name' => $this->makeName($yearNumber),
        ]);

        $this->showEdit = false;
        $this->resetForm();
        $this->resetPage();

        $this->success('Cập nhật khóa thành công.');
    }

    public function headers(): array
    {
        return [
            [
                'key' => 'stt',
                'label' => 'STT',
                'sortable' => false,
                'class' => 'w-5',
            ],
            [
                'key' => 'name',
                'label' => 'Tên khóa',
                'class' => 'w-64',
            ],
            [
                'key' => 'students_count',
                'label' => 'Số sinh viên',
                'class' => 'w-32',
            ],
            [
                'key' => 'actions',
                'label' => 'Hành động',
                'sortable' => false,
                'class' => 'w-28',
            ],
        ];
    }

    public function delete(int $id): void
    {
        $intake = Intake::query()
            ->withCount([
                'trainingPrograms',
                'students',
            ])
            ->findOrFail($id);

        if ($intake->training_programs_count > 0) {
            $this->error('Khóa này đang có chương trình đào tạo, không thể xóa.');
            return;
        }

        if ($intake->students_count > 0) {
            $this->error('Khóa này đang có sinh viên, không thể xóa.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa khóa này không?',
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
        $intake = Intake::query()
            ->withCount([
                'trainingPrograms',
                'students',
            ])
            ->findOrFail($id);

        if ($intake->training_programs_count > 0) {
            $this->error('Khóa này đang có chương trình đào tạo, không thể xóa.');
            return;
        }

        if ($intake->students_count > 0) {
            $this->error('Khóa này đang có sinh viên, không thể xóa.');
            return;
        }

        $intake->delete();

        $this->resetPage();

        $this->success('Đã xóa khóa thành công.');
    }
};
?>

<div>
    <x-slot:title>Danh sách khóa</x-slot:title>

    <x-slot:breadcrumb>
        Quản lý khóa
    </x-slot:breadcrumb>

    <x-header title="Danh sách khóa" separator>
        <x-slot:middle class="justify-end!">
            <x-input
                placeholder="Tìm tên khóa hoặc số khóa..."
                wire:model.live.debounce.300ms="search"
                class="w-full lg:w-96"
                clearable
            />
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                icon="o-plus"
                class="btn-primary text-white"
                label="Tạo mới"
                wire:click="openCreate"
                spinner="openCreate"
            />
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->intakes"
            :sort-by="$this->sortBy"
            with-pagination
            per-page="perPage"
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
            "
        >
            @scope('cell_stt', $intake)
                {{ ($this->intakes->currentPage() - 1) * $this->intakes->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $intake)
            <div class="font-medium">
                {{ $intake->name }}
            </div>
            @endscope

            @scope('cell_students_count', $intake)
                <div>
                    {{ $intake->students_count }}
                </div>
            @endscope

            @scope('cell_actions', $intake)
            <div class="flex space-x-2">
                <x-button
                    icon="o-pencil"
                    class="btn-sm btn-ghost text-primary"
                    wire:click="openEdit({{ $intake->id }})"
                    spinner="openEdit({{ $intake->id }})"
                />

                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-danger"
                    wire:click="delete({{ $intake->id }})"
                    spinner="delete({{ $intake->id }})"
                />
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-folder" class="w-10 h-10 text-gray-400 mx-auto" />
                    <p class="mt-2 text-gray-500">Không có khóa nào.</p>
                </div>
            </x-slot:empty>
        </x-table>

        <div
            wire:loading.flex
            class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300"
        >
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg" />
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>

    {{-- Modal Create --}}
    <x-modal wire:model="showCreate" title="Tạo khóa" separator>
        <div class="space-y-3">
            <x-input
                label="Số khóa"
                wire:model="year_number"
                type="number"
                min="1"
                required
                placeholder="Ví dụ: 67"
            />
        </div>

        <x-slot:actions>
            <x-button
                label="Hủy"
                wire:click="$wire.showCreate = false"
            />

            <x-button
                label="Lưu"
                class="btn-primary"
                wire:click="store"
                spinner="store"
            />
        </x-slot:actions>
    </x-modal>

    {{-- Modal Edit --}}
    <x-modal wire:model="showEdit" title="Chỉnh sửa khóa" separator>
        <div class="space-y-3">
            <x-input
                label="Số khóa"
                wire:model="year_number"
                type="number"
                min="1"
                required
                placeholder="Ví dụ: 67"
            />
        </div>

        <x-slot:actions>
            <x-button
                label="Hủy"
                wire:click="$wire.showEdit = false  "
            />

            <x-button
                label="Cập nhật"
                class="btn-primary"
                wire:click="update"
                spinner="update"
            />
        </x-slot:actions>
    </x-modal>
</div>
