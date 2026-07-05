<?php

use App\Jobs\SyncStudentGradesJob;
use App\Models\Department;
use App\Models\Intake;
use App\Models\Major;
use App\Models\ProgramMajor;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Str;

new
#[Layout('layouts.client')]
class extends Component {
    use Toast;

    public bool $isSetupMode = false;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $gender = '';

    public string $student_code = '';
    public string $class_name = '';
    public string $date_of_birth = '';
    public $major_id = null;
    public $program_major_id = null;
    public $intake_id = null;

    public string $staff_code = '';
    public $department_id = null;
    public string $degree = '';
    public string $academic_title = '';
    public string $degree_other = '';
    public string $academic_title_other = '';
    public string $position_vi = '';
    public string $position_en = '';

    public string $vnua_password = '';
    public string $last_academic_stats_updated_at = 'Chưa đồng bộ';
    public string $grade_sync_status = 'idle';
    public string $grade_sync_message = '';
    public bool $check_agree = false;
    public bool $redirectAfterSync = false;

    public bool $isStudent = false;
    public bool $isLecturer = false;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $user->loadMissing('student.major', 'student.intake', 'lecturer.department');

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;

        $this->isStudent = (bool) $user->student;
        $this->isLecturer = (bool) $user->lecturer;

        if ($user->user_type === 'student') {
            $student = $user->student;

            $this->isSetupMode = !$student
                || empty($student->intake_id)
                || empty($student->class_name)
                || empty($student->program_major_id);
        } else {
            $this->isSetupMode = false;
        }

        if ($this->isStudent) {
            $student = $user->student;

            $this->student_code = (string) $student->student_code;
            $this->class_name = (string) ($student->class_name ?? '');
            $this->gender = (string) ($student->gender ?? '');
            $this->date_of_birth = (string) optional($student->date_of_birth)->format('Y-m-d');
            $this->phone = (string) ($student->phone ?? '');
            $this->major_id = $student->major_id;
            $this->program_major_id = $student->program_major_id;
            $this->intake_id = $student->intake_id;

            $this->refreshSyncState($student);

            $this->check_agree = filled($student->vnua_password);
        }

