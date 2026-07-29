<?php

use App\Models\Department;
use App\Models\Intake;
use App\Models\Major;
use App\Models\ProgramMajor;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Student;
use App\Models\Lecturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LecturerSlugService;

new class extends Component {
    use Toast, WithFileUploads;

    public $userType = [
        ['id' => 'admin', 'name' => 'Cán bộ'],
        ['id' => 'lecturer', 'name' => 'Giảng viên'],
        ['id' => 'student', 'name' => 'Sinh viên'],
    ];

    public $genders = [
        ['id' => 'male', 'name' => 'Nam'],
        ['id' => 'female', 'name' => 'Nữ'],
        ['id' => 'other', 'name' => 'Khác'],
    ];

    public $academicTitleOptions = [
        ['id' => 'gs', 'name' => 'GS'],
        ['id' => 'pgs', 'name' => 'PGS'],
        ['id' => 'other', 'name' => 'Khác'],
    ];

    public $degreeOptions = [
        ['id' => 'cn', 'name' => 'Cử nhân'],
        ['id' => 'ths', 'name' => 'ThS'],
        ['id' => 'ts', 'name' => 'TS'],
        ['id' => 'tsk', 'name' => 'TSKH'],
        ['id' => 'other', 'name' => 'Khác'],
    ];

    public $user_type;
    public $name;
    public $email;
//    public $password;
    public $selectedRoles = [];
    public $avatar;
    public $avatarUrl;

    public $date_of_birth;
    public $gender;
    public $phone;

//    student
    public $student_code;
    public $class_name;
    public $intake_id;
    public $major_id;
    public $program_major_id = null; // Bổ sung
//    lecturer
    public $staff_code;
    public $department_id;
    public $position_vi;
    public $position_en;
    public $academic_title;
    public $degree;
    public $academic_title_other;
    public $degree_other;

    public $userId;
    public $studentId;
    public $lecturerId;
    public $is_active;
    public bool $showPasswordModal = false;
    public string $password = '';
    public string $password_confirmation = '';

    public string $last_login_at = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',

            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],

            'user_type' => 'required|in:admin,lecturer,student',

            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // STUDENT
            'student_code' => [
                'exclude_unless:user_type,student',
                'required',
                'string',
                'max:100',
                Rule::unique('students', 'student_code')->ignore($this->studentId),
            ],

            'class_name' => 'exclude_unless:user_type,student|nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'phone' => 'nullable|regex:/^0[0-9]{9}$/',
            'intake_id' => 'exclude_unless:user_type,student|nullable|exists:intakes,id',
            'program_major_id' => 'exclude_unless:user_type,student|nullable|exists:program_majors,id', // Bổ sung
            'major_id' => 'exclude_unless:user_type,student|nullable|exists:majors,id',

            // LECTURER
            'staff_code' => [
                'exclude_unless:user_type,lecturer',
                'required',
                'string',
                'max:100',
                Rule::unique('lecturers', 'staff_code')->ignore($this->lecturerId),
            ],

            'department_id' => 'exclude_unless:user_type,lecturer|nullable|exists:departments,id',
            'position_vi' => 'exclude_unless:user_type,lecturer|nullable|string|max:255',
            'position_en' => 'exclude_unless:user_type,lecturer|nullable|string|max:255',
            'academic_title' => 'exclude_unless:user_type,lecturer|nullable|in:gs,pgs,other',
            'academic_title_other' => 'exclude_unless:user_type,lecturer|nullable|required_if:academic_title,other|string|max:100',
            'degree' => 'exclude_unless:user_type,lecturer|nullable|in:cn,ths,ts,tsk,other',
            'degree_other' => 'exclude_unless:user_type,lecturer|nullable|required_if:degree,other|string|max:100',

            'selectedRoles' => 'nullable|array',
            'selectedRoles.*' => 'exists:roles,name',
        ];
    }

    protected $messages = [

        'name.required' => 'Họ và tên không được để trống.',
        'name.string' => 'Họ và tên phải là một chuỗi.',
        'name.max' => 'Họ và tên không được vượt quá 255 ký tự.',

        'email.required' => 'Email không được để trống.',
        'email.email' => 'Email phải có định dạng hợp lệ.',
        'email.unique' => 'Email đã tồn tại trong hệ thống.',

        'password.required' => 'Mật khẩu không được để trống.',
        'password.string' => 'Mật khẩu phải là một chuỗi.',
        'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',

        'user_type.required' => 'Loại người dùng không được để trống.',
        'user_type.in' => 'Loại người dùng không hợp lệ.',

        'avatar.image' => 'File tải lên phải là hình ảnh.',
        'avatar.mimes' => 'Ảnh chỉ được định dạng jpg, jpeg hoặc png.',
        'avatar.max' => 'Ảnh đại diện không được lớn hơn 2MB.',

        // STUDENT
        'student_code.required' => 'Mã sinh viên không được để trống.',
        'student_code.string' => 'Mã sinh viên phải là một chuỗi.',
        'student_code.max' => 'Mã sinh viên không được vượt quá 100 ký tự.',
        'student_code.unique' => 'Mã sinh viên đã tồn tại trong hệ thống.',

        'class_name.string' => 'Lớp phải là một chuỗi.',
        'class_name.max' => 'Lớp không được vượt quá 100 ký tự.',

        'date_of_birth.date' => 'Ngày sinh phải là một ngày hợp lệ.',

        'gender.in' => 'Giới tính không hợp lệ.',

        'phone.regex' => 'Số điện thoại phải có dạng 0xx.xxxx.xxx.',

        'intake_id.exists' => 'Khóa học không tồn tại.',
        'program_major_id.exists' => 'Ngành học không tồn tại.',
        'major_id.exists' => 'Chuyên ngành học không tồn tại.',

        // LECTURER
        'staff_code.required' => 'Mã giảng viên không được để trống.',
        'staff_code.string' => 'Mã giảng viên phải là một chuỗi.',
        'staff_code.max' => 'Mã giảng viên không được vượt quá 100 ký tự.',
        'staff_code.unique' => 'Mã giảng viên đã tồn tại trong hệ thống.',

        'department_id.exists' => 'Bộ môn không tồn tại.',

        'position_vi.string' => 'Chức vụ (VI) phải là một chuỗi.',
        'position_vi.max' => 'Chức vụ (VI) không được vượt quá 255 ký tự.',
        'position_en.string' => 'Chức vụ (EN) phải là một chuỗi.',
        'position_en.max' => 'Chức vụ (EN) không được vượt quá 255 ký tự.',
        'academic_title.in' => 'Học hàm không hợp lệ.',
        'academic_title_other.required_if' => 'Vui lòng nhập học hàm khác.',
        'academic_title_other.string' => 'Học hàm khác phải là một chuỗi.',
        'academic_title_other.max' => 'Học hàm khác không được vượt quá 100 ký tự.',
        'degree.in' => 'Học vị không hợp lệ.',
        'degree_other.required_if' => 'Vui lòng nhập học vị khác.',
        'degree_other.string' => 'Học vị khác phải là một chuỗi.',
        'degree_other.max' => 'Học vị khác không được vượt quá 100 ký tự.',
        'selectedRoles.array' => 'Danh sách vai trò không hợp lệ.',
        'selectedRoles.*.exists' => 'Vai trò không tồn tại trong hệ thống.',
    ];

    protected function normalizeToken(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $value))) ?? '';
    }

    protected function applyAcademicTitleValue(?string $value): void
    {
        $token = $this->normalizeToken($value);

        if ($token === 'gs') {
            $this->academic_title = 'gs';
            $this->academic_title_other = null;
            return;
        }

        if ($token === 'pgs') {
            $this->academic_title = 'pgs';
            $this->academic_title_other = null;
            return;
        }

        if (filled($value)) {
            $this->academic_title = 'other';
            $this->academic_title_other = $value;
            return;
        }

        $this->academic_title = null;
        $this->academic_title_other = null;
    }

    protected function applyDegreeValue(?string $value): void
    {
        $token = $this->normalizeToken($value);

        if (in_array($token, ['cn', 'cunhan'], true)) {
            $this->degree = 'cn';
            $this->degree_other = null;
            return;
        }

        if (in_array($token, ['ths', 'ths'], true)) {
            $this->degree = 'ths';
            $this->degree_other = null;
            return;
        }

        if ($token === 'ts') {
            $this->degree = 'ts';
            $this->degree_other = null;
            return;
        }

        if (in_array($token, ['tsk', 'tskh'], true)) {
            $this->degree = 'tsk';
            $this->degree_other = null;
            return;
        }

        if (filled($value)) {
            $this->degree = 'other';
            $this->degree_other = $value;
            return;
        }

        $this->degree = null;
        $this->degree_other = null;
    }

    protected function applyPositionValues(mixed $positions): void
    {
        $this->position_vi = null;
        $this->position_en = null;

        if (is_array($positions)) {
            $this->position_vi = $positions['vi'] ?? null;
            $this->position_en = $positions['en'] ?? null;
            return;
        }

        if (is_string($positions) && trim($positions) !== '') {
            $decoded = json_decode($positions, true);

            if (is_array($decoded)) {
                $this->position_vi = $decoded['vi'] ?? null;
                $this->position_en = $decoded['en'] ?? null;
                return;
            }

            // Backward compatibility: old plain string is treated as VI value
            $this->position_vi = $positions;
        }
    }

    protected function buildPositionsPayload(): ?array
    {
        $vi = trim((string) ($this->position_vi ?? ''));
        $en = trim((string) ($this->position_en ?? ''));

        if ($vi === '' && $en === '') {
            return null;
        }

        return [
            'vi' => $vi !== '' ? $vi : null,
            'en' => $en !== '' ? $en : null,
        ];
    }

    public function getRolesProperty()
    {
        return Role::select('id', 'name', 'display_name')->get();
    }

    // LẤY DỮ LIỆU KHÓA, NGÀNH, CHUYÊN NGÀNH GIỐNG TRANG CREATE
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
        $intakeId = $this->intake_id;

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
        if (!$this->program_major_id) {
            return collect();
        }

        $intakeId = $this->intake_id;

        return Major::query()
            ->where('program_major_id', $this->program_major_id)
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

    public function getDepartmentsProperty()
    {
        return Department::select('id', 'name')->get();
    }

    public function updated($property): void
    {
        if (in_array($property, ['intake_id', 'program_major_id', 'major_id', 'department_id', 'date_of_birth']) && $this->$property === '') {
            $this->$property = null;
        }

        if ($property === 'user_type') {
            $this->reset(
                'phone',
                'gender',
                'date_of_birth',
                'student_code',
                'class_name',
                'intake_id',
                'program_major_id', // Bổ sung
                'major_id',
                'staff_code',
                'department_id',
                'position_vi',
                'position_en',
                'academic_title',
                'degree',
                'academic_title_other',
                'degree_other'
            );
        }

        if ($property === 'academic_title' && $this->academic_title !== 'other') {
            $this->academic_title_other = null;
        }

        if ($property === 'degree' && $this->degree !== 'other') {
            $this->degree_other = null;
        }

        if (in_array($property, ['showPasswordModal', 'password', 'password_confirmation'], true)) {
            return;
        }

        $this->validateOnly($property);
    }

    public function updatedProgramMajorId($value)
    {
        if ($value === '') {
            $this->program_major_id = null;
        }
        $this->major_id = null;
    }

    public function updatedIntakeId($value)
    {
        if ($value === '') {
            $this->intake_id = null;
        }
        $this->program_major_id = null;
        $this->major_id = null;
    }

    public function updatedShowPasswordModal($value): void
    {
        if (! $value) {
            $this->reset(['password', 'password_confirmation']);
            $this->resetValidation(['password', 'password_confirmation']);
        }
    }

    public function openPasswordModal(): void
    {
        $this->reset(['password', 'password_confirmation']);
        $this->resetValidation(['password', 'password_confirmation']);
        $this->showPasswordModal = true;
    }

    public function updatePassword(): void
    {
        try {
            $this->validate([
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ], [
                'password.required' => 'Mật khẩu mới không được để trống.',
                'password.string' => 'Mật khẩu mới phải là một chuỗi.',
                'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
                'password.confirmed' => 'Xác nhận mật khẩu chưa khớp.',
            ]);
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại mật khẩu đã nhập.');
            throw $e;
        }

        try {
            DB::transaction(function () {
                $user = User::findOrFail($this->userId);

                $user->forceFill([
                    'password' => Hash::make($this->password), // Thêm Hash::make cho an toàn
                    'remember_token' => Str::random(60),
                ])->save();
            });

            $this->showPasswordModal = false;
            $this->reset(['password', 'password_confirmation']);
            $this->resetValidation(['password', 'password_confirmation']);
            $this->success('Đổi mật khẩu thành công!');
        } catch (\Throwable $e) {
            $this->error('Không thể đổi mật khẩu.');
            report($e);
        }
    }

    public function mount($id)
    {
        if ($id) {
            $user = User::with(['student', 'lecturer', 'roles'])->findOrFail($id);
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->user_type = $user->user_type;
            $this->selectedRoles = $user->roles->pluck('name')->toArray();
            $this->avatarUrl = $user->avatar ? asset($user->avatar) : asset('/assets/images/default-user-image.png');
            $this->is_active = $user->is_active;
            $this->last_login_at = $user->last_login_at?$user->last_login_at->format('d/m/Y H:i'): 'Chưa đăng nhập lần nào';

            if ($user->user_type === 'student' && $user->student) {
                $this->student_code = $user->student->student_code;
                $this->class_name = $user->student->class_name;
                $this->date_of_birth = (string)optional( $user->student->date_of_birth)->format('Y-m-d');
                $this->gender = $user->student->gender;
                $this->phone = $user->student->phone;
                $this->intake_id = $user->student->intake_id;
                $this->program_major_id = $user->student->program_major_id; // Bổ sung
                $this->major_id = $user->student->major_id;
                $this->studentId = $user->student?->id;
            }
            if ($user->user_type === 'lecturer' && $user->lecturer) {
                $this->staff_code = $user->lecturer->staff_code;
//                $this->date_of_birth = $user->lecturer->date_of_birth;
                $this->gender = $user->lecturer->gender;
                $this->phone = $user->lecturer->phone;
                $this->department_id = $user->lecturer->department_id;
                $this->applyPositionValues($user->lecturer->positions);
                $this->applyAcademicTitleValue($user->lecturer->academic_title);
                $this->applyDegreeValue($user->lecturer->degree);
                $this->lecturerId = $user->lecturer?->id;
            }
        }
    }

    public function save()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        try {
            DB::transaction(function () {
                $user = User::findOrFail($this->userId);

                $avatarPath = $user->avatar;

                if ($this->avatar) {
                    // xóa avatar cũ
                    if ($user->avatar && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar))) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
                    }

                    // upload avatar mới
                    $avatarPath = '/storage/' . $this->avatar->store('uploads/avatars', 'public');
                }

                // Cập nhật user
                $user->update([
                    'name' => $this->name,
                    'email' => $this->email,
                    'user_type' => $this->user_type,
                    'avatar' => $avatarPath,
                    'is_active' => $this->is_active,
                ]);

                // Gán role
                $user->syncRoles($this->selectedRoles ?? []);

                /*
                |--------------------------------------------------------------------------
                | STUDENT
                |--------------------------------------------------------------------------
                */
                if ($this->user_type === 'student') {

                    Student::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'student_code' => $this->student_code,
                            'class_name' => $this->class_name,
                            'gender' => $this->gender?: null,
                            'date_of_birth' => $this->date_of_birth?: null,
                            'phone' => $this->phone?: null,
                            'intake_id' => $this->intake_id?: null,
                            'program_major_id' => $this->program_major_id?: null, // Bổ sung
                            'major_id' => $this->major_id?: null
                        ]
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | LECTURER
                |--------------------------------------------------------------------------
                */
                if ($this->user_type === 'lecturer') {
                    $academicTitleValue = $this->academic_title === 'other'
                        ? trim((string) $this->academic_title_other)
                        : $this->academic_title;

                    $degreeValue = $this->degree === 'other'
                        ? trim((string) $this->degree_other)
                        : $this->degree;

                    $slugService = new LecturerSlugService();
                    $slug = $slugService->generateSlug($this->name, $this->lecturerId);

                    Lecturer::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'staff_code' => $this->staff_code,
                            'slug' => $slug,
                            'gender' => $this->gender?: null,
//                            'date_of_birth' => $this->date_of_birth, // Có cập nhật date_of_birth như tạo mới
                            'department_id' => $this->department_id?: null,
                            'phone' => $this->phone?: null,
                            'positions' => $this->buildPositionsPayload(),
                            'academic_title' => $academicTitleValue ?: null,
                            'degree' => $degreeValue ?: null,
                        ]
                    );
                }

            });

            $this->success('Chỉnh sửa người dùng thành công!',
            );
        } catch (\Throwable $e) {
            $this->error(
                'Không thể chỉnh sửa người dùng.'
            );

            report($e);
        }
    }
};
?>

