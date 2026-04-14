<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;
use App\Models\ProgramSemester;
use App\Models\Subject;
use App\Models\TrainingProgram;
use App\Models\SubjectPrerequisite;
use App\Models\SubjectEquivalent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

new class extends Component {
    use Toast;

    public int $programId;
    public bool $modalSemester = false;
    public bool $modalAddSubject = false;
    public array $expanded = [];

    public ?int $selectedSemesterId = null;
    public ?int $editingSemesterId = null;

    public int $semester_no = 1;
    public int $semester_total_credits = 0;
    public ?string $semester_start_date = null;
    public ?string $semester_end_date = null;

    public ?int $attach_subject_id = null;
    public ?int $pendingRemoveSubjectId = null;
    public ?int $pivot_original_subject_id = null;
    public string $attach_type = 'required';
    public string $attach_notes = '';
    public int $attach_order = 0;
    public string $attach_credits = '';
    public string $subjectSearch = '';
    public string $prerequisiteSearch = '';
    public string $equivalentSearch = '';
    public int $subjectSearchMinLength = 2;
    public int $subjectSearchLimit = 30;

    public array $attach_subject_prerequisite_id = [];
    public array $attach_subject_equivalent_id = [];

    public function mount(int $id): void
    {
        $this->programId = TrainingProgram::query()->findOrFail($id)->id;

        $this->selectedSemesterId = ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->orderBy('semester_no')
            ->value('id');
    }

    public function updated($property): void
    {
        if (in_array($property, [
            'semester_no',
            'semester_total_credits',
            'semester_start_date',
            'semester_end_date',
        ], true)) {
            $this->validateOnly($property);
        }
    }

    public function getProgramProperty(): TrainingProgram
    {
        return TrainingProgram::query()->findOrFail($this->programId);
    }

    public function getSemestersProperty()
    {
        return ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->withCount('subjects')
            ->orderBy('semester_no')
            ->get();
    }

//    lấy học kỳ hiện tại, kèm theo toàn bộ môn học,
    public function getSelectedSemesterProperty(): ?ProgramSemester
    {
        if (!$this->selectedSemesterId) {
            return null;
        }

        return ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->where('id', $this->selectedSemesterId)
            ->with(['subjects' => function ($q) {
                $q->orderBy('program_semester_subjects.order')
                    ->withCount([
                        'prerequisites as prerequisites_count' => function ($prereqQuery) {
                            $prereqQuery->where('subject_prerequisites.training_program_id', $this->programId);
                        },
                        'equivalents as equivalents_count' => function ($equivalentQuery) {
                            $equivalentQuery->where('subject_equivalents.training_program_id', $this->programId);
                        },
                    ])
                    ->with(['prerequisites' => function ($prereqQuery) {
                        $prereqQuery
                            ->where('subject_prerequisites.training_program_id', $this->programId)
                            ->join('group_subjects', 'group_subjects.id', '=', 'subjects.group_subject_id')
                            ->orderBy('group_subjects.sort_order');
                    }, 'equivalents' => function ($equivalentQuery) {
                        $equivalentQuery
                            ->where('subject_equivalents.training_program_id', $this->programId)
                            ->join('group_subjects', 'group_subjects.id', '=', 'subjects.group_subject_id')
                            ->orderBy('group_subjects.sort_order');
                    }]);
            }])
            ->first();
    }

    public function getUsedSubjectIdsProperty()
    {
        return ProgramSemester::query()
            ->join('program_semester_subjects', 'program_semesters.id', '=', 'program_semester_subjects.program_semester_id')
            ->where('program_semesters.training_program_id', $this->programId)
            ->distinct()
            ->pluck('program_semester_subjects.subject_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function getSubjectOptionsProperty(): array
    {
        $usedSubjectIds = $this->usedSubjectIds;
        $currentSubjectId = (int) ($this->attach_subject_id ?: $this->pivot_original_subject_id ?: 0);
        $keyword = trim($this->subjectSearch);

        // Khi edit, vẫn giữ môn hiện tại trong danh sách để tránh bị mất option đang chọn.
        if ($this->pivot_original_subject_id) {
            $usedSubjectIds = $usedSubjectIds
                ->reject(fn ($id) => $id === $this->pivot_original_subject_id)
                ->values();
        }

        $baseQuery = Subject::query()
            ->where(function ($q) {
                $q->where('is_active', true);

                // Keep current subject selectable in edit mode even if it was deactivated later.
                if ($this->pivot_original_subject_id) {
                    $q->orWhere('id', $this->pivot_original_subject_id);
                }
            })
            ->when($usedSubjectIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $usedSubjectIds->all()));

        // If user has typed keyword, search; otherwise show initial list
        if (mb_strlen($keyword) > 0) {
            $subjects = (clone $baseQuery)
                ->where(function ($q) use ($keyword) {
                    $q->where('code', 'like', '%' . $keyword . '%')
                        ->orWhereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ?",
                            ['%' . $keyword . '%']
                        )
                        ->orWhereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ?",
                            ['%' . $keyword . '%']
                        );
                })
                ->orderBy('code')
                ->limit($this->subjectSearchLimit)
                ->get();
        } else {
            // Show initial list (first 30 active subjects, ordered by code)
            $subjects = (clone $baseQuery)
                ->orderBy('code')
                ->limit($this->subjectSearchLimit)
                ->get();
        }

        // Ensure current subject is always in the list
        if ($currentSubjectId > 0 && !$subjects->contains('id', $currentSubjectId)) {
            $currentSubject = (clone $baseQuery)->where('id', $currentSubjectId)->first();
            if ($currentSubject) {
                $subjects->prepend($currentSubject);
            }
        }

        return $subjects
            ->unique('id')
            ->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->code . ' - ' . ($subject->getTranslation('name', 'vi', false) ?: 'N/A'),
            ])
            ->toArray();
    }

    public function getSubjectUsedOptionsProperty(): array
    {
        $usedSubjectIds = $this->usedSubjectIds;

        if ($usedSubjectIds->isEmpty()) {
            return [];
        }

        return Subject::query()
            ->whereIn('id', $usedSubjectIds->all())
            ->when($this->attach_subject_id, fn ($q) => $q->where('id', '!=', $this->attach_subject_id))
            ->orderBy('code')
            ->get()
            ->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->code
                    . ' - ' . ($subject->getTranslation('name', 'vi', false) ?: 'N/A')
                    . ' (' . Subject::formatCredit($subject->credits) . ' TC)'
            ])
            ->toArray();
    }

    public function getEquivalentSubjectOptionsProperty(): array
    {
        $usedSubjectIds = $this->usedSubjectIds->all();

        return Subject::query()
            ->where(function ($q) use ($usedSubjectIds) {
                $q->where('is_active', true)
                    ->when(!empty($usedSubjectIds), fn ($inner) => $inner->whereNotIn('id', $usedSubjectIds));
            })
            ->when($this->attach_subject_id, fn ($q) => $q->where('id', '!=', $this->attach_subject_id))
            ->orderBy('code')
            ->get()
            ->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->code
                    . ' - ' . ($subject->getTranslation('name', 'vi', false) ?: 'N/A')
                    . ' (' . Subject::formatCredit($subject->credits) . ' TC)'
            ])
            ->toArray();
    }

    public function getFilteredSubjectUsedOptionsProperty(): array
    {
        $keyword = trim($this->prerequisiteSearch);

        if ($keyword === '') {
            return $this->subjectUsedOptions;
        }

        $normalizedKeyword = $this->normalizeSearchText($keyword);

        return collect($this->subjectUsedOptions)
            ->filter(function (array $subject) use ($keyword, $normalizedKeyword) {
                $name = (string) ($subject['name'] ?? '');

                if (mb_stripos($name, $keyword) !== false) {
                    return true;
                }

                return str_contains($this->normalizeSearchText($name), $normalizedKeyword);
            })
            ->values()
            ->all();
    }

    public function getFilteredEquivalentSubjectOptionsProperty(): array
    {
        $keyword = trim($this->equivalentSearch);

        if ($keyword === '') {
            return $this->equivalentSubjectOptions;
        }

        $normalizedKeyword = $this->normalizeSearchText($keyword);

        return collect($this->equivalentSubjectOptions)
            ->filter(function (array $subject) use ($keyword, $normalizedKeyword) {
                $name = (string) ($subject['name'] ?? '');

                if (mb_stripos($name, $keyword) !== false) {
                    return true;
                }

                return str_contains($this->normalizeSearchText($name), $normalizedKeyword);
            })
            ->values()
            ->all();
    }

    protected function normalizeSearchText(string $value): string
    {
        return mb_strtolower(trim(Str::ascii($value)));
    }

    public function selectSemester(int $id): void
    {
        $exists = ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->where('id', $id)
            ->exists();

        if (!$exists) {
            $this->error('Hoc ky khong hop le.');
            return;
        }

        $this->selectedSemesterId = $id;
        $this->resetSubjectForm();
    }

    public function openCreateSemester(): void
    {
        $this->editingSemesterId = null;
        $this->semester_no = max(1, (int) ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->max('semester_no') + 1);
        $this->semester_total_credits = 0;
        $this->semester_start_date = null;
        $this->semester_end_date = null;
        $this->resetValidation([
            'semester_no',
            'semester_total_credits',
            'semester_start_date',
            'semester_end_date',
        ]);
    }

    public function editSemester(int $id): void
    {
        $semester = ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->findOrFail($id);

        $this->editingSemesterId = $semester->id;
        $this->semester_no = $semester->semester_no;
        $this->semester_total_credits = $semester->total_credits;
        $this->semester_start_date = $semester->start_date
            ? Carbon::parse($semester->start_date)->format('Y-m-d')
            : null;
        $this->semester_end_date = $semester->end_date
            ? Carbon::parse($semester->end_date)->format('Y-m-d')
            : null;
        $this->resetValidation([
            'semester_no',
            'semester_total_credits',
            'semester_start_date',
            'semester_end_date',
        ]);
    }

    protected function semesterRules(): array
    {
        return [
            'semester_no' => [
                'required', 'integer', 'min:1', 'max:20',
                Rule::unique('program_semesters', 'semester_no')
                    ->ignore($this->editingSemesterId)
                    ->where(fn($q) => $q->where('training_program_id', $this->programId)),
            ],
            'semester_total_credits' => ['required', 'integer', 'min:0', 'max:200'],
            'semester_start_date' => ['nullable', 'date', 'required_with:semester_end_date'],
            'semester_end_date' => ['nullable', 'date', 'required_with:semester_start_date'],
        ];
    }

    protected function rules(): array
    {
        return $rules = [
            'semester_no' => [
                'required', 'integer', 'min:1', 'max:20',
                Rule::unique('program_semesters', 'semester_no')
                    ->ignore($this->editingSemesterId)
                    ->where(fn($q) => $q->where('training_program_id', $this->programId)),
            ],
            'semester_total_credits' => ['required', 'integer', 'min:0', 'max:200'],
            'attach_subject_id' => ['required', 'exists:subjects,id'],
            'attach_type' => ['required', Rule::in(['required', 'elective', 'pcbb'])],
            'attach_notes' => ['nullable', 'string'],
            'attach_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'attach_subject_prerequisite_id' => ['array'],
            'attach_subject_prerequisite_id.*' => ['integer', 'exists:subjects,id', 'different:attach_subject_id'],
            'attach_subject_equivalent_id' => ['array'],
            'attach_subject_equivalent_id.*' => [
                'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('is_active', true)),
                'distinct',
                'different:attach_subject_id',
                function ($attribute, $value, $fail) {
                    if ($this->usedSubjectIds->contains((int) $value)) {
                        $fail('Môn học tương đương phải nằm ngoài chương trình đào tạo hiện tại.');
                    }
                },
            ],
        ];
    }
    protected $messages = [
        'semester_no.required' => 'Vui lòng nhập số học kỳ.',
        'semester_no.integer' => 'Số học kỳ phải là một số nguyên.',
        'semester_no.min' => 'Số học kỳ phải lớn hơn hoặc bằng 1.',
        'semester_no.max' => 'Số học kỳ không được lớn hơn 20.',
        'semester_no.unique' => 'Số học kỳ đã tồn tại trong chương trình đào tạo này.',
        'semester_total_credits.required' => 'Vui lòng nhập tổng tín chỉ.',
        'semester_total_credits.integer' => 'Tổng tín chỉ phải là một số nguyên.',
        'semester_total_credits.min' => 'Tổng tín chỉ phải lớn hơn hoặc bằng 0.',
        'semester_total_credits.max' => 'Tổng tín chỉ không được lớn hơn 200.',
        'semester_start_date.date' => 'Ngày bắt đầu không hợp lệ.',
        'semester_start_date.required_with' => 'Vui lòng nhập cả ngày bắt đầu và ngày kết thúc.',
        'semester_end_date.date' => 'Ngày kết thúc không hợp lệ.',
        'semester_end_date.required_with' => 'Vui lòng nhập cả ngày bắt đầu và ngày kết thúc.',
        'attach_subject_id.required' => 'Vui lòng chọn môn học.',
        'attach_subject_id.exists' => 'Môn học không tồn tại.',
        'attach_type.required' => 'Vui lòng chọn loại môn học.',
        'attach_type.in' => 'Loại môn học không hợp lệ.',
        'attach_notes.string' => 'Ghi chú phải là một chuỗi.',
        'attach_order.required' => 'Vui lòng nhập thứ tự hiển thị.',
        'attach_order.integer' => 'Thứ tự hiển thị phải là một số nguyên.',
        'attach_order.min' => 'Thứ tự hiển thị phải lớn hơn hoặc bằng 0.',
        'attach_order.max' => 'Thứ tự hiển thị không được lớn hơn 1000.',
        'attach_subject_prerequisite_id.array' => 'Danh sách môn tiên quyết phải là một mảng.',
        'attach_subject_prerequisite_id.*.integer' => 'Môn tiên quyết không hợp lệ.',
        'attach_subject_prerequisite_id.*.exists' => 'Môn tiên quyết không tồn tại.',
        'attach_subject_prerequisite_id.*.different' => 'Môn tiên quyết không được trùng với môn đang thêm.',
        'attach_subject_equivalent_id.array' => 'Danh sách môn học tương đương phải là một mảng.',
        'attach_subject_equivalent_id.*.integer' => 'Môn học tương đương không hợp lệ.',
        'attach_subject_equivalent_id.*.exists' => 'Môn học tương đương không tồn tại.',
        'attach_subject_equivalent_id.*.distinct' => 'Môn học tương đương bị trùng trong danh sách đã chọn.',
        'attach_subject_equivalent_id.*.different' => 'Môn học tương đương không được trùng với môn đang thêm.',
    ];

    public function saveSemester(): void
    {
        $this->validate($this->semesterRules());

        if ($this->semester_start_date && $this->semester_end_date) {
            $startDate = Carbon::parse($this->semester_start_date)->startOfDay();
            $endDate = Carbon::parse($this->semester_end_date)->startOfDay();

            if ($endDate->lt($startDate)) {
                $this->addError('semester_end_date', 'Thời gian kết thúc học kỳ phải lớn hơn hoặc bằng thời gian bắt đầu.');
                return;
            }
        }

        $payload = [
            'training_program_id' => $this->programId,
            'semester_no' => $this->semester_no,
            'total_credits' => $this->semester_total_credits,
            'start_date' => $this->semester_start_date,
            'end_date' => $this->semester_end_date,
        ];

        if ($this->editingSemesterId) {
            $semester = ProgramSemester::query()
                ->where('training_program_id', $this->programId)
                ->findOrFail($this->editingSemesterId);

            $semester->update($payload);
        } else {
            $semester = ProgramSemester::query()->create($payload);
        }

        $this->selectedSemesterId = $semester->id;
        $this->editingSemesterId = null;
        $this->recalculateProgramCredits();
        $this->modalSemester=false;
        $this->success('Đã lưu học kỳ thành công.');
    }

    public function deleteSemester(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa học kỳ này không ?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDeleteSemester',
            'id' => $id,
        ]);
    }

    #[On('confirmDeleteSemester')]
    public function confirmDeleteSemester(int $id): void
    {
        $semester = ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->with(['subjects:id,code'])
            ->findOrFail($id);

        $semesterSubjectIds = $semester->subjects
            ->pluck('id')
            ->map(fn ($subjectId) => (int) $subjectId)
            ->values();

        if ($semesterSubjectIds->isNotEmpty()) {
            // Block deletion if any subject in this semester is used as prerequisite by subjects outside this semester.
            $dependentCodes = SubjectPrerequisite::query()
                ->forProgram($this->programId)
                ->whereIn('prerequisite_subject_id', $semesterSubjectIds->all())
                ->whereNotIn('subject_id', $semesterSubjectIds->all())
                ->join('subjects', 'subjects.id', '=', 'subject_prerequisites.subject_id')
                ->orderBy('subjects.code')
                ->distinct()
                ->pluck('subjects.code');

            if ($dependentCodes->isNotEmpty()) {
                $preview = $dependentCodes->take(5)->implode(', ');
                $this->error('Không thể xóa học kỳ: Có môn đang là môn tiên quyết của môn ' . $preview . ($dependentCodes->count() > 5 ? '...' : '') . '.');
                return;
            }

            // Safe cleanup: remove prerequisite links related to subjects that will be removed from this program.
            SubjectPrerequisite::query()
                ->forProgram($this->programId)
                ->where(function ($q) use ($semesterSubjectIds) {
                    $q->whereIn('subject_id', $semesterSubjectIds->all())
                        ->orWhereIn('prerequisite_subject_id', $semesterSubjectIds->all());
                })
                ->delete();

            SubjectEquivalent::query()
                ->forProgram($this->programId)
                ->where(function ($q) use ($semesterSubjectIds) {
                    $q->whereIn('subject_id', $semesterSubjectIds->all())
                        ->orWhereIn('equivalent_subject_id', $semesterSubjectIds->all());
                })
                ->delete();
        }

        $semester->delete();

        if ($this->selectedSemesterId === $id) {
            $this->selectedSemesterId = ProgramSemester::query()
                ->where('training_program_id', $this->programId)
                ->orderBy('semester_no')
                ->value('id');
        }

        $this->recalculateProgramCredits();
        $this->success('Đã xóa học kỳ thành công.');
    }

    public function updatedAttachSubjectId()
    {
        $subject = Subject::query()->find($this->attach_subject_id);
        if ($subject) {
            $this->attach_credits = 'Tổng số tín chỉ: ' . Subject::formatCredit($subject->credits)
                . ' (LT: ' . Subject::formatCredit($subject->credits_theory)
                . ', TH: ' . Subject::formatCredit($subject->credits_practice) . ')';

            $this->attach_subject_prerequisite_id = SubjectPrerequisite::query()
                ->forProgram($this->programId)
                ->forSubject($subject->id)
                ->pluck('prerequisite_subject_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $this->attach_subject_equivalent_id = SubjectEquivalent::query()
                ->forProgram($this->programId)
                ->forSubject($subject->id)
                ->pluck('equivalent_subject_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } else {
            $this->attach_credits = '';
            $this->attach_subject_prerequisite_id = [];
            $this->attach_subject_equivalent_id = [];
        }
    }

    public function resetSubjectForm(): void
    {
        $this->attach_subject_id = null;
        $this->pivot_original_subject_id = null;
        $this->attach_type = 'required';
        $this->attach_notes = '';
        $this->attach_order = 0;
        $this->attach_credits = '';
        $this->subjectSearch = '';
        $this->attach_subject_prerequisite_id = [];
        $this->attach_subject_equivalent_id = [];
        $this->prerequisiteSearch = '';
        $this->equivalentSearch = '';
    }

    public function openCreateSubjectPivot(): void
    {
        if (!$this->selectedSemesterId) {
            $this->error('Vui lòng chọn học kỳ trước.');
            return;
        }

        $nextOrder = (int) ProgramSemester::query()
            ->findOrFail($this->selectedSemesterId)
            ->subjects()
            ->max('program_semester_subjects.order');

        $this->resetSubjectForm();
        $this->attach_order = $nextOrder + 1;
        $this->modalAddSubject = true;
    }

    public function editSubjectPivot(int $subjectId): void
    {
        $semester = $this->selectedSemester;
        if (!$semester) {
            return;
        }

        $subject = $semester->subjects->firstWhere('id', $subjectId);
        if (!$subject) {
            $this->error('Không tìm thấy môn học trong học kỳ này.');
            return;
        }

        $this->attach_subject_id = $subject->id;
        $this->pivot_original_subject_id = $subject->id;
        $this->attach_type = $subject->pivot->type;
        $this->attach_notes = (string) ($subject->pivot->notes ?? '');
        $this->attach_order = (int) $subject->pivot->order;
        $this->updatedAttachSubjectId();
        $this->modalAddSubject = true;
    }

    public function saveSubjectToSemester(): void
    {
        if (!$this->selectedSemesterId) {
            $this->error('Vui lòng chọn học kỳ trước.');
            return;
        }

        // Prevent subject change when editing
        if ($this->pivot_original_subject_id && $this->attach_subject_id !== $this->pivot_original_subject_id) {
            $this->error('Không thể thay đổi môn học khi đang chỉnh sửa.');
            return;
        }

        $this->validate([
            'attach_subject_id' => ['required', 'exists:subjects,id'],
            'attach_type' => ['required', Rule::in(['required', 'elective', 'pcbb'])],
            'attach_notes' => ['nullable', 'string'],
            'attach_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'attach_subject_prerequisite_id' => ['array'],
            'attach_subject_prerequisite_id.*' => ['integer', 'exists:subjects,id', 'different:attach_subject_id'],
            'attach_subject_equivalent_id' => ['array'],
            'attach_subject_equivalent_id.*' => [
                'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('is_active', true)),
                'distinct',
                'different:attach_subject_id',
                function ($attribute, $value, $fail) {
                    if ($this->usedSubjectIds->contains((int) $value)) {
                        $fail('Môn học tương đương phải nằm ngoài chương trình đào tạo hiện tại.');
                    }
                },
            ],
        ]);

        $semester = ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->findOrFail($this->selectedSemesterId);

        $existsInAnotherSemester = ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->where('id', '!=', $this->selectedSemesterId)
            ->whereHas('subjects', fn ($q) => $q->where('subjects.id', $this->attach_subject_id))
            ->value('semester_no');

        if ($existsInAnotherSemester) {
            $message = "Môn này đã tồn tại ở học kỳ {$existsInAnotherSemester} trong CTDT.";
            $this->addError('attach_subject_id', $message);
            $this->error($message);
            return;
        }

        $payload = [
            'type' => $this->attach_type,
            'notes' => trim($this->attach_notes) ?: null,
            'order' => $this->attach_order,
        ];

        $semester->subjects()->syncWithoutDetaching([$this->attach_subject_id => $payload]);
        $semester->subjects()->updateExistingPivot($this->attach_subject_id, $payload);

        try {
            SubjectPrerequisite::syncForProgramSubject(
                $this->programId,
                (int) $this->attach_subject_id,
                $this->attach_subject_prerequisite_id
            );

            SubjectEquivalent::syncForProgramSubject(
                $this->programId,
                (int) $this->attach_subject_id,
                $this->attach_subject_equivalent_id
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->recalculateSemesterCredits($semester->id);
        $this->resetSubjectForm();
        $this->modalAddSubject = false;
        $this->success('Đã lưu môn học vào học kỳ thành công.');
    }

    public function removeSubjectFromSemester(int $subjectId): void
    {
        $this->pendingRemoveSubjectId = $subjectId;

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa môn học này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveSubject',
            'id' => $subjectId,
        ]);
    }

    #[On('confirmRemoveSubject')]
    public function confirmRemoveSubject(mixed $payload = null): void
    {
        $subjectId = (int) ($this->pendingRemoveSubjectId ?? 0);

        if (is_array($payload)) {
            $subjectId = (int) ($payload['id'] ?? 0);
        } elseif (is_numeric($payload)) {
            $subjectId = (int) $payload;
        }

        if ($subjectId <= 0) {
            $this->error('Không xác định được môn học cần xóa.');
            return;
        }

        if (!$this->selectedSemesterId) {
            return;
        }

        try {
            $dependentCodes = SubjectPrerequisite::query()
                ->forProgram($this->programId)
                ->where('prerequisite_subject_id', $subjectId)
                ->join('subjects', 'subjects.id', '=', 'subject_prerequisites.subject_id')
                ->orderBy('subjects.code')
                ->distinct()
                ->pluck('subjects.code');

            if ($dependentCodes->isNotEmpty()) {
                $preview = $dependentCodes->take(5)->implode(', ');
                $this->error('Không thể xóa: Môn học này đang là môn tiên quyết của ' . $preview . ($dependentCodes->count() > 5 ? '...' : '') . '.');
                return;
            }

            $semester = ProgramSemester::query()
                ->where('training_program_id', $this->programId)
                ->findOrFail($this->selectedSemesterId);

            $semester->subjects()->detach($subjectId);

            // Xoa cac lien ket tien quyet do mon nay khai bao (A -> B) khi mon A bi bo khoi CTDT.
            SubjectPrerequisite::query()
                ->forProgram($this->programId)
                ->where('subject_id', $subjectId)
                ->delete();

            SubjectEquivalent::query()
                ->forProgram($this->programId)
                ->where(function ($q) use ($subjectId) {
                    $q->where('subject_id', $subjectId)
                        ->orWhere('equivalent_subject_id', $subjectId);
                })
                ->delete();

            $this->recalculateSemesterCredits($semester->id);
            $this->success('Đã xóa môn học khỏi học kỳ thành công.');
        } catch (QueryException $e) {
            $this->error('Lỗi khi xóa môn học: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->error('Đã xảy ra lỗi: ' . $e->getMessage());
        } finally {
            $this->pendingRemoveSubjectId = null;
        }
    }

    protected function recalculateSemesterCredits(int $semesterId): void
    {
        $semester = ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->find($semesterId);

        if (!$semester) {
            return;
        }

        $semesterCredits = (int) $semester->subjects()->sum('subjects.credits');
        $semester->update(['total_credits' => $semesterCredits]);

//        $this->recalculateProgramCredits();
    }

    protected function recalculateProgramCredits(): void
    {
        $total = (int) ProgramSemester::query()
            ->where('training_program_id', $this->programId)
            ->sum('total_credits');

        TrainingProgram::query()->where('id', $this->programId)->update(['total_credits' => $total]);
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => 'STT', 'sortable' => false, 'class' => 'w-12'],
            ['key' => 'code', 'label' => 'Mã MH', 'sortable' => false, 'class' => 'w-8'],
            ['key' => 'name', 'label' => 'Tên môn học ', 'sortable' => false],
            ['key' => 'credits', 'label' => 'Tín chỉ', 'sortable' => false, 'class' => 'w-20'],
            ['key' => 'type', 'label' => 'Loại', 'sortable' => false, 'class' => 'w-28'],
            ['key' => 'notes', 'label' => 'Ghi chú', 'sortable' => false],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-28'],
        ];
    }
};
?>

