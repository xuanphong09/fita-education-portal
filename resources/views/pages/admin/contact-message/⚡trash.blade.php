<?php

use App\Models\ContactMessage;
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

    public string $filterStatus = '';

    public int $perPage = 10;

    public bool $showDetail = false;

    public ?int $selectedContactMessageId = null;

    public string $detailStatus = ContactMessage::STATUS_NEW;

    public bool $selectPage = false;

    public array $selected = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatedSelectPage(bool $value): void
    {
        if ($value) {
            $this->selected = $this->messages->pluck('id')->map(fn ($id) => (int) $id)->all();
            return;
        }

        $this->selected = [];
    }

    public function updatedSelected(): void
    {
        $this->selected = collect($this->selected)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->selectPage = count($this->selected) > 0
            && count($this->selected) === $this->messages->count();
    }

    public function resetSelection(): void
    {
        $this->selectPage = false;
        $this->selected = [];
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(ContactMessage::statusOptions())
            ->map(fn (array $meta, string $key) => ['id' => $key, 'name' => $meta['label']])
            ->values()
            ->all();
    }

    public function headers(): array
    {
        return [
            ['key' => 'select', 'label' => '', 'sortable' => false, 'class' => 'w-12'],
            ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
            ['key' => 'full_name', 'label' => 'Họ và tên'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'subject', 'label' => 'Tiêu đề'],
            ['key' => 'status', 'label' => 'Trạng thái', 'class' => 'w-40'],
            ['key' => 'deleted_at', 'label' => 'Đã xóa lúc', 'class' => 'w-44'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-48'],
        ];
    }

    public function getMessagesProperty()
    {
        return ContactMessage::onlyTrashed()
            ->when(trim($this->search) !== '', function ($query) {
                $keyword = '%' . trim($this->search) . '%';
                $query->where(function ($q) use ($keyword) {
                    $q->where('full_name', 'like', $keyword)
                        ->orWhere('email', 'like', $keyword)
                        ->orWhere('phone', 'like', $keyword)
                        ->orWhere('subject', 'like', $keyword)
                        ->orWhere('message', 'like', $keyword);
                });
            })
            ->when($this->filterStatus !== '', fn ($query) => $query->where('status', $this->filterStatus))
            ->orderByDesc('deleted_at')
            ->paginate($this->perPage);
    }

    public function getHasSelectionProperty(): bool
    {
        return count($this->selected) > 0;
    }

    protected function selectedMessagesQuery()
    {
        return ContactMessage::onlyTrashed()->whereIn('id', $this->selected);
    }

    public function getSelectedContactMessageProperty(): ?ContactMessage
    {
        if (!$this->selectedContactMessageId) {
            return null;
        }

        return ContactMessage::withTrashed()->find($this->selectedContactMessageId);
    }

    protected function syncNewCountBadge(int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $this->dispatch('contact-message:new-count-changed', delta: $delta);
    }

    public function openDetail(int $id): void
    {
        $message = ContactMessage::withTrashed()->findOrFail($id);

        $this->selectedContactMessageId = $message->id;
        $this->detailStatus = $message->status ?: ContactMessage::STATUS_NEW;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->reset(['showDetail', 'selectedContactMessageId']);
        $this->detailStatus = ContactMessage::STATUS_NEW;
        $this->resetErrorBag('detailStatus');
    }

    public function saveDetailStatus(): void
    {
        $this->validate([
            'detailStatus' => 'required|in:' . implode(',', array_keys(ContactMessage::statusOptions())),
        ]);

        $message = $this->selectedContactMessage;

        if (!$message) {
            return;
        }

        $previousStatus = $message->status;
        $message->update(['status' => $this->detailStatus]);

        if ($previousStatus !== $this->detailStatus) {
            $delta = 0;

            if ($previousStatus === ContactMessage::STATUS_NEW && $this->detailStatus !== ContactMessage::STATUS_NEW) {
                $delta = -1;
            }

            if ($previousStatus !== ContactMessage::STATUS_NEW && $this->detailStatus === ContactMessage::STATUS_NEW) {
                $delta = 1;
            }

            $this->syncNewCountBadge($delta);
        }

        $this->success('Đã cập nhật trạng thái liên hệ.');
    }

    public function restore(int $id): void
    {
        $message = ContactMessage::onlyTrashed()->findOrFail($id);
        $isNew = $message->status === ContactMessage::STATUS_NEW;

        $message->restore();
        $this->selected = array_values(array_filter($this->selected, fn ($selectedId) => (int) $selectedId !== $id));

        if ($isNew) {
            $this->syncNewCountBadge(1);
        }

        $this->success('Đã khôi phục tin nhắn liên hệ.');
    }

    public function bulkRestore(): void
    {
        if (!$this->hasSelection) {
            $this->warning('Vui lòng chọn ít nhất một liên hệ.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Khôi phục các liên hệ đã chọn?',
            'icon' => 'question',
            'confirmButtonText' => 'Khôi phục',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkRestore',
        ]);
    }

    #[On('confirmBulkRestore')]
    public function confirmBulkRestore(): void
    {
        if (!$this->hasSelection) {
            return;
        }

        $newCount = (clone $this->selectedMessagesQuery())
            ->where('status', ContactMessage::STATUS_NEW)
            ->count();

        $count = $this->selectedMessagesQuery()->restore();
        $this->resetSelection();

        if ($newCount > 0) {
            $this->syncNewCountBadge($newCount);
        }

        $this->success("Đã khôi phục {$count} liên hệ.");
    }

    public function forceDelete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn tin nhắn này? Hành động không thể hoàn tác.',
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
        ContactMessage::onlyTrashed()->findOrFail($id)->forceDelete();
        $this->selected = array_values(array_filter($this->selected, fn ($selectedId) => (int) $selectedId !== $id));
        $this->success('Đã xóa vĩnh viễn tin nhắn liên hệ.');
    }

    public function bulkForceDelete(): void
    {
        if (!$this->hasSelection) {
            $this->warning('Vui lòng chọn ít nhất một liên hệ.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Xóa vĩnh viễn các liên hệ đã chọn? Hành động không thể hoàn tác.',
            'icon' => 'warning',
            'confirmButtonText' => 'Xóa vĩnh viễn',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkForceDelete',
        ]);
    }

    #[On('confirmBulkForceDelete')]
    public function confirmBulkForceDelete(): void
    {
        if (!$this->hasSelection) {
            return;
        }

        $count = $this->selectedMessagesQuery()->forceDelete();
        $this->resetSelection();

        $this->success("Đã xóa vĩnh viễn {$count} liên hệ.");
    }
};
?>

