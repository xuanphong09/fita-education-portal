<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Mary\Traits\Toast;
use Illuminate\Support\Str;

new class extends Component {
    use Toast;

    public $id;
    public string $display_name = '';
    public string $name = '';
    public array $selectedPermissions = [];

    protected function rules()
    {
        return [
            'display_name' => 'required|string|max:255|unique:roles,display_name,' . $this->id . ',id',
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'exists:permissions,name',
        ];
    }

    protected $messages = [
        'display_name.required' => 'Tên vai trò không được để trống.',
        'display_name.string' => 'Tên vai trò phải là một chuỗi.',
        'display_name.max' => 'Tên vai trò không được vượt quá 255 ký tự.',
        'display_name.unique' => 'Tên vai trò đã tồn tại trong hệ thống.',
        'selectedPermissions.*.exists' => 'Quyền đã chọn không tồn tại.',
        'selectedPermissions.array' => 'Danh sách quyền phải là một mảng.',
    ];

    public function mount($id = null)
    {
        if ($id) {
            $this->id = $id;
            $role = Role::findOrFail($id);
            $this->display_name = $role->display_name ?? '';
            $this->name = $role->name ?? '';
            $this->selectedPermissions = $role->permissions()->pluck('name')->toArray();
        }
    }

    public function updated($property)
    {
        $this->validateOnly($property);
    }

    public function getPermissionsProperty()
    {
        return Permission::select('id', 'name', 'display_name')->get();
    }

    public function save()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        if ($this->name === 'super_admin') {
            $this->error('Không thể chỉnh sửa vai trò Super Admin.');
            return;
        }

        $slug = Str::slug($this->display_name, '_');
        $count = Role::where('name', 'like', "{$slug}%")->count();
        $name = $count ? "{$slug}_{$count}" : $slug;

        $role = Role::findOrFail($this->id);
        $role->update([
            'display_name' => $this->display_name,
            'name' => $name
        ]);
        $role->syncPermissions($this->selectedPermissions);
        $this->success(
            'Cập nhật vai trò thành công!',
        );

    }
};
?>

<div>
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Edit new roles') }}
    </x-slot:title>
    {{--  end - title  --}}

    {{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
        <a href="{{route('admin.role.index')}}"
           class="font-semibold text-slate-700">{{__('List of Roles and Permissions')}}</a>
        <span class="mx-1">/</span>
        <span>{{__('Edit new roles')}}</span>
    </x-slot:breadcrumb>
    {{-- end - breadcrumb --}}

    {{--    start - header--}}
    <x-header title="{{__('Edit new roles')}}"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"></x-header>
    {{--    end - header--}}
    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        <x-card class="col-span-10 flex flex-col p-3!">
            <x-input label="Tên vai trò" wire:model.live.debounce.500ms="display_name" required
                     :readonly="$name === 'super_admin' || $name === 'sinh_vien' || $name === 'giang_vien'"/>
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
                                :disabled="$name === 'super_admin'"
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
            <x-button label="{{__('Save')}}" class="bg-primary text-white my-1 w-full" wire:click="save" spinner/>
        </x-card>
    </div>
</div>
