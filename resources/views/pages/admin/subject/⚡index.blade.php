<?php

use App\Models\GroupSubject;
use App\Models\Subject;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'code', 'direction' => 'asc'];
    public int $perPage = 15;
    #[Url(as: 'search')]
    public string $search = '';
    public string $group_subject_id = '';
    public string $filterActive = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedGroupSubjectId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterActive(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->group_subject_id = '';
        $this->filterActive = '';
        $this->resetPage();
    }

    public function getHasActiveFiltersProperty(): bool
    {
        return trim($this->search) !== ''
            || $this->group_subject_id !== ''
            || $this->filterActive !== '';
    }

    public function getGroupOptionsProperty(): array
    {
        return GroupSubject::query()
            ->orderBy('sort_order')
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') ASC")
            ->get()
            ->map(fn(GroupSubject $group) => [
                'id' => $group->id,
                'name' => $group->getTranslation('name', 'vi', false) ?: ('#' . $group->id),
            ])
            ->toArray();
    }

    public function getSubjectsProperty()
    {
        return Subject::query()
            ->with('groupSubject')
            ->withCount(['programSemesters', 'prerequisites', 'requiredBy'])
            ->search($this->search)
            ->when($this->group_subject_id !== '', fn($query) => $query->where('group_subject_id', (int)$this->group_subject_id))
            ->when($this->filterActive !== '', fn($query) => $query->where('is_active', $this->filterActive === '1'))
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
            ['key' => 'code', 'label' => 'Mã môn', 'class' => 'w-28'],
            ['key' => 'name', 'label' => 'Tên môn học', 'sortable' => false, 'class' => 'min-w-68'],
            ['key' => 'group_subject', 'label' => 'Nhóm môn', 'sortable' => false, 'class' => 'w-56'],
            ['key' => 'credits', 'label' => 'Tín chỉ', 'class' => 'w-32'],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'class' => 'w-28'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-28'],
        ];
    }

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa môn học này?',
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
        $subject = Subject::query()
            ->withCount(['programSemesters', 'requiredBy'])
            ->findOrFail($id);

        if ($subject->program_semesters_count > 0) {
            $this->error('Môn học đang được dùng trong học kỳ của CTDT, chưa thể xóa.');
            return;
        }

        if ($subject->required_by_count > 0) {
            $this->error('Môn học đang là tiên quyết của môn khác, chưa thể xóa.');
            return;
        }

        $subject->delete();
        $this->success('Đã chuyển môn học vào thùng rác.');
    }

    public function toggleActive(int $id): void
    {
        $subject = Subject::query()->findOrFail($id);
        $subject->update(['is_active' => !$subject->is_active]);

        $this->success($subject->is_active ? 'Đã kích hoạt môn học.' : 'Đã tắt môn học.');
    }
};
?>

<div>
    <x-slot:title>Quản lý môn học</x-slot:title>

    <x-slot:breadcrumb>
        <span>Quản lý môn học</span>
    </x-slot:breadcrumb>

    <x-header title="Quản lý môn học"
              subtitle="Danh sách môn học dùng chung cho CTDT"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo mã môn, tên môn hoặc nhóm môn..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-trash" class="btn-ghost" label="Thùng rác" link="{{ route('admin.subject.trash') }}"/>
            <x-button icon="o-plus" class="btn-primary text-white" label="Tạo môn học"
                      link="{{ route('admin.subject.create') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="flex flex-wrap gap-3 mb-4">
        <x-select
            placeholder="Lọc theo nhóm môn"
            placeholder-value=""
            wire:model.live="group_subject_id"
            :options="$this->groupOptions"
            option-value="id"
            option-label="name"
            class="select-md w-56"
        />

        <x-select
            placeholder="Tất cả trạng thái"
            placeholder-value=""
            wire:model.live="filterActive"
            :options="[
                ['id' => '1', 'name' => 'Đang kích hoạt'],
                ['id' => '0', 'name' => 'Đang tắt'],
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
            :rows="$this->subjects"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[10, 15, 25, 50]"
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
            @scope('cell_id', $subject)
            {{ ($this->subjects->currentPage() - 1) * $this->subjects->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_code', $subject)
            <div class="font-mono font-semibold text-primary">{{ $subject->code }}</div>
            <div class="text-sm text-gray-400">ID: {{ $subject->id }}</div>
            @endscope

            @scope('cell_name', $subject)
            <div class="font-semibold">{{ $subject->getTranslation('name', 'vi', false) ?: '—' }}</div>
            <div
                class="text-sm text-gray-400">{{ $subject->getTranslation('name', 'en', false) ?: 'Chưa có tên tiếng Anh' }}</div>
            @endscope

            @scope('cell_group_subject', $subject)
            @if($subject->groupSubject)
                <div>{{ $subject->groupSubject->getTranslation('name', 'vi', false) ?: '—' }}</div>
                <div
                    class="text-sm text-gray-400">{{ $subject->groupSubject->getTranslation('name', 'en', false) ?: '' }}</div>
            @else
                <x-badge value="Chưa phân nhóm" class="badge-ghost badge-md font-semibold whitespace-nowrap"/>
            @endif
            @endscope

            @scope('cell_credits', $subject)
            <div class="font-semibold">{{ $subject->credits_display }} tín chỉ</div>
            <div class="text-sm text-gray-500">LT/TH: {{ $subject->credits_theory_display }}
                / {{ $subject->credits_practice_display }}</div>
            @endscope

            @scope('cell_is_active', $subject)
            <button wire:click="toggleActive({{ $subject->id }})" class="cursor-pointer">
                @if($subject->is_active)
                    <x-badge value="Kích hoạt"
                             class="badge-success badge-md text-white font-semibold whitespace-nowrap"/>
                @else
                    <x-badge value="Đang tắt" class="badge-error badge-md text-white font-semibold whitespace-nowrap"/>
                @endif
            </button>
            @endscope

            @scope('cell_actions', $subject)
            <div class="flex gap-2">
                <x-button
                    icon="o-pencil"
                    class="btn-sm btn-ghost text-primary"
                    tooltip="Chỉnh sửa"
                    link="{{ route('admin.subject.edit', $subject->id) }}"
                />
                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-error"
                    tooltip="Xóa"
                    wire:click="delete({{ $subject->id }})"
                    spinner="delete({{ $subject->id }})"
                />
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-8">
                    <x-icon name="o-academic-cap" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Chưa có môn học nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->subjects" wire:model.live="perPage"/>
        </x-table>

        <div wire:loading.flex
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>

