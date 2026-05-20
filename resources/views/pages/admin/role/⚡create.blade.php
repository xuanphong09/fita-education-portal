<?php

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

    public string $display_name = '';
    public string $name = '';

    public array $selectedPermissions = [];
    public string $searchCategory = '';

    // Thêm các biến trạng thái giao diện (UI State)
    public bool $selectAllWrite = false;
    public bool $selectAllReview = false;
    public bool $selectAllManage = false;

    protected function rules()
    {
        return [
            'display_name' => 'required|string|max:255|unique:roles,display_name',
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'exists:permissions,name',
        ];
    }

    protected $messages = [
        'display_name.required' => 'Tên vai trò không được để trống.',
        'display_name.string' => 'Tên vai trò phải là một chuỗi.',
        'display_name.unique' => 'Tên vai trò đã tồn tại trong hệ thống.',
        'display_name.max' => 'Tên vai trò không được vượt quá 255 ký tự.',
        'selectedPermissions.array' => 'Danh sách quyền phải là một mảng.',
        'selectedPermissions.*.exists' => 'Quyền đã chọn không tồn tại trong hệ thống.',
    ];

    public function updated($property)
    {
        $this->validateOnly($property);
    }

    // Khi người dùng bấm "Tất cả Viết bài"
    public function updatedSelectAllWrite($value)
    {
        $writePerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'viet_bai_viet:'))->pluck('name')->toArray();
        if ($value) {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $writePerms)));
        } else {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $writePerms));
        }
        $this->syncSelectAllManage();
    }

    // Khi người dùng bấm "Tất cả Duyệt bài"
    public function updatedSelectAllReview($value)
    {
        $reviewPerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'duyet_bai_viet:'))->pluck('name')->toArray();
        if ($value) {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $reviewPerms)));
        } else {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $reviewPerms));
        }
        $this->syncSelectAllManage();
    }

    // Khi người dùng bấm "Quản lý bài viết"
    public function updatedSelectAllManage($value)
    {
        $writePerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'viet_bai_viet:'))->pluck('name')->toArray();
        $reviewPerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'duyet_bai_viet:'))->pluck('name')->toArray();

        if ($value) {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $writePerms, $reviewPerms)));
            $this->selectAllWrite = true;
            $this->selectAllReview = true;
        } else {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, array_merge($writePerms, $reviewPerms)));
            $this->selectAllWrite = false;
            $this->selectAllReview = false;
        }
    }

    // Khi người dùng bấm vào các ô con lẻ tẻ
    public function updatedSelectedPermissions()
    {
        $writePerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'viet_bai_viet:'))->pluck('name')->toArray();
        $reviewPerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'duyet_bai_viet:'))->pluck('name')->toArray();

        // Kiểm tra xem đã tích full chưa để bật/tắt ô Tất cả
        $this->selectAllWrite = count($writePerms) > 0 && count(array_intersect($writePerms, $this->selectedPermissions)) === count($writePerms);
        $this->selectAllReview = count($reviewPerms) > 0 && count(array_intersect($reviewPerms, $this->selectedPermissions)) === count($reviewPerms);

        $this->syncSelectAllManage();
    }
    public function getHasCategoryPermissionsProperty()
    {
        $writePerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'viet_bai_viet:'))->pluck('name')->toArray();
        $reviewPerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'duyet_bai_viet:'))->pluck('name')->toArray();
        $managePerms = ['viet_bai_viet', 'duyet_bai_viet', 'quan_ly_bai_viet'];

        $allArticlePerms = array_merge($writePerms, $reviewPerms, $managePerms);

        return count(array_intersect($this->selectedPermissions, $allArticlePerms)) > 0;
    }

    public function deselectAll()
    {
        $writePerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'viet_bai_viet:'))->pluck('name')->toArray();
        $reviewPerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'duyet_bai_viet:'))->pluck('name')->toArray();

        // 2. Gộp lại và xóa sạch khỏi mảng đang chọn
        $allCategoryPerms = array_merge($writePerms, $reviewPerms);
        $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $allCategoryPerms));

        // 3. Xóa luôn các quyền tổng liên quan
        $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, ['viet_bai_viet', 'duyet_bai_viet', 'quan_ly_bai_viet']));

        // 4. Tắt ô Checkbox "Quản lý bài viết" ở trên cùng
        $this->selectAllManage = false;
        $this->selectAllReview = false;
        $this->selectAllWrite = false;

        $this->success('Đã xóa bỏ toàn bộ quyền danh mục!');
    }

    private function syncSelectAllManage()
    {
        $this->selectAllManage = $this->selectAllWrite && $this->selectAllReview;
    }

    public function getPermissionsProperty()
    {
        return Permission::select('id','name','display_name')->get();
    }

    public function getGeneralPermissionsProperty()
    {
        return $this->permissions->filter(function($p) {
            // Loại trừ các quyền đặc biệt để hiển thị tay trên Blade
            return !Str::contains($p->name, ':') && !in_array($p->name, [
                    'viet_bai_viet',
                    'duyet_bai_viet',
                    'trang_quan_tri',
                    'quan_ly_bai_viet' // <--- Đã loại trừ khỏi vòng lặp
                ]);
        });
    }

    public function getCategoryScopedPermissionsProperty()
    {
        return $this->permissions->filter(fn($p) => Str::contains($p->name, ':'));
    }

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
        $allCategoryPermissions = $this->buildRecursivePermissions($categories, null, $grouped);

        if (trim($this->searchCategory) !== '') {
            $searchTerm = Str::lower(trim($this->searchCategory));

            $allCategoryPermissions = array_filter($allCategoryPermissions, function ($item) use ($searchTerm) {
                $categoryName = Str::lower($item['category']->getTranslatedName());
                return Str::contains($categoryName, $searchTerm);
            });
        }

        return collect($allCategoryPermissions);
    }

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
            $results = array_merge($results, $children);
        }

        return $results;
    }

    public function getActiveCategoriesProperty()
    {
        return Category::where('is_active', true)->orderBy('order')->get();
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

        $finalPermissions = $this->selectedPermissions;

        // DỌN DẸP TRƯỚC KHI LƯU DB ĐỂ TỐI ƯU
        $writePerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'viet_bai_viet:'))->pluck('name')->toArray();
        $reviewPerms = $this->categoryScopedPermissions->filter(fn($p) => Str::startsWith($p->name, 'duyet_bai_viet:'))->pluck('name')->toArray();

        if ($this->selectAllManage) {
            $finalPermissions = array_diff($finalPermissions, array_merge($writePerms, $reviewPerms));
            $finalPermissions[] = 'quan_ly_bai_viet';
        } else {
            if ($this->selectAllWrite) {
                $finalPermissions = array_diff($finalPermissions, $writePerms);
                $finalPermissions[] = 'viet_bai_viet';
            }
            if ($this->selectAllReview) {
                $finalPermissions = array_diff($finalPermissions, $reviewPerms);
                $finalPermissions[] = 'duyet_bai_viet';
            }
        }

        // Bắt buộc đẩy quyền truy cập trang quản trị vào danh sách
        $mandatoryPermission = 'trang_quan_tri';
        if (!in_array($mandatoryPermission, $finalPermissions)) {
            $finalPermissions[] = $mandatoryPermission;
        }

        $role = Role::create([
            'display_name' => $this->display_name,
            'name' => $this->name,
        ]);

        $role->syncPermissions(array_unique($finalPermissions));

        $this->success('Tạo vai trò và cấp quyền thành công!', redirectTo: route('admin.role.index'));
    }
};
?>

