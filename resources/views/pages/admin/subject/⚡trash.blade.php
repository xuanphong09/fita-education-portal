<?php

use App\Models\GroupSubject;
use App\Models\Subject;
use Illuminate\Database\QueryException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public int $perPage = 15;
    public string $search = '';
    public string $group_subject_id = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedGroupSubjectId(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function getGroupOptionsProperty(): array
    {
        return GroupSubject::query()
            ->orderBy('sort_order')
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') ASC")
            ->get()
            ->map(fn (GroupSubject $group) => [
                'id' => $group->id,
                'name' => $group->getTranslation('name', 'vi', false) ?: ('#' . $group->id),
            ])
            ->toArray();
    }

    public function getSubjectsProperty()
    {
        return Subject::onlyTrashed()
            ->with('groupSubject')
            ->withCount(['programSemesters', 'requiredBy'])
            ->search($this->search)
            ->when($this->group_subject_id !== '', fn ($query) => $query->where('group_subject_id', (int) $this->group_subject_id))
            ->orderByDesc('deleted_at')
            ->paginate($this->perPage);
    }

    public function restore(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục môn học này?',
            'icon' => 'question',
            'confirmButtonText' => 'Khôi phục',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRestore',
            'id' => $id,
        ]);
    }


    #[On('confirmRestore')]
    public function confirmRestore(int $id): void
    {
        $subject = Subject::onlyTrashed()->findOrFail($id);
        $subject->restore();

        $this->success('Đã khôi phục môn học thành công.');
    }

    public function forceDelete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn môn học này? Hành động không thể hoàn tác.',
            'icon' => 'warning',
            'confirmButtonText' => 'Xóa vĩnh viễn',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmForceDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmForceDelete')]
    public function confirmForceDelete(int $id): void
    {
        try {
            Subject::onlyTrashed()->findOrFail($id)->forceDelete();
            $this->success('Đã xóa vĩnh viễn môn học.');
        } catch (QueryException) {
            $this->error('Không thể xóa vĩnh viễn vì môn học vẫn còn ràng buộc dữ liệu.');
        }
    }
};
?>

<div>
    <x-slot:title>Thùng rác môn học</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.subject.index') }}" class="font-semibold text-slate-700">Môn học</a>
        <span class="mx-1">/</span>
        <span>Thùng rác</span>
    </x-slot:breadcrumb>

    <x-header title="Thùng rác môn học"
              subtitle="Có thể khôi phục trước khi xóa vĩnh viễn"
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
            <x-button icon="o-arrow-left" class="btn-ghost" label="Quay lại" link="{{ route('admin.subject.index') }}"/>
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
    </div>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="[
                ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
                ['key' => 'code', 'label' => 'Mã môn', 'class' => 'w-28'],
                ['key' => 'name', 'label' => 'Tên môn học', 'sortable' => false],
                ['key' => 'group_subject', 'label' => 'Nhóm môn', 'sortable' => false, 'class' => 'w-56'],
                ['key' => 'deleted_at', 'label' => 'Ngày xóa', 'class' => 'w-36'],
                ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-38'],
            ]"
            :rows="$this->subjects"
            striped
            :per-page-values="[10, 15, 25, 50]"
            per-page="perPage"
            with-pagination
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
            "
        >
            @scope('cell_id', $subject)
                {{ ($this->subjects->currentPage() - 1) * $this->subjects->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_code', $subject)
                <div class="font-mono font-semibold text-primary">{{ $subject->code }}</div>
            @endscope

            @scope('cell_name', $subject)
                <div class="font-semibold">{{ $subject->getTranslation('name', 'vi', false) ?: '—' }}</div>
                <div class="text-xs text-gray-400">{{ $subject->getTranslation('name', 'en', false) ?: 'Chưa có tên tiếng Anh' }}</div>
            @endscope

            @scope('cell_group_subject', $subject)
                {{ $subject->groupSubject?->getTranslation('name', 'vi', false) ?: '—' }}
            @endscope

            @scope('cell_deleted_at', $subject)
                {{ optional($subject->deleted_at)->format('d/m/Y H:i') }}
            @endscope

            @scope('cell_actions', $subject)
                <div class="flex gap-2">
                    <x-button
                        icon="o-arrow-uturn-left"
                        class="btn-sm btn-ghost text-success"
                        tooltip="Khôi phục"
                        wire:click="restore({{ $subject->id }})"
                        spinner="restore({{ $subject->id }})"
                    />
                    <x-button
                        icon="o-trash"
                        class="btn-sm btn-ghost text-error"
                        tooltip="Xóa vĩnh viễn"
                        wire:click="forceDelete({{ $subject->id }})"
                        spinner="forceDelete({{ $subject->id }})"
                    />
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-trash" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Thùng rác đang trống.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->subjects" wire:model.live="perPage"/>
        </x-table>
    </div>
</div>

