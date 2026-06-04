<?php

use App\Models\Subject;
use App\Models\SubjectEquivalent;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'subject_id', 'direction' => 'asc'];
    public int $perPage = 15;
    #[Url(as: 'search')]
    public string $search = '';

    public function updatedSearch(): void
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
        $this->resetPage();
    }

    public function getHasActiveFiltersProperty(): bool
    {
        return trim($this->search) !== '';
    }

    public function getEquivalentsProperty()
    {
        return SubjectEquivalent::query()
            ->with(['subject', 'equivalentSubject'])
            ->whereRaw('subject_id < equivalent_subject_id')
            ->when(trim($this->search) !== '', function ($query) {
                $keyword = '%' . trim($this->search) . '%';
                $query->whereHas('subject', function ($q) use ($keyword) {
                    $q->where('code', 'like', $keyword)
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')) LIKE ?", [$keyword])
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) LIKE ?", [$keyword]);
                })
                ->orWhereHas('equivalentSubject', function ($q) use ($keyword) {
                    $q->where('code', 'like', $keyword)
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')) LIKE ?", [$keyword])
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) LIKE ?", [$keyword]);
                });
            })
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
            ['key' => 'subject', 'label' => 'Môn học', 'sortable' => false, 'class' => 'min-w-68'],
            ['key' => 'equivalent', 'label' => 'Môn tương đương', 'sortable' => false, 'class' => 'min-w-68'],
//            ['key' => 'created_at', 'label' => 'Ngày tạo', 'class' => 'w-40'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-28'],
        ];
    }

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa mối quan hệ tương đương này?',
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
        $equivalent = SubjectEquivalent::query()->findOrFail($id);

        // Get both subjects involved in the relationship
        $subjectId = $equivalent->subject_id;
        $equivalentSubjectId = $equivalent->equivalent_subject_id;

        // Delete both directions of the relationship to maintain symmetry
        SubjectEquivalent::query()
            ->where(function ($q) use ($subjectId, $equivalentSubjectId) {
                $q->where('subject_id', $subjectId)
                    ->where('equivalent_subject_id', $equivalentSubjectId);
            })
            ->orWhere(function ($q) use ($subjectId, $equivalentSubjectId) {
                $q->where('subject_id', $equivalentSubjectId)
                    ->where('equivalent_subject_id', $subjectId);
            })
            ->delete();

        $this->success('Đã xóa mối quan hệ tương đương.');
    }
};
?>

<div>
    <x-slot:title>Quản lý môn tương đương</x-slot:title>

    <x-slot:breadcrumb>
        <span>Quản lý môn tương đương</span>
    </x-slot:breadcrumb>

    <x-header title="Quản lý môn tương đương"
              subtitle="Danh sách các cặp môn học tương đương"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo mã môn hoặc tên môn..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            @if($this->hasActiveFilters)
                <x-button
                    label="Xóa bộ lọc"
                    icon="o-funnel"
                    class="btn-outline btn-error"
                    wire:click="resetFilters"
                    spinner="resetFilters"
                />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->equivalents"
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
            @scope('cell_id', $equivalent)
            {{ ($this->equivalents->currentPage() - 1) * $this->equivalents->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_subject', $equivalent)
            <div class="font-mono font-semibold text-primary">{{ $equivalent->subject->code }}</div>
            <div class="font-semibold">{{ $equivalent->subject->getTranslation('name', 'vi', false) ?: '—' }}</div>
            <div class="text-sm text-gray-400">{{ $equivalent->subject->credits_display }} tín chỉ</div>
            @endscope

            @scope('cell_equivalent', $equivalent)
            <div class="flex items-center gap-2">
{{--                <x-icon name="o-arrow-right" class="w-4 h-4 text-gray-400" />--}}
                <div>
                    <div class="font-mono font-semibold text-primary">{{ $equivalent->equivalentSubject->code }}</div>
                    <div class="font-semibold">{{ $equivalent->equivalentSubject->getTranslation('name', 'vi', false) ?: '—' }}</div>
                    <div class="text-sm text-gray-400">{{ $equivalent->equivalentSubject->credits_display }} tín chỉ</div>
                </div>
            </div>
            @endscope

{{--            @scope('cell_created_at', $equivalent)--}}
{{--            <div class="font-semibold">{{ $equivalent->created_at->format('d/m/Y') }}</div>--}}
{{--            <div class="text-sm text-gray-400">{{ $equivalent->created_at->format('H:i') }}</div>--}}
{{--            @endscope--}}

            @scope('cell_actions', $equivalent)
            <div class="flex gap-2">
                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-error"
                    tooltip="Xóa"
                    wire:click="delete({{ $equivalent->id }})"
                    spinner="delete({{ $equivalent->id }})"
                />
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-8">
                    <x-icon name="o-academic-cap" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Chưa có cặp môn tương đương nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->equivalents" wire:model.live="perPage"/>
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


