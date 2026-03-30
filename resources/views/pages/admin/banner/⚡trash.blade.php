<?php

use App\Models\Banner;
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

    #[Url(as: 'search')]
    public string $search = '';

    public int $perPage = 10;
    public bool $selectPage = false;
    public array $selected = [];

    public function headers(): array
    {
        return [
            ['key' => 'select', 'label' => '', 'sortable' => false, 'class' => 'w-12'],
            ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
            ['key' => 'image', 'label' => 'Ảnh', 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'title', 'label' => 'Tiêu đề'],
            ['key' => 'position', 'label' => 'Vị trí', 'class' => 'w-36'],
            ['key' => 'deleted_at', 'label' => 'Xóa lúc', 'class' => 'w-40'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-36'],
        ];
    }

    public function getBannersProperty()
    {
        $query = Banner::onlyTrashed();

        if (trim($this->search) !== '') {
            $this->applySearchFilter($query, trim($this->search));
        }

        return $query->orderByDesc('deleted_at')->paginate($this->perPage);
    }

    protected function applySearchFilter($query, string $search): void
    {
        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $keyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

            $query->where(function ($q) use ($keyword) {
                $q->whereRaw(
                    "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                    [$keyword]
                )
                ->orWhereRaw(
                    "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                    [$keyword]
                )
                ->orWhere('position', 'like', $keyword)
                ->orWhere('url', 'like', $keyword);
            });
        }
    }

    protected function selectedBannersQuery()
    {
        return Banner::onlyTrashed()->whereIn('id', array_map('intval', $this->selected));
    }

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
            $this->selected = $this->banners->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            return;
        }

        $this->selected = [];
    }

    public function updatedSelected(): void
    {
        $currentIds = $this->banners->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $selectedInPage = array_intersect($currentIds, array_map('intval', $this->selected));
        $this->selectPage = count($currentIds) > 0 && count($selectedInPage) === count($currentIds);
    }

    protected function resetSelection(): void
    {
        $this->selectPage = false;
        $this->selected = [];
    }

    public function restore(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục banner này?',
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
        Banner::onlyTrashed()->findOrFail($id)->restore();
        $this->success('Đã khôi phục banner thành công.');
        $this->resetSelection();
    }

    public function forceDelete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn banner này? Hành động không thể hoàn tác.',
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
            $banner = Banner::onlyTrashed()->findOrFail($id);

            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }

            $banner->forceDelete();
            $this->success('Đã xóa vĩnh viễn banner.');
            $this->resetSelection();
        } catch (QueryException) {
            $this->error('Không thể xóa vĩnh viễn do dữ liệu đang được sử dụng.');
        }
    }

    public function bulkRestore(): void
    {
        if (count($this->selected) === 0) {
            $this->warning('Vui lòng chọn ít nhất 1 banner để khôi phục.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục các banner đã chọn?',
            'icon' => 'question',
            'confirmButtonText' => 'Khôi phục',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkRestore',
        ]);
    }

    #[On('confirmBulkRestore')]
    public function confirmBulkRestore(): void
    {
        $restored = $this->selectedBannersQuery()->restore();
        $this->resetSelection();
        $this->success("Đã khôi phục {$restored} banner.");
    }

    public function bulkForceDelete(): void
    {
        if (count($this->selected) === 0) {
            $this->warning('Vui lòng chọn ít nhất 1 banner để xóa vĩnh viễn.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn các banner đã chọn? Hành động không thể hoàn tác.',
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
            $banners = $this->selectedBannersQuery()->get();

            foreach ($banners as $banner) {
                if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                    Storage::disk('public')->delete($banner->image);
                }
            }

            $deleted = $this->selectedBannersQuery()->forceDelete();
            $this->resetSelection();
            $this->success("Đã xóa vĩnh viễn {$deleted} banner.");
        } catch (QueryException) {
            $this->error('Không thể xóa vĩnh viễn do dữ liệu đang được sử dụng.');
        }
    }
};
?>

<div>
    <x-slot:title>Thùng rác banner</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.banner.index') }}" class="font-semibold text-slate-700">Danh sách banner</a>
        <span class="mx-1">/</span>
        <span>Thùng rác</span>
    </x-slot:breadcrumb>

    <x-header
        title="Thùng rác banner"
        subtitle="Có thể khôi phục trước khi xóa vĩnh viễn"
        class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"
    >
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo tiêu đề..."
                wire:model.live.debounce.300ms="search"
                clearable
                class="w-full lg:w-96"
            />
        </x-slot:middle>

        <x-slot:actions>
            <x-button icon="o-arrow-left" class="btn-ghost" label="Quay lại" link="{{ route('admin.banner.index') }}"/>
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
            :rows="$this->banners"
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
            @scope('cell_select', $banner)
                <input
                    type="checkbox"
                    class="checkbox checkbox-sm"
                    value="{{ $banner->id }}"
                    wire:model.live="selected"
                />
            @endscope

            @scope('cell_id', $banner)
                {{ ($this->banners->currentPage() - 1) * $this->banners->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_image', $banner)
                @if($banner->image && Storage::disk('public')->exists($banner->image))
                    <img src="{{ Storage::url($banner->image) }}" alt="Banner" class="w-10 h-10 rounded object-cover ring-1 ring-gray-200"/>
                @else
                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center ring-1 ring-gray-200">
                        <x-icon name="o-photo" class="w-5 h-5 text-gray-400"/>
                    </div>
                @endif
            @endscope

            @scope('cell_title', $banner)
                <div class="font-medium line-clamp-1">{{ $banner->getTranslation('title', 'vi', false) ?: '—' }}</div>
                <div class="text-xs text-gray-400 line-clamp-1">{{ $banner->getTranslation('title', 'en', false) ?: '' }}</div>
            @endscope

            @scope('cell_position', $banner)
                <span class="whitespace-nowrap">{{ \App\Models\Banner::POSITIONS[$banner->position] ?? $banner->position }}</span>
            @endscope

            @scope('cell_deleted_at', $banner)
                {{ optional($banner->deleted_at)->format('d/m/Y H:i') }}
            @endscope

            @scope('cell_actions', $banner)
                <div class="flex gap-2">
                    <x-button
                        icon="o-arrow-uturn-left"
                        class="btn-sm btn-ghost text-success"
                        tooltip="Khôi phục"
                        wire:click="restore({{ $banner->id }})"
                    />
                    <x-button
                        icon="o-trash"
                        class="btn-sm btn-ghost text-error"
                        tooltip="Xóa vĩnh viễn"
                        wire:click="forceDelete({{ $banner->id }})"
                    />
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-trash" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Thùng rác đang trống.</p>
                </div>
            </x-slot:empty>
        </x-table>

        <div wire:loading.flex class="absolute inset-0 z-5 items-center justify-center bg-white/40 backdrop-blur-sm rounded-md">
            <x-loading class="text-primary loading-lg"/>
        </div>
    </div>
</div>

