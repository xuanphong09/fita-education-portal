<?php

use App\Models\Department;
use App\Models\Intake;
use App\Models\Major;
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

new class extends Component {
    use Toast, WithFileUploads;

    public $userType = [
        ['id' => 'admin', 'name' => 'Admin'],
        ['id' => 'lecturer', 'name' => 'Giảng viên'],
        ['id' => 'student', 'name' => 'Sinh viên'],
    ];

    public $genders = [
        ['id' => 'male', 'name' => 'Nam'],
        ['id' => 'female', 'name' => 'Nữ'],
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
//    lecturer
    public $staff_code;
    public $department_id;
    public $position;

    public $userId;
    public $studentId;
    public $lecturerId;
    public $is_active;

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
            'position' => 'exclude_unless:user_type,lecturer|nullable|string|max:255',

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
        'major_id.exists' => 'Chuyên ngành không tồn tại.',

        // LECTURER
        'staff_code.required' => 'Mã giảng viên không được để trống.',
        'staff_code.string' => 'Mã giảng viên phải là một chuỗi.',
        'staff_code.max' => 'Mã giảng viên không được vượt quá 100 ký tự.',
        'staff_code.unique' => 'Mã giảng viên đã tồn tại trong hệ thống.',

        'department_id.exists' => 'Bộ môn không tồn tại.',

        'position.string' => 'Chức vụ phải là một chuỗi.',
        'position.max' => 'Chức vụ không được vượt quá 255 ký tự.',
        'selectedRoles.array' => 'Danh sách vai trò không hợp lệ.',
        'selectedRoles.*.exists' => 'Vai trò không tồn tại trong hệ thống.',
    ];

    public function getRolesProperty()
    {
        return Role::select('id', 'name', 'display_name')->get();
    }

    public function getMajorsProperty()
    {
        return Major::select('id', 'name')->get();
    }

    public function getIntakesProperty()
    {
        return Intake::select('id', 'name')->get();
    }

    public function getDepartmentsProperty()
    {
        return Department::select('id', 'name')->get();
    }

    public function updated($property): void
    {
        if ($property === 'user_type') {
            $this->reset(
                'phone',
                'gender',
                'date_of_birth',
                'student_code',
                'class_name',
                'intake_id',
                'major_id',
                'staff_code',
                'department_id',
                'position'
            );
        }

        $this->validateOnly($property);
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

            if ($user->user_type === 'student' && $user->student) {
                $this->student_code = $user->student->student_code;
                $this->class_name = $user->student->class_name;
                $this->date_of_birth = $user->student->date_of_birth;
                $this->gender = $user->student->gender;
                $this->phone = $user->student->phone;
                $this->intake_id = $user->student->intake_id;
                $this->major_id = $user->student->major_id;
                $this->studentId = $user->student?->id;
            }
            if ($user->user_type === 'lecturer' && $user->lecturer) {
                $this->staff_code = $user->lecturer->staff_code;
                $this->date_of_birth = $user->lecturer->date_of_birth;
                $this->gender = $user->lecturer->gender;
                $this->phone = $user->lecturer->phone;
                $this->department_id = $user->lecturer->department_id;
                $this->position = $user->lecturer->positions;
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

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn lưu chỉnh sửa người dùng này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmSave'
        ]);
    }

    #[On('confirmSave')]
    public function confirmSave()
    {
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

                // Tạo user
                $user->update([
                    'name' => $this->name,
                    'email' => $this->email,
                    'user_type' => $this->user_type,
                    'avatar' => $avatarPath,
                    'is_active' => $this->is_active,
                ]);

                // Gán role
                if (!empty($this->selectedRoles)) {
                    $user->assignRole($this->selectedRoles);
                }

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
                            'gender' => $this->gender,
                            'date_of_birth' => $this->date_of_birth,
                            'phone' => $this->phone,
                            'intake_id' => $this->intake_id,
                            'major_id' => $this->major_id
                        ]
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | LECTURER
                |--------------------------------------------------------------------------
                */
                if ($this->user_type === 'lecturer') {

                    Lecturer::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'staff_code' => $this->staff_code,
                            'gender' => $this->gender,
                            'department_id' => $this->department_id,
                            'phone' => $this->phone,
                            'positions' => $this->position
                        ]
                    );
                }

            });

            $this->success('Chỉnh sửa người dùng thành công!',
                redirectTo: route('admin.user.user-list')
            );
        } catch (\Throwable $e) {
            $this->error(
                'Không thể tạo người dùng.'
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
           class="font-semibold text-slate-700">{{__('User list')}}</a>
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
                    <x-toggle label="{{$is_active?'Hoạt  đông' : 'Đã khóa'}}" wire:model.live.debounce.500ms="is_active" class="toggle-success"/>
                    <x-input label="Họ và tên" wire:model.live="name"
                             required/>
                    <x-input label="Email" wire:model.live.debounce.500ms="email"
                             required/>
                    {{--                    <x-input label="Mật khẩu" wire:model.live.debounce.500ms="password" required/>--}}
                    <x-file
                        wire:model.live.debounce.500ms="avatar"
                        accept="image/png, image/jpeg" label="Ảnh đại diện"
                        change-text="Thay đổi ảnh">
                        <img src="{{ $avatar ?? $avatarUrl}}" class="size-32 rounded-lg object-cover"
                             alt="ảnh đại diện"/>
                    </x-file>
                    <x-select
                        label="Loại người dùng"
                        wire:model.live.debounce.500ms="user_type"
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
                            <x-input label="Mã sinh viên" wire:model.live.debounce.500ms="student_code"/>
                            <x-input label="Lớp" wire:model.live.debounce.500ms="class_name"/>
                            <x-datetime label="Ngày sinh" wire:model.live.debounce.500ms="date_of_birth"/>
                            <x-radio label="Giới tính" wire:model.live.debounce.500ms="gender" :options="$genders"
                                     inline
                                     class="radio-primary radio-sm"/>
                            <x-input label="Số điện thoại" wire:model.live.debounce.500ms="phone"/>
                            <x-select
                                label="Khóa"
                                wire:model.live.debounce.500ms="intake_id"
                                :options="$this->intakes"
                                placeholder="Chọn Khóa"
                                required
                            />
                            <x-select
                                label="Chuyên ngành"
                                wire:model.live.debounce.500ms="major_id"
                                :options="$this->majors"
                                placeholder="Chọn Chuyên ngành"
                                required
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
                            <x-input label="Mã giảng viên" wire:model.live.debounce.500ms="staff_code"/>
                            <x-select
                                label="Bộ môn"
                                wire:model.live.debounce.500ms="department_id"
                                :options="$this->departments"
                                placeholder="Chọn Bộ môn"
                                required
                            />
                            <x-datetime label="Ngày sinh" wire:model.live.debounce.500ms="date_of_birth"/>
                            <x-radio label="Giới tính" wire:model.live.debounce.500ms="gender" :options="$genders"
                                     inline
                                     class="radio-primary radio-sm"/>
                            <x-input label="Số điện thoại" wire:model.live.debounce.500ms="phone"/>
                            <x-input label="Chức vụ" wire:model.live.debounce.500ms="position"/>
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
                        {{--                        <label class="font-semibold text-gray-700 mb-3 block">Danh sách vai trò</label>--}}

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

        <x-card class="col-span-2 bg-white p-3!" title="Hành động" shadow separator progress-indicator="save">
            <x-button label="Lưu" class="bg-primary text-white my-1 w-full" wire:click="save" spinner/>
            <x-button label="Trở lại" class="bg-warning text-white my-1 w-full"
                      link="{{route('admin.user.user-list')}}"/>
        </x-card>
    </div>
</div>
