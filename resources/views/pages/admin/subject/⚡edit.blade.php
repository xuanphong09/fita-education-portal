<?php

use App\Models\GroupSubject;
use App\Models\ProgramSemester;
use App\Models\Subject;
use App\Models\SubjectPrerequisite;
use App\Models\TrainingProgram;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

new class extends Component {
    use Toast, WithFileUploads;

    public int $id;
    public string $code = '';
    public string $name_vi = '';
    public string $name_en = '';
    public int|string|null $group_subject_id = null;
    public string $credits = '0';
    public string $credits_theory = '0';
    public string $credits_practice = '0';
    public bool $is_active = true;
    public array $prerequisite_subject_ids = [];
    public $syllabus_file;
    public ?string $current_syllabus_path = null;
    public ?string $current_syllabus_name = null;
    public bool $remove_syllabus = false;

    public function mount(int $id): void
    {
        $this->id = $id;

        $subject = Subject::query()
            ->findOrFail($id);

        $this->code = $subject->code;
        $this->name_vi = $subject->getTranslation('name', 'vi', false) ?: '';
        $this->name_en = $subject->getTranslation('name', 'en', false) ?: '';
        $this->group_subject_id = $subject->group_subject_id;
        $this->credits = Subject::formatCredit($subject->credits);
        $this->credits_theory = Subject::formatCredit($subject->credits_theory);
        $this->credits_practice = Subject::formatCredit($subject->credits_practice);
        $this->is_active = (bool)$subject->is_active;
        $this->current_syllabus_path = $subject->syllabus_path;
        $this->current_syllabus_name = $subject->syllabus_original_name;
        $programId = $this->programIdsUsingSubject()->first();

        $this->prerequisite_subject_ids = $programId
            ? $subject->prerequisitesForProgram((int)$programId)->pluck('subjects.id')->map(fn($value) => (int)$value)->all()
            : [];
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('subjects', 'code')->ignore($this->id)],
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'group_subject_id' => ['nullable', 'integer', 'exists:group_subjects,id'],
            'credits' => $this->decimalRules('Tổng tín chỉ'),
            'credits_theory' => $this->decimalRules('Tín chỉ lý thuyết'),
            'credits_practice' => $this->decimalRules('Tín chỉ thực hành'),
            'is_active' => ['boolean'],
            'prerequisite_subject_ids' => ['array'],
            'prerequisite_subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
            'syllabus_file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:' . implode(',', $this->allowedSyllabusMimeTypes()),
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    $detectedMime = strtolower((string) $value->getMimeType());

                    if (!in_array($detectedMime, $this->allowedSyllabusMimeTypes(), true)) {
                        $fail('Định dạng nội dung file không hợp lệ. Chỉ chấp nhận PDF.');
                    }
                },
                'max:10240',
            ],
        ];
    }

    protected $messages = [
        'code.required' => 'Mã môn học không được để trống.',
        'code.regex' => 'Mã môn chỉ gồm chữ cái, số và dấu gạch nối (-).',
        'code.unique' => 'Mã môn học đã tồn tại.',
        'name_vi.required' => 'Tên môn học tiếng Việt không được để trống.',
        'credits.required' => 'Tổng tín chỉ không được để trống.',
        'credits.regex' => 'Tổng tín chỉ chỉ nhận số nguyên hoặc thập phân 1 chữ số (vd: 1.5 hoặc 1,5).',
        'credits_theory.required' => 'Tín chỉ lý thuyết không được để trống.',
        'credits_theory.regex' => 'Tín chỉ lý thuyết chỉ nhận số nguyên hoặc thập phân 1 chữ số (vd: 1.5 hoặc 1,5).',
        'credits_practice.required' => 'Tín chỉ thực hành không được để trống.',
        'credits_practice.regex' => 'Tín chỉ thực hành chỉ nhận số nguyên hoặc thập phân 1 chữ số (vd: 1.5 hoặc 1,5).',
        'syllabus_file.mimes' => 'Đề cương môn học chỉ hỗ trợ định dạng PDF.',
        'syllabus_file.mimetypes' => 'Nội dung file không đúng định dạng PDF hợp lệ.',
        'syllabus_file.max' => 'Đề cương môn học không được vượt quá 10MB.',
    ];

    protected function allowedSyllabusMimeTypes(): array
    {
        return [
            'application/pdf',
        ];
    }

    protected function syllabusPreviewType(?string $path): string
    {
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'office',
            default => 'download',
        };
    }

    protected function syllabusFileUrl(?string $path): ?string
    {
        if (!filled($path)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'client.subject-syllabus.stream',
            now()->addMinutes(15),
            ['subject' => $this->id]
        );
    }

    protected function syllabusPreviewUrl(?string $path): ?string
    {
        if (!filled($path)) {
            return null;
        }

        return route('client.subject-syllabus.preview', ['subject' => $this->id]);
    }

    protected function deleteSyllabusFile(?string $path): void
    {
        if (!filled($path)) {
            return;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists((string) $path)) {
                Storage::disk($disk)->delete((string) $path);
                return;
            }
        }
    }

    protected function validationAttributes(): array
    {
        return [
            'credits' => 'Tổng tín chỉ',
            'credits_theory' => 'Tín chỉ lý thuyết',
            'credits_practice' => 'Tín chỉ thực hành',
        ];
    }

    protected function decimalRules(string $label): array
    {
        return [
            'required',
            'regex:/^\d+(?:[\.,]\d)?$/',
            function ($attribute, $value, $fail) use ($label) {
            $decimal = $this->toDecimal($value);

            if ($decimal === null) {
                    $fail($label . ' không hợp lệ.');
                    return;
            }

                if ($decimal < 0 || $decimal > 20) {
                    $fail($label . ' phải nằm trong khoảng từ 0 đến 20.');
                }
            },
        ];
    }

    protected function toDecimal(int|float|string|null $value): ?float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        if ($normalized === '' || !preg_match('/^\d+(?:\.\d)?$/', $normalized)) {
            return null;
        }

        return round((float) $normalized, 1);
    }

    public function updated(string $property): void
    {
        // Some UI interactions may emit "$field"; normalize before validateOnly.
        $property = ltrim($property, '$');

        if (!property_exists($this, $property)) {
            return;
        }

        if ($property === 'group_subject_id' && $this->group_subject_id === '') {
            $this->group_subject_id = null;
        }

        if (in_array($property, ['credits', 'credits_theory', 'credits_practice'], true)) {
            $this->validateCreditsDistribution();
        }

        $this->validateOnly($property);
    }

    public function getGroupOptionsProperty(): array
    {
        return GroupSubject::query()
            ->orderBy('sort_order')
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') ASC")
            ->get()
            ->map(fn(GroupSubject $group) => [
                'id' => $group->id,
                'name' => $group->getTranslation('name', 'vi', false) ?: ('#' . $group->id),
            ])
            ->toArray();
    }

    public function getPrerequisiteOptionsProperty()
    {
        return Subject::query()
            ->with('groupSubject')
            ->where('id', '!=', $this->id)
            ->ordered()
            ->get();
    }

    public function getRequiredBySubjectsProperty()
    {
        // Gọi thẳng từ Model Pivot (nơi chứa training_program_id)
        return SubjectPrerequisite::query()
            ->with([
                'subject.groupSubject', // Kéo theo môn học bị ràng buộc và nhóm môn
                'trainingProgram'       // Kéo theo CTĐT
            ])
            ->where('prerequisite_subject_id', $this->id)
            // Nếu muốn sắp xếp, bạn có thể sort theo tên CTĐT hoặc mã môn
            ->orderBy('training_program_id')
            ->get();
    }

    public function getSemesterUsagesProperty()
    {
        return ProgramSemester::query()
            ->with('trainingProgram')
            ->whereHas('subjects', fn($query) => $query->where('subjects.id', $this->id))
            ->orderBy('training_program_id')
            ->orderBy('semester_no')
            ->get();
    }

    protected function normalizePrerequisiteIds(): array
    {
        return collect($this->prerequisite_subject_ids)
            ->map(fn($id) => (int)$id)
            ->filter()
            ->reject(fn(int $id) => $id === $this->id)
            ->unique()
            ->values()
            ->all();
    }

    protected function programIdsUsingSubject(): \Illuminate\Support\Collection
    {
        return ProgramSemester::query()
            ->whereHas('subjects', fn($query) => $query->where('subjects.id', $this->id))
            ->distinct()
            ->pluck('training_program_id');
    }

    protected function validateCreditsDistribution(): void
    {
        $credits = $this->toDecimal($this->credits);
        $creditsTheory = $this->toDecimal($this->credits_theory);
        $creditsPractice = $this->toDecimal($this->credits_practice);

        // Skip cross-field check while user is still typing/clearing one of the fields.
        if ($credits === null || $creditsTheory === null || $creditsPractice === null) {
            return;
        }

        if (abs(($creditsTheory + $creditsPractice) - $credits) > 0.0001) {
            throw ValidationException::withMessages([
                'credits' => ' ',
                'credits_theory' => ' ',
                'credits_practice' => ' ',
                'credits_error' => 'Tổng tín chỉ phải bằng tổng tín chỉ lý thuyết và thực hành.',
            ]);
        }

        $this->resetValidation(['credits', 'credits_theory', 'credits_practice', 'credits_error']);
    }

    protected function payload(): array
    {
        return [
            'code' => strtoupper(trim($this->code)),
            'name' => [
                'vi' => trim($this->name_vi),
                'en' => trim($this->name_en),
            ],
            'group_subject_id' => !blank($this->group_subject_id) ? (int)$this->group_subject_id : null,
            'credits' => $this->toDecimal($this->credits),
            'credits_theory' => $this->toDecimal($this->credits_theory),
            'credits_practice' => $this->toDecimal($this->credits_practice),
            'is_active' => $this->is_active,
        ];
    }

    public function removeCurrentSyllabus(): void
    {
        $this->remove_syllabus = true;
        $this->syllabus_file = null;
    }

    public function save(): void
    {
        if ($this->group_subject_id === '') {
            $this->group_subject_id = null;
        }

        try {
            $this->validate();
            $this->validateCreditsDistribution();

            if (in_array($this->id, collect($this->prerequisite_subject_ids)->map(fn($id) => (int)$id)->all(), true)) {
                throw ValidationException::withMessages([
                    'prerequisite_subject_ids' => 'Không thể chọn chính môn học này làm môn tiên quyết.',
                ]);
            }
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin môn học.');
            throw $e;
        }

        $subject = Subject::query()->findOrFail($this->id);

        $syllabusPath = $subject->syllabus_path;
        $syllabusOriginalName = $subject->syllabus_original_name;

        if ($this->remove_syllabus && $syllabusPath) {
            $this->deleteSyllabusFile($syllabusPath);

            $syllabusPath = null;
            $syllabusOriginalName = null;
        }

        if ($this->syllabus_file) {
            $this->deleteSyllabusFile($syllabusPath);

            $syllabusPath = $this->syllabus_file->store('uploads/subjects/syllabi', 'local');
            $syllabusOriginalName = (string) $this->syllabus_file->getClientOriginalName();
        }

        $subject->update(array_merge($this->payload(), [
            'syllabus_path' => $syllabusPath,
            'syllabus_original_name' => $syllabusOriginalName,
        ]));

        $this->current_syllabus_path = $subject->syllabus_path;
        $this->current_syllabus_name = $subject->syllabus_original_name;
        $this->remove_syllabus = false;
        $this->syllabus_file = null;

        $prerequisiteIds = $this->normalizePrerequisiteIds();

        foreach ($this->programIdsUsingSubject() as $programId) {
            $program = TrainingProgram::query()->find($programId);

            if ($program) {
                $program->syncSubjectPrerequisites($this->id, $prerequisiteIds);
            }
        }

        $this->success('Cập nhật môn học thành công!');
    }
};
?>

