<?php

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination;
    use Toast;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public int $perPage = 10;

    public string $search = '';

    public function getUsersProperty()
    {
        return User::query()
            ->with(['roles', 'student', 'lecturer']) // Quan trọng: Gọi sẵn dữ liệu họ hàng
            ->when($this->search, function ($query) {
                $query->where(function($q) {
                    // Tìm trong bảng users
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')

                        // Tìm lấn sang bảng students (Mã SV)
                        ->orWhereHas('student', function($subQuery) {
                            $subQuery->where('student_code', 'like', '%' . $this->search . '%');
                        })

                        // Tìm lấn sang bảng lecturers (Mã CB)
                        ->orWhereHas('lecturer', function($subQuery) {
                            $subQuery->where('staff_code', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    // Cấu hình lại các cột cho bảng
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'user_info', 'label' => 'Người dùng', 'sortable' => false],
            ['key' => 'user_code', 'label' => 'Mã định danh', 'sortable' => false],
            ['key' => 'roles', 'label' => 'Vai trò', 'sortable' => false, 'class' => 'w-48'],
            ['key' => 'is_active', 'label' => 'Trạng thái'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function delete($id)
    {
        // Ví dụ hàm xóa
        // User::find($id)?->delete();
        // $this->success('Đã xóa người dùng thành công!');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
}
?>

<div>
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Quản lý người dùng') }}
    </x-slot:title>
    {{--  end - title  --}}

    {{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
        <span>{{__('Quản lý người dùng')}}</span>
    </x-slot:breadcrumb>
    {{-- end - breadcrumb --}}

    {{--    start - header--}}
    <x-header title="Danh sách người dùng"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm tên, email, mã SV/CB..."
                wire:model.live.debounce.300ms="search"
                clearable="true"
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" class="btn-primary text-white" label="{{__('Create new')}}" link="{{route('admin.user.create')}}"/>
        </x-slot:actions>
    </x-header>
    {{--    end - header--}}

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->users"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[5, 10, 20, 25, 50]"
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
            {{-- Cột 1: STT --}}
            @scope('cell_id', $user)
            {{ ($this->users->currentPage() - 1) * $this->users->perPage() + $loop->iteration }}
            @endscope

            {{-- Cột 2: Gom Avatar, Tên và Email --}}
            @scope('cell_user_info', $user)
            <div class="flex items-center gap-3 text-left w-full">
                <x-avatar :image="$user->avatar ? asset($user->avatar) :'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=random'" class="w-10! h-10!" />
                <div class="flex flex-col items-start">
                    <span class="font-semibold text-gray-800">{{ $user->name }}</span>
                    <span class="text-sm text-gray-500">{{ $user->email }}</span>
                </div>
            </div>
            @endscope

            {{-- Cột 3: Mã Sinh viên / Mã Cán bộ --}}
            @scope('cell_user_code', $user)
            <span class="font-medium text-gray-700">
                @if($user->user_type === 'student')
                    {{ $user->student->student_code ?? '-' }}
                @elseif($user->user_type === 'lecturer')
                    {{ $user->lecturer->staff_code ?? '-' }}
                @else
                    -
                @endif
                </span>
            @endscope

            {{-- Cột 4: Hiển thị các Role thành các huy hiệu (Badge) --}}
            @scope('cell_roles', $user)
            <div class="flex flex-wrap gap-1">
                @forelse($user->roles as $role)
                    @php
                        // Tự động gán màu tùy theo chức vụ
                        $color = match($role->display_name) {
                            'Super Admin' => 'badge-error',
                            'Ban Chủ Nhiệm Khoa' => 'badge-warning',
                            'Giảng viên' => 'badge-info',
                            'Sinh viên' => 'badge-success',
                            default => 'badge-neutral'
                        };
                    @endphp
                    <x-badge :value="$role->display_name" class="{{ $color }} badge-sm font-semibold" />
                @empty
                    <span class="text-gray-400 text-sm">Chưa có</span>
                @endforelse
            </div>
            @endscope

            {{-- Cột 5: Trạng thái Hoạt động/Bị khóa --}}
            @scope('cell_is_active', $user)
            @if($user->is_active)
                <x-badge value="Hoạt động" class="badge-success badge-outline badge-sm" />
            @else
                <x-badge value="Đã khóa" class="badge-error badge-outline badge-sm" />
            @endif
            @endscope

            {{-- Cột 6: Hành động --}}
            @scope('cell_actions', $user)
            <div class="flex space-x-2 justify-center">
                <x-button
                    icon="o-pencil"
                    class="btn-sm btn-ghost text-primary [&]:hover:bg-gray-200/40 [&]:hover:border-gray-400/70"
                    tooltip="Chỉnh Sửa"
                    link="{{route('admin.user.edit', $user->id)}}"
                />

                <x-button
                    icon="o-trash"
                    class="btn-sm btn-ghost text-danger [&]:hover:bg-gray-200/40 [&]:hover:border-gray-400/70"
                    tooltip="Xóa"
                    wire:click="delete({{ $user->id }})"
                />
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-users" class="w-10 h-10 text-gray-400 mx-auto" />
                    <p class="mt-2 text-gray-500">Chưa có người dùng nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->users" wire:model.live="perPage"/>
        </x-table>
        <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg" />
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>