<div>
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Create new roles') }}
    </x-slot:title>

    {{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
        <a href="{{route('admin.role.index')}}"
           class="font-semibold text-slate-700" wire:navigate>{{__('List of Roles and Permissions')}}</a>
        <span class="mx-1">/</span>
        <span>{{__('Create new roles')}}</span>
    </x-slot:breadcrumb>

    {{-- start - header --}}
    <x-header title="{{__('Create new roles')}}" class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"></x-header>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        <x-card class="col-span-10 flex flex-col p-3!">
            <x-input label="Tên vai trò" wire:model.live.debounce.500ms="display_name" required/>

            {{-- General Permissions Section --}}
            <div class="mt-6">
                <label class="font-semibold text-gray-700 mb-3 block">Nhóm quyền chung</label>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-5 bg-gray-50/50 rounded-xl border border-gray-200 shadow-sm">

                    {{-- Quyền: Quản lý bài viết (Được tách riêng ra để liên kết logic với bảng dưới) --}}
                    <div class="select-none">
                        <x-checkbox
                            label="Quản lý bài viết"
                            wire:model.live="selectAllManage"
                            class="checkbox-primary checkbox-sm font-semibold"
                        />
                    </div>

                    {{-- Các quyền chung khác --}}
                    @forelse($this->generalPermissions as $permission)
                        <div class="select-none" wire:key="permission-{{ $permission->id }}">
                            <x-checkbox
                                label="{{ $permission->display_name }}"
                                wire:model.live="selectedPermissions"
                                value="{{ $permission->name }}"
                                class="checkbox-primary checkbox-sm"
                            />
                        </div>
                    @empty
                    @endforelse
                </div>
            </div>

            {{-- Category-Scoped Permissions Section --}}
            <div class="mt-8">
                <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="font-semibold mb-3 block">Nhóm quyền theo danh mục bài viết</label>
                    <div class="flex gap-2">
                        @if($this->has_category_permissions)
                            <x-button label="Bỏ chọn tất cả" icon="o-x-mark" class="btn-md text-danger btn-ghost" wire:click="deselectAll" spinner="deselectAll"></x-button>
                        @endif
                        <x-input
                            icon="o-magnifying-glass"
                            placeholder="Tìm danh mục..."
                            wire:model.live.debounce.300ms="searchCategory"
                            class="w-full sm:w-82"
                            clearable
                        />
                    </div>
                </div>

                @if($this->categoryPermissions->count() > 0)
                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b border-gray-200">
                            <tr class="divide-x divide-gray-200">
                                <th class="px-4 py-3 text-left font-semibold">Danh mục</th>
                                <th class="px-4 py-3 text-center w-32">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <span class="font-semibold">Viết bài</span>
                                        <label class="cursor-pointer text-[14px] font-normal text-primary flex items-center gap-1 hover:text-blue-700">
                                            {{-- Đổi thành wire:model="selectAllWrite" --}}
                                            <input type="checkbox" wire:model.live="selectAllWrite" class="checkbox checkbox-primary checkbox-sm" />
                                            (Tất cả)
                                        </label>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-center w-32">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <span class="font-semibold">Duyệt bài</span>
                                        <label class="cursor-pointer text-[14px] font-normal text-primary flex items-center gap-1 hover:text-blue-700">
                                            {{-- Đổi thành wire:model="selectAllReview" --}}
                                            <input type="checkbox" wire:model.live="selectAllReview" class="checkbox checkbox-primary checkbox-sm" />
                                            (Tất cả)
                                        </label>
                                    </div>
                                </th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @foreach($this->categoryPermissions as $categoryId => $categoryData)
                                @php
                                    $category = $categoryData['category'];
                                    $permissions = $categoryData['permissions'];
                                    $writePermission = $permissions['viet_bai_viet'] ?? null;
                                    $reviewPermission = $permissions['duyet_bai_viet'] ?? null;
                                    $depth = $categoryData['depth'] ?? 0;
                                @endphp
                                <tr class="hover:bg-gray-50 divide-x divide-gray-200">
                                    <td class="py-2" style="padding-left: {{ $depth * 1.5 + 1 }}rem; padding-right: 1rem;">
                                        <div class="flex items-center gap-2">
                                            @if($depth > 0)
                                                <span class="text-gray-300">└─</span>
                                            @endif
                                            <p class="font-medium text-gray-800">{{ $category->getTranslatedName() }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @if($writePermission)
                                            <div class="flex justify-center">
                                                {{-- Đã xóa bỏ thuộc tính Disabled và Checked tĩnh --}}
                                                <input wire:key="write-single-{{ $category->id }}" type="checkbox" wire:model.live="selectedPermissions" value="{{ $writePermission['name'] }}" class="checkbox checkbox-primary checkbox-sm" />
                                            </div>
                                        @else
                                            <span class="text-gray-400 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @if($reviewPermission)
                                            <div class="flex justify-center">
                                                {{-- Đã xóa bỏ thuộc tính Disabled và Checked tĩnh --}}
                                                <input wire:key="review-single-{{ $category->id }}" type="checkbox" wire:model.live="selectedPermissions" value="{{ $reviewPermission['name'] }}" class="checkbox checkbox-primary checkbox-sm" />
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

        <x-card class="col-span-2 bg-white p-3! sticky top-22 self-start" title="Hành động" shadow separator progress-indicator="save">
            <x-button label="{{__('Save')}}" class="bg-primary text-white my-1 w-full" wire:click="save" spinner/>
        </x-card>
    </div>
</div>