<div>
    <x-slot:title>Chỉnh sửa môn học</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.subject.index') }}" class="font-semibold text-slate-700">Danh sách môn học</a>
        <span class="mx-1">/</span>
        <span>Chỉnh sửa</span>
    </x-slot:breadcrumb>

    <x-header title="Chỉnh sửa môn học"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"/>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">
            <x-card title="Thông tin cơ bản" shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Mã môn học"
                        wire:model.live.debounce.300ms="code"
                        required
                        readonly
                    />
                    <x-select
                        label="Nhóm môn học"
                        wire:model.live="group_subject_id"
                        :options="$this->groupOptions"
                        option-value="id"
                        option-label="name"
                        placeholder="Chọn nhóm môn học"
                        placeholder-value=""
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-input
                        label="Tên (Tiếng Việt)"
                        wire:model.live.debounce.300ms="name_vi"
                        required
                    />
                    <x-input
                        label="Tên (Tiếng Anh)"
                        wire:model.live.debounce.300ms="name_en"
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <x-input label="Tổng tín chỉ" type="text" inputmode="decimal" wire:model.live.debounce.300ms="credits"
                             required/>
                    <x-input label="Tín chỉ lý thuyết" type="text" inputmode="decimal"
                             wire:model.live.debounce.300ms="credits_theory" required/>
                    <x-input label="Tín chỉ thực hành" type="text" inputmode="decimal"
                             wire:model.live.debounce.300ms="credits_practice" required/>
                </div>
                @error('credits_error')
                <div class="mt-2 text-xs text-red-500">
                    {{ $message }}
                </div>
                @enderror
                <div class="mt-3 text-xs text-gray-500">
                    Gợi ý: tổng LT + TH bằng tổng tín chỉ của môn học.
                </div>
                @if($this->current_syllabus_path && !$this->remove_syllabus)
                    @php
                        $previewUrl = $this->syllabusPreviewUrl($this->current_syllabus_path);
                    @endphp
                @endif
                <div class="mt-4 space-y-2">
                    @if($this->current_syllabus_path && !$this->remove_syllabus)
                        <div class="rounded border border-primary/20 bg-primary/5 p-3 text-sm">
                            <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer"
                               class="text-primary hover:underline font-medium">
                                {{ $this->current_syllabus_name ?: __('Xem đề cương hiện tại') }}
                            </a>

                            <div class="mt-2">
                                <x-button
                                    label="Gỡ đề cương hiện tại"
                                    class="btn-xs btn-outline btn-error"
                                    wire:click="removeCurrentSyllabus"
                                />
                            </div>
                        </div>
                    @endif

                    <x-file
                        label="Đề cương môn học (PDF)"
                        wire:model.live="syllabus_file"
                        accept=".pdf,application/pdf"
                        hint="Tối đa 10MB"
                    />
                </div>
            </x-card>


