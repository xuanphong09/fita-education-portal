<?php

use App\Models\GroupSubject;
use App\Models\ProgramSemester;
use App\Models\Subject;
use App\Models\SubjectPrerequisite;
use App\Models\TrainingProgram;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public int $id;
    public string $code = '';
    public string $name_vi = '';
    public string $name_en = '';
    public int|string|null $group_subject_id = null;
    public int|string|null $credits = 0;
    public int|string|null $credits_theory = 0;
    public int|string|null $credits_practice = 0;
    public bool $is_active = true;
    public array $prerequisite_subject_ids = [];

    public function mount(int $id): void
    {
        $this->id = $id;

        $subject = Subject::query()
            ->findOrFail($id);

        $this->code = $subject->code;
        $this->name_vi = $subject->getTranslation('name', 'vi', false) ?: '';
        $this->name_en = $subject->getTranslation('name', 'en', false) ?: '';
        $this->group_subject_id = $subject->group_subject_id;
        $this->credits = (int)$subject->credits;
        $this->credits_theory = (int)$subject->credits_theory;
        $this->credits_practice = (int)$subject->credits_practice;
        $this->is_active = (bool)$subject->is_active;
        $programId = $this->programIdsUsingSubject()->first();

        $this->prerequisite_subject_ids = $programId
            ? $subject->prerequisitesForProgram((int)$programId)->pluck('subjects.id')->map(fn($value) => (int)$value)->all()
            : [];
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('subjects', 'code')->ignore($this->id)],
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'group_subject_id' => ['nullable', 'integer', 'exists:group_subjects,id'],
            'credits' => ['required', 'integer', 'min:0', 'max:20'],
            'credits_theory' => ['required', 'integer', 'min:0', 'max:20'],
            'credits_practice' => ['required', 'integer', 'min:0', 'max:20'],
            'is_active' => ['boolean'],
            'prerequisite_subject_ids' => ['array'],
            'prerequisite_subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
        ];
    }

    protected $messages = [
        'code.required' => 'Mã môn học không được để trống.',
        'code.regex' => 'Mã môn chỉ gồm chữ cái, số và dấu gạch nối (-).',
        'code.unique' => 'Mã môn học đã tồn tại.',
        'name_vi.required' => 'Tên môn học tiếng Việt không được để trống.',
        'credits.required' => 'Tổng tín chỉ không được để trống.',
        'credits.integer' => 'Tổng tín chỉ phải là số nguyên.',
        'credits.min' => 'Tổng tín chỉ không được âm.',
        'credits.max' => 'Tổng tín chỉ không được lớn hơn 20.',
        'credits_theory.required' => 'Tín chỉ lý thuyết không được để trống.',
        'credits_theory.integer' => 'Tín chỉ lý thuyết phải là số nguyên.',
        'credits_theory.min' => 'Tín chỉ lý thuyết không được âm.',
        'credits_theory.max' => 'Tín chỉ lý thuyết không được lớn hơn 20.',
        'credits_practice.required' => 'Tín chỉ thực hành không được để trống.',
        'credits_practice.integer' => 'Tín chỉ thực hành phải là số nguyên.',
        'credits_practice.min' => 'Tín chỉ thực hành không được âm.',
        'credits_practice.max' => 'Tín chỉ thực hành không được lớn hơn 20.',
    ];

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
        $credits = filter_var($this->credits, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $creditsTheory = filter_var($this->credits_theory, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $creditsPractice = filter_var($this->credits_practice, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        // Skip cross-field check while user is still typing/clearing one of the fields.
        if ($credits === null || $creditsTheory === null || $creditsPractice === null) {
            return;
        }

        if (($creditsTheory + $creditsPractice) !== $credits) {
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
            'credits' => (int)$this->credits,
            'credits_theory' => (int)$this->credits_theory,
            'credits_practice' => (int)$this->credits_practice,
            'is_active' => $this->is_active,
        ];
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
        $subject->update($this->payload());

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
                    <x-input label="Tổng tín chỉ" type="number" min="0" wire:model.live.debounce.300ms="credits"
                             required/>
                    <x-input label="Tín chỉ lý thuyết" type="number" min="0"
                             wire:model.live.debounce.300ms="credits_theory" required/>
                    <x-input label="Tín chỉ thực hành" type="number" min="0"
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
            </x-card>


            <x-card title="Môn học phụ thuộc vào môn này" shadow class="p-3!">
                @if($this->requiredBySubjects->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table w-full text-sm">
                            <thead>
                            <tr>
                                <th class="w-20">Mã môn</th>
                                <th>Tên môn học</th>
                                <th>Chương trình đào tạo</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($this->requiredBySubjects as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="font-mono font-semibold text-primary">{{ $item->subject->code }}</td>
                                    <td>
                                        <div>{{ $item->subject->getTranslation('name', 'vi', false) ?: '—' }}</div>
                                        <div
                                            class="text-xs text-gray-400">{{ $item->subject->getTranslation('name', 'en', false) ?: 'Chưa có tên tiếng Anh' }}</div>
                                    </td>
                                    <td>{{ $item->trainingProgram?->getTranslation('name', 'vi', false) ?: '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-sm text-gray-500 py-2">Hiện chưa có môn học nào lấy môn này làm tiên quyết.</div>
                @endif
            </x-card>

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
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Môn phụ thuộc:</span>
                        <x-badge :value="$this->requiredBySubjects->count() . ' môn'" class="badge-info badge-sm"/>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">Dùng trong CTDT:</span>
                        <x-badge :value="$this->semesterUsages->count() . ' học kỳ'" class="badge-success badge-sm"/>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>