<div>
    <x-slot:title>Thùng rác liên hệ</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.contact-message.index') }}" class="font-semibold text-slate-700">Quản lý liên hệ</a>
        <span class="mx-1">/</span>
        <span>Thùng rác</span>
    </x-slot:breadcrumb>

    <x-header title="Thùng rác liên hệ" class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <div class="flex w-full flex-col gap-2 lg:w-auto lg:flex-row lg:items-center lg:justify-end">
                <x-input
                    icon="o-magnifying-glass"
                    placeholder="Tìm theo họ tên, email, số điện thoại..."
                    wire:model.live.debounce.300ms="search"
                    clearable
                    class="w-full lg:w-96"
                />

                <x-select
                    wire:model.live="filterStatus"
                    placeholder="Tất cả trạng thái"
                    placeholder-value=""
                    :options="$this->statusOptions"
                    option-value="id"
                    option-label="name"
                    class="w-full lg:w-56"
                />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-arrow-left" class="btn-ghost" label="Quay lại" link="{{ route('admin.contact-message.index') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-md">
            <div class="flex items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selectPage">
                    <span>Chọn tất cả trong trang</span>
                </label>
                <p class="text-sm text-slate-700">Đã chọn <span class="font-semibold">{{ count($selected) }}</span> liên hệ.</p>
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
            :rows="$this->messages"
            :per-page-values="[10, 20, 50]"
            per-page="perPage"
            with-pagination
            class="
                bg-white
                [&_th]:text-left [&_th]:bg-white [&_th]:text-black! [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
            "
        >
            @scope('cell_select', $item)
            <x-checkbox wire:model.live="selected" value="{{ $item->id }}" class="checkbox-sm checkbox-primary"/>
            @endscope

            @scope('cell_id', $item)
            {{ ($this->messages->currentPage() - 1) * $this->messages->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_subject', $item)
            <div>
                <p class="font-semibold line-clamp-1">{{ $item->subject }}</p>
                <p class="text-xs text-gray-500 line-clamp-1">{{ $item->message }}</p>
            </div>
            @endscope

            @scope('cell_status', $item)
            @php($meta = \App\Models\ContactMessage::statusMeta($item->status))
            <x-badge :value="$meta['label']" class="{{ $meta['class'] }} badge-md text-white font-semibold whitespace-nowrap"/>
            @endscope

            @scope('cell_deleted_at', $item)
            {{ $item->deleted_at?->format('d/m/Y H:i') }}
            @endscope

            @scope('cell_actions', $item)
            <div class="flex items-center gap-1">
                <x-button icon="o-eye" class="btn-sm btn-ghost text-primary" wire:click="openDetail({{ $item->id }})" tooltip="Xem chi tiết" spinner="openDetail({{ $item->id }})"/>
                <x-button icon="o-arrow-uturn-left" class="btn-sm btn-ghost text-success" wire:click="restore({{ $item->id }})" spinner="restore({{ $item->id }})"/>
                <x-button icon="o-trash" class="btn-sm btn-ghost text-error" wire:click="forceDelete({{ $item->id }})" spinner="forceDelete({{ $item->id }})"/>
            </div>
            @endscope
            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-chat-bubble-bottom-center-text" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có liên hệ nào.</p>
                </div>
            </x-slot:empty>
        </x-table>

        <div wire:loading.flex
             wire:target="search, filterStatus, perPage, openDetail, restore, forceDelete, confirmForceDelete, bulkRestore, confirmBulkRestore, bulkForceDelete, confirmBulkForceDelete, saveDetailStatus"
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>

    <x-modal wire:model="showDetail" title="Chi tiết liên hệ" size="5xl" separator class="modalContactMessage">
        @if($this->selectedContactMessage)
            @php($message = $this->selectedContactMessage)
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 max-h-[70vh] overflow-y-auto pr-1">
                <div class="xl:col-span-2 space-y-4">
                    <x-card title="Thông tin liên hệ" shadow class="p-4!">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">Họ và tên</p>
                                <p class="font-semibold text-gray-900">{{ $message->full_name }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="font-semibold text-gray-900">{{ $message->email }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Số điện thoại</p>
                                <p class="font-semibold text-gray-900">{{ $message->phone ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Tiêu đề</p>
                                <p class="font-semibold text-gray-900">{{ $message->subject }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Ngôn ngữ</p>
                                <p class="font-semibold text-gray-900">{{ strtoupper($message->locale ?: '—') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Thời gian gửi</p>
                                <p class="font-semibold text-gray-900">{{ $message->sent_at?->format('d/m/Y H:i') ?? $message->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </x-card>

                    <x-card title="Nội dung" shadow class="p-4!">
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-[15px]/[24px] whitespace-pre-line text-slate-700">
                            {{ $message->message }}
                        </div>
                    </x-card>
                </div>

                <div class="space-y-6">
                    <x-card title="Trạng thái" shadow class="p-4!">
                        <div class="space-y-4">
                            @php($meta = \App\Models\ContactMessage::statusMeta($detailStatus))
                            <x-badge :value="$meta['label']" class="{{ $meta['class'] }} badge-md text-white font-semibold whitespace-nowrap"/>

                            <x-select
                                label="Cập nhật trạng thái"
                                wire:model="detailStatus"
                                :options="$this->statusOptions"
                                option-value="id"
                                option-label="name"
                            />

                            <x-button
                                label="Lưu trạng thái"
                                icon="o-check"
                                class="bg-primary text-white w-full"
                                wire:click="saveDetailStatus"
                                spinner="saveDetailStatus"
                            />

                            @if($message->trashed())
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 text-sm">
                                    Bản ghi này đang nằm trong thùng rác.
                                </div>
                            @endif
                        </div>
                    </x-card>
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-button label="Đóng" @click="$wire.showDetail = false"/>
        </x-slot:actions>
    </x-modal>
</div>


