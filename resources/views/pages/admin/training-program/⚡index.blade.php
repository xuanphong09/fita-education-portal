<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\Major;
use App\Models\Intake;
use App\Models\TrainingProgram;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'updated_at', 'direction' => 'desc'];
    public int $perPage = 10;
    public string $search = '';
    public ?int $major_id = null;
    public ?int $intake_id = null;
    public string $filterStatus = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMajorId(): void
    {
        $this->resetPage();
    }

    public function updatedIntakeId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->major_id = null;
        $this->intake_id = null;
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function getHasActiveFiltersProperty(): bool
    {
        return trim($this->search) !== ''
            || !is_null($this->major_id)
            || !is_null($this->intake_id)
            || $this->filterStatus !== '';
    }

    public function getProgramsProperty()
    {
        $search = trim($this->search);

        $query = TrainingProgram::query()
            ->with(['major', 'intake'])
            ->withCount('semesters');

        if ($search !== '') {
            $keyword = '%' . $search . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('version', 'like', $keyword)
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$keyword]);
            });
        }

        if ($this->major_id) {
            $query->where('major_id', $this->major_id);
        }

        if ($this->intake_id) {
            $query->where('intake_id', $this->intake_id);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $query->orderBy(...array_values($this->sortBy));

        return $query->paginate($this->perPage);
    }

    public function getMajorOptionsProperty(): array
    {
        return Major::query()
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get(['id', 'name', 'slug'])
            ->map(function (Major $major) {
                return [
                    'id' => $major->id,
                    'name' => $major->getTranslation('name', app()->getLocale(), false)
                        ?: $major->getTranslation('name', 'vi', false)
                        ?: $major->getTranslation('name', 'en', false)
                        ?: $major->slug,
                ];
            })
            ->toArray();
    }

    public function getIntakeOptionsProperty(): array
    {
        return Intake::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-8'],
            ['key' => 'version', 'label' => 'Phiên bản', 'class' => 'w-15'],
            ['key' => 'name', 'label' => 'Tên chương trình', 'sortable' => false],
            ['key' => 'scope', 'label' => 'Phạm vi', 'sortable' => false],
            ['key' => 'total_credits', 'label' => 'Tổng TC', 'class' => 'w-10'],
            ['key' => 'status', 'label' => 'Trạng thái', 'class' => 'w-34'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa chương trình đào tạo này?',
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
        $program = TrainingProgram::findOrFail($id);
        $program->delete();

        $this->success('Đã chuyển CTDT vào thùng rác.');
    }
};
?>

<div>
    <x-slot:title>Quản lý chương trình đào tạo</x-slot:title>

    <x-slot:breadcrumb>
        <span>Quản lý chương trình đào tạo</span>
    </x-slot:breadcrumb>

    <x-header title="Quản lý chương trình đào tạo"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
                <x-input
                    icon="o-magnifying-glass"
                    placeholder="Tìm theo phiên bản hoặc tên..."
                    wire:model.live.debounce.300ms="search"
                    clearable
                />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-trash" class="btn-ghost" label="Thùng rác" link="{{ route('admin.training-program.trash') }}"/>
            <x-button icon="o-plus" class="btn-primary text-white" label="Tạo mới" link="{{ route('admin.training-program.create') }}"/>
        </x-slot:actions>
    </x-header>
    <div class="flex flex-wrap gap-3 mb-4">
        <x-select
            placeholder="{{ __('Filter by major') }}"
            placeholder-value=""
            wire:model.live="major_id"
            :options="$this->majorOptions"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />

        <x-select
            placeholder="Lọc theo khóa"
            placeholder-value=""
            wire:model.live="intake_id"
            :options="$this->intakeOptions"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        <x-select
            wire:model.live="filterStatus"
            placeholder="Tất cả trạng thái"
            placeholder-value=""
            :options="[
                ['id'=>'draft',     'name'=>'Nháp'],
                ['id'=>'published', 'name'=>'Đã đăng'],
                ['id'=>'archived',  'name'=>'Lưu trữ'],
            ]"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        @if($this->hasActiveFilters)
            <x-button
                label="Xóa bộ lọc"
                icon="o-funnel"
                class="btn-outline btn-error"
                wire:click="resetFilters"
                spinner="resetFilters"
            />
        @endif
    </div>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->programs"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[5, 10, 20, 50]"
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
            @scope('cell_id', $program)
                {{ ($this->programs->currentPage() - 1) * $this->programs->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $program)
                <div class="font-semibold">{{ $program->getTranslation('name', 'vi', false) ?: '—' }}</div>
                <div class="text-sm text-gray-400">{{ $program->getTranslation('name', 'en', false) ?: '' }}</div>
            @endscope

            @scope('cell_scope', $program)
                <div class="text-sm">
                    <div><span class="text-gray-500">{{ __('Major:') }}</span> {{ $program->major?->getTranslation('name', app()->getLocale(), false) ?: $program->major?->getTranslation('name', 'vi', false) ?: $program->major?->getTranslation('name', 'en', false) ?: __('General') }}</div>
                    <div><span class="text-gray-500">Khóa:</span> {{ $program->intake?->name ?? '—' }}</div>
                </div>
            @endscope

            @scope('cell_version', $program)
                <div class="font-semibold" >{{$program->version}} </div>
            @endscope

            @scope('cell_total_credits', $program)
                <div>{{$program->total_credits . ' TC'}}</div>
            @endscope

            @scope('cell_status', $program)
                @if($program->status === 'published')
                    <x-badge value="Đã xuất bản" class="badge-success badge-md" />
                @elseif($program->status === 'archived')
                    <x-badge value="Lưu trữ" class="badge-error badge-md" />
                @else
                    <x-badge value="Nháp" class="badge-ghost badge-md" />
                @endif
            @endscope

            @scope('cell_actions', $program)
                <div class="flex space-x-2">
                    <x-button icon="o-calendar-days" class="btn-sm btn-ghost text-info" tooltip="Học kỳ & môn học" link="{{ route('admin.training-program.semesters', $program->id) }}"/>
                    <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary" tooltip="Chỉnh sửa" link="{{ route('admin.training-program.edit', $program->id) }}"/>
                    <x-button icon="o-trash" class="btn-sm btn-ghost text-danger" tooltip="Xóa" wire:click="delete({{ $program->id }})" spinner="delete({{ $program->id }})" />
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-academic-cap" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Chưa có chương trình đào tạo nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->programs" wire:model.live="perPage"/>
        </x-table>

        <div wire:loading.flex class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>


