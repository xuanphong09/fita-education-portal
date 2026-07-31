<?php

use App\Jobs\SyncStudentGradesJob;
use App\Models\Department;
use App\Models\Intake;
use App\Models\Major;
use App\Models\ProgramMajor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
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
    public $filterProgramMajor = '';
    #[Url(as: 'major')]
    public $filterMajor = '';
    #[Url(as: 'grades')]
    public bool $filterGrades = false;

    /** @var array<int, int|string> */
    public array $selectedUserIds = [];

    /**
     * Danh sách tài khoản thuộc đợt đồng bộ gần nhất để hiển thị tiến trình.
     * Không đưa thuộc tính này lên URL.
     *
     * @var array<int, int>
     */
    public array $trackingUserIds = [];

    public bool $selectPage = false;

    public array $syncProgress = [
        'idle' => 0,
        'queued' => 0,
        'syncing' => 0,
        'success' => 0,
        'failed' => 0,
        'invalid_password' => 0,
        'no_data' => 0,
    ];

    public function mount(): void
    {
        $this->refreshSyncStatuses();
    }


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

    protected function usersQuery(): Builder
    {
        return User::query()
            ->with(['roles', 'student', 'lecturer'])
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhereHas('student', function (Builder $subQuery) {
                            $subQuery->where('student_code', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('lecturer', function (Builder $subQuery) {
                            $subQuery->where('staff_code', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->filterRole !== '', function (Builder $query) {
                $query->whereHas('roles', function (Builder $roleQuery) {
                    $roleQuery->where('id', $this->filterRole);
                });
            })
            ->when($this->filterDepartment !== '', function (Builder $query) {
                $query->whereHas('lecturer.department', function (Builder $departmentQuery) {
                    $departmentQuery->where('id', $this->filterDepartment);
                });
            })
            ->when($this->filterIntake !== '', function (Builder $query) {
                $query->whereHas('student', function (Builder $studentQuery) {
                    $studentQuery->where('intake_id', $this->filterIntake);
                });
            })
            ->when($this->filterProgramMajor !== '', function (Builder $query) {
                $query->whereHas('student', function (Builder $studentQuery) {
                    $studentQuery->where('program_major_id', $this->filterProgramMajor);
                });
            })
            ->when($this->filterMajor !== '', function (Builder $query) {
                $query->whereHas('student', function (Builder $studentQuery) {
                    $studentQuery->where('major_id', $this->filterMajor);
                });
            })
            ->when($this->filterGrades, function (Builder $query) {
                // Bộ lọc này dùng để lấy sinh viên đã lưu kết nối đào tạo.
                // Không lọc theo grade_sync_status vì khi đồng bộ lại trạng thái
                // sẽ đổi success -> queued -> syncing và làm tài khoản biến mất.
                $query->where('user_type', 'student')
                    ->whereHas('student', function (Builder $studentQuery) {
                        $studentQuery
                            ->whereNotNull('vnua_password')
                            ->where('vnua_password', '!=', '');
                    });
            })
            ->when($this->filterUserType !== '', function (Builder $query) {
                $query->where('user_type', $this->filterUserType);
            });
    }

    public function getUsersProperty()
    {
        return $this->usersQuery()
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    /**
     * Thống kê trạng thái đồng bộ trong toàn bộ kết quả đang lọc.
     * Livewire dùng dữ liệu này để bật/tắt polling tự động.
     */
    public function getSyncStatusCountsProperty(): array
    {
        $trackingIds = array_values(array_unique(array_filter(
            array_map('intval', $this->trackingUserIds),
            fn(int $id) => $id > 0
        )));

        $studentQuery = Student::query();

        if ($trackingIds !== []) {
            // Khi vừa tạo một đợt đồng bộ, chỉ thống kê đúng các tài khoản
            // thuộc đợt đó để con số trên giao diện không lẫn dữ liệu cũ.
            $studentQuery->whereIn('user_id', $trackingIds);
        } else {
            // Sau khi tải lại trang, theo dõi các tác vụ đang chạy trong
            // kết quả lọc hiện tại.
            $userIdsQuery = (clone $this->usersQuery())
                ->where('users.user_type', 'student')
                ->select('users.id');

            $studentQuery->whereIn('user_id', $userIdsQuery);
        }

        $counts = $studentQuery
            ->selectRaw("COALESCE(grade_sync_status, 'idle') as sync_status, COUNT(*) as total")
            ->groupByRaw("COALESCE(grade_sync_status, 'idle')")
            ->pluck('total', 'sync_status')
            ->map(fn($total) => (int)$total)
            ->all();

        return array_merge([
            'idle' => 0,
            'queued' => 0,
            'syncing' => 0,
            'success' => 0,
            'failed' => 0,
            'invalid_password' => 0,
            'no_data' => 0,
        ], $counts);
    }

    public function getHasActiveSyncsProperty(): bool
    {
        $counts = $this->syncStatusCounts;

        return ($counts['queued'] + $counts['syncing']) > 0;
    }

    public function getCurrentPageSyncableIdsProperty(): array
    {
        return $this->users
            ->getCollection()
            ->filter(fn(User $user) => $this->canSyncUser($user))
            ->pluck('id')
            ->map(fn($id) => (int)$id)
            ->values()
            ->all();
    }

    public function getFilteredSyncableCountProperty(): int
    {
        return (clone $this->usersQuery())
            ->where('user_type', 'student')
            ->whereHas('student', function (Builder $query) {
                $query->whereNotNull('vnua_password')
                    ->where('vnua_password', '!=', '');
            })
            ->count();
    }

    public function getSelectedCountProperty(): int
    {
        return count(array_unique(array_map('intval', $this->selectedUserIds)));
    }

    public function canSyncUser(User $user): bool
    {
        return $user->user_type === 'student'
            && $user->student
            && filled($user->student->getRawOriginal('vnua_password'));
    }

    public function updatedSelectPage(bool $value): void
    {
        $pageIds = $this->currentPageSyncableIds;

        if ($value) {
            $this->selectedUserIds = array_values(array_unique([
                ...array_map('intval', $this->selectedUserIds),
                ...$pageIds,
            ]));

            return;
        }

        $this->selectedUserIds = array_values(array_diff(
            array_map('intval', $this->selectedUserIds),
            $pageIds
        ));
    }

    public function updatedSelectedUserIds(): void
    {
        $this->selectedUserIds = array_values(array_unique(
            array_map('intval', $this->selectedUserIds)
        ));

        $pageIds = $this->currentPageSyncableIds;

        $this->selectPage = $pageIds !== []
            && empty(array_diff($pageIds, $this->selectedUserIds));
    }

    public function selectAllFiltered(): void
    {
        $this->selectedUserIds = (clone $this->usersQuery())
            ->where('user_type', 'student')
            ->whereHas('student', function (Builder $query) {
                $query->whereNotNull('vnua_password')
                    ->where('vnua_password', '!=', '');
            })
            ->pluck('users.id')
            ->map(fn($id) => (int)$id)
            ->values()
            ->all();

        $this->selectPage = $this->currentPageSyncableIds !== [];

        if ($this->selectedUserIds === []) {
            $this->warning('Không có tài khoản sinh viên nào đã lưu kết nối để chọn.');
            return;
        }

        $this->success('Đã chọn toàn bộ ' . count($this->selectedUserIds) . ' tài khoản phù hợp.');
    }

    public function clearSelection(): void
    {
        $this->selectedUserIds = [];
        $this->selectPage = false;
    }

    public function clearSyncTracking(): void
    {
        $this->trackingUserIds = [];
        $this->refreshSyncStatuses();
    }

    public function syncSelectedStudents(): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn đồng bộ các tài khoản đã chọn?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmSyncSelectedStudents',
            'id' => null,
        ]);
    }

    #[On('confirmSyncSelectedStudents')]
    public function confirmSyncSelectedStudents(): void
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $this->selectedUserIds),
            fn(int $id) => $id > 0
        )));

        if ($userIds === []) {
            $this->warning('Vui lòng chọn ít nhất một tài khoản sinh viên.');
            return;
        }

        // Giữ riêng danh sách của đợt đồng bộ để hiển thị tiến trình
        // chính xác cho các tài khoản vừa chọn.
        $this->trackingUserIds = $userIds;

        // Xóa trạng thái checkbox ngay để giao diện không giữ số lượng cũ.
        $this->clearSelection();

        $students = Student::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('vnua_password')
            ->where('vnua_password', '!=', '')
            ->get(['id', 'user_id']);

        if ($students->isEmpty()) {
            $this->clearSelection();
            $this->warning('Các tài khoản đã chọn chưa lưu kết nối tới hệ thống đào tạo.');
            return;
        }

        $queued = 0;
        $skipped = 0;

        foreach ($students as $student) {
            // Cập nhật có điều kiện để hai quản trị viên không tạo trùng tác vụ.
            $updated = Student::query()
                ->whereKey($student->id)
                ->where(function (Builder $query) {
                    $query->whereNull('grade_sync_status')
                        ->orWhereNotIn('grade_sync_status', ['queued', 'syncing']);
                })
                ->update([
                    'grade_sync_status' => 'queued',
                    'grade_sync_message' => 'Quản trị viên đã đưa yêu cầu đồng bộ vào hàng đợi.',
                    'grade_sync_started_at' => null,
                    'grade_sync_failed_at' => null,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                $skipped++;
                continue;
            }

            try {
                SyncStudentGradesJob::dispatch($student->id, true);
                $queued++;
            } catch (\Throwable $exception) {
                Student::query()
                    ->whereKey($student->id)
                    ->update([
                        'grade_sync_status' => 'failed',
                        'grade_sync_message' => 'Không thể đưa yêu cầu đồng bộ vào hàng đợi.',
                        'grade_sync_failed_at' => now(),
                        'updated_at' => now(),
                    ]);

                report($exception);
                $skipped++;
            }
        }

        $this->refreshSyncStatuses();

        if ($queued === 0 && $skipped > 0) {
            $this->warning("{$skipped} tài khoản đang chờ hoặc đang đồng bộ, không tạo thêm tác vụ trùng.");
            return;
        }

        $message = "Đã đưa {$queued} tài khoản sinh viên vào hàng đợi đồng bộ.";

        if ($skipped > 0) {
            $message .= " Bỏ qua {$skipped} tài khoản đang chờ/đang đồng bộ.";
        }

        $this->success($message);
    }

    protected function resetBulkSelection(): void
    {
        $this->clearSelection();
    }

    public function refreshSyncStatuses(): void
    {
        $this->syncProgress = $this->getSyncStatusCountsProperty();
    }

    public function syncCount(string $status): int
    {
        return (int)($this->syncProgress[$status] ?? 0);
    }

    public function activeSyncCount(): int
    {
        return $this->syncCount('queued') + $this->syncCount('syncing');
    }

    public function showSyncPanel(): bool
    {
        return $this->trackingUserIds !== [] || $this->activeSyncCount() > 0;
    }

    public function syncPanelTitle(): string
    {
        return $this->activeSyncCount() > 0
            ? 'Đang cập nhật trạng thái đồng bộ'
            : 'Đợt đồng bộ đã hoàn tất';
    }

    public function syncPanelMessage(): string
    {
        return $this->activeSyncCount() > 0
            ? ''
            : 'Bạn có thể xem kết quả của từng sinh viên ngay trong bảng.';
    }

    public function syncPanelSpinnerClass(): string
    {
        return $this->activeSyncCount() > 0
            ? 'loading loading-spinner loading-sm text-primary'
            : 'hidden';
    }

    public function userCode(User $user): string
    {
        return match ($user->user_type) {
            'student' => (string)($user->student?->student_code ?? '-'),
            'lecturer' => (string)($user->lecturer?->staff_code ?? '-'),
            default => '-',
        };
    }

    public function roleLabel(User $user): string
    {
        return (string)($user->roles->first()?->display_name ?? 'Chưa có');
    }

    public function roleBadgeClass(User $user): string
    {
        $displayName = $user->roles->first()?->display_name;

        return match ($displayName) {
            'Super Admin' => 'badge-error text-white',
            'Ban Chủ Nhiệm Khoa' => 'badge-warning text-white',
            'Giảng viên' => 'badge-info text-white',
            'Sinh viên' => 'badge-success text-white',
            null => 'badge-ghost text-gray-500',
            default => 'badge-neutral text-white',
        };
    }

    public function remainingRoleCount(User $user): int
    {
        return max($user->roles->count() - 1, 0);
    }

    public function remainingRoleLabel(User $user): string
    {
        $count = $this->remainingRoleCount($user);

        return $count > 0 ? '+' . $count : '';
    }

    public function remainingRoleClass(User $user): string
    {
        return $this->remainingRoleCount($user) > 0
            ? 'badge badge-ghost badge-md font-semibold'
            : 'hidden';
    }

    public function activeStatusLabel(User $user): string
    {
        return $user->is_active ? 'Hoạt động' : 'Đã khóa';
    }

    public function activeStatusClass(User $user): string
    {
        return $user->is_active
            ? 'badge-success badge-outline'
            : 'badge-error badge-outline';
    }

    public function syncStatus(User $user): string
    {
        if ($user->user_type !== 'student' || !$user->student) {
            return 'not_student';
        }

        if (blank($user->student->getRawOriginal('vnua_password'))) {
            return 'not_connected';
        }

        return (string)($user->student->grade_sync_status ?? 'idle');
    }

    public function syncStatusLabel(User $user): string
    {
        return match ($this->syncStatus($user)) {
            'queued' => 'Đang chờ',
            'syncing' => 'Đang đồng bộ',
            'success' => 'Thành công',
            'failed' => 'Thất bại',
            'invalid_password' => 'Sai mật khẩu',
            'no_data' => 'Không có dữ liệu',
            'not_connected' => 'Chưa kết nối',
            'not_student' => '-',
            default => 'Chưa đồng bộ',
        };
    }

    public function syncStatusBadgeClass(User $user): string
    {
        return match ($this->syncStatus($user)) {
            'queued' => 'badge-warning',
            'syncing' => 'badge-info text-white',
            'success' => 'badge-success text-white',
            'failed', 'invalid_password' => 'badge-error text-white',
            'no_data' => 'badge-warning',
            'not_connected', 'not_student', 'idle' => 'badge-ghost text-gray-700',
            default => 'badge-ghost text-gray-700',
        };
    }

    public function syncStatusLoadingClass(User $user): string
    {
        return match ($this->syncStatus($user)) {
            'queued' => 'loading loading-dots loading-sm text-warning',
            'syncing' => 'loading loading-spinner loading-sm text-info',
            default => 'hidden',
        };
    }

    public function syncStatusUpdatedAt(User $user): string
    {
        return $user->student?->last_academic_stats_updated_at
            ? $user->student->last_academic_stats_updated_at->format('H:i d/m/Y')
            : '';
    }

    public function syncStatusUpdatedAtClass(User $user): string
    {
        return filled($this->syncStatusUpdatedAt($user))
            ? 'whitespace-nowrap text-md text-gray-500'
            : 'hidden';
    }

    public function canViewStudentGrades(User $user): bool
    {
        return (bool)(
            auth()->user()?->can('xem_diem_sinh_vien')
            && $user->user_type === 'student'
            && $user->student
            && filled($user->student->getRawOriginal('vnua_password'))
            && $user->student->grade_sync_status === 'success'
        );
    }

    // Cấu hình lại các cột cho bảng
    public function headers(): array
    {
        return [
            ['key' => 'select', 'label' => '', 'sortable' => false, 'class' => 'w-10 pe-2! text-center'],
            ['key' => 'id', 'label' => '#', 'class' => 'w-10 ps-2! text-center'],
            ['key' => 'user_info', 'label' => 'Người dùng', 'sortable' => false, 'class' => 'ps-0!'],
            ['key' => 'user_code', 'label' => 'Mã định danh', 'sortable' => false],
            ['key' => 'roles', 'label' => 'Vai trò', 'sortable' => false, 'class' => 'w-48'],
            ['key' => 'is_active', 'label' => 'Trạng thái'],
            ['key' => 'grade_sync_status', 'label' => 'Đồng bộ đào tạo', 'sortable' => false, 'class' => 'w-44'],
            ['key' => 'last_login_at', 'label' => 'Đăng nhập cuối', 'class' => 'w-32 px-2'],
//            ['key' => 'created_at', 'label' => 'Ngày tạo', 'class' => 'w-32 px-2'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-12 p-2'],
        ];
    }

    public function delete($id)
    {
        // Ví dụ hàm xóa
        // User::find($id)?->delete();
        // $this->success('Đã xóa người dùng thành công!');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedFilterUserType(): void
    {
        $this->resetPage();
        $this->filterRole = '';
        $this->filterDepartment = '';
        $this->filterIntake = '';
        $this->filterProgramMajor = '';
        $this->filterMajor = '';
        $this->filterGrades = false;
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedFilterDepartment(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedFilterIntake(): void
    {
        $this->filterProgramMajor = '';
        $this->filterMajor = '';
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedFilterProgramMajor(): void
    {
        $this->filterMajor = '';
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedFilterMajor(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedFilterGrades(): void
    {
        if ($this->filterGrades) {
            $this->filterUserType = 'student';
            $this->filterRole = '';
            $this->filterDepartment = '';
        }

        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
        $this->selectPage = false;
    }

    public function updatedPaginators($page, $pageName): void
    {
        $pageIds = $this->currentPageSyncableIds;

        $this->selectPage = $pageIds !== []
            && empty(array_diff($pageIds, array_map('intval', $this->selectedUserIds)));
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
        $this->selectPage = false;
    }
}
?>

<div wire:poll.1000ms.visible="refreshSyncStatuses">
    <x-slot:title>
        {{ __('Quản lý người dùng') }}
    </x-slot:title>

    <x-slot:breadcrumb>
        <span>{{ __('Quản lý người dùng') }}</span>
    </x-slot:breadcrumb>

    <x-header
        title="Danh sách người dùng"
        class="pb-3 mb-2! border-(length:--var(--border)) border-b border-gray-300"
    >
        <x-slot:middle class="justify-end!">
            <div class="flex flex-col gap-3 md:flex-row">
                <x-input
                    icon="o-magnifying-glass"
                    placeholder="Tìm tên, email, mã SV/CB..."
                    wire:model.live.debounce.300ms="search"
                    clearable
                    class="w-full lg:w-96"
                />
            </div>
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                icon="o-plus"
                class="btn-primary text-white"
                label="{{ __('Create new') }}"
                link="{{ route('admin.user.create') }}"
            />
        </x-slot:actions>
    </x-header>

    <div class="mb-4 flex flex-wrap items-center gap-3">
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

        <x-select
            wire:model.live="filterDepartment"
            placeholder="Tất cả bộ môn"
            placeholder-value=""
            :options="$this->departmentOptions"
            option-value="id"
            option-label="name"
            class="{{ $filterUserType === 'lecturer' ? 'w-full md:w-48' : 'hidden' }}"
        />

        <div class="{{ $filterUserType === 'student' ? 'contents' : 'hidden' }}">
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

            <x-checkbox
                wire:model.live="filterGrades"
                label="Đã lưu kết nối đào tạo"
                class="checkbox-primary checkbox-sm"
            />
        </div>
    </div>

    <div
        class="{{ $this->showSyncPanel() ? 'mb-3 rounded-md border border-blue-200 bg-blue-50 p-3 shadow-sm' : 'hidden' }}">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <span class="{{ $this->syncPanelSpinnerClass() }}"></span>

                <div>
                    <p class="font-semibold text-blue-900">
                        {{ $this->syncPanelTitle() }}
                    </p>
                    <p class="text-sm text-blue-700">
                        {{ $this->syncPanelMessage() }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 text-sm">
                <span class="badge badge-warning gap-1 whitespace-nowrap">
                    {{ $this->syncCount('queued') }} đang chờ
                </span>

                <span class="badge badge-info gap-1 whitespace-nowrap text-white">
                    {{ $this->syncCount('syncing') }} đang đồng bộ
                </span>

                <span class="badge badge-success whitespace-nowrap text-white">
                    {{ $this->syncCount('success') }} thành công
                </span>

                <span class="badge badge-error whitespace-nowrap text-white">
                    {{ $this->syncCount('failed') + $this->syncCount('invalid_password') }} thất bại
                </span>

                <span
                    class="{{ $this->syncCount('no_data') > 0 ? 'badge badge-warning whitespace-nowrap' : 'hidden' }}">
                    {{ $this->syncCount('no_data') }} không có dữ liệu
                </span>

                <x-button
                    icon="o-x-mark"
                    class="{{ $this->trackingUserIds !== [] ? 'btn-xs btn-ghost' : 'hidden' }}"
                    tooltip="Ẩn"
                    wire:click="clearSyncTracking"
                />
            </div>
        </div>
    </div>

    <div
        class="mb-3 flex flex-col gap-3 rounded-md border border-gray-200 bg-white p-3 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700">
            <span>
                Đã chọn
                <strong class="text-primary">{{ $this->selectedCount }}</strong>
                tài khoản
            </span>

            <span class="text-gray-300">|</span>

            <span>
                Có
                <strong>{{ $this->filteredSyncableCount }}</strong>
                SV đã lưu kết nối trong kết quả lọc
            </span>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-button
                label="Chọn tất cả kết quả lọc"
                icon="o-check-circle"
                class="btn-sm btn-outline"
                wire:click="selectAllFiltered"
                spinner="selectAllFiltered"
                :disabled="$this->filteredSyncableCount === 0"
            />

            <x-button
                label="Bỏ chọn"
                icon="o-x-mark"
                class="btn-sm btn-ghost"
                wire:click="clearSelection"
                :disabled="$this->selectedCount === 0"
            />

            <x-button
                label="Đồng bộ ngay"
                icon="o-arrow-path"
                class="btn-sm btn-primary text-white"
                wire:click="syncSelectedStudents"
                spinner="syncSelectedStudents"
                :disabled="$this->selectedCount === 0"
            />
        </div>
    </div>

    <div class="relative rounded-md shadow-md ring-1 ring-gray-200">
        <x-table
            :headers="$this->headers()"
            :rows="$this->users"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[5, 10, 20, 25, 50]"
            per-page="perPage"
            with-pagination
            wire:loading.class="pointer-events-none select-none opacity-50"
            wire:target="search,filterUserType,filterRole,filterDepartment,filterIntake,filterProgramMajor,filterMajor,filterGrades,perPage,sortBy,syncSelectedStudents,selectAllFiltered"
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
            "
        >
            @scope('header_select', $header)
            <div class="flex justify-center">
                <input
                    type="checkbox"
                    class="checkbox checkbox-primary checkbox-sm"
                    wire:model.live="selectPage"
                    aria-label="Chọn tất cả sinh viên có thể đồng bộ trên trang này"
                />
            </div>
            @endscope

            @scope('header_last_login_at', $header)
            <div
                class="tooltip tooltip-bottom inline-flex cursor-help items-center gap-1"
                data-tip="Thời điểm người dùng đăng nhập gần nhất vào hệ thống"
            >
                <span>{{ $header['label'] }}</span>
                <x-icon name="o-information-circle" class="h-4 w-4 text-gray-400"/>
            </div>
            @endscope

            @scope('cell_select', $user)
            <div class="flex justify-center">
                <input
                    type="checkbox"
                    class="checkbox checkbox-primary checkbox-sm"
                    wire:model.live="selectedUserIds"
                    value="{{ $user->id }}"
                    wire:key="sync-user-{{ $user->id }}"
                    aria-label="Chọn {{ $user->name }} để đồng bộ"
                    {!! $this->canSyncUser($user) ? '' : 'disabled' !!}
                />
            </div>
            @endscope

            @scope('cell_id', $user)
            {{ ($this->users->currentPage() - 1) * $this->users->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_user_info', $user)
            <div class="flex w-full items-center gap-3 text-left">
                <x-avatar
                    :image="$user->avatar
                            ? asset($user->avatar)
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random'"
                    class="h-10! w-10!"
                />

                <div class="flex flex-col items-start">
                    <span class="font-semibold text-gray-800">{{ $user->name }}</span>
                    <span class="text-sm text-gray-500">{{ $user->email }}</span>
                </div>
            </div>
            @endscope

            @scope('cell_user_code', $user)
            <span class="font-medium text-gray-700">
                    {{ $this->userCode($user) }}
                </span>
            @endscope

            @scope('cell_roles', $user)
            <div class="flex flex-wrap gap-1">
                <x-badge
                    :value="$this->roleLabel($user)"
                    class="{{ $this->roleBadgeClass($user) }} badge-md font-semibold whitespace-nowrap"
                />

                <span class="{{ $this->remainingRoleClass($user) }}">
                        {{ $this->remainingRoleLabel($user) }}
                    </span>
            </div>
            @endscope

            @scope('cell_is_active', $user)
            <x-badge
                :value="$this->activeStatusLabel($user)"
                class="{{ $this->activeStatusClass($user) }} badge-md whitespace-nowrap font-semibold"
            />
            @endscope

            @scope('cell_grade_sync_status', $user)
            <div
                class="flex flex-col items-start gap-1"
                wire:key="sync-status-{{ $user->id }}-{{ $this->syncStatus($user) }}"
            >
                <div class="inline-flex items-center gap-2">
                    <span class="{{ $this->syncStatusLoadingClass($user) }}"></span>

                    <x-badge
                        :value="$this->syncStatusLabel($user)"
                        class="{{ $this->syncStatusBadgeClass($user) }} badge-md whitespace-nowrap"
                    />
                </div>

                <span class="{{ $this->syncStatusUpdatedAtClass($user) }}">
                        {{ $this->syncStatusUpdatedAt($user) }}
                    </span>
            </div>
            @endscope

            @scope('cell_created_at', $user)
            <span class="whitespace-nowrap text-gray-700">
                    {{ $user->created_at->format('d/m/Y H:i') }}
                </span>
            @endscope

            @scope('cell_last_login_at', $user)
            <span class="whitespace-nowrap text-gray-700">
                    {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : '-' }}
                </span>
            @endscope

            @scope('cell_actions', $user)
            <div class="flex justify-center space-x-2">
                <x-button
                    icon="o-eye"
                    class="{{ $this->canViewStudentGrades($user)
                            ? 'btn-sm btn-ghost text-success'
                            : 'hidden' }}"
                    tooltip="Xem tiến độ học tập"
                    link="{{ route('admin.users.student-grades', $user->id) }}"
                    external
                />

                <x-button
                    icon="o-pencil"
                    class="btn-sm btn-ghost text-primary"
                    tooltip-left="Chỉnh sửa"
                    link="{{ route('admin.user.edit', $user->id) }}"
                />
            </div>
            @endscope

            <x-slot:empty>
                <div class="py-5 text-center">
                    <x-icon name="o-users" class="mx-auto h-10 w-10 text-gray-400"/>
                    <p class="mt-2 text-gray-500">Chưa có người dùng nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->users" wire:model.live="perPage"/>
        </x-table>
        <div wire:loading.flex
             wire:target="search,filterUserType,filterRole,filterDepartment,filterIntake,filterProgramMajor,filterMajor,filterGrades,perPage,sortBy,syncSelectedStudents,selectAllFiltered, setPage,gotoPage,nextPage,previousPage,paginators,clearSelection"
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>
</div>