        if ($this->isLecturer) {
            $lecturer = $user->lecturer;
            $positions = is_array($lecturer->positions) ? $lecturer->positions : [];

            $this->staff_code = (string) $lecturer->staff_code;
            $this->department_id = $lecturer->department_id;
            $this->gender = (string) ($lecturer->gender ?? '');
            $this->phone = (string) ($lecturer->phone ?? '');

            $this->applyDegreeValue($lecturer->degree);
            $this->applyAcademicTitleValue($lecturer->academic_title);

            $this->position_vi = (string) ($positions['vi'] ?? '');
            $this->position_en = (string) ($positions['en'] ?? '');
        }
    }

    protected function refreshSyncState(?Student $student): void
    {
        if (!$student) {
            $this->last_academic_stats_updated_at = __('Never synchronized');
            $this->grade_sync_status = 'idle';
            $this->grade_sync_message = '';
            return;
        }

        $this->last_academic_stats_updated_at = $student->last_academic_stats_updated_at
            ? (string) optional($student->last_academic_stats_updated_at)->format('H:i d/m/Y')
            : __('Never synchronized');

        $this->grade_sync_status = (string) ($student->grade_sync_status ?? 'idle');
        $this->grade_sync_message = (string) ($student->grade_sync_message ?? '');
    }

    public function getGenderOptionsProperty(): array
    {
        return [
            ['id' => 'male', 'name' => __('Male')],
            ['id' => 'female', 'name' => __('Female')],
            ['id' => 'other', 'name' => __('Other')],
        ];
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
            ->map(fn (Intake $intake) => [
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

    public function updatedProgramMajorId(): void
    {
        $this->major_id = null;
    }

    public function updatedIntakeId(): void
    {
        $this->program_major_id = null;
        $this->major_id = null;
    }

    public function updatedCheckAgree($value): void
    {
        if (!$value) {
            $this->vnua_password = '';
        }
    }

    public function getDepartmentsProperty()
    {
        return Department::query()
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get(['id', 'name', 'slug'])
            ->map(function (Department $department) {
                return [
                    'id' => $department->id,
                    'name' => $department->getTranslation('name', app()->getLocale(), false)
                        ?: $department->getTranslation('name', 'vi', false)
                            ?: $department->getTranslation('name', 'en', false)
                                ?: $department->slug,
                ];
            });
    }

    public function getAcademicTitleOptionsProperty(): array
    {
        return [
            ['id' => 'gs', 'name' => 'GS'],
            ['id' => 'pgs', 'name' => 'PGS'],
            ['id' => 'other', 'name' => __('Other')],
        ];
    }

    public function getDegreeOptionsProperty(): array
    {
        return [
            ['id' => 'cn', 'name' => 'CN'],
            ['id' => 'ths', 'name' => 'ThS'],
            ['id' => 'ts', 'name' => 'TS'],
            ['id' => 'tsk', 'name' => 'TSKH'],
            ['id' => 'other', 'name' => __('Other')],
        ];
    }

    protected function normalizeToken(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $value))) ?? '';
    }

    protected function applyAcademicTitleValue(?string $value): void
    {
        $token = $this->normalizeToken($value);

        if ($token === 'gs') {
            $this->academic_title = 'gs';
            $this->academic_title_other = '';
            return;
        }

        if ($token === 'pgs') {
            $this->academic_title = 'pgs';
            $this->academic_title_other = '';
            return;
        }

        if (filled($value)) {
            $this->academic_title = 'other';
            $this->academic_title_other = (string) $value;
            return;
        }

        $this->academic_title = '';
        $this->academic_title_other = '';
    }

    protected function applyDegreeValue(?string $value): void
    {
        $token = $this->normalizeToken($value);

        if (in_array($token, ['cn', 'cunhan'], true)) {
            $this->degree = 'cn';
            $this->degree_other = '';
            return;
        }

        if ($token === 'ths') {
            $this->degree = 'ths';
            $this->degree_other = '';
            return;
        }

        if ($token === 'ts') {
            $this->degree = 'ts';
            $this->degree_other = '';
            return;
        }

        if (in_array($token, ['tsk', 'tskh'], true)) {
            $this->degree = 'tsk';
            $this->degree_other = '';
            return;
        }

        if (filled($value)) {
            $this->degree = 'other';
            $this->degree_other = (string) $value;
            return;
        }

        $this->degree = '';
        $this->degree_other = '';
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $user->loadMissing('student', 'lecturer');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ];

        $messages = [
            'name.required' => __('Full name is required.'),
            'email.required' => __('Email is required.'),
            'email.email' => __('Email is invalid.'),
            'email.unique' => __('Email has already been taken.'),
        ];

        if ($this->isStudent) {
            $rules['class_name'] = ['required', 'string', 'max:50'];
            $rules['gender'] = ['nullable', 'in:male,female,other'];
            $rules['date_of_birth'] = ['nullable', 'date'];
            $rules['phone'] = ['nullable', 'string', 'max:20', 'regex:/^0[0-9]{9}$/'];
            $rules['intake_id'] = ['required', 'exists:intakes,id'];
            $rules['major_id'] = ['nullable', 'exists:majors,id'];
            $rules['program_major_id'] = ['required', 'exists:program_majors,id'];
            $rules['vnua_password'] = ['nullable', 'string', 'max:255'];
            $rules['check_agree'] = [
                'boolean',
                function ($attribute, $value, $fail) {
                    if (filled($this->vnua_password) && !$value) {
                        $fail(__('You must agree to allow password saving and data updates.'));
                    }
                },
            ];

            $messages['class_name.required'] = __('Class is required.');
            $messages['class_name.max'] = __('Class name may not be greater than 50 characters.');
            $messages['gender.in'] = __('Gender selection is invalid.');
            $messages['date_of_birth.date'] = __('Date of birth is invalid.');
            $messages['phone.max'] = __('Phone number may not be greater than 20 characters.');
            $messages['phone.regex'] = __('Phone number format is invalid (0xxxxxxxxx).');
            $messages['intake_id.required'] = __('Intake is required.');
            $messages['intake_id.exists'] = __('Selected intake is invalid.');
            $messages['major_id.exists'] = __('Selected major is invalid.');
            $messages['program_major_id.required'] = __('Major is required.');
            $messages['program_major_id.exists'] = __('Selected specialized is invalid.');
        }

        if ($this->isLecturer) {
            $rules['gender'] = ['nullable', 'in:male,female,other'];
            $rules['phone'] = ['nullable', 'string', 'max:20', 'regex:/^0[0-9]{9}$/'];
            $rules['department_id'] = ['nullable', 'exists:departments,id'];
            $rules['degree'] = ['nullable', 'in:cn,ths,ts,tsk,other'];
            $rules['degree_other'] = ['nullable', 'required_if:degree,other', 'string', 'max:100'];
            $rules['academic_title'] = ['nullable', 'in:gs,pgs,other'];
            $rules['academic_title_other'] = ['nullable', 'required_if:academic_title,other', 'string', 'max:100'];
            $rules['position_vi'] = ['nullable', 'string', 'max:255'];
            $rules['position_en'] = ['nullable', 'string', 'max:255'];

            $messages['gender.in'] = __('Gender selection is invalid.');
            $messages['phone.max'] = __('Phone number may not be greater than 20 characters.');
            $messages['phone.regex'] = __('Phone number format is invalid (0xxxxxxxxx).');
            $messages['department_id.exists'] = __('Selected department is invalid.');
            $messages['degree.in'] = __('Selected degree is invalid.');
            $messages['degree_other.required_if'] = __('Please enter custom degree.');
            $messages['academic_title.in'] = __('Selected academic title is invalid.');
            $messages['academic_title_other.required_if'] = __('Please enter custom academic title.');
        }

        $data = $this->validate($rules, $messages);

        $user->forceFill([
            'name' => trim((string) $data['name']),
            'email' => trim((string) $data['email']),
        ])->save();

        if ($this->isStudent && $user->student) {
            $student = $user->student;

            $studentUpdateData = [
                'class_name' => trim((string) ($data['class_name'] ?? '')) ?: null,
                'gender' => trim((string) ($data['gender'] ?? '')) ?: null,
                'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'intake_id' => $data['intake_id'] ?: null,
                'major_id' => $data['major_id'] ?: null,
                'program_major_id' => $data['program_major_id'] ?: null,
            ];

            $shouldQueueSync = false;

            if ($this->check_agree) {
                if (filled($this->vnua_password)) {
                    $studentUpdateData['vnua_password'] = $this->vnua_password;
                    $shouldQueueSync = true;
                }
            } else {
                $studentUpdateData['vnua_password'] = null;
                $studentUpdateData['grade_sync_status'] = 'idle';
                $studentUpdateData['grade_sync_message'] = null;
                $studentUpdateData['grade_sync_started_at'] = null;
                $studentUpdateData['grade_sync_failed_at'] = null;
            }

            $student->update($studentUpdateData);
            $student->refresh();

            if ($shouldQueueSync) {
                $this->queueGradeSync($student, true);
            } else {
                $this->refreshSyncState($student);
            }

            $this->vnua_password = '';
            $this->check_agree = filled($student->vnua_password);
        }

        if ($this->isLecturer && $user->lecturer) {
            $positions = [
                'vi' => trim((string) ($data['position_vi'] ?: '')),
                'en' => trim((string) ($data['position_en'] ?: '')),
            ];

            $user->lecturer->update([
                'gender' => trim((string) ($data['gender'] ?? '')) ?: null,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'department_id' => $data['department_id'] ?? null,
                'degree' => ($data['degree'] ?? null) === 'other'
                    ? trim((string) ($data['degree_other'] ?? '')) ?: null
                    : (($data['degree'] ?? null) ?: null),
                'academic_title' => ($data['academic_title'] ?? null) === 'other'
                    ? trim((string) ($data['academic_title_other'] ?? '')) ?: null
                    : (($data['academic_title'] ?? null) ?: null),
                'positions' => array_filter($positions, fn ($value) => $value !== ''),
            ]);
        }

        if ($this->isSetupMode) {
            $this->success(
                __('You have completed setting up your personal information. Welcome to the system!'),
                redirectTo: route('client.home')
            );

            return;
        }

        $this->success(__('Profile updated successfully.'));
    }

    protected function queueGradeSync(Student $student, bool $force = true): void
    {
        $student->forceFill([
            'grade_sync_status' => 'queued',
            'grade_sync_message' => 'Đã đưa yêu cầu đồng bộ vào hàng đợi.',
        ])->save();

        SyncStudentGradesJob::dispatch($student->id, $force);

        $student->refresh();

        $this->refreshSyncState($student);
        $this->last_academic_stats_updated_at = __('Synchronization is queued');
    }

    public function syncNow(bool $redirect = false): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $student = $user->student()->first();

        if (!$student) {
            $this->error(__('Student profile not found.'));
            return;
        }

        if (filled($this->vnua_password)) {
            $this->error(__('Please save your profile before synchronizing with the new password.'));
            return;
        }

        if (blank($student->vnua_password)) {
            $this->error(__('Please save your training page password first.'));
            return;
        }

        $this->queueGradeSync($student, true);

        $this->success(__('The synchronization process has begun. The system will update your score shortly.'));

        if ($redirect) {
            $this->redirectRoute('client.training-programs.major', navigate: true);
        }
    }

    public function getCanSyncGradesProperty(): bool
    {
        if (!$this->check_agree) {
            return false;
        }

        $user = Auth::user();

        if (!$user) {
            return false;
        }

        $student = $user->student()->first();

        return $student && filled($student->vnua_password);
    }

    public function refreshSyncHint(): void
    {
        $student = Auth::user()?->student()->first();

        if (!$student) {
            return;
        }

        $this->refreshSyncState($student);
    }
};
?>