<div>
    {{--  start - title  --}}
    <x-slot:title>
        Chỉnh sửa người dùng
    </x-slot:title>
    {{--  end - title  --}}

    {{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
        <a href="{{route('admin.user.user-list')}}"
           class="font-semibold text-slate-700" wire:navigate>{{__('User list')}}</a>
        <span class="mx-1">/</span>
        <span>Chỉnh sửa người dùng</span>
    </x-slot:breadcrumb>
    {{-- end - breadcrumb --}}

    {{--    start - header--}}
    <x-header title="Chỉnh sửa người dùng"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"></x-header>
    {{--    end - header--}}
    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]! relative">

        <x-card class="col-span-10 flex flex-col p-3!">
            <div x-data="{ open: true }"
                 class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                {{-- HEADER KHỐI --}}
                <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                    <button type="button"
                            class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                            @click="open = !open">
                        Thông tin tài khoản
                    </button>

                    <div class="flex items-center gap-1">
                        <x-icon name="o-chevron-down"
                                class="w-5 h-5 cursor-pointer transition-transform"
                                x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                    </div>
                </div>

                {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                    <div class="flex gap-5 items-center">
                        <x-toggle label="{{$is_active?'Hoạt  động' : 'Đã khóa'}}" wire:model.live.debounce.500ms="is_active" class="toggle-success"/>
                        <span class="text-md text-slate-800 font-medium">Đăng nhập gần nhất: {{$last_login_at}}</span>
                    </div>
                    <x-input label="Họ và tên" wire:model.blur="name"
                             placeholder="Nhập họ và tên người dùng"
                             required/>
                    <x-input label="Email" wire:model.blur="email"
                             placeholder="Nhập email người dùng"
                             required/>
                    <x-file
                        wire:model="avatar"
                        accept="image/png, image/jpeg" label="Ảnh đại diện"
                        change-text="Thay đổi ảnh">
                        <img src="{{ $avatar ? $avatar->temporaryUrl() : $avatarUrl }}" class="size-32 rounded-lg object-cover"
                             alt="ảnh đại diện"/>
                    </x-file>
                    <x-select
                        label="Loại người dùng"
                        wire:model.live="user_type"
                        :options="$userType"
                        placeholder="Chọn loại người dùng"
                        required
                    />
                </div>
            </div>
            @switch($user_type)
                @case('student')
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden mt-5">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Thông tin sinh viên
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input label="Mã sinh viên" wire:model.blur="student_code" placeholder="Nhập mã sinh viên"/>
                            <x-input label="Lớp" wire:model.blur="class_name" placeholder="Nhập tên lớp"/>
                            <x-datetime label="Ngày sinh" wire:model.blur="date_of_birth" />
                            <x-radio label="Giới tính" wire:model.live="gender" :options="$genders"
                                     inline
                                     class="radio-primary radio-sm"/>
                            <x-input label="Số điện thoại" wire:model.blur="phone" placeholder="Nhập số điện thoại "/>

                            {{-- CẬP NHẬT 3 DROPDOWN CHUẨN --}}
                            <x-select
                                label="{{ __('Intake') }}"
                                wire:model.live="intake_id"
                                :options="$this->intakes"
                                option-value="id"
                                option-label="name"
                                placeholder="{{ __('No intake selected') }}"
                            />
                            <x-select
                                wire:key="select-program-major"
                                label="{{ __('Major') }}"
                                wire:model.live="program_major_id"
                                :options="$this->programMajors"
                                option-value="id"
                                option-label="name"
                                placeholder="{{!$intake_id? __('Select intake first') : __('Select major') }}"
                                :disabled="empty($intake_id)"
                            />

                            <x-select
                                wire:key="select-major-{{ $program_major_id }}"
                                label="{{__('Specialization/Area of specialization')}}"
                                wire:model="major_id"
                                :options="$this->majors"
                                option-value="id"
                                option-label="name"
                                placeholder="{{!$program_major_id?__('Select specialization first'):__('No specialization selected') }}"
                                :disabled="empty($program_major_id) || $this->majors->isEmpty()"
                            />
                        </div>
                    </div>
                    @break
                @case('lecturer')
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden mt-5">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Thông tin giảng viên
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input label="Mã giảng viên" wire:model.blur="staff_code" placeholder="Nhập mã giảng viên"/>
                            <x-select
                                label="Bộ môn"
                                wire:model.live="department_id"
                                :options="$this->departments"
                                placeholder="Chọn Bộ môn"
                                required
                            />
{{--                            <x-datetime label="Ngày sinh" wire:model.blur="date_of_birth"/>--}}
                            <x-radio label="Giới tính" wire:model.live="gender" :options="$genders"
                                     inline
                                     class="radio-primary radio-sm"/>
                            <x-input label="Số điện thoại" wire:model.blur="phone" placeholder="Nhập số điện thoại"/>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-input label="Chức vụ (Tiếng Việt)" wire:model.blur="position_vi" placeholder="Nhập chức vụ Tiếng Việt"/>
                                <x-input label="Chức vụ (Tiếng Anh)" wire:model.blur="position_en" placeholder="Nhập chức vụ Tiếng Anh"/>

                            </div>
                            <x-select
                                label="Học hàm"
                                wire:model.live="academic_title"
                                :options="$academicTitleOptions"
                                placeholder="Chọn học hàm"
                            />
                            @if($academic_title === 'other')
                                <x-input label="Học hàm (khác)" wire:model.blur="academic_title_other" placeholder="Nhập học hàm khác"/>
                            @endif

                            <x-select
                                label="Học vị"
                                wire:model.live="degree"
                                :options="$degreeOptions"
                                placeholder="Chọn học vị"
                            />
                            @if($degree === 'other')
                                <x-input label="Học vị (khác)" wire:model.blur="degree_other" placeholder="Nhập học vị khác"/>
                            @endif
                        </div>
                    </div>
                    @break
            @endswitch

            <div x-data="{ open: true }"
                 class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden mt-5">

                {{-- HEADER KHỐI --}}
                <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                    <button type="button"
                            class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                            @click="open = !open">
                        Danh sách vai trò
                    </button>

                    <div class="flex items-center gap-1">
                        <x-icon name="o-chevron-down"
                                class="w-5 h-5 cursor-pointer transition-transform"
                                x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                    </div>
                </div>

                {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                    <div class="">
                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-2 ">
                            @forelse($this->roles as $role)
                                <div class="select-none" wire:key="permission-{{ $role->id }}">
                                    <x-checkbox
                                        label="{{ $role->display_name }}"
                                        wire:model="selectedRoles"
                                        value="{{ $role->name }}"
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
                </div>
            </div>
            <div wire:loading.flex
                 wire:target="user_type"
                 class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
                <div class="flex flex-col items-center gap-2 flex-1">
                    <x-loading class="text-primary loading-lg"/>
                    <span class="text-sm font-medium text-gray-500">Đang cập nhật dữ liệu...</span>
                </div>
            </div>
        </x-card>

        <x-card class="col-span-2 bg-white p-3! sticky top-22 self-start" title="Hành động" shadow separator progress-indicator="save">
            <x-button label="Đổi mật khẩu" class="btn-warning text-white  my-1 w-full" wire:click="openPasswordModal" spinner="openPasswordModal"/>
            <x-button label="Lưu" class="bg-primary text-white my-1 w-full" wire:click="save" spinner/>
        </x-card>
    </div>

    <x-modal wire:model="showPasswordModal" title="Đổi mật khẩu" separator>
        <div class="space-y-0">
            <x-password
                label="Mật khẩu mới"
                wire:model.defer="password"
                password-icon="o-lock-closed"
                password-visible-icon="o-lock-open"
                placeholder="••••••••"
                required
            />

            <x-password
                label="Xác nhận mật khẩu"
                wire:model.defer="password_confirmation"
                password-icon="o-lock-closed"
                password-visible-icon="o-lock-open"
                placeholder="••••••••"
                required
            />
        </div>

        <x-slot:actions>
            <x-button label="Hủy" wire:click="$wire.showPasswordModal = false" />
            <x-button label="Lưu mật khẩu" class="btn-primary" wire:click="updatePassword" spinner="updatePassword" />
        </x-slot:actions>
    </x-modal>
</div>
