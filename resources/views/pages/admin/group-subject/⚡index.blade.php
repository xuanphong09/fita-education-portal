<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\GroupSubject;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'sort_order', 'direction' => 'asc'];
    public int $perPage = 15;
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getGroupsProperty()
    {
        $query = GroupSubject::query()->withCount('subjects');

        if (trim($this->search) !== '') {
            $keyword = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')) like ?", [$keyword])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) like ?", [$keyword]);
            });
        }

        $query->orderBy(...array_values($this->sortBy));

        return $query->paginate($this->perPage);
    }

    public function headers(): array
    {
        return [
            ['key' => 'id',             'label' => '#',           'class' => 'w-12'],
            ['key' => 'name',           'label' => 'Tên nhóm',    'sortable' => false],
            ['key' => 'subjects_count', 'label' => 'Số môn',      'class' => 'w-24'],
            ['key' => 'sort_order',     'label' => 'Thứ tự',      'class' => 'w-24'],
            ['key' => 'is_active',      'label' => 'Kích hoạt',   'class' => 'w-28'],
            ['key' => 'actions',        'label' => 'Hành động',   'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title'             => 'Bạn có chắc chắn muốn xóa nhóm môn học này?',
            'icon'              => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText'  => 'Hủy',
            'method'            => 'confirmDelete',
            'id'                => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete(int $id): void
    {
        $group = GroupSubject::withCount('subjects')->findOrFail($id);

        if ($group->subjects_count > 0) {
            $this->error('Nhóm môn học đang chứa môn học, không thể xóa.');
            return;
        }

        $group->delete();
        $this->success('Đã xóa nhóm môn học thành công.');
    }

    public function toggleActive(int $id): void
    {
        $group = GroupSubject::findOrFail($id);
        $group->update(['is_active' => !$group->is_active]);
        $this->success($group->is_active ? 'Đã kích hoạt nhóm môn học.' : 'Đã tắt nhóm môn học.');
    }
};
?>

<div>
    <x-slot:title>Quản lý nhóm môn học</x-slot:title>

    <x-slot:breadcrumb>
        <span>Quản lý nhóm môn học</span>
    </x-slot:breadcrumb>

    <x-header title="Quản lý nhóm môn học"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo tên nhóm..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button
                icon="o-plus"
                class="btn-primary text-white"
                label="Tạo nhóm mới"
                link="{{ route('admin.group-subject.create') }}"
            />
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->groups"
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
            @scope('cell_id', $group)
                {{ ($this->groups->currentPage() - 1) * $this->groups->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $group)
                <div class="font-semibold">{{ $group->getTranslation('name', 'vi', false) ?: '—' }}</div>
                <div class="text-xs text-gray-400">{{ $group->getTranslation('name', 'en', false) ?: 'No EN name' }}</div>
                @if($group->getTranslation('description', 'vi', false))
                    <div class="text-xs text-gray-500 mt-1 line-clamp-1">
                        {{ $group->getTranslation('description', 'vi', false) }}
                    </div>
                @endif
            @endscope

            @scope('cell_subjects_count', $group)
                <x-badge :value="$group->subjects_count . ' môn'" class="badge-neutral badge-sm" />
            @endscope

            @scope('cell_sort_order', $group)
                <x-badge :value="$group->sort_order" class="badge-ghost badge-sm" />
            @endscope

            @scope('cell_is_active', $group)
                <button wire:click="toggleActive({{ $group->id }})" class="cursor-pointer">
                    @if($group->is_active)
                        <x-badge value="Kích hoạt" class="badge-success badge-sm" />
                    @else
                        <x-badge value="Tắt" class="badge-error badge-sm" />
                    @endif
                </button>
            @endscope

            @scope('cell_actions', $group)
                <div class="flex gap-2">
                    <x-button
                        icon="o-pencil"
                        class="btn-sm btn-ghost text-primary"
                        tooltip="Chỉnh sửa"
                        link="{{ route('admin.group-subject.edit', $group->id) }}"
                    />
                    <x-button
                        icon="o-trash"
                        class="btn-sm btn-ghost text-error"
                        tooltip="Xóa"
                        wire:click="delete({{ $group->id }})"
                        spinner="delete({{ $group->id }})"
                    />
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-8">
                    <x-icon name="o-rectangle-stack" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Chưa có nhóm môn học nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->groups" wire:model.live="perPage"/>
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

