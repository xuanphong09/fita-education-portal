<?php

use App\Models\Department;
use App\Models\Intake;
use App\Models\Major;
use App\Models\ProgramMajor;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;
    use Toast;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public int $perPage = 10;
    #[Url(as: 'search')]
    public string $search = '';
    #[Url(as: 'user-type')]
    public string $filterUserType = '';
    #[Url(as: 'role')]
    public string $filterRole = '';
    #[Url(as: 'department')]
    public $filterDepartment = '';
    #[Url(as: 'intake')]
    public $filterIntake = '';
    #[Url(as: 'programMajor')]
    public  $filterProgramMajor = '';
    #[Url(as: 'major')]
    public $filterMajor = '';
    #[Url(as: 'grades')]
    public bool $filterGrades = false;

    public function getRoleOptionsProperty()
    {
        return Role::query()
            ->orderBy('name')
            ->get(['id', 'display_name'])
            ->map(fn($role) => ['id' => $role->id, 'name' => $role->display_name])
            ->toArray();
    }

    public function getDepartmentOptionsProperty()
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($department) => ['id' => $department->id, 'name' => $department->name])
            ->toArray();
    }

    public function getIntakesProperty()
    {
        return Intake::query()
            ->whereHas('trainingPrograms', function ($query) {
                $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(Intake $intake) => [
                'id' => $intake->id,
                'name' => $intake->name,
            ]);
    }

    public function getProgramMajorsProperty()
    {
        $intakeId = $this->filterIntake;

        return ProgramMajor::query()
            ->where('is_active', true)
            ->where(function ($q) use ($intakeId) {
                $q->whereHas('trainingPrograms', function ($query) use ($intakeId) {
                    $query->where('status', 'published')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now());

                    if ($intakeId) {
                        $query->where('intake_id', $intakeId);
                    }
                })->orWhereHas('majors.trainingPrograms', function ($query) use ($intakeId) {
                    $query->where('status', 'published')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now());

                    if ($intakeId) {
                        $query->where('intake_id', $intakeId);
                    }
                });
            })
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get()
            ->map(function ($major) {
                return [
                    'id' => $major->id,
                    'name' => $major->getTranslation('name', app()->getLocale(), false)
                        ?: $major->getTranslation('name', 'vi', false)
                            ?: $major->getTranslation('name', 'en', false)
                                ?: $major->slug,
                ];
            });
    }

    public function getMajorsProperty()
    {
        if (!$this->filterProgramMajor) {
            return collect();
        }

        $intakeId = $this->filterIntake;

        return Major::query()
            ->where('program_major_id', $this->filterProgramMajor)
            ->where('is_active', true)
            ->whereHas('trainingPrograms', function ($query) use ($intakeId) {
                $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());

                if ($intakeId) {
                    $query->where('intake_id', $intakeId);
                }
            })
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get()
            ->map(function (Major $major) {
                return [
                    'id' => $major->id,
                    'name' => $major->getTranslation('name', app()->getLocale(), false)
                        ?: $major->getTranslation('name', 'vi', false)
                            ?: $major->getTranslation('name', 'en', false)
                                ?: $major->slug,
                ];
            });
    }

    public function updatedFilterProgramMajor(): void
    {
        $this->filterMajor = '';
    }

    public function updatedFilterIntake(): void
    {
        $this->filterProgramMajor = '';
        $this->filterMajor = '';
    }

    public function getUsersProperty()
    {
        return User::query()
            ->with(['roles', 'student', 'lecturer']) // Quan trọng: Gọi sẵn dữ liệu họ hàng
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    // Tìm trong bảng users
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')

                        // Tìm lấn sang bảng students (Mã SV)
                        ->orWhereHas('student', function ($subQuery) {
                            $subQuery->where('student_code', 'like', '%' . $this->search . '%');
                        })

                        // Tìm lấn sang bảng lecturers (Mã CB)
                        ->orWhereHas('lecturer', function ($subQuery) {
                            $subQuery->where('staff_code', 'like', '%' . $this->search . '%');
                        });

                });
            })
            ->when($this->filterRole !== '', function ($query) {
                $query->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('id', $this->filterRole);
                });
            })
            ->when($this->filterDepartment !== '', function ($query) {
                $query->whereHas('lecturer.department', function ($departmentQuery) {
                    $departmentQuery->where('id', $this->filterDepartment);
                });
            })
            ->when($this->filterIntake !== '', function ($query) {
                $query->whereHas('student', function ($studentQuery) {
                    $studentQuery->where('intake_id', $this->filterIntake);
                });
            })
            ->when($this->filterProgramMajor !== '', function ($query) {
                $query->whereHas('student', function ($trainingProgramQuery) {
                    $trainingProgramQuery->where('program_major_id', $this->filterProgramMajor);
                });
            })
            ->when($this->filterMajor !== '', function ($query) {
                $query->whereHas('student', function ($trainingProgramQuery) {
                    $trainingProgramQuery->where('major_id', $this->filterMajor);
                });
            })
            ->when($this->filterUserType !== '', function ($query) {
                $query->where('user_type', $this->filterUserType);
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
            ['key' => 'last_login_at', 'label' => 'Đăng nhập cuối', 'class' => 'w-32 px-2'],
            ['key' => 'created_at', 'label' => 'Ngày tạo', 'class' => 'w-32 px-2'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-12 p-2'],
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

    public function updatedFilterUserType()
    {
        $this->resetPage();
        $this->filterRole = '';
        $this->filterDepartment = '';
        $this->filterIntake = '';
        $this->filterProgramMajor = '';
        $this->filterMajor = '';
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
              class="pb-3 mb-2! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <div class=" flex flex-col md:flex-row gap-3">
                <x-input
                    icon="o-magnifying-glass"
                    placeholder="Tìm tên, email, mã SV/CB..."
                    wire:model.live.debounce.300ms="search"
                    clearable="true"
                    class="w-full lg:w-96"
                />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" class="btn-primary text-white" label="{{__('Create new')}}"
                      link="{{route('admin.user.create')}}"/>
        </x-slot:actions>
    </x-header>
    <div class="flex flex-wrap gap-3 mb-4">
        <x-select
            wire:model.live="filterUserType"
            placeholder="Tất cả người dùng"
            placeholder-value=""
            :options="[
                        ['id' => 'admin', 'name' => 'Cán bộ'],
                        ['id' => 'lecturer', 'name' => 'Giảng viên'],
                        ['id' => 'student', 'name' => 'Sinh viên'],
                    ]"
            option-value="id"
            option-label="name"
            class="w-full md:w-48"
        />
        <x-select
            wire:model.live="filterRole"
            placeholder="Tất cả vai trò"
            placeholder-value=""
            :options="$this->roleOptions"
            option-value="id"
            option-label="name"
            class="w-full md:w-48"
        />
        @if($this->departmentOptions && $filterUserType === 'lecturer')
            <x-select
                wire:model.live="filterDepartment"
                placeholder="Tất cả bộ môn"
                placeholder-value=""
                :options="$this->departmentOptions"
                option-value="id"
                option-label="name"
                class="w-full md:w-48"
            />
        @endif

        @if($filterUserType === 'student')
            <x-select
                wire:model.live="filterIntake"
                :options="$this->intakes"
                option-value="id"
                option-label="name"
                placeholder="{{ __('No intake selected') }}"
            />

            <x-select
                wire:key="select-program-major"
                wire:model.live="filterProgramMajor"
                :options="$this->programMajors"
                option-value="id"
                option-label="name"
                placeholder="{{ !$filterIntake ? __('Select intake first') : __('Select major') }}"
                :disabled="empty($filterIntake)"
            />

            <x-select
                wire:key="select-major-{{ $filterProgramMajor }}"
                wire:model.live="filterMajor"
                :options="$this->majors"
                option-value="id"
                option-label="name"
                placeholder="{{ !$filterProgramMajor ? __('Select specialization first') : __('No specialization selected') }}"
                :disabled="empty($filterProgramMajor) || $this->majors->isEmpty()"
            />
        @endif

    </div>
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
            @scope('header_last_login_at', $header)
            <div
                class="inline-flex items-center gap-1 tooltip tooltip-bottom cursor-help"
                data-tip="Thời điểm người dùng đăng nhập gần nhất vào hệ thống"
            >
                <span>{{ $header['label'] }}</span>
                <x-icon name="o-information-circle" class="w-4 h-4 text-gray-400"/>
            </div>
            @endscope

            {{-- Cột 1: STT --}}
            @scope('cell_id', $user)
            {{ ($this->users->currentPage() - 1) * $this->users->perPage() + $loop->iteration }}
            @endscope

            {{-- Cột 2: Gom Avatar, Tên và Email --}}
            @scope('cell_user_info', $user)
            <div class="flex items-center gap-3 text-left w-full">
                <x-avatar
                    :image="$user->avatar ? asset($user->avatar) :'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=random'"
                    class="w-10! h-10!"/>
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
                            default => 'badge-gray-400 text-gray-700!',
                        };
                    @endphp
                    <x-badge :value="$role->display_name" class="{{ $color }} badge-md text-white font-semibold "/>
                @empty
                    <span class="text-gray-400 text-sm">Chưa có</span>
                @endforelse
            </div>
            @endscope

            {{-- Cột 5: Trạng thái Hoạt động/Bị khóa --}}
            @scope('cell_is_active', $user)
            @if($user->is_active)
                <x-badge value="Hoạt động" class="badge-success badge-outline badge-md font-semibold whitespace-nowrap"/>
            @else
                <x-badge value="Đã khóa" class="badge-error badge-outline badge-md font-semibold whitespace-nowrap"/>
            @endif
            @endscope

            @scope('cell_created_at', $user)
            <span class="text-gray-700 whitespace-nowrap">
                    {{ $user->created_at->format('d/m/Y H:i') }}
                </span>
            @endscope

            @scope('cell_last_login_at', $user)
            <span class="text-gray-700 whitespace-nowrap">
                    {{ $user->last_login_at?$user->last_login_at->format('d/m/Y H:i'): '-' }}
                </span>
            @endscope

            {{-- Cột 6: Hành động --}}
            @scope('cell_actions', $user)
            <div class="flex space-x-2 justify-center">
                @can('xem_diem_sinh_vien')
                    @if(
                        $user->user_type === 'student'
                        && $user->student
                        && filled($user->student->vnua_password)
                        && $user->student->grade_sync_status === 'success'
                    )
                        <x-button
                            icon="o-eye"
                            class="btn-sm btn-ghost text-success [&]:hover:bg-gray-200/40 [&]:hover:border-gray-400/70"
                            tooltip="Xem tiến độ học tập"
                            link="{{ route('admin.users.student-grades', $user->id) }}"
                            external
                        />
                    @endif
                @endcan
                <x-button
                    icon="o-pencil"
                    class="btn-sm btn-ghost text-primary [&]:hover:bg-gray-200/40 [&]:hover:border-gray-400/70"
                    tooltip="Chỉnh Sửa"
                    link="{{route('admin.user.edit', $user->id)}}"
                />

                {{--                <x-button--}}
                {{--                    icon="o-trash"--}}
                {{--                    class="btn-sm btn-ghost text-danger [&]:hover:bg-gray-200/40 [&]:hover:border-gray-400/70"--}}
                {{--                    tooltip="Xóa"--}}
                {{--                    wire:click="delete({{ $user->id }})"--}}
                {{--                />--}}
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-users" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Chưa có người dùng nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->users" wire:model.live="perPage"/>
        </x-table>
        <div wire:loading.flex
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>