<div class="container mx-auto max-w-6xl py-8 px-4 space-y-6">
    <x-slot:title>{{ __('My Account') }}</x-slot:title>

    <x-slot:breadcrumb>
        <span class="whitespace-nowrap font-semibold text-slate-700">{{ __('My Account') }}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{ __('My Account') }}
    </x-slot:titleBreadcrumb>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <x-card class="shadow-md p-4 lg:col-span-3 h-fit">
            <x-client.account-sidebar/>
        </x-card>

        <x-card class="shadow-md p-6 lg:col-span-9">
            <h2 class="text-lg font-semibold mb-4">{{ __('Profile Information') }}</h2>

            @if($isSetupMode)
                <div class="alert alert-warning font-medium shadow-sm mb-6 text-md">
                    <x-icon name="o-exclamation-triangle" class="w-6 h-6"/>
                    <span>{{ __('Vui lòng cập nhật Ngành, Khóa, Lớp để bắt đầu sử dụng hệ thống.') }}</span>
                </div>
            @endif

            <form wire:submit.prevent="saveProfile" class="space-y-0">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                    <x-input
                        label="{{ __('Full name') }}"
                        wire:model.defer="name"
                        placeholder="{{ __('Enter your full name') }}"
                        required
                    />

                    <x-input
                        label="Email"
                        wire:model.defer="email"
                        type="email"
                        readonly
                    />

                    <x-input
                        label="{{ __('Phone number') }}"
                        wire:model.defer="phone"
                        placeholder="0xxxxxxxxx"
                    />

                    <x-radio
                        label="{{ __('Gender') }}"
                        wire:model.defer="gender"
                        :options="$this->genderOptions"
                        inline
                        class="radio-primary radio-sm"
                    />
                </div>

                @if($isStudent)
                    <div class="border-t mt-4 pt-4 space-y-0">
                        <h3 class="text-lg font-semibold text-gray-700">{{ __('Student Information') }}</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <x-input
                                label="{{ __('Student code') }}"
                                :value="$student_code"
                                readonly
                            />

                            <x-input
                                label="{{ __('Class') }}"
                                wire:model.defer="class_name"
                                placeholder="{{ __('Enter class') }}"
                                required
                            />

                            <x-select
                                label="{{ __('Intake') }}"
                                wire:model.live="intake_id"
                                :options="$this->intakes"
                                option-value="id"
                                option-label="name"
                                placeholder="{{ __('No intake selected') }}"
                                required
                            />

                            <x-input
                                label="{{ __('Date of birth') }}"
                                wire:model.defer="date_of_birth"
                                type="date"
                            />

                            <x-select
                                wire:key="select-program-major"
                                label="{{ __('Major') }}"
                                wire:model.live="program_major_id"
                                :options="$this->programMajors"
                                option-value="id"
                                option-label="name"
                                placeholder="{{ !$intake_id ? __('Select intake first') : __('Select major') }}"
                                required
                                :disabled="empty($intake_id)"
                            />

                            <x-select
                                wire:key="select-major-{{ $program_major_id }}"
                                label="{{ __('Specialization/Area of specialization') }}"
                                wire:model="major_id"
                                :options="$this->majors"
                                option-value="id"
                                option-label="name"
                                placeholder="{{ !$program_major_id ? __('Select specialization first') : __('No specialization selected') }}"
                                :disabled="empty($program_major_id) || $this->majors->isEmpty()"
                            />
                        </div>
                    </div>

                    <div class="border-t mt-4 pt-2 space-y-0">
                        <h3 class="text-lg font-semibold text-gray-700">{{ __('Data synchronization') }}</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <div
                                @if(in_array($grade_sync_status, ['queued', 'syncing'], true))
                                    wire:poll.3s="refreshSyncHint"
                                @endif
                            >
                                <x-password
                                    label="{{ __('Training page password') }}"
                                    wire:model="vnua_password"
                                    placeholder="{{ $check_agree ? '••••••••' : __('Enter training page password') }}"
                                    password-icon="o-lock-closed"
                                    clearable
                                    hint="{{ $grade_sync_message }}"
                                    hint-class="text-[16px]! text-gray-600 italic"
                                />
                            </div>

                            <div class="flex justify-end mt-4 flex-col md:mt-0">
                                <x-button
                                    label="{{ __('Sync Now') }}"
                                    icon="o-arrow-path"
                                    class="btn-outline btn-md mb-1"
                                    wire:click="syncNow"
                                    spinner="syncNow"
                                    :hidden="!$this->canSyncGrades"
                                />
                                <p class="text-md text-gray-600 italic mb-1">
                                    ({{ __('Last updated: ') }} {{ $last_academic_stats_updated_at }})
                                </p>
                            </div>
                        </div>

                        <x-checkbox wire:model="check_agree" class="checkbox-primary mt-1">
                            <x-slot:label>
                                <span class="italic text-md font-normal text-gray-600 select-none mt-1">
                                    {{ __('I agree to allow password saving and data updates from the training management page.') }}
                                </span>
                            </x-slot:label>
                        </x-checkbox>
                    </div>
                @endif

                @if($isLecturer)
                    <div class="border-t mt-4 pt-4 space-y-0">
                        <h3 class="text-lg font-semibold text-gray-700">{{ __('Lecturer Information') }}</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">
                            <x-input
                                label="{{ __('Staff code') }}"
                                :value="$staff_code"
                                readonly
                            />

                            <x-select
                                label="{{ __('Department') }}"
                                wire:model.defer="department_id"
                                :options="$this->departments"
                                option-value="id"
                                option-label="name"
                                placeholder="{{ __('Select department') }}"
                            />

                            <div>
                                <x-select
                                    label="{{ __('Degree') }}"
                                    wire:model.live.debounce.300ms="degree"
                                    :options="$this->degreeOptions"
                                    option-value="id"
                                    option-label="name"
                                    placeholder="{{ __('Select degree') }}"
                                />

                                @if($degree === 'other')
                                    <x-input
                                        label="{{ __('Degree (other)') }}"
                                        wire:model.live.debounce.300ms="degree_other"
                                        placeholder="{{ __('Enter custom degree') }}"
                                    />
                                @endif
                            </div>

                            <div>
                                <x-select
                                    label="{{ __('Academic title') }}"
                                    wire:model.live.debounce.300ms="academic_title"
                                    :options="$this->academicTitleOptions"
                                    option-value="id"
                                    option-label="name"
                                    placeholder="{{ __('Select academic title') }}"
                                />

                                @if($academic_title === 'other')
                                    <x-input
                                        label="{{ __('Academic title (other)') }}"
                                        wire:model.live.debounce.300ms="academic_title_other"
                                        placeholder="{{ __('Enter custom academic title') }}"
                                    />
                                @endif
                            </div>

                            <x-input
                                label="{{ __('Position (Tiếng Việt)') }}"
                                wire:model.defer="position_vi"
                                placeholder="{{ __('Enter position (VI)') }}"
                            />

                            <x-input
                                label="{{ __('Position (Tiếng Anh)') }}"
                                wire:model.defer="position_en"
                                placeholder="{{ __('Enter position (EN)') }}"
                            />
                        </div>
                    </div>
                @endif

                <div class="flex justify-center">
                    <x-button
                        label="{{ __('Save profile') }}"
                        class="bg-fita text-white mt-4"
                        type="submit"
                        spinner="saveProfile"
                    />
                </div>
            </form>
        </x-card>
    </div>
</div>