{{--            <x-card title="Môn học phụ thuộc vào môn này" shadow class="p-3!">--}}
{{--                @if($this->requiredBySubjects->isNotEmpty())--}}
{{--                    <div class="overflow-x-auto">--}}
{{--                        <table class="table w-full text-sm">--}}
{{--                            <thead>--}}
{{--                            <tr>--}}
{{--                                <th class="w-20">Mã môn</th>--}}
{{--                                <th>Tên môn học</th>--}}
{{--                                <th>Chương trình đào tạo</th>--}}
{{--                            </tr>--}}
{{--                            </thead>--}}
{{--                            <tbody>--}}
{{--                            @foreach($this->requiredBySubjects as $item)--}}
{{--                                <tr class="hover:bg-gray-50">--}}
{{--                                    <td class="font-mono font-semibold text-primary">{{ $item->subject->code }}</td>--}}
{{--                                    <td>--}}
{{--                                        <div>{{ $item->subject->getTranslation('name', 'vi', false) ?: '—' }}</div>--}}
{{--                                        <div--}}
{{--                                            class="text-xs text-gray-400">{{ $item->subject->getTranslation('name', 'en', false) ?: 'Chưa có tên tiếng Anh' }}</div>--}}
{{--                                    </td>--}}
{{--                                    <td>{{ $item->trainingProgram?->getTranslation('name', 'vi', false) ?: '—' }}</td>--}}
{{--                                </tr>--}}
{{--                            @endforeach--}}
{{--                            </tbody>--}}
{{--                        </table>--}}
{{--                    </div>--}}
{{--                @else--}}
{{--                    <div class="text-sm text-gray-500 py-2">Hiện chưa có môn học nào lấy môn này làm tiên quyết.</div>--}}
{{--                @endif--}}
{{--            </x-card>--}}

            <x-card title="Đang được dùng trong CTDT" shadow class="p-3!">
                @if($this->semesterUsages->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table w-full text-sm">
                            <thead>
                            <tr>
                                <th>Chương trình đào tạo</th>
                                <th class="w-24">Học kỳ</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($this->semesterUsages as $semester)
                                <tr class="hover:bg-gray-50">
                                    <td>
                                        <div>{{ $semester->trainingProgram?->version ?: '—' }}</div>
                                        <div
                                            class="text-xs text-gray-400">{{ $semester->trainingProgram?->getTranslation('name', 'vi', false) ?: '—' }}</div>
                                    </td>
                                    <td>Học kỳ {{ $semester->semester_no }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-sm text-gray-500 py-2">Môn học này chưa được gán vào học kỳ nào.</div>
                @endif
            </x-card>
        </div>

        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">
            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button
                    label="Lưu thay đổi"
                    class="bg-primary text-white w-full my-1"
                    wire:click="save"
                    spinner="save"
                />
            </x-card>

            <x-card title="Cài đặt" shadow class="p-3!">
                <x-toggle
                    label="Kích hoạt"
                    wire:model="is_active"
                    class="toggle-primary"
                />
            </x-card>

            <x-card title="Thống kê" shadow class="p-3!">
                <div class="text-sm space-y-2">
{{--                    <div class="flex justify-between gap-3">--}}
{{--                        <span class="text-gray-500">Môn phụ thuộc:</span>--}}
{{--                        <x-badge :value="$this->requiredBySubjects->count() . ' môn'" class="badge-info badge-md text-white font-semibold"/>--}}
{{--                    </div>--}}
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Dùng trong CTDT:</span>
                        <x-badge :value="$this->semesterUsages->count() . ' học kỳ'" class="badge-success badge-md text-white font-semibold"/>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>


