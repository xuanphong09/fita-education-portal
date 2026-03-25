<?php

use App\Models\Partner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination, Toast;

    #[Url(as: 'tim-kiem')]
    public string $search = '';

    public int $perPage = 10;
    public bool $selectPage = false;
    public array $selected = [];

    public function updatedSearch(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatedSelectPage(bool $value): void
    {
        if ($value) {
            $this->selected = $this->partners->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            return;
        }

        $this->selected = [];
    }

    public function updatedSelected(): void
    {
        $currentIds = $this->partners->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $selectedInPage = array_intersect($currentIds, array_map('intval', $this->selected));
        $this->selectPage = count($currentIds) > 0 && count($selectedInPage) === count($currentIds);
    }

    protected function resetSelection(): void
    {
        $this->selectPage = false;
        $this->selected = [];
    }

    public function headers(): array
    {
        return [
            ['key' => 'select', 'label' => '', 'sortable' => false, 'class' => 'w-12'],
            ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
            ['key' => 'logo', 'label' => __('Logo'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'url', 'label' => 'URL', 'sortable' => false, 'class' => 'w-80'],
            ['key' => 'deleted_at', 'label' => __('Deleted at'), 'class' => 'w-40'],
            ['key' => 'actions', 'label' => __('Actions'), 'sortable' => false, 'class' => 'w-40'],
        ];
    }

    public function getPartnersProperty()
    {
        return Partner::onlyTrashed()
            ->when(trim($this->search) !== '', function ($query) {
                $keyword = '%' . trim($this->search) . '%';
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', $keyword)
                        ->orWhere('url', 'like', $keyword);
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate($this->perPage);
    }

    protected function selectedPartnersQuery()
    {
        return Partner::onlyTrashed()->whereIn('id', array_map('intval', $this->selected));
    }

    public function restore(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục đối tác này?',
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
        Partner::onlyTrashed()->findOrFail($id)->restore();
        $this->success('Đã khôi phục đối tác thành công.');
        $this->resetSelection();
    }

    public function forceDelete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn đối tác này? Hành động không thể hoàn tác.',
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
            $partner = Partner::onlyTrashed()->findOrFail($id);

            if ($partner->logo && Storage::disk('public')->exists($partner->logo)) {
                Storage::disk('public')->delete($partner->logo);
            }

            $partner->forceDelete();
            $this->success('Đã xóa vĩnh viễn đối tác.');
            $this->resetSelection();
        } catch (QueryException) {
            $this->error('Không thể xóa vĩnh viễn do dữ liệu đang được sử dụng.');
        }
    }

    public function bulkRestore(): void
    {
        if (count($this->selected) === 0) {
            $this->warning('Vui lòng chọn ít nhất 1 đối tác để khôi phục.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục các đối tác đã chọn?',
            'icon' => 'question',
            'confirmButtonText' => 'Khôi phục',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkRestore',
        ]);
    }

    #[On('confirmBulkRestore')]
    public function confirmBulkRestore(): void
    {
        $restored = $this->selectedPartnersQuery()->restore();
        $this->resetSelection();
        $this->success("Đã khôi phục {$restored} đối tác.");
    }

    public function bulkForceDelete(): void
    {
        if (count($this->selected) === 0) {
            $this->warning('Vui lòng chọn ít nhất 1 đối tác để xóa vĩnh viễn.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn các đối tác đã chọn? Hành động không thể hoàn tác.',
            'icon' => 'warning',
            'confirmButtonText' => 'Xóa vĩnh viễn',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkForceDelete',
        ]);
    }

    #[On('confirmBulkForceDelete')]
    public function confirmBulkForceDelete(): void
    {
        try {
            $partners = $this->selectedPartnersQuery()->get();

            foreach ($partners as $partner) {
                if ($partner->logo && Storage::disk('public')->exists($partner->logo)) {
                    Storage::disk('public')->delete($partner->logo);
                }
            }

            $deleted = $this->selectedPartnersQuery()->forceDelete();
            $this->resetSelection();
            $this->success("Đã xóa vĩnh viễn {$deleted} đối tác.");
        } catch (QueryException) {
            $this->error('Không thể xóa vĩnh viễn do dữ liệu đang được sử dụng.');
        }
    }
};
?>

<div>
    <x-slot:title>Thùng rác đối tác</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.partner.index') }}" class="font-semibold text-slate-700">Danh sách đối tác</a>
        <span class="mx-1">/</span>
        <span>Thùng rác</span>
    </x-slot:breadcrumb>

    <x-header title="Thùng rác đối tác"
              subtitle="Có thể khôi phục trước khi xóa vĩnh viễn"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo tên hoặc URL..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-arrow-left" class="btn-ghost" label="Quay lại" link="{{ route('admin.partner.index') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-md">
            <div class="flex items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selectPage">
                    <span>Chọn tất cả trong trang</span>
                </label>
                <span class="text-sm text-gray-500">Đã chọn: {{ count($selected) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <x-button
                    icon="o-arrow-uturn-left"
                    class="btn-sm btn-ghost text-success"
                    label="Khôi phục đã chọn"
                    wire:click="bulkRestore"
                    spinner="bulkRestore"
                    :disabled="count($selected) === 0"
                />
                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-error"
                    label="Xóa vĩnh viễn đã chọn"
                    wire:click="bulkForceDelete"
                    spinner="bulkForceDelete"
                    :disabled="count($selected) === 0"
                />
            </div>
        </div>

        <x-table
            :headers="$this->headers()"
            :rows="$this->partners"
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
            @scope('cell_select', $partner)
                <input
                    type="checkbox"
                    class="checkbox checkbox-sm"
                    value="{{ $partner->id }}"
                    wire:model.live="selected"
                />
            @endscope

            @scope('cell_id', $partner)
                {{ ($this->partners->currentPage() - 1) * $this->partners->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_logo', $partner)
                @if($partner->logo && Storage::disk('public')->exists($partner->logo))
                    <img src="{{ Storage::url($partner->logo) }}" alt="{{ $partner->name }}"
                         class="w-10 h-10 rounded object-cover ring-1 ring-gray-200"/>
                @else
                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center ring-1 ring-gray-200">
                        <x-icon name="o-photo" class="w-5 h-5 text-gray-400"/>
                    </div>
                @endif
            @endscope

            @scope('cell_name', $partner)
                <div class="font-medium">{{ $partner->name }}</div>
            @endscope

            @scope('cell_url', $partner)
                @if($partner->url)
                    <a href="{{ $partner->url }}" target="_blank" class="text-blue-600 hover:underline truncate block">
                        {{ $partner->url }}
                    </a>
                @else
                    <span class="text-gray-400">—</span>
                @endif
            @endscope

            @scope('cell_deleted_at', $partner)
                {{ optional($partner->deleted_at)->format('d/m/Y H:i') }}
            @endscope

            @scope('cell_actions', $partner)
                <div class="flex gap-2">
                    <x-button
                        icon="o-arrow-uturn-left"
                        class="btn-sm btn-ghost text-success"
                        tooltip="Khôi phục"
                        wire:click="restore({{ $partner->id }})"
                        spinner="restore({{ $partner->id }})"
                    />
                    <x-button
                        icon="o-trash"
                        class="btn-sm btn-ghost text-error"
                        tooltip="Xóa vĩnh viễn"
                        wire:click="forceDelete({{ $partner->id }})"
                        spinner="forceDelete({{ $partner->id }})"
                    />
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-trash" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Thùng rác đang trống.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->partners" wire:model.live="perPage"/>
        </x-table>
    </div>
</div>

