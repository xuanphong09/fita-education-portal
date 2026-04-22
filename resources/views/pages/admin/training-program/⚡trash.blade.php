<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\TrainingProgram;
use Illuminate\Database\QueryException;

new class extends Component {
    use WithPagination, Toast;

    public int $perPage = 10;
    #[Url(as: 'search')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getProgramsProperty()
    {
        $query = TrainingProgram::onlyTrashed()
            ->with(['major', 'intake'])
            ->orderByDesc('deleted_at');

        if (trim($this->search) !== '') {
            $keyword = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('version', 'like', $keyword)
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$keyword]);
            });
        }

        return $query->paginate($this->perPage);
    }

    public function restore(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục chương trình đào tạo này?',
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
        $program = TrainingProgram::onlyTrashed()->findOrFail($id);

        try {
            $program->restore();
            $this->success('Đã khôi phục CTDT thành công.');
        } catch (QueryException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $this->error('Không thể khôi phục CTDT này do trùng phiên bản với một CTDT đang tồn tại. Vui lòng chỉnh sửa phiên bản của CTDT hiện tại trước khi khôi phục.');
                return;
            }

            throw $e;
        }
    }

    public function forceDelete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn CTDT này? Hành động không thể hoàn tác.',
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
        $program = TrainingProgram::onlyTrashed()->findOrFail($id);
        $program->forceDelete();

        $this->success('Đã xóa vĩnh viễn CTDT.');
    }
};
?>

<div>
    <x-slot:title>Thùng rác CTDT</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.training-program.index') }}" class="font-semibold text-slate-700" wire:navigate>Quản lý CTDT</a>
        <span class="mx-1">/</span>
        <span>Thùng rác</span>
    </x-slot:breadcrumb>

    <x-header title="Thùng rác chương trình đào tạo"
              subtitle="Có thể khôi phục trước khi xóa vĩnh viễn"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo phiên bản hoặc tên..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-arrow-left" class="btn-ghost" label="Quay lại"
                      link="{{ route('admin.training-program.index') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="[
                ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
                ['key' => 'version', 'label' => 'Phiên bản', 'class' => 'w-28'],
                ['key' => 'name', 'label' => 'Tên chương trình', 'sortable' => false],
                ['key' => 'scope', 'label' => 'Phạm vi', 'sortable' => false],
                ['key' => 'deleted_at', 'label' => 'Ngày xóa', 'class' => 'w-40'],
                ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-40'],
            ]"
            :rows="$this->programs"
            striped
            :per-page-values="[5, 10, 20, 50]"
            per-page="perPage"
            with-pagination
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
            "
        >
            @scope('cell_id', $program)
            {{ ($this->programs->currentPage() - 1) * $this->programs->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $program)
            <div class="font-semibold">{{ $program->getTranslation('name', 'vi', false) ?: '—' }}</div>
            <div class="text-xs text-gray-400">{{ $program->getTranslation('name', 'en', false) ?: '' }}</div>
            @endscope

            @scope('cell_scope', $program)
            <div class="text-sm">
                <div><span
                        class="text-gray-500">{{ __('Major:') }}</span> {{ $program->major?->getTranslation('name', app()->getLocale(), false) ?: $program->major?->getTranslation('name', 'vi', false) ?: $program->major?->getTranslation('name', 'en', false) ?: __('General') }}
                </div>
                <div><span class="text-gray-500">Khóa:</span> {{ $program->intake?->name ?? '—' }}</div>
            </div>
            @endscope

            @scope('cell_deleted_at', $program)
            {{ optional($program->deleted_at)->format('d/m/Y H:i') }}
            @endscope

            @scope('cell_actions', $program)
            <div class="flex gap-2">
                <x-button
                    icon="o-arrow-uturn-left"
                    class="btn-sm btn-ghost text-success"
                    tooltip="Khôi phục"
                    wire:click="restore({{ $program->id }})"
                    spinner="restore({{ $program->id }})"
                />
                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-error"
                    tooltip="Xóa vĩnh viễn"
                    wire:click="forceDelete({{ $program->id }})"
                    spinner="forceDelete({{ $program->id }})"
                />
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-trash" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Thùng rác đang trống.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->programs" wire:model.live="perPage"/>
        </x-table>
    </div>
</div>

