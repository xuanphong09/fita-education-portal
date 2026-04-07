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

    public string $bulkStatus = '';

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
        $this->bulkStatus = '';
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
            ['key' => 'select', 'label' => '', 'sortable' => false, 'class' => 'w-10'],
            ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
            ['key' => 'full_name', 'label' => 'Họ và tên'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Số điện thoại'],
            ['key' => 'subject', 'label' => 'Tiêu đề'],
            ['key' => 'status', 'label' => 'Trạng thái', 'class' => 'w-40'],
            ['key' => 'sent_at', 'label' => 'Thời gian gửi', 'class' => 'w-44'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-40'],
        ];
    }

    public function getMessagesProperty()
    {
        return ContactMessage::query()
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
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function getHasSelectionProperty(): bool
    {
        return count($this->selected) > 0;
    }

    protected function selectedMessagesQuery()
    {
        return ContactMessage::query()->whereIn('id', $this->selected);
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
//        $this->reset(['showDetail', 'selectedContactMessageId']);
//        $this->detailStatus = ContactMessage::STATUS_NEW;
//        $this->resetErrorBag('detailStatus');
        $this->showDetail = false;
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

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn muốn xóa tin nhắn liên hệ này?',
            'icon' => 'warning',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete(int $id): void
    {
        $message = ContactMessage::findOrFail($id);
        $wasNew = $message->status === ContactMessage::STATUS_NEW;
        $message->delete();
        $this->selected = array_values(array_filter($this->selected, fn ($selectedId) => (int) $selectedId !== $id));

        if ($wasNew) {
            $this->syncNewCountBadge(-1);
        }

        $this->success('Đã chuyển tin nhắn vào thùng rác.');
    }

    public function bulkDelete(): void
    {
        if (!$this->hasSelection) {
            $this->warning('Vui lòng chọn ít nhất một liên hệ.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Xóa các liên hệ đã chọn?',
            'icon' => 'warning',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkDelete',
        ]);
    }

    #[On('confirmBulkDelete')]
    public function confirmBulkDelete(): void
    {
        if (!$this->hasSelection) {
            return;
        }

        $newCount = (clone $this->selectedMessagesQuery())
            ->where('status', ContactMessage::STATUS_NEW)
            ->count();

        $count = $this->selectedMessagesQuery()->delete();
        $this->resetSelection();

        if ($newCount > 0) {
            $this->syncNewCountBadge(-$newCount);
        }

        $this->success("Đã chuyển {$count} liên hệ vào thùng rác.");
    }

    public function bulkUpdateStatus(): void
    {
        if (!$this->hasSelection) {
            $this->warning('Vui lòng chọn ít nhất một liên hệ.');
            return;
        }

        $allowed = array_keys(ContactMessage::statusOptions());
        if (!in_array($this->bulkStatus, $allowed, true)) {
            $this->warning('Vui lòng chọn trạng thái cần cập nhật.');
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Cập nhật trạng thái cho các liên hệ đã chọn?',
            'icon' => 'question',
            'confirmButtonText' => 'Cập nhật',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmBulkUpdateStatus',
        ]);
    }

    #[On('confirmBulkUpdateStatus')]
    public function confirmBulkUpdateStatus(): void
    {
        if (!$this->hasSelection || $this->bulkStatus === '') {
            return;
        }

        $query = $this->selectedMessagesQuery();
        $oldNewCount = (clone $query)->where('status', ContactMessage::STATUS_NEW)->count();
        $selectedCount = (clone $query)->count();

        $count = $this->selectedMessagesQuery()->update(['status' => $this->bulkStatus]);
        $this->resetSelection();

        $newNewCount = $this->bulkStatus === ContactMessage::STATUS_NEW ? $selectedCount : 0;
        $this->syncNewCountBadge($newNewCount - $oldNewCount);

        $this->success("Đã cập nhật trạng thái cho {$count} liên hệ.");
    }
};
?>

<div>
    <x-slot:title>Quản lý liên hệ</x-slot:title>

    <x-slot:breadcrumb>
        Quản lý liên hệ
    </x-slot:breadcrumb>

    <x-header title="Quản lý liên hệ" class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
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
            <x-button icon="o-trash" class="btn-ghost" label="Thùng rác" link="{{ route('admin.contact-message.trash') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
{{--        @if($this->hasSelection)--}}
{{--            <div class="px-4 py-3 border-b border-gray-200 bg-amber-50/70 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">--}}
{{--                <p class="text-sm text-slate-700">Đã chọn <span class="font-semibold">{{ count($selected) }}</span> liên hệ.</p>--}}
{{--                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">--}}
{{--                    <x-select--}}
{{--                        wire:model="bulkStatus"--}}
{{--                        placeholder="Chọn trạng thái"--}}
{{--                        placeholder-value=""--}}
{{--                        :options="$this->statusOptions"--}}
{{--                        option-value="id"--}}
{{--                        option-label="name"--}}
{{--                        class="w-full sm:w-52"--}}
{{--                    />--}}
{{--                    <x-button label="Cập nhật trạng thái" icon="o-check" class="btn-primary" wire:click="bulkUpdateStatus" spinner="bulkUpdateStatus"/>--}}
{{--                    <x-button label="Bỏ chọn" icon="o-x-mark" class="btn-ghost" wire:click="resetSelection"/>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        @endif--}}
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-md">
            <div class="flex items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selectPage">
                    <span>Chọn tất cả trong trang</span>
                </label>
                <p class="text-sm text-slate-700">Đã chọn <span class="font-semibold">{{ count($selected) }}</span> liên hệ.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-select
                    wire:model="bulkStatus"
                    placeholder="Chọn trạng thái"
                    placeholder-value=""
                    :options="$this->statusOptions"
                    option-value="id"
                    option-label="name"
                    class="w-full sm:w-52"
                    :disabled="count($selected) === 0"
                />
                <x-button label="Cập nhật trạng thái" icon="o-check" class="text-primary btn-ghost" wire:click="bulkUpdateStatus" spinner="bulkUpdateStatus" :disabled="count($selected) === 0"/>
                <x-button
                    icon="o-trash"
                    class="btn-ghost text-error"
                    label="Xóa đã chọn"
                    wire:click="bulkDelete"
                    spinner="bulkDelete"
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
            </div>
            @endscope

            @scope('cell_status', $item)
            @php($meta = \App\Models\ContactMessage::statusMeta($item->status))
            <x-badge :value="$meta['label']" class="{{ $meta['class'] }} badge-md text-white font-semibold whitespace-nowrap"/>
            @endscope

            @scope('cell_sent_at', $item)
            {{ $item->sent_at?->format('d/m/Y H:i') ?? $item->created_at?->format('d/m/Y H:i') }}
            @endscope

            @scope('cell_actions', $item)
            <div class="flex items-center gap-1">
                <x-button icon="o-eye" class="btn-sm btn-ghost text-primary" wire:click="openDetail({{ $item->id }})" tooltip="Xem chi tiết" spinner="openDetail({{ $item->id }})"/>
                <x-button icon="o-trash" class="btn-sm btn-ghost text-error" wire:click="delete({{ $item->id }})" spinner="delete({{ $item->id }})"/>
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
                wire:target="search, filterStatus, perPage, openDetail, confirmDelete, saveDetailStatus, bulkDelete, confirmBulkDelete, bulkUpdateStatus, confirmBulkUpdateStatus"
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
            <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 max-h-[70vh] overflow-y-auto pr-1">
                <div class="xl:col-span-3 space-y-4">
                    <x-card title="Thông tin liên hệ" class="p-4!" shadow>
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