<div>
    <x-slot:title>Quản lý học kỳ và môn học</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.training-program.index') }}" class="font-semibold text-slate-700">Danh sách chương trình đào tạo</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.training-program.edit', $this->program->id) }}"
           class="font-semibold text-slate-700">{{ $this->program->getTranslation('name', 'vi', false) }}</a>
        <span class="mx-1">/</span>
        <span>Học kỳ & môn học</span>
    </x-slot:breadcrumb>

    <x-header title="Quản lý học kỳ và môn học"
              subtitle="{{ $this->program->version }} - {{ $this->program->major->programMajor->name }} - {{ $this->program->major->name }}"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:actions>
            <x-button label="Danh sách môn học" icon="o-rectangle-stack" class="btn-ghost" link="{{ route('admin.subject.index') }}" />
            <x-button label="Chỉnh sửa Chương trình đào tạo" icon="o-pencil" class="btn-ghost" link="{{ route('admin.training-program.edit', $this->program->id) }}" />
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-12 gap-5 text-[14px]!">
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">
            <x-card title="Môn học trong học kỳ" shadow class="p-3!">
                <div wire:loading.remove wire:target="selectSemester">
                    @if($this->selectedSemester)
                        <div class="flex flex-wrap justify-between gap-2 mb-3">
                            <div class="text-md text-gray-600">
                                Đang quản lý: <span class="font-semibold">Học kỳ {{ $this->selectedSemester->semester_no }}</span>
                            </div>
                            <x-button label="Thêm môn" icon="o-plus" class="btn-sm btn-primary text-white" wire:click="openCreateSubjectPivot" spinner/>
                        </div>

                        <div class="mt-5 overflow-x-auto">
                            <x-table
                                :headers="$this->headers()"
                                :rows="$this->selectedSemester->subjects"
                                wire:model="expanded" expandable
                                striped
                                wire:loading.class="opacity-50 pointer-events-none select-none"
                                class="
                                    bg-white
                                    [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                                    [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                                    [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                                    [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
                                "
                            >
                                @scope('cell_id', $subject)
                                {{ $subject->pivot->order ?? $subject->id }}
                                @endscope

                                @scope('cell_code', $subject)
                                {{ $subject->code }}
                                @endscope

                                @scope('cell_name', $subject)
                                <div class="font-semibold">{{ $subject->getTranslation('name', 'vi', false) ?: '—' }}</div>
                                <div class="text-sm text-gray-400">{{ $subject->getTranslation('name', 'en', false) ?: '' }}</div>
                                @endscope

                                @scope('cell_credits', $subject)
                                <div class="font-semibold">{{ $subject->credits_display }} tín chỉ</div>
                                <div class="text-sm text-gray-500 whitespace-nowrap">LT/TH: {{ $subject->credits_theory_display }}/{{ $subject->credits_practice_display }}</div>
                                @endscope

                                @scope('cell_type', $subject)
                                @php
                                    $typeLabel = match ($subject->pivot->type) {
                                        'required' => 'Bắt buộc',
                                        'elective' => 'Tự chọn',
                                        'pcbb' => 'PCBB',
                                        default => strtoupper((string) $subject->pivot->type),
                                    };

                                    $typeClass = match ($subject->pivot->type) {
                                        'required' => 'badge-error',
                                        'elective' => 'badge-success',
                                        'pcbb' => 'badge-warning',
                                        default => 'badge-neutral',
                                    };
                                @endphp
                                <x-badge :value="$typeLabel" class="{{ $typeClass }} badge-md text-white font-semibold whitespace-nowrap" />
                                @endscope

                                @scope('cell_notes', $subject)
                                {{ $subject->pivot->notes ?: '—' }}
                                @endscope

                                @scope('cell_actions', $subject)
                                <div class="flex gap-1 justify-end">
                                    <x-button icon="o-pencil" class="btn-xs btn-ghost text-primary" wire:click="editSubjectPivot({{ $subject->id }})" tooltip="Chỉnh sửa"/>
                                    <x-button icon="o-trash" class="btn-xs btn-ghost text-error" wire:click="removeSubjectFromSemester({{ $subject->id }})" tooltip="Xóa"/>
                                </div>
                                @endscope

                                @scope('expansion', $subject)
                                <div class="bg-base-200/50 rounded-md">
                                    <div class="text-sm font-semibold mb-2"> Môn học tiên quyết</div>

                                    @if($subject->prerequisites->isEmpty())
                                        <div class="text-sm text-gray-500">Không có môn học tiên quyết.</div>
                                    @else
                                        <div class="grid md:grid-cols-2 gap-2">
                                            @foreach($subject->prerequisites as $prerequisite)
                                                <div class="rounded border border-base-300 bg-white px-3 py-2 text-sm">
                                                    <div class="font-semibold">{{ $prerequisite->code }}</div>
                                                    <div class="text-gray-600">{{ $prerequisite->getTranslation('name', 'vi', false) ?: '—' }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="text-sm font-semibold mb-2 mt-4">Môn học tương đương</div>

                                    @if($subject->equivalents->isEmpty())
                                        <div class="text-sm text-gray-500">Không có môn học tương đương.</div>
                                    @else
                                        <div class="grid md:grid-cols-2 gap-2">
                                            @foreach($subject->equivalents as $equivalent)
                                                <div class="rounded border border-base-300 bg-white px-3 py-2 text-sm">
                                                    <div class="font-semibold">{{ $equivalent->code }}</div>
                                                    <div class="text-gray-600">{{ $equivalent->getTranslation('name', 'vi', false) ?: '—' }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @endscope

                                <x-slot:empty>
                                    <div class="py-4 text-center text-gray-500">Học kỳ này chưa có môn học nào.</div>
                                </x-slot:empty>

                            </x-table>
                            <div wire:loading.flex wire:target="openCreateSubjectPivot, editSubjectPivot, removeSubjectFromSemester"
                                 class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
                                <div class="flex flex-col items-center gap-2 flex-1">
                                    <x-loading class="text-primary loading-lg"/>
                                    <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-gray-500">Vui lòng chọn hoặc tạo học kỳ để quản lý môn học</div>
                    @endif
                </div>
                <div wire:loading wire:target="selectSemester" class="size-full! p-10 py-30 text-center">
                    <x-loading class="" />
                    <p>Đang tải dữ liệu...</p>
                </div>
            </x-card>
        </div>
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">
            <x-card title="Danh sách học kỳ" shadow class="p-3!">
                <x-button label="Thêm học kỳ" icon="o-plus" class="btn-primary text-white mb-3 btn-sm" @click="$wire.modalSemester = true" wire:click="openCreateSemester" spinner="openCreateSemester"/>
                <div class="space-y-2">
                    @forelse($this->semesters as $semester)
                        <div class="p-3 rounded border hover:bg-gray-200/50 {{ $selectedSemesterId === $semester->id ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white' }}">
                            <div class="flex items-start justify-between gap-2">
                                <button class="text-left flex-1" wire:click="selectSemester({{ $semester->id }})">
                                    <div class="font-semibold">Học kỳ {{ $semester->semester_no }}</div>
                                    <div class="text-xs text-gray-500 mt-1">{{ $semester->total_credits }} TC • {{ $semester->subjects_count }} môn</div>
                                    @if($semester->start_date && $semester->end_date)
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ \Illuminate\Support\Carbon::parse($semester->start_date)->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($semester->end_date)->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </button>
                                <div class="flex gap-1">
                                    <x-button icon="o-pencil" class="btn-xs btn-ghost text-primary" @click="$wire.modalSemester = true" wire:click="editSemester({{ $semester->id }})" tooltip="Chỉnh sửa"/>
                                    <x-button icon="o-trash" class="btn-xs btn-ghost text-error" wire:click="deleteSemester({{ $semester->id }})" tooltip="Xóa"/>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Chưa có học kỳ nào.</div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
{{--start modal hoc ky--}}
    <x-modal wire:model="modalSemester" title="{{ $editingSemesterId ? 'Chỉnh sửa học kỳ' : 'Thêm học kỳ' }}" separator>
        <div wire:loading wire:target="openCreateSemester, editSemester" class="size-full p-10 text-center">
            <x-loading class="" />
            <p>Đang tải dữ liệu...</p>
        </div>
        <div class="space-y-3" wire:loading.remove wire:target="openCreateSemester,editSemester">
            <div class="grid grid-cols-1 gap-3">
                <x-input label="Số học kỳ" type="number" min="1" wire:model.live.debounce.300ms="semester_no" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <x-input label="Ngày bắt đầu" type="date" wire:model.live.debounce.300ms="semester_start_date" />
                <x-input label="Ngày kết thúc" type="date" wire:model.live.debounce.300ms="semester_end_date" />
            </div>
{{--                    <x-input label="Tong tin chi" type="number" min="0" wire:model="semester_total_credits" />--}}
        </div>


        <x-slot:actions>
            <x-button label="Đóng" class="btn-ghost" @click="$wire.modalSemester = false"  />
            <x-button label="Lưu" class="btn-primary text-white" wire:click="saveSemester" spinner="saveSemester" />
        </x-slot:actions>
    </x-modal>
    {{--end modal hoc ky--}}

    {{--start modal them mon hoc--}}
    <x-modal wire:model="modalAddSubject" title="{{ $pivot_original_subject_id ? 'Chỉnh sửa môn học' : 'Thêm môn học' }}" separator class="modalAddSubject">
        <div wire:loading wire:target="openCreateSubjectPivot" class="size-full p-10 text-center">
            <x-loading class="" />
            <p>Đang tải dữ liệu...</p>
        </div>
        <div class="space-y-3 py-0 px-1 max-h-[70vh] overflow-y-auto pr-1" wire:loading.remove wire:target="openCreateSubjectPivot" >
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-3 ">
                    @if($pivot_original_subject_id)
                        <div class="">
                            <x-input label="Môn học" type="number" min="0" placeholder="{{ collect($this->subjectOptions)->firstWhere('id', $attach_subject_id)['name'] ?? 'N/A' }}" readonly />
                        </div>
                    @else
                        <x-input
                            label="Tìm thêm môn học"
                            icon="o-magnifying-glass"
                            placeholder="Nhập tên hoặc mã môn học..."
                            wire:model.live.debounce.400ms="subjectSearch"
                            clearable
                        />
                        <div>
                            <x-choices-offline single
                                               clearable
                                               searchable
                                               label="Môn học"
                                               wire:model.live.debounce.300ms="attach_subject_id"
                                               :options="$this->subjectOptions"
                                               option-value="id" option-label="name"
                                               placeholder="Chọn môn học"
                                               noResultText="Không tìm thấy môn học nào"
                            />
                            <div class="text-sm mt-2 text-primary font-medium">{{$attach_credits}}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Hiển thị tối đa {{ $this->subjectSearchLimit }} môn học
                            </div>
                        </div>
                    @endif

                <x-select label="Loại" wire:model.live.debounce.300ms="attach_type" :options="[
                    ['id' => 'required', 'name' => 'Môn bắt buộc'],
                    ['id' => 'elective', 'name' => 'Môn tự chọn'],
                    ['id' => 'pcbb', 'name' => 'PCBB - Phần cứng bắt buộc']
                ]" option-value="id" option-label="name" />
                <x-input label="Thứ tự" type="number" min="0" wire:model="attach_order" />
                <div class="mt-4 col-span-2">
                    <label class="font-semibold text-gray-700 mb-3 block">Danh sách môn học tiên quyết</label>
                    <x-input
                        icon="o-magnifying-glass"
                        placeholder="Tìm theo mã hoặc tên môn..."
                        wire:model.live.debounce.300ms="prerequisiteSearch"
                        clearable
                    />
                    <div class="relative mt-2">
                        <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-4 p-5 bg-gray-50/50 rounded-xl border border-gray-200 shadow-sm max-h-35 overflow-auto">
                        @forelse($this->filteredSubjectUsedOptions as $subject)
                            <div class="select-none" wire:key="subject-used-{{ $subject['id'] }}">
                                <x-checkbox
                                    label="{{ $subject['name'] }}"
                                    wire:model="attach_subject_prerequisite_id"
                                    value="{{ $subject['id'] }}"
                                    class="checkbox-primary checkbox-sm"
                                />
                            </div>
                        @empty
                            <div class="col-span-full text-center py-4 text-red-500">
                                Chưa có môn học nào trong chương trình đào tạo này.
                            </div>
                        @endforelse
                            <div wire:loading.flex wire:target="prerequisiteSearch,attach_subject_id" class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <x-loading class="loading-spinner text-primary" />
                                    <span>Đang lọc môn học...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 col-span-2">
                    <label class="font-semibold text-gray-700 mb-3 block">Danh sách môn học tương đương</label>
                    <x-input
                        icon="o-magnifying-glass"
                        placeholder="Tìm môn tương đương theo mã hoặc tên môn..."
                        wire:model.live.debounce.300ms="equivalentSearch"
                        clearable
                    />
                    <div class="relative mt-2">
                        <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-4 p-5 bg-gray-50/50 rounded-xl border border-gray-200 shadow-sm max-h-35 overflow-auto">
                        @forelse($this->filteredEquivalentSubjectOptions as $subject)
                            <div class="select-none" wire:key="subject-equivalent-{{ $subject['id'] }}">
                                <x-checkbox
                                    label="{{ $subject['name'] }}"
                                    wire:model="attach_subject_equivalent_id"
                                    value="{{ $subject['id'] }}"
                                    class="checkbox-primary checkbox-sm"
                                />
                            </div>
                        @empty
                            <div class="col-span-full text-center py-4 text-red-500">
                                Chưa có môn học nào trong chương trình đào tạo này.
                            </div>
                        @endforelse
                            <div wire:loading.flex wire:target="equivalentSearch,attach_subject_id" class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <x-loading class="loading-spinner text-primary" />
                                    <span>Đang lọc môn học tương đương...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
{{--                </div>--}}
                <div class="md:col-span-2">
                    <x-textarea label="Ghi chú" wire:model.live.debounce.300ms="attach_notes" rows="3" />
                </div>
            </div>
        </div>


        <x-slot:actions>
            <x-button label="Đóng" class="btn-ghost" @click="$wire.modalAddSubject = false"  />
            <x-button label="Lưu" class="btn-primary text-white" wire:click="saveSubjectToSemester" spinner="saveSubjectToSemester" />
        </x-slot:actions>
    </x-modal>
    {{--end modal them mon hoc--}}

</div>



