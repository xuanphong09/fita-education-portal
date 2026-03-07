<?php

use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Mary\Traits\Toast;
use Illuminate\Support\Str;

new class extends Component {
    use Toast;
    public string $display_name = '';
    public string $name = '';

    public array $selectedPermissions = [];

    #[Validate([
        'display_name' => 'required|string|max:255|unique:roles,display_name',
        'selectedPermissions' => 'array',
        'selectedPermissions.*' => 'exists:permissions,name',
    ], as: [
        'display_name' => 'Tên vai trò',
        'selectedPermissions' => 'Danh sách quyền',
    ],
        message: [
            'display_name.required' => 'Tên vai trò không được để trống.',
            'display_name.string' => 'Tên vai trò phải là một chuỗi.',
            'display_name.unique' => 'Tên vai trò đã tồn tại trong hệ thống.',
            'display_name.max' => 'Tên vai trò không được vượt quá 255 ký tự.',
            'selectedPermissions.array' => 'Danh sách quyền phải là một mảng.',
            'selectedPermissions.*.exists' => 'Quyền đã chọn không tồn tại trong hệ thống.',
        ]
    )]

    public function updated($property)
    {
        $this->validateOnly($property);
    }

    public function getPermissionsProperty()
    {
        return Permission::select('id','name','display_name')->get();
    }

    public function save()
    {
        try {
            $this->validate();
        }
        catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        $slug = Str::slug($this->display_name, '_');
        $count = Role::where('name', 'like', "{$slug}%")->count();
        $this->name = $count ? "{$slug}_{$count}" : $slug;

        $role = Role::create([
            'display_name' => $this->display_name,
            'name' => $this->name,
        ]);
        $role->syncPermissions($this->selectedPermissions);

        $this->success(
            'Tạo vai trò và cấp quyền thành công!',
            redirectTo: route('admin.role.index')
        );
    }
};
?>

<div>
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Create new roles') }}
    </x-slot:title>
    {{--  end - title  --}}

    {{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
        <a href="{{route('admin.role.index')}}"
           class="font-semibold text-slate-700">{{__('List of Roles and Permissions')}}</a>
        <span class="mx-1">/</span>
        <span>{{__('Create new roles')}}</span>
    </x-slot:breadcrumb>
    {{-- end - breadcrumb --}}

    {{--    start - header--}}
    <x-header title="{{__('Create new roles')}}"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"></x-header>
    {{--    end - header--}}
    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        <x-card class="col-span-10 flex flex-col p-3!">
            <x-input label="Tên vai trò" wire:model.live.debounce.500ms="display_name" required/>
            <div class="mt-4">
                <label class="font-semibold text-gray-700 mb-3 block">Danh sách quyền hạn (Permissions)</label>

                <div
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-5 bg-gray-50/50 rounded-xl border border-gray-200 shadow-sm">
                    @forelse($this->permissions as $permission)
                        <div class="select-none" wire:key="permission-{{ $permission->id }}">
                            <x-checkbox
                                label="{{ $permission->display_name }}"
                                wire:model="selectedPermissions"
                                value="{{ $permission->name }}"
                                class="checkbox-primary checkbox-sm"
                            />
                        </div>
                    @empty
                        <div class="col-span-full text-center py-4 text-red-500">
                            Hệ thống chưa có quyền nào.
                        </div>
                    @endforelse
                </div>
            </div>
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="Hành động" shadow separator progress-indicator="save">
            <x-button label="Lưu" class="bg-primary text-white my-1 w-full" wire:click="save" spinner/>
            <x-button label="Trở lại" class="bg-warning text-white my-1 w-full" link="{{route('admin.role.index')}}" />
        </x-card>
    </div>
</div>
