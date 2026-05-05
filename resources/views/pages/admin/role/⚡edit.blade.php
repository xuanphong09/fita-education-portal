<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Mary\Traits\Toast;
use Illuminate\Support\Str;
use App\Models\Category;

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

    /**
     * Get all permissions (both general and category-scoped)
     */
    public function getPermissionsProperty()
    {
        return Permission::select('id', 'name', 'display_name')->get();
    }

    /**
     * Get only general permissions (those without ':' in the name)
     */
    public function getGeneralPermissionsProperty()
    {
        return $this->permissions->filter(fn($p) => !Str::contains($p->name, ':'));
    }

    /**
     * Get only category-scoped permissions (those with ':' in the name)
     */
    public function getCategoryScopedPermissionsProperty()
    {
        return $this->permissions->filter(fn($p) => Str::contains($p->name, ':'));
    }

    /**
     * Parse category-scoped permissions and group them by category
     */
    /**
     * Lấy danh sách quyền và sắp xếp theo thứ tự Cha - Con
     */
    public function getCategoryPermissionsProperty()
    {
        $grouped = [];
        foreach ($this->categoryScopedPermissions as $permission) {
            preg_match('/^(.+):(\d+)$/', $permission->name, $matches);
            if (!empty($matches)) {
                $permissionType = $matches[1];
                $categoryId = (int)$matches[2];

                if (!isset($grouped[$categoryId])) {
                    $grouped[$categoryId] = ['permissions' => []];
                }

                $grouped[$categoryId]['permissions'][$permissionType] = [
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                ];
            }
        }

        $categories = Category::orderBy('order')->get();

        // Ép kiểu sang Collection ở đây
        return collect($this->buildRecursivePermissions($categories, null, $grouped));
    }

    /**
     * Hàm đệ quy để sắp xếp permission theo cấu trúc cha-con
     */
    private function buildRecursivePermissions($categories, $parentId, $groupedPermissions, $depth = 0): array
    {
        $results = [];

        foreach ($categories->where('parent_id', $parentId) as $category) {
            if (isset($groupedPermissions[$category->id])) {
                $data = $groupedPermissions[$category->id];

                $results[] = [
                    'category' => $category,
                    'permissions' => $data['permissions'],
                    'depth' => $depth,
                ];
            }

            $children = $this->buildRecursivePermissions($categories, $category->id, $groupedPermissions, $depth + 1);

            // $children lúc này chắc chắn là array nên array_merge sẽ không bị lỗi
            $results = array_merge($results, $children);
        }

        // Trả về array nguyên thủy
        return $results;
    }

    /**
     * Get all active categories (for displaying missing categories)
     */
    public function getActiveCategoriesProperty()
    {
        return Category::where('is_active', true)->orderBy('order')->get();
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
           class="font-semibold text-slate-700" wire:navigate>{{__('List of Roles and Permissions')}}</a>
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

            {{-- General Permissions Section --}}
            <div class="mt-6">
                <label class="font-semibold text-gray-700 mb-3 block">Quyền hạn chung</label>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-5 bg-gray-50/50 rounded-xl border border-gray-200 shadow-sm">
                    @forelse($this->generalPermissions as $permission)
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
                        <div class="col-span-full text-center py-4 text-gray-400 text-sm">
                            Không có quyền hạn chung nào.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Category-Scoped Permissions Section --}}
            <div class="mt-8">
                <div class="mb-4">
                    <label class="font-semibold mb-3 block">Quyền hạn theo danh mục bài viết</label>
                </div>

                @if($this->categoryPermissions->count() > 0)
                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b border-gray-200">
                                <tr class="divide-x divide-gray-200">
                                    <th class="px-4 py-3 text-left font-semibold">Danh mục</th>
                                    <th class="px-4 py-3 text-center font-semibold w-32">Viết bài</th>
                                    <th class="px-4 py-3 text-center font-semibold w-32">Duyệt bài</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($this->categoryPermissions as $categoryId => $categoryData)
                                    @php
                                        $category = $categoryData['category'];
                                        $permissions = $categoryData['permissions'];
                                        $writePermission = $permissions['viet_bai_viet'] ?? null;
                                        $reviewPermission = $permissions['duyet_bai_viet'] ?? null;
                                    @endphp
                                    <tr class="hover:bg-gray-50 divide-x divide-gray-200">
                                        <td class="px-4 py-2">
                                            <div class="flex items-center">
                                                <div>
                                                    <p class="font-medium text-gray-800">{{ $category->getTranslatedName() }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            @if($writePermission)
                                                <div class="flex justify-center">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="selectedPermissions"
                                                        value="{{ $writePermission['name'] }}"
                                                        class="checkbox checkbox-primary checkbox-sm"
                                                        :disabled="$name === 'super_admin'"
                                                    />
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-xs">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            @if($reviewPermission)
                                                <div class="flex justify-center">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="selectedPermissions"
                                                        value="{{ $reviewPermission['name'] }}"
                                                        class="checkbox checkbox-primary checkbox-sm"
                                                        :disabled="$name === 'super_admin'"
                                                    />
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-xs">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 bg-gray-50 rounded-xl border border-gray-200">
                        <p class="text-gray-400 text-sm">Chưa có danh mục nào trong hệ thống.</p>
                    </div>
                @endif
            </div>
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="Hành động" shadow separator progress-indicator="save">
            <x-button label="{{__('Save')}}" class="bg-primary text-white my-1 w-full" wire:click="save" spinner/>
        </x-card>
    </div>
</div>
