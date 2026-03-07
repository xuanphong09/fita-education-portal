<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public int $perPage = 10;

    public string $search = '';

    public function getRolesProperty()
    {
        return Role::query()
            ->withCount('users') // Đếm số lượng người dùng liên quan đến mỗi vai trò
            ->with('permissions') // Gọi sẵn dữ liệu permissions để hiển thị
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('display_name', 'like', "%{$this->search}%");
            })
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'display_name', 'label' => 'Tên Vai trò', 'class' => 'font-medium text-primary w-48'],
            ['key' => 'users_count', 'label' => 'Số tài khoản', 'class' => 'w-32'],
            ['key' => 'permissions', 'label' => 'Các quyền hạn (Permissions)', 'sortable' => false],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function getPermissionsProperty()
    {
        return Permission::all();
    }

    public function delete($id)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa vai trò này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete($id)
    {
        $role = Role::findOrFail($id);
        if ($role->users_count > 0) {
            $this->error('Vai trò này đang được sử dụng.');
            return;
        }

        if($role->name === 'super_admin') {
            $this->error('Không thể xóa vai trò Super Admin.');
            return;
        }

        $role->delete();
        $this->success('Đã xóa vai trò thành công!');

    }
};
?>

<div>
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('List of Roles and Permissions') }}
    </x-slot:title>
    {{--  end - title  --}}

    {{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
        <span>{{__('List of Roles and Permissions')}}</span>
    </x-slot:breadcrumb>
    {{-- end - breadcrumb --}}

    {{--    start - header--}}
    <x-header title="{{__('List of Roles and Permissions')}}"
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
            <x-button icon="o-plus" class="btn-primary text-white" label="{{__('Create new')}}"
                      link="{{route('admin.role.create')}}"/>
        </x-slot:actions>

    </x-header>
    {{--    end - header--}}
    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->roles"
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

            @scope('cell_id', $user)
            {{ ($this->roles->currentPage() - 1) * $this->roles->perPage() + $loop->iteration }}
            @endscope

            {{-- Cột Số lượng tài khoản --}}
            @scope('cell_users_count', $role)
            <x-badge :value="$role->users_count . ' người'" class=""/>
            @endscope

            {{-- Cột Hiển thị các quyền (Badges) --}}
            @scope('cell_permissions', $role)
            <div class="flex flex-wrap gap-1">
                @if($this->permissions->count() > 0 && $role->permissions->count() === $this->permissions->count())
                    <x-badge value="Toàn quyền hệ thống" class="badge-error badge-outline badge-sm font-semibold"/>
                @else
                    @forelse($role->permissions as $permission)
                        <x-badge :value="$permission->display_name"
                                 class="badge-ghost badge-sm border-gray-300 text-gray-600"/>
                    @empty
                        <span class="text-xs text-gray-400 italic">Chưa được cấp quyền nào</span>
                    @endforelse
                @endif
            </div>
            @endscope

            {{-- Cột Hành động --}}
            @scope('cell_actions', $role)
            <div class="flex space-x-2">
                <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary" tooltip="Chỉnh sửa"
                          link="{{route('admin.role.edit',$role->id)  }}"/>

                @if($role->name !== 'Super Admin')
                    <x-button icon="o-trash" class="btn-sm btn-ghost text-danger" tooltip="Xóa"
                              wire:click="delete({{ $role->id }})"
                              spinner="delete({{ $role->id }})"
                    :hidden="$role->users_count > 0 || $role->name === 'sper_admin'"
                    />
                @endif
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-users" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Chưa có vai trò nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->roles" wire:model.live="perPage"/>
        </x-table>
        <div wire:loading.flex
             wire:target="search,perPage,sortBy,page"
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>
