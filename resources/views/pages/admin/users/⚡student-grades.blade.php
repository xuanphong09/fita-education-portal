<?php

use App\Models\Major;
use App\Models\ProgramMajor;
use App\Models\Subject;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\Intake;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.client')]
class extends Component {
    public ?User $viewingUser = null;
    public ?Major $major = null;

    #[Url(as: 'nganh')]
    public ?string $programMajorSlug = null;

    #[Url(as: 'chuyen-nganh')]
    public ?string $selectedMajorSlug = null;

    #[Url(as: 'khoa')]
    public ?int $intakeId = null;

    #[Url(as: 'hoc-ky')]
    public ?int $semesterNo = null;

    #[Url(as: 'kieu')]
    public string $viewMode = 'semester';

    #[Url(as: 'tim')]
    public string $search = '';

    #[Url(as: 'loai')]
    public string $typeFilter = '';

    #[Url(as: 'trang-thai')]
    public string $statusFilter = '';

    public bool $isLockedProfile = false;

    public array $expanded = [];
    public bool $showSemesterTimelineModal = false;
    public bool $pendingOpenSemesterTimelineModal = true;
    public ?string $canonicalRedirectUrl = null;

    #[Computed]
    public function majorLabel(): string
    {
        if ($this->programMajorSlug) {
            $selectedMajor = ProgramMajor::query()->where('slug', $this->programMajorSlug)->first();

            if ($selectedMajor) {
                return $this->localizedName($selectedMajor);
            }
        }
        return $this->localizedName($this->major);
    }

    #[Computed]
    public function specializationLabel(): string
    {
        if ($this->selectedMajorSlug) {
            $selectedMajor = Major::query()->where('slug', $this->selectedMajorSlug)->first();

            if ($selectedMajor) {
                return $this->localizedName($selectedMajor);
            }
        }

        return $this->majorLabel;
    }

    public function mount(User $user): void
    {
        abort_unless(auth()->user()?->can('xem_diem_sinh_vien'), 403);

        $user->loadMissing('student.major.programMajor', 'student.programMajor');

        abort_unless($user->user_type === 'student' && $user->student, 404);

        abort_unless(
            filled($user->student->vnua_password)
            && $user->student->grade_sync_status === 'success',
            403,
            'Sinh viên chưa đồng ý hoặc chưa đồng bộ điểm thành công.'
        );

        abort_unless(
            $user->student->intake_id
            && ($user->student->program_major_id || $user->student->major_id),
            403,
            'Sinh viên chưa thiết lập Khóa/Ngành nên chưa xác định được chương trình đào tạo.'
        );

        $this->viewingUser = $user;

        $student = $user->student;

        $this->isLockedProfile = true;
        $this->intakeId = (int) $student->intake_id;
        $this->programMajorSlug = (string) (
        $student->major?->programMajor?->slug
            ?: $student->programMajor?->slug
            ?: ''
        );

        if ($student->major?->slug) {
            $this->selectedMajorSlug = (string) $student->major->slug;
        } else {
            $this->selectedMajorSlug = null;
        }

        if ($this->selectedMajorSlug) {
            $selectedMajor = Major::query()
                ->where('slug', $this->selectedMajorSlug)
                ->first();

            if ($selectedMajor) {
                $this->major = $selectedMajor;
                $this->programMajorSlug = $selectedMajor->programMajor?->slug;
            }
        }
    }

    public function updatedProgramMajorSlug(): void
    {
        $this->selectedMajorSlug = null;
        $this->major = null;
        $this->semesterNo = null;
        $this->expanded = [];
        $this->showSemesterTimelineModal = false;
        $this->pendingOpenSemesterTimelineModal = true;
    }

    public function updatedSelectedMajorSlug(): void
    {
        if (!$this->selectedMajorSlug) {
            return;
        }

        $selectedMajor = Major::query()
            ->where('slug', $this->selectedMajorSlug)
            ->where('is_active', true)
            ->first();

        if (!$selectedMajor) {
            return;
        }

        $this->major = $selectedMajor;
        $this->programMajorSlug = $selectedMajor->programMajor?->slug;
        $this->semesterNo = null;
        $this->expanded = [];
        $this->showSemesterTimelineModal = false;
        $this->pendingOpenSemesterTimelineModal = true;
    }

    protected function redirectToCanonicalMajorUrl(Major $major): void
    {
        if ((string) request()->query('chuyen-nganh', '') === (string) $major->slug) {
            return;
        }

        $params = [
            'chuyen-nganh' => (string) $major->slug,
            'nganh' => $major->programMajor?->slug,
            'khoa' => $this->intakeId,
            'hoc-ky' => $this->semesterNo,
            'kieu' => $this->viewMode !== 'semester' ? $this->viewMode : null,
            'tim' => trim($this->search) !== '' ? $this->search : null,
            'loai' => $this->typeFilter !== '' ? $this->typeFilter : null,
            'trang-thai' => $this->statusFilter !== '' ? $this->statusFilter : null,
        ];

        $this->redirectRoute('client.training-programs.major', array_filter($params, fn ($value) => $value !== null), navigate: true);
    }

    public function updatedIntakeId(): void
    {
        $this->programMajorSlug = null;
        $this->selectedMajorSlug = null;
        $this->major = null;
        $this->semesterNo = null;
        $this->expanded = [];
        $this->showSemesterTimelineModal = false;
        $this->pendingOpenSemesterTimelineModal = true;
    }

    public function closeSemesterTimelineModal(): void
    {
        $this->showSemesterTimelineModal = false;
        $this->pendingOpenSemesterTimelineModal = false;
    }

    public function openSemesterTimelineModal(): void
    {
        $this->showSemesterTimelineModal = true;
        $this->pendingOpenSemesterTimelineModal = false;
    }

    public function updatedSemesterNo(): void
    {
        $this->expanded = [];
    }

    public function updatedViewMode(): void
    {
        $this->expanded = [];
    }

    public function updatedSearch(): void
    {
        $this->expanded = [];
    }

    public function updatedTypeFilter(): void
    {
        $this->expanded = [];
    }

    public function updatedStatusFilter(): void
    {
        $this->expanded = [];
    }

    public function setViewMode(string $mode): void
    {
        if (!in_array($mode, ['semester', 'group'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    protected function localizedName(mixed $model, string $field = 'name'): string
    {
        if (!$model) {
            return '';
        }

        if (method_exists($model, 'getTranslation')) {
            $locale = app()->getLocale();

            return trim((string) ($model->getTranslation($field, $locale, false)
                ?: $model->getTranslation($field, 'vi', false)
                    ?: $model->getTranslation($field, 'en', false)
                        ?: ''));
        }

        return trim((string) data_get($model, $field, '')) ?: '';
    }

    protected function normalizeSearchText(?string $value): string
    {
        return Str::lower(Str::ascii(trim((string) $value)));
    }

    protected function highlightMatch(?string $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '—';
        }

        $normalizedKeyword = $this->normalizeSearchText($this->search);
        if ($normalizedKeyword === '') {
            return e($text);
        }

        $tokens = collect(preg_split('/\s+/u', $normalizedKeyword) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '')
            ->unique()
            ->sortByDesc(fn ($token) => mb_strlen($token))
            ->values()
            ->all();

        if (empty($tokens)) {
            return e($text);
        }

        [$chars, $normalizedText, $normalizedToOriginal] = $this->buildNormalizedIndexMap($text);

        if ($normalizedText === '' || empty($normalizedToOriginal)) {
            return e($text);
        }

        $ranges = [];

        foreach ($tokens as $token) {
            $tokenLength = mb_strlen($token, 'UTF-8');
            if ($tokenLength <= 0) {
                continue;
            }

            $offset = 0;

            while (($position = mb_strpos($normalizedText, $token, $offset, 'UTF-8')) !== false) {
                $normalizedStart = (int) $position;
                $normalizedEnd = $normalizedStart + $tokenLength - 1;

                if (!isset($normalizedToOriginal[$normalizedStart], $normalizedToOriginal[$normalizedEnd])) {
                    $offset = $normalizedStart + 1;
                    continue;
                }

                $ranges[] = [
                    'start' => (int) $normalizedToOriginal[$normalizedStart],
                    'end' => (int) $normalizedToOriginal[$normalizedEnd],
                ];

                $offset = $normalizedStart + 1;
            }
        }

        if (empty($ranges)) {
            return e($text);
        }

        usort($ranges, function (array $left, array $right): int {
            if ($left['start'] === $right['start']) {
                return $left['end'] <=> $right['end'];
            }

            return $left['start'] <=> $right['start'];
        });

        $mergedRanges = [];
        foreach ($ranges as $range) {
            $lastIndex = count($mergedRanges) - 1;
            if ($lastIndex < 0 || $range['start'] > ($mergedRanges[$lastIndex]['end'] + 1)) {
                $mergedRanges[] = $range;
                continue;
            }

            $mergedRanges[$lastIndex]['end'] = max($mergedRanges[$lastIndex]['end'], $range['end']);
        }

        $result = '';
        $cursor = 0;

        foreach ($mergedRanges as $range) {
            if ($range['start'] > $cursor) {
                $result .= e(implode('', array_slice($chars, $cursor, $range['start'] - $cursor)));
            }

            $result .= '<mark class="rounded bg-amber-200 px-1 text-black">'
                . e(implode('', array_slice($chars, $range['start'], $range['end'] - $range['start'] + 1)))
                . '</mark>';

            $cursor = $range['end'] + 1;
        }

        if ($cursor < count($chars)) {
            $result .= e(implode('', array_slice($chars, $cursor)));
        }

        return $result;
    }

    protected function renderSubjectName(array $subject): string
    {
        $nameHtml = $this->highlightMatch((string) ($subject['name'] ?? ''));
        $syllabusUrl = trim((string) ($subject['syllabus_preview_url'] ?? ($subject['syllabus_url'] ?? '')));

        if ($syllabusUrl === '') {
            return $nameHtml;
        }

        return '<a href="' . e($syllabusUrl) . '" target="_blank" rel="noopener noreferrer" class="text-fita2 hover:opacity-85">'
            . $nameHtml
            . '</a>';
    }

    protected function buildNormalizedIndexMap(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $normalizedText = '';
        $normalizedToOriginal = [];

        foreach ($chars as $index => $char) {
            $normalizedChar = $this->normalizeSearchText((string) $char);
            if ($normalizedChar === '') {
                continue;
            }

            $normalizedParts = preg_split('//u', $normalizedChar, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($normalizedParts as $part) {
                $normalizedText .= $part;
                $normalizedToOriginal[] = $index;
            }
        }

        return [$chars, $normalizedText, $normalizedToOriginal];
    }

    protected function formatSemesterTimeline(mixed $semester): ?string
    {
        $startDate = data_get($semester, 'start_date');
        $endDate = data_get($semester, 'end_date');

        if ($startDate && $endDate) {
            return \Illuminate\Support\Carbon::parse($startDate)->format('m/Y')
                . ' - '
                . \Illuminate\Support\Carbon::parse($endDate)->format('m/Y');
        }

        return null;
    }

    protected function buildEquivalentItemsForSubject(mixed $subject): \Illuminate\Support\Collection
    {
        $subjectCredits = (float) ($subject->credits ?? 0);

        if ($subjectCredits <= 0) {
            return collect();
        }

        return collect($subject->equivalents ?? [])
            ->filter(function ($equivalent) use ($subjectCredits) {
                $equivalentCredits = (float) ($equivalent->credits ?? 0);

                return $equivalentCredits > 0
                    && $equivalentCredits >= $subjectCredits;
            })
            ->map(fn ($equivalent) => [
                'id' => (int) $equivalent->id,
                'code' => (string) $equivalent->code,
                'name' => $this->localizedName($equivalent),
                'credits' => (float) ($equivalent->credits ?? 0),
                'credits_theory' => (float) ($equivalent->credits_theory ?? 0),
                'credits_practice' => (float) ($equivalent->credits_practice ?? 0),
            ])
            ->values();
    }

    public function semesterHeaders(): array
    {
        return [
            ['key' => 'no', 'label' => __('No.'), 'sortable' => false, 'class' => 'w-16 px-1 text-center!'],
            ['key' => 'code', 'label' => __('Subject code'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'name', 'label' => __('Subject name'), 'sortable' => false, 'class' => 'w-70'],
            ['key' => 'credits', 'label' => __('Credits'), 'sortable' => false, 'class' => 'w-6 w-1 px-1 text-center!'],
            ['key' => 'theory', 'label' => __('Theory'), 'sortable' => false, 'class' => 'w-6 w-1 px-2 text-center!'],
            ['key' => 'practice', 'label' => __('Practice'), 'sortable' => false, 'class' => 'w-6 w-1 px-1 text-center!'],
            ['key' => 'prerequisite_subjects', 'label' => __('Prerequisite subjects'), 'sortable' => false, 'class' => 'w-16 ps-5! pe-1!'],
            ['key' => 'prerequisite_subjects_codes', 'label' => __('PS codes'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'type', 'label' => __('Type'), 'sortable' => false,],
            ['key' => 'final_score', 'label' => __('Điểm'), 'sortable' => false, 'class' => 'w-24 px-2 text-center! '. ($this->isLockedProfile ? '' : 'hidden')],
            ['key' => 'learning_status', 'label' => __('Trạng thái'), 'sortable' => false, 'class' => 'w-24 text-center ' . ($this->isLockedProfile ? '' : 'hidden')],
            ['key' => 'note', 'label' => __('Note'), 'sortable' => false],
        ];
    }

    public function groupHeaders(): array
    {
        return [
            ['key' => 'no', 'label' => __('No.'), 'sortable' => false, 'class' => 'w-16 px-1 text-center!'],
            ['key' => 'semester_no', 'label' => __('Semester'),'class'=>'px-2', 'sortable' => false],
            ['key' => 'code', 'label' => __('Subject code'), 'sortable' => false, 'class' => 'w-16 px-2'],
            ['key' => 'name', 'label' => __('Subject name'), 'sortable' => false, 'class' => 'w-70 px-2'],
            ['key' => 'credits', 'label' => __('Credits'), 'sortable' => false, 'class' => 'w-1 px-1 text-center!'],
            ['key' => 'theory', 'label' => __('Theory'), 'sortable' => false, 'class' => 'w-6 px-2 text-center!'],
            ['key' => 'practice', 'label' => __('Practice'), 'sortable' => false, 'class' => 'w-6 px-1 text-center!'],
            ['key' => 'prerequisite_subjects', 'label' => __('Prerequisite subjects'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'prerequisite_subjects_codes', 'label' => __('PS codes'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'type', 'label' => __('Type'), 'sortable' => false,],
            ['key' => 'final_score', 'label' => __('Điểm'), 'sortable' => false, 'class' => 'px-1 text-center! w-24! '. ($this->isLockedProfile ? '' : 'hidden')],
            ['key' => 'learning_status', 'label' => __('Trạng thái'), 'sortable' => false, 'class' => 'text-center! ' . ($this->isLockedProfile ? '' : 'hidden')],
            ['key' => 'note', 'label' => __('Note'), 'class'=>'px-1', 'sortable' => false],
        ];
    }

    public function with(): array
    {
        $studentGrades = collect();
        $user = $this->viewingUser;

        if ($user?->student) {
            $studentGrades = $user->student
                ->grades()
                ->get()
                ->groupBy('subject_id')
                ->map(fn ($grades) => $this->pickBestGrade($grades));
        }

        $publishedProgramQuery = fn ($query) => $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        $intakeOptions = Intake::query()
            ->whereHas('trainingPrograms', function ($query) use ($publishedProgramQuery) {
                $publishedProgramQuery($query);
            })
            ->orderByDesc('name')
            ->get(['id', 'name'])
            ->map(fn ($item) => ['id' => (int) $item->id, 'name' => (string) $item->name])
            ->toArray();

        $programMajorOptions = ProgramMajor::query()
            ->where('is_active', true)
            ->where(function ($q) use ($publishedProgramQuery) {
                $q->whereHas('trainingPrograms', function ($query) use ($publishedProgramQuery) {
                    $publishedProgramQuery($query);
                    if ($this->intakeId) {
                        $query->where('intake_id', $this->intakeId);
                    }
                })
                    ->orWhereHas('majors.trainingPrograms', function ($query) use ($publishedProgramQuery) {
                        $publishedProgramQuery($query);
                        if ($this->intakeId) {
                            $query->where('intake_id', $this->intakeId);
                        }
                    });
            })
            ->ordered()
            ->get();

        $majorOptions = Major::query()
            ->where('is_active', true)
            ->when($this->intakeId, function ($query) use ($publishedProgramQuery) {
                $query->whereHas('trainingPrograms', function ($programQuery) use ($publishedProgramQuery) {
                    $publishedProgramQuery($programQuery);
                    $programQuery->where('intake_id', $this->intakeId);
                });
            })
            ->when($this->programMajorSlug, function ($query) {
                $query->whereHas('programMajor', fn ($programMajorQuery) => $programMajorQuery->where('slug', $this->programMajorSlug));
            })
            ->whereHas('trainingPrograms', function ($query) use ($publishedProgramQuery) {
                $publishedProgramQuery($query);
            })
            ->ordered()
            ->get(['id', 'name', 'slug', 'program_major_id']);

        $availableIntakes = collect($intakeOptions)->pluck('id');
        if ($this->intakeId && !$availableIntakes->contains($this->intakeId)) {
            $this->intakeId = null;
            $this->programMajorSlug = null;
            $this->selectedMajorSlug = null;
            $this->major = null;
        }

        if ($this->programMajorSlug && !$programMajorOptions->contains(fn ($item) => $item->slug === $this->programMajorSlug)) {
            $this->programMajorSlug = null;
            $this->selectedMajorSlug = null;
            $this->major = null;
        }

        if ($this->selectedMajorSlug && !$majorOptions->contains(fn ($item) => $item->slug === $this->selectedMajorSlug)) {
            $this->selectedMajorSlug = null;
            $this->major = null;
        }

        $selectedMajor = $this->selectedMajorSlug
            ? $majorOptions->firstWhere('slug', $this->selectedMajorSlug)
            : null;

        $this->major = $selectedMajor;

        $selectedProgramMajor = $this->programMajorSlug
            ? $programMajorOptions->firstWhere('slug', $this->programMajorSlug)
            : null;

        // Chỉ cần khóa + ngành để bắt đầu tải CTĐT (ưu tiên CTĐT chung của ngành).
        $isSelectionComplete = (bool) ($this->intakeId && $selectedProgramMajor);

        // Nếu chưa chọn đủ thông tin -> Trả về giao diện trống
        if (!$isSelectionComplete) {
            return [
                'programs' => collect(),
                'intakeOptions' => $intakeOptions,
                'activeProgram' => null,
                'semesterBlocks' => collect(),
                'groupBlocks' => collect(),
                'currentSemesterTimeline' => null,
                'nextSemesterTimeline' => null,
                'programMajorOptions' => $programMajorOptions,
                'majorOptions' => $majorOptions,
                'studentGrades' => $studentGrades,
            ];
        }

        // --- 2. TRUY VẤN DUY NHẤT 1 CTĐT KHI ĐÃ CHỌN ĐỦ THÔNG TIN ---
        $normalizedKeyword = $this->normalizeSearchText($this->search);
        $majorId = $this->major?->id ? (int) $this->major->id : null;
        $programMajorId = $selectedProgramMajor?->id ? (int) $selectedProgramMajor->id : null;

        $activeProgramBaseQuery = TrainingProgram::query()
            ->where('intake_id', $this->intakeId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with([
                'major',
                'programMajor',
                'intake',
                'semesters' => function ($semesterQuery) {
                    $semesterQuery->orderBy('semester_no')
                        ->with(['subjects' => function ($subjectQuery) {
                            $subjectQuery
                                ->where('subjects.is_active', true)
                                ->with(['groupSubject', 'prerequisites', 'equivalents'])
                                ->orderBy('program_semester_subjects.order');
                        }]);
                },
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $activeProgram = null;

        if ($majorId) {
            $activeProgram = (clone $activeProgramBaseQuery)
                ->where('major_id', $majorId)
                ->first();
        }

        if (!$activeProgram && $programMajorId) {
            $activeProgram = (clone $activeProgramBaseQuery)
                ->where('program_major_id', $programMajorId)
                ->whereNull('major_id')
                ->first();
        }

        $programs = $activeProgram ? collect([$activeProgram]) : collect();

        // --- 3. XỬ LÝ KHỐI DỮ LIỆU MÔN HỌC BÊN TRONG ---
        $semesterBlocks = collect();
        $groupBlocks = collect();
        $currentSemesterTimeline = null;
        $nextSemesterTimeline = null;

        if ($activeProgram) {
            $today = now()->startOfDay();

            $timelineSemesters = $activeProgram->semesters
                ->filter(function ($semester) {
                    if (!$this->formatSemesterTimeline($semester)) return false;
                    $startDate = \Illuminate\Support\Carbon::parse((string) data_get($semester, 'start_date'))->startOfDay();
                    $endDate = \Illuminate\Support\Carbon::parse((string) data_get($semester, 'end_date'))->startOfDay();
                    return $endDate->greaterThanOrEqualTo($startDate);
                })
                ->values();

            $currentSemesterTimeline = $timelineSemesters
                ->first(function ($semester) use ($today) {
                    $startDate = \Illuminate\Support\Carbon::parse((string) data_get($semester, 'start_date'))->startOfDay();
                    $endDate = \Illuminate\Support\Carbon::parse((string) data_get($semester, 'end_date'))->startOfDay();
                    return $today->between($startDate, $endDate, true);
                });

            if ($currentSemesterTimeline) {
                $nextSemesterTimeline = $timelineSemesters
                    ->first(fn ($semester) => (int) $semester->semester_no > (int) $currentSemesterTimeline->semester_no);
            }

            if ($this->pendingOpenSemesterTimelineModal) {
                $this->showSemesterTimelineModal = false;
                $this->pendingOpenSemesterTimelineModal = false;
            }

            $semesterCollection = $activeProgram->semesters;
            if ($this->semesterNo) {
                $semesterCollection = $semesterCollection->where('semester_no', $this->semesterNo)->values();
            }

            // --- ĐÂY LÀ HÀM HELPER CHUNG ĐỂ RENDER CHUẨN MỌI CHỖ TRANG CHÍNH ---
            $buildSubjectData = function ($subject, $semester, $activeProgram, $studentGrades) {
                $prerequisites = $subject->prerequisites
                    ->filter(fn ($prerequisite) => (int) ($prerequisite->pivot->training_program_id ?? 0) === (int) $activeProgram->id)
                    ->values();

                $prerequisiteNames = $prerequisites
                    ->map(fn ($prerequisite) => $this->localizedName($prerequisite))
                    ->filter(fn ($name) => trim((string) $name) !== '' && $name !== 'N/A')
                    ->implode(', ');

                $prerequisiteCodes = $prerequisites
                    ->map(fn ($prerequisite) => (string) $prerequisite->code)
                    ->filter(fn ($code) => trim($code) !== '')
                    ->implode(', ');

                $prerequisiteSearchText = $prerequisites
                    ->flatMap(function ($prerequisite) {
                        return [
                            (string) $prerequisite->code,
                            $this->localizedName($prerequisite),
                            trim((string) $prerequisite->getTranslation('name', 'vi', false)),
                            trim((string) $prerequisite->getTranslation('name', 'en', false)),
                        ];
                    })
                    ->implode(' ');

                // 1. Map các môn tương đương và tính điểm cho từng môn
                $equivalentItems = $this->buildEquivalentItemsForSubject($subject)->map(function ($equivalent) use ($studentGrades) {
                    $gradeInfo = $studentGrades->get($equivalent['id']);
                    $learningStatus = 'pending';
                    $finalScore = null;

                    if ($gradeInfo) {
                        $statusValue = (int) $gradeInfo->is_passed;
                        $finalScore = $gradeInfo->score_10;

                        $learningStatus = match($statusValue) {
                            1  => 'passed',
                            0  => ($finalScore !== null) ? 'failed' : 'no_grade',
                            -1 => 'studying',
                            default => 'pending'
                        };
                    }

                    $equivalent['learning_status'] = $learningStatus;
                    $equivalent['final_score'] = $finalScore;
                    return $equivalent;
                });

                $subjectNameVi = trim((string) $subject->getTranslation('name', 'vi', false));
                $subjectNameEn = trim((string) $subject->getTranslation('name', 'en', false));

                // 2. Điểm của MÔN CHÍNH (Chỉ xét đúng id của nó)
                $subjectIdsToCheck = array_merge([$subject->id], $equivalentItems->pluck('id')->toArray());

                $gradeInfo = $this->pickBestGrade(
                    collect($subjectIdsToCheck)
                        ->map(fn ($id) => $studentGrades->get($id))
                        ->filter()
                );

                $learningStatus = 'pending';
                $finalScore = null;
                $passedByEquivalentCode = null;

                if ($gradeInfo) {
                    $statusValue = (int) $gradeInfo->is_passed;
                    $finalScore = $gradeInfo->score_10;

                    $learningStatus = match($statusValue) {
                        1  => 'passed',
                        0  => ($finalScore !== null) ? 'failed' : 'no_grade',
                        -1 => 'studying',
                        default => 'pending'
                    };

                    if ($gradeInfo->subject_id !== $subject->id) {
                        $equivalentMatch = $equivalentItems->firstWhere('id', $gradeInfo->subject_id);
                        if ($equivalentMatch) {
                            $passedByEquivalentCode = $equivalentMatch['code'];
                        }
                    }
                }

                return [
                    'id' => (int) $subject->id,
                    'code' => (string) $subject->code,
                    'name' => $this->localizedName($subject),
                    'syllabus_url' => $subject->syllabus_url,
                    'syllabus_preview_url' => $subject->syllabus_preview_url,
                    'credits' => (float) ($subject->credits ?? 0),
                    'theory' => (float) ($subject->credits_theory ?? 0),
                    'practice' => (float) ($subject->credits_practice ?? 0),
                    'credits_theory' => (float) ($subject->credits_theory ?? 0),
                    'credits_practice' => (float) ($subject->credits_practice ?? 0),
                    'prerequisite_subjects' => $prerequisiteNames,
                    'prerequisite_subjects_codes' => $prerequisiteCodes,
                    'type' => (string) ($subject->pivot->type ?? 'required'),
                    'note' => (string) ($subject->pivot->notes ?? ''),
                    'order' => (int) ($subject->pivot->order ?? 0),
                    'semester_no' => (int) $semester->semester_no,
                    'group_name' => $subject->groupSubject
                        ? $this->localizedName($subject->groupSubject)
                        : __('Uncategorized Group'),
                    'group_sort_order' => (int) ($subject->groupSubject->sort_order ?? 9999),
                    'can_expand' => (int) $equivalentItems->count() > 0,
                    'equivalents_count' => (int) $equivalentItems->count(),
                    'equivalents' => $equivalentItems,
                    'search_index' => $this->normalizeSearchText(implode(' ', [
                        (string) $subject->code,
                        $this->localizedName($subject),
                        $subjectNameVi,
                        $subjectNameEn,
                        $prerequisiteSearchText,
                        $equivalentItems->pluck('code')->implode(' '),
                        $equivalentItems->pluck('name')->implode(' '),
                    ])),
                    'learning_status' => $learningStatus,
                    'final_score' => $finalScore,
                    'passed_by_equivalent_code' => $passedByEquivalentCode,
                ];
            };
            // --- KẾT THÚC HÀM HELPER ---

            $semesterBlocks = $semesterCollection
                ->map(function ($semester) use ($activeProgram, $normalizedKeyword, $studentGrades, $buildSubjectData) {
                    $subjects = $semester->subjects
                        ->map(function ($subject) use ($semester, $activeProgram, $studentGrades, $buildSubjectData) {
                            return $buildSubjectData($subject, $semester, $activeProgram, $studentGrades);
                        })
                        ->when($this->statusFilter !== '', function ($collection) {
                            return $collection->filter(function ($subject) {
                                if ($this->statusFilter === 'pending') {
                                    return $subject['learning_status'] === 'pending';
                                }

                                if ((string) $subject['learning_status'] === $this->statusFilter) {
                                    return true;
                                }

                                foreach ($subject['equivalents'] as $eq) {
                                    if ((string) $eq['learning_status'] === $this->statusFilter) {
                                        return true;
                                    }
                                }
                                return false;
                            });
                        })
                        ->when($normalizedKeyword !== '', function ($collection) use ($normalizedKeyword) {
                            return $collection->filter(function ($subject) use ($normalizedKeyword) {
                                return str_contains((string) ($subject['search_index'] ?? ''), $normalizedKeyword);
                            });
                        })
                        ->when($this->typeFilter !== '', function ($collection) {
                            return $collection->filter(fn ($subject) => (string) $subject['type'] === $this->typeFilter);
                        })
                        ->sortBy('order')
                        ->values()
                        ->map(function ($subject, $index) {
                            $subject['row_index'] = $index + 1;
                            unset($subject['search_index']);
                            return $subject;
                        });

                    return [
                        'semester_no' => (int) $semester->semester_no,
                        'timeline' => $this->formatSemesterTimeline($semester),
                        'semester_name' => $semester->semester_name,
                        'total_credits' => $semester->total_credits,
                        'subjects' => $subjects,
                    ];
                })
                ->values();

            if ($normalizedKeyword !== '' || $this->statusFilter !== '') {
                $semesterBlocks = $semesterBlocks->filter(fn ($block) => $block['subjects']->isNotEmpty())->values();
            }

            $groupBlocks = $semesterBlocks
                ->flatMap(fn ($semesterBlock) => $semesterBlock['subjects'])
                ->groupBy('group_name')
                ->map(function ($subjects, $groupName) {
                    $sorted = collect($subjects)
                        ->sortBy([['semester_no', 'asc'], ['order', 'asc']])
                        ->values()
                        ->map(function ($subject, $index) {
                            $subject['row_index'] = $index + 1;
                            return $subject;
                        });

                    return [
                        'group_name' => (string) $groupName,
                        'group_sort_order' => (int) ($sorted->first()['group_sort_order'] ?? 9999),
                        'total_subjects' => (int) $sorted->count(),
                        'total_credits' => (float) $sorted->sum('credits'),
                        'subjects' => $sorted,
                    ];
                })
                ->sortBy('group_sort_order')
                ->values();
        } else {
            $this->showSemesterTimelineModal = false;
            $this->pendingOpenSemesterTimelineModal = false;
        }

        return [
            'programs' => $programs,
            'intakeOptions' => $intakeOptions,
            'activeProgram' => $activeProgram,
            'semesterBlocks' => $semesterBlocks,
            'groupBlocks' => $groupBlocks,
            'currentSemesterTimeline' => $currentSemesterTimeline,
            'nextSemesterTimeline' => $nextSemesterTimeline,
            'programMajorOptions' => $programMajorOptions,
            'majorOptions' => $majorOptions,
            'studentGrades' => $studentGrades,
        ];
    }

    public function getDegreeClassification(?float $gpa4): string
    {
        if ($gpa4 === null) return __('Chưa có');
        if ($gpa4 >= 3.6) return __('Xuất sắc');
        if ($gpa4 >= 3.2) return __('Giỏi');
        if ($gpa4 >= 2.5) return __('Khá');
        if ($gpa4 >= 2.0) return __('Trung bình');
        return __('Chưa đạt');
    }

    protected function gradeNumericScore($grade): float
    {
        if (!$grade) {
            return -1;
        }

        if (is_numeric($grade->score_10)) {
            return (float) $grade->score_10;
        }

        if (is_numeric($grade->final_score)) {
            return (float) $grade->final_score;
        }

        return -1;
    }

    protected function pickBestGrade($grades)
    {
        $grades = collect($grades)->filter();

        if ($grades->isEmpty()) {
            return null;
        }

        // 1. Ưu tiên các lần đã đạt, lấy điểm cao nhất.
        $passed = $grades
            ->filter(fn ($grade) => (int) $grade->is_passed === 1)
            ->sortByDesc(fn ($grade) => $this->gradeNumericScore($grade))
            ->values();

        if ($passed->isNotEmpty()) {
            return $passed->first();
        }

        // 2. Nếu chưa có lần đạt, nhưng đang học lại thì ưu tiên hiển thị "Đang học".
        $studying = $grades
            ->filter(fn ($grade) => (int) $grade->is_passed === -1)
            ->sortByDesc('academic_semester')
            ->values();

        if ($studying->isNotEmpty()) {
            return $studying->first();
        }

        // 3. Nếu toàn trượt, lấy lần có điểm cao nhất.
        return $grades
            ->sortByDesc(fn ($grade) => $this->gradeNumericScore($grade))
            ->values()
            ->first();
    }
};
?>

<div x-data="{
        storageKey: 'page_home_open_state',
        openStates: {},

        init() {
            try {
                const raw = localStorage.getItem(this.storageKey);
                this.openStates = raw ? JSON.parse(raw) : {};
            } catch (e) {
                this.openStates = {};
            }
        },

        saveToLocal() {
            localStorage.setItem(this.storageKey, JSON.stringify(this.openStates));
        },

        ensureState(id, defaultState = true) {
            if (this.openStates[id] === undefined) {
                this.openStates[id] = defaultState;
                this.saveToLocal();
            }
        },

        isOpen(id) {
            return this.openStates[id] !== false;
        },

        toggle(id) {
            this.ensureState(id);
            this.openStates[id] = !this.openStates[id];
            this.saveToLocal();
        },

        getDegreeClassificationText(gpa4) {
             if (gpa4 === null) return 'Chưa có';
             if (gpa4 >= 3.6) return 'Xuất sắc';
             if (gpa4 >= 3.2) return 'Giỏi';
             if (gpa4 >= 2.5) return 'Khá';
             if (gpa4 >= 2.0) return 'Trung bình';
             return 'Chưa đạt';
        },

        getDegreeClassificationClass(classification) {
             switch(classification) {
                 case 'Xuất sắc': return 'badge-secondary';
                 case 'Giỏi': return 'badge-info';
                 case 'Khá': return 'badge-success';
                 case 'Trung bình': return 'badge-warning';
                 default: return 'badge-error';
             }
        }
    }">
    <x-slot:title>{{ __('Tiến độ học tập') }} - {{ $viewingUser?->name }}</x-slot:title>

    <div class="w-full">
        <div class="bg-white px-6 py-6 relative overflow-hidden min-h-20">
            <div class="absolute inset-0 z-0">
                <div class="absolute inset-0 bg-slate-200 opacity-65"></div>
                <img
                    src="{{asset('assets/images/backgrounds/pager-bg.png')}}"
                    alt="Background"
                    class="w-full h-full object-cover object-center"
                />
            </div>
            <div class="relative z-20">
                <h2 class="text-center text-[35px]/[44px] font-semibold uppercase line-clamp-2">
                    {{ __('Tiến độ học tập sinh viên') }}
                </h2>
                <div class="flex items-center gap-1 text-gray-500 justify-center w-full">
                    <a href="{{ route('admin.user.user-list') }}" class="whitespace-nowrap hover:text-fita font-semibold text-slate-700">{{ __('Quản lý người dùng') }}</a>
                    <span><x-icon name="s-chevron-right" class="w-4 h-4" /></span>
                    <span class="whitespace-nowrap line-clamp-1">{{ $viewingUser?->name }} - {{ $viewingUser?->student?->student_code }}</span>
                </div>
            </div>

            <h2 class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[12vw]/[12vw] md:text-[8vw]/[8vw] tracking-[0.15em] lg:tracking-[0.3em] text-fita opacity-[0.07] font-extrabold pointer-events-none whitespace-nowrap z-10 w-full text-center">
                FITA - VNUA
            </h2>

        </div>
    </div>
    <div class="container mx-auto px-4 py-8 ctdt">
        <div class="space-y-4">
            <x-card shadow>
                <div class="grid grid-cols-5 gap-4">
                    @if($isLockedProfile)
                        <div class="col-span-5 text-[15px] text-fita2 font-medium flex items-center gap-2 bg-fita2/10 p-3 rounded-md">
                            <x-icon name="o-academic-cap" class="w-5 h-5" />
                            <span>{{ $viewingUser?->name }} - {{ $viewingUser?->student?->student_code }}</span>
                        </div>
                    @endif
                    <div class="w-full md:col-span-1 col-span-5">
                        <x-select
                            label="{{__('Intake')}}"
                            wire:model.live="intakeId"
                            :options="$intakeOptions"
                            option-value="id"
                            option-label="name"
                            placeholder="{{ __('No intake selected') }}"
                            :disabled="$isLockedProfile || empty($intakeOptions)"
                        />
                    </div>

                    <div class="w-full md:col-span-2 col-span-5">
                        <x-select
                            label="{{__('Major')}}"
                            wire:model.live="programMajorSlug"
                            placeholder="{{ $this->intakeId ? __('No major selected') : __('Select intake first') }}"
                            :options="$programMajorOptions->map(fn ($item) => [
                            'value' => $item->slug,
                            'label' => $this->localizedName($item),
                        ])->values()->toArray()"
                            option-value="value"
                            option-label="label"
                            :disabled="$isLockedProfile || !$this->intakeId || $programMajorOptions->isEmpty()"
                        />
                    </div>

                    <div class="w-full md:col-span-2 col-span-5">
                        <x-select
                            label="{{__('Specialization/Area of specialization')}}"
                            wire:model.live="selectedMajorSlug"
                            placeholder="{{ $this->programMajorSlug ? __('No specialization selected') : __('Select major first') }}"
                            :options="$majorOptions->map(fn ($item) => [
                            'value' => $item->slug,
                            'label' => $this->localizedName($item),
                        ])->values()->toArray()"
                            option-value="value"
                            option-label="label"
                            :disabled="$isLockedProfile || !$this->intakeId || !$this->programMajorSlug || $majorOptions->isEmpty()"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap items-end gap-4 mt-2">
                    <div class="w-full sm:w-50">
                        @php
                            $semesterOptions = $activeProgram
                                ? $activeProgram->semesters->map(function ($semester) {
                                    return [
                                        'value' => $semester->semester_no,
                                        'label' => __('Semester') . ' ' . $semester->semester_no . ($semester->semester_name? ' ('.$semester->semester_name.')':''),
                                    ];
                                })->toArray()
                                : [];
                        @endphp
                        <x-select
                            label="{{__('Filter by semester')}}"
                            wire:model.live="semesterNo"
                            :options="$semesterOptions"
                            option-value="value"
                            option-label="label"
                            placeholder="{{__('All semesters')}}"
                            :disabled="!$activeProgram"
                        />
                    </div>

                    <div class="w-full sm:w-50">
                        <x-select label="{{__('Group by')}}" :options="[
                            ['value' => 'semester', 'label' => __('Semester')],
                            ['value' => 'group', 'label' => __('Subject Group')],
                        ]"
                                  option-value="value"
                                  option-label="label"
                                  wire:model.live="viewMode"
                                  :disabled="!$activeProgram"
                        />
                    </div>

                    <div class="w-full sm:w-50">
                        <x-select
                            label="{{__('Filter by type')}}"
                            wire:model.live="typeFilter"
                            :options="[
                            ['value' => '', 'label' => __('All types')],
                            ['value' => 'required', 'label' => __('Required')],
                            ['value' => 'elective', 'label' => __('Elective')],
                            ['value' => 'pcbb', 'label' => __('Hardware Required')],
                        ]"
                            option-value="value"
                            option-label="label"
                            :disabled="!$activeProgram"
                        />
                    </div>

                    <div class="w-full sm:w-50">
                        <x-select
                            label="{{__('Filter by status')}}"
                            wire:model.live="statusFilter"
                            :options="[
                                ['value' => '', 'label' => __('All statuses')],
                                ['value' => 'passed', 'label' => __('Pass')],
                                ['value' => 'failed', 'label' => __('Fail')],
                                ['value' => 'no_grade', 'label' => __('Not yet graded')],
                                ['value' => 'studying', 'label' => __('Currently studying')],
                                ['value' => 'pending', 'label' => __('Not yet studied')],
                            ]"
                            option-value="value"
                            option-label="label"
                            :disabled="!$activeProgram || !$this->isLockedProfile"
                        />
                    </div>

                    <div class="w-full sm:flex-1 sm:min-w-60">
                        <x-input
                            label="{{ __('Search by subject name/code') }}"
                            wire:model.live.debounce.350ms="search"
                            placeholder="{{ __('Enter subject code or name...') }}"
                            clearable
                        />
                    </div>
                </div>
            </x-card>

            @if(!$activeProgram)
                <x-card shadow>
                    <div class="text-center text-[18px] py-10 text-gray-500">
                        @if(!$this->programMajorSlug || !$this->intakeId)
                            {{ __('Please select intake and major to view the training program.') }}
                        @elseif(!$this->selectedMajorSlug && $majorOptions->isNotEmpty())
                            {{ __('No general training program for this major yet. Please choose a specialization to continue.') }}
                        @else
                            {{ __('This major has no published training programs.') }}
                        @endif
                    </div>
                </x-card>
            @else
                @php
                    $programTitle = $activeProgram->getTranslation('name', app()->getLocale(), false)
                        ?: $activeProgram->getTranslation('name', 'vi', false)
                        ?: $activeProgram->getTranslation('name', 'en', false)
                        ?: 'N/A';
                    $programLevel = $this->localizedName($activeProgram, 'level');
                    $programType = $this->localizedName($activeProgram, 'type');
                    $programLanguage = $this->localizedName($activeProgram, 'language');
                    $programDuration = $activeProgram->duration_time
                        ? ($activeProgram->duration_time . ' ' . (app()->getLocale() === 'en' ? 'years' : 'năm'))
                        : 'N/A';
                    $majorCode = $activeProgram->major?->programMajor?->code
                        ?: $activeProgram->programMajor?->code
                        ?: 'N/A';

                    $student = $this->viewingUser?->student;
                    $gpa_4 = $student?->gpa_4;
                    $gpa_10 = $student?->gpa_10;
                    $total_credits_earned = $student?->total_credits_earned ?? 0;
                    $last_academic_stats_updated_at = $student?->last_academic_stats_updated_at?->format('H:i d/m/Y');
                    $total_program_credits = $activeProgram->total_credits > 0 ? $activeProgram->total_credits : 1;
                    $total_elective_credits = max($activeProgram->elective_credits, 0);
                    $total_required_credits = $total_program_credits - $total_elective_credits;
                    $classification = $this->getDegreeClassification($gpa_4);
                    $badgeClass = match($classification) {
                        'Xuất sắc', 'Excellent' => 'badge-secondary',
                        'Giỏi', 'Good' => 'badge-info',
                        'Khá', 'Fair' => 'badge-success',
                        'Trung bình', 'Average' => 'badge-warning',
                        default => 'badge-error'
                    };
                    $progressPercent = min(100, round(($total_credits_earned / $total_program_credits) * 100, 1));
                @endphp

                <div x-data="{
                    showTargetCalc: false,

                    totalCr: @js((float) $total_program_credits),
                    earnedCr: @js((float) $total_credits_earned),
                    currentGpa4: @js((float) ($gpa_4 ?? 0)),

                    targetRows: [
                        { label: 'Trung bình', target: 2.0 },
                        { label: 'Khá', target: 2.5 },
                        { label: 'Giỏi', target: 3.2 },
                        { label: 'Xuất sắc', target: 3.6 },
                    ],

                    get remainingCr() {
                        return Math.max(0, this.totalCr - this.earnedCr);
                    },

                    ceil2(value) {
                        return Math.ceil(Number(value) * 100) / 100;
                    },

                    requiredScaleFromGpa4(gpa) {
                        gpa = Number(gpa);

                        if (!Number.isFinite(gpa)) {
                            return {
                                letter: '—',
                                score10: '—',
                                note: 'Không xác định'
                            };
                        }

                        if (gpa <= 1.0) {
                            return {
                                letter: 'D',
                                score10: '4.0 - 4.9',
                                note: 'Tối thiểu mức D'
                            };
                        }

                        if (gpa <= 1.5) {
                            return {
                                letter: 'D+',
                                score10: '5.0 - 5.4',
                                note: 'Tối thiểu mức D+'
                            };
                        }

                        if (gpa <= 2.0) {
                            return {
                                letter: 'C',
                                score10: '5.5 - 6.4',
                                note: 'Tối thiểu mức C'
                            };
                        }

                        if (gpa <= 2.5) {
                            return {
                                letter: 'C+',
                                score10: '6.5 - 6.9',
                                note: 'Tối thiểu mức C+'
                            };
                        }

                        if (gpa <= 3.0) {
                            return {
                                letter: 'B',
                                score10: '7.0 - 7.9',
                                note: 'Tối thiểu mức B'
                            };
                        }

                        if (gpa <= 3.5) {
                            return {
                                letter: 'B+',
                                score10: '8.0 - 8.4',
                                note: 'Tối thiểu mức B+'
                            };
                        }

                        return {
                            letter: 'A',
                            score10: '8.5 - 10',
                            note: 'Tối thiểu mức A'
                        };
                    },

                    calcTarget(targetGpa) {
                        if (this.remainingCr === 0) {
                            return this.currentGpa4 >= targetGpa
                                ? {
                                    gpa4: '—',
                                    letter: '—',
                                    score10: '—',
                                    note: 'Đã đạt'
                                }
                                : {
                                    gpa4: '—',
                                    letter: '—',
                                    score10: '—',
                                    note: 'Không đạt'
                                };
                        }

                        let needed = (
                            (targetGpa * this.totalCr) -
                            (this.currentGpa4 * this.earnedCr)
                        ) / this.remainingCr;

                        if (needed > 4.0) {
                            return {
                                gpa4: 'Bất khả thi',
                                letter: '—',
                                score10: '—',
                                note: 'Cần vượt quá 4.00'
                            };
                        }

                        if (needed <= 0) {
                            return {
                                gpa4: 'Đã đủ GPA',
                                letter: '—',
                                score10: 'Chỉ cần qua môn',
                                note: 'Không cần tăng thêm GPA'
                            };
                        }

                        let neededRounded = this.ceil2(needed);
                        let scale = this.requiredScaleFromGpa4(neededRounded);

                        return {
                            gpa4: neededRounded.toFixed(2),
                            letter: scale.letter,
                            score10: scale.score10,
                            note: scale.note
                        };
                    }
                }">

                    <x-card shadow>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <h2 class="text-2xl font-bold">{{ $programTitle }}</h2>
                            <div class="flex flex-wrap gap-2 md:text-[16px] lg:justify-end justify-start mb-4">
                                <x-badge value="Phiên bản: {{ $activeProgram->version }}" class="badge-md bg-fita2 text-white" />
{{--                                <x-badge value="{{ Subject::formatCredit($activeProgram->total_credits) }} {{__('Credits')}}" class="badge-outline badge-md" />--}}
                                @if($currentSemesterTimeline)
                                    <x-button
                                        label="{{ __('Current semester') }}"
                                        icon="o-calendar-days"
                                        class="btn-outline btn-xs"
                                        wire:click="openSemesterTimelineModal"
                                        spinner="openSemesterTimelineModal"
                                    />
                                @endif
                            </div>
                        </div>
                        <div class="grid lg:grid-cols-2 grid-cols-1 gap-3">
                            <div>
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-15 md:text-[16px] text-gray-800 font-medium">
                                    <div><span class="font-normal">{{__('Level of Education')}}:</span> {{ $programLevel }}</div>
                                    <div><span class="font-normal">{{__('Code')}}:</span> {{ $majorCode }}</div>
                                    <div><span class="font-normal">{{__('Type of Education')}}:</span> {{ $programType }}</div>
                                    <div><span class="font-normal">{{__('Duration time')}}:</span> {{ $programDuration }}</div>
                                    <div class="sm:col-span-2"><span class="font-normal">{{__('Language')}}:</span> {{ $programLanguage }}</div>
                                </div>
                                <div class="mt-3 grid grid-cols-1 gap-x-15 md:text-[16px] text-gray-800 font-medium">
                                    <div><span class="font-normal">{{__('Total compulsory credits')}}:</span> {{ Subject::formatCredit($total_required_credits) }}</div>
                                    <div><span class="font-normal">{{__('Total elective credits')}}:</span> {{ Subject::formatCredit($total_elective_credits) }}</div>
                                    <div><span class="font-normal">{{__('Total credits of the training program')}}:</span> {{ Subject::formatCredit($total_program_credits) }}</div>
                                </div>
                            </div>
                            <div class="w-full lg:w-auto mt-4 lg:mt-0">
                                @if($gpa_4 !== null)
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100 shadow-sm w-full lg:min-w-87.5">
                                        <div class="flex justify-between items-center mb-3 border-b border-gray-200 pb-2">
                                            <h3 class="font-bold text-gray-700">{{ __('Kết quả học tập tích lũy') }}</h3>
                                            <x-button @click="showTargetCalc = !showTargetCalc" class="btn btn-xs btn-outline btn-primary" icon="o-calculator">
                                                {{ __('Tính điểm') }}
                                            </x-button>
                                        </div>

                                        <div class="grid grid-cols-3 gap-x-2 gap-y-3 text-sm md:text-base">
                                            <div>
                                                <div class="text-gray-500 text-sm font-normal">{{ __('GPA Hệ 4') }}</div>
                                                <div class="font-bold text-xl text-fita2">{{ $gpa_4 }}</div>
                                            </div>
                                            <div>
                                                <div class="text-gray-500 text-sm font-normal">{{ __('GPA Hệ 10') }}</div>
                                                <div class="font-bold text-xl text-fita2">{{ $gpa_10 }}</div>
                                            </div>
                                            <div>
                                                <div class="text-gray-500 text-sm font-normal">{{ __('Xếp loại') }}</div>
                                                <x-badge value="{{ $classification }}" class="{{ $badgeClass }} text-white font-bold" />
                                            </div>
                                            <div class="col-span-3">
                                                <div class="flex justify-between text-gray-600 text-sm font-medium">
                                                    <span>{{ __('Tiến độ tín chỉ') }} ({{ $total_credits_earned }} / {{ Subject::formatCredit($activeProgram->total_credits) }})</span>
                                                    <span class="font-bold text-fita2">{{ $progressPercent }}%</span>
                                                </div>
                                                <progress class="progress progress-success w-full h-2.5" value="{{ $progressPercent }}" max="100"></progress>
                                            </div>
                                        </div>

                                        <div x-show="showTargetCalc" x-collapse>
                                            <div class="mt-2 p-3 bg-blue-50 rounded-lg border border-blue-100 text-sm">
                                                <div class="font-semibold text-blue-800 mb-3">
                                                    Mục tiêu tốt nghiệp cho
                                                    <span class="font-bold underline" x-text="remainingCr"></span>
                                                    tín chỉ còn lại:
                                                </div>

                                                <div class="overflow-x-auto rounded-md border border-blue-100 bg-white">
                                                    <table class="table table-md">
                                                        <thead>
                                                        <tr class="bg-blue-50 text-gray-700">
                                                            <th>Mục tiêu</th>
                                                            <th class="text-center">TB hệ 4 cần đạt</th>
                                                            <th class="text-center">Điểm chữ</th>
                                                            <th class="text-center">Dải điểm hệ 10</th>
                                                            {{--                                                            <th>Ghi chú</th>--}}
                                                        </tr>
                                                        </thead>

                                                        <tbody>
                                                        <template x-for="row in targetRows" :key="row.target">
                                                            <tr>
                                                                <td class="font-semibold">
                                                                    <span x-text="row.label"></span>
                                                                    <span class="text-gray-500">
                                                                        (<span x-text="row.target.toFixed(2)"></span>)
                                                                    </span>
                                                                </td>

                                                                <td class="text-center font-bold text-fita2">
                                                                    <span x-text="calcTarget(row.target).gpa4"></span>
                                                                </td>

                                                                <td class="text-center font-bold">
                                                                    <span x-text="calcTarget(row.target).letter"></span>
                                                                </td>

                                                                <td class="text-center">
                                                                    <span x-text="calcTarget(row.target).score10"></span>
                                                                </td>

                                                                {{--                                                                <td class="text-gray-500 text-sm">--}}
                                                                {{--                                                                    <span x-text="calcTarget(row.target).note"></span>--}}
                                                                {{--                                                                </td>--}}
                                                            </tr>
                                                        </template>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <div class="mt-2 text-[16px] text-gray-500 italic leading-tight">
                                                    * Đây là điểm trung bình tối thiểu của phần tín chỉ còn lại.
                                                </div>
                                            </div>
                                        </div>
                                        @if($last_academic_stats_updated_at)
                                            <div class="text-right text-[14px] text-gray-400 italic mt-1">
                                                {{__('Cập nhật lần cuối: ')}} {{ $last_academic_stats_updated_at }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </x-card>

                    @if($currentSemesterTimeline)
                        @php
                            $title = __('Semester timeline') .' - ' . $activeProgram->intake->name;

                            if ($this->majorLabel && $this->specializationLabel) {
                                if ($this->majorLabel === $this->specializationLabel) {
                                    $title .= ' - ' . __('Major') . ' ' . $this->majorLabel;
                                } else {
                                    $title .= ' - ' . __('Major') . ' ' . $this->majorLabel;
                                    if($activeProgram->intake->year_number>68){
                                        $title .= ' - ' . __('Area of specialization') . ' ' . $this->specializationLabel;
                                    }
                                    else{
                                        $title .= ' - ' . __('Specialization') . ' ' . $this->specializationLabel;
                                    }
                                }
                            }
                        @endphp
                        <x-modal wire:model="showSemesterTimelineModal" :title="$title"
                                 separator class="modalDisplaySemesterTimeline"
                        >
                            @php
                                $buildSemesterRows = function ($semester) use ($activeProgram, $studentGrades) {
                                    if (!$semester) {
                                        return collect();
                                    }

                                    return collect($semester->subjects ?? [])
                                        ->sortBy(fn ($subject) => (int) ($subject->pivot->order ?? 0))
                                        ->values()
                                        ->map(function ($subject, $index) use ($activeProgram, $studentGrades) {
                                            $prerequisites = collect($subject->prerequisites ?? [])
                                                ->filter(fn ($prerequisite) => (int) ($prerequisite->pivot->training_program_id ?? 0) === (int) $activeProgram->id)
                                                ->values();

                                            $prerequisiteNames = $prerequisites
                                                ->map(fn ($prerequisite) => $this->localizedName($prerequisite))
                                                ->filter(fn ($name) => trim((string) $name) !== '' && $name !== 'N/A')
                                                ->implode(', ');

                                            $prerequisiteCodes = $prerequisites
                                                ->map(fn ($prerequisite) => (string) $prerequisite->code)
                                                ->filter(fn ($code) => trim($code) !== '')
                                                ->implode(', ');

                                            $equivalentItems = $this->buildEquivalentItemsForSubject($subject)->map(function ($equivalent) use ($studentGrades) {
                                                $gradeInfo = $studentGrades->get($equivalent['id']);
                                                $learningStatus = 'pending';
                                                $finalScore = null;

                                                if ($gradeInfo) {
                                                    $statusValue = (int) $gradeInfo->is_passed;
                                                    $finalScore = $gradeInfo->score_10;

                                                    $learningStatus = match($statusValue) {
                                                        1  => 'passed',
                                                        0  => ($finalScore !== null) ? 'failed' : 'no_grade',
                                                        -1 => 'studying',
                                                        default => 'pending'
                                                    };
                                                }

                                                $equivalent['learning_status'] = $learningStatus;
                                                $equivalent['final_score'] = $finalScore;
                                                return $equivalent;
                                            });

                                            // TÌM ĐIỂM CHÍNH VÀ TƯƠNG ĐƯƠNG CHO MODAL
                                            $gradeInfo = null;
                                            $subjectIdsToCheck = array_merge([$subject->id], $equivalentItems->pluck('id')->toArray());

                                            foreach ($subjectIdsToCheck as $id) {
                                                $grade = $studentGrades->get($id);
                                                if ($grade) {
                                                    if ($grade->is_passed == 1) {
                                                        $gradeInfo = $grade;
                                                        break;
                                                    }
                                                    if (!$gradeInfo) {
                                                        $gradeInfo = $grade;
                                                    } elseif ($grade->is_passed == -1 && $gradeInfo->is_passed == 0) {
                                                        $gradeInfo = $grade;
                                                    }
                                                }
                                            }

                                            $learningStatus = 'pending';
                                            $finalScore = null;
                                            $passedByEquivalentCode = null;

                                            if ($gradeInfo) {
                                                $statusValue = (int) $gradeInfo->is_passed;
                                                $finalScore = $gradeInfo->score_10;

                                                $learningStatus = match($statusValue) {
                                                    1  => 'passed',
                                                    0  => ($finalScore !== null) ? 'failed' : 'no_grade',
                                                    -1 => 'studying',
                                                    default => 'pending'
                                                };

                                                if ($gradeInfo->subject_id !== $subject->id) {
                                                    $equivalentMatch = $equivalentItems->firstWhere('id', $gradeInfo->subject_id);
                                                    if ($equivalentMatch) {
                                                        $passedByEquivalentCode = $equivalentMatch['code'];
                                                    }
                                                }
                                            }

                                            return [
                                                'id' => (int) $subject->id,
                                                'row_index' => $index + 1,
                                                'code' => (string) $subject->code,
                                                'name' => $this->localizedName($subject),
                                                'syllabus_url' => $subject->syllabus_url,
                                                'syllabus_preview_url' => $subject->syllabus_preview_url,
                                                'credits' => (float) ($subject->credits ?? 0),
                                                'theory' => (float) ($subject->credits_theory ?? 0),
                                                'practice' => (float) ($subject->credits_practice ?? 0),
                                                'prerequisite_subjects' => $prerequisiteNames,
                                                'prerequisite_subjects_codes' => $prerequisiteCodes,
                                                'type' => (string) ($subject->pivot->type ?? 'required'),
                                                'note' => (string) ($subject->pivot->notes ?? ''),
                                                'can_expand' => (int) $equivalentItems->count() > 0,
                                                'equivalents_count' => (int) $equivalentItems->count(),
                                                'equivalents' => $equivalentItems,
                                                'learning_status' => $learningStatus,
                                                'final_score' => $finalScore,
                                                'passed_by_equivalent_code' => $passedByEquivalentCode,
                                            ];
                                        });
                                };

                                $currentRows = $buildSemesterRows($currentSemesterTimeline);
                                $nextRows = $buildSemesterRows($nextSemesterTimeline);
                            @endphp

                            <div class="space-y-4 md:text-[16px] py-0 px-1 max-h-[65vh] overflow-y-auto pr-1">
                                <div class="rounded-md border border-gray-200">
                                    <div class="flex flex-wrap items-center justify-between bg-fita2 rounded-t-md px-4 py-2 text-white select-none cursor-pointer" @click="toggle('table-semester-modal-current')">
                                        <div class="flex items-center gap-2">
                                        <span class="tooltip tooltip-right z-100 font-medium" x-bind:data-tip="isOpen('table-semester-modal-current') ? 'Thu gọn' : 'Mở rộng'">
                                            <x-icon
                                                name="o-chevron-down"
                                                class="w-5 h-5 cursor-pointer transition-transform"
                                                x-bind:class="isOpen('table-semester-modal-current') ? 'rotate-180' : ''"
                                            />
                                        </span>
                                            <h3 class="text-md md:text-lg font-semibold select-none">{{ __('Current semester') }}: {{__('Semester')}} {{ data_get($currentSemesterTimeline, 'semester_no') }} {{ data_get($currentSemesterTimeline, 'semester_name')?'('.data_get($currentSemesterTimeline, 'semester_name').')':'' }}</h3>
                                        </div>
                                        <span
                                            class="text-md">{{ count(data_get($currentSemesterTimeline, 'subjects')) }} {{__('subject')}} • {{ Subject::formatCredit(data_get($currentSemesterTimeline, 'total_credits')) }} {{__('Credits ')}}</span>
                                    </div>

                                    <div class="overflow-x-auto rounded border border-base-300 bg-white transition-all duration-300" x-show="isOpen('table-semester-modal-current')" x-collapse>
                                        <x-table
                                            :headers="$this->semesterHeaders()"
                                            :rows="$currentRows"
                                            wire:model="expanded"
                                            expandable
                                            expandable-condition="can_expand"
                                            striped
                                            class="bg-white
                                        md:text-[16px]!
                                        [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left [&_th]:md:text-[16px]!
                                        [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50 [&_th]:whitespace-wrap
                                        [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                                        [&_tbody_tr]:cursor-pointer [&_tbody_tr:hover]:bg-gray-200/50
                                        [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
                                    "
                                        >
                                            @scope('cell_no', $subject)
                                            <span class="select-none">{{ $subject['row_index'] }}</span>
                                            @endscope

                                            @scope('cell_code', $subject)
                                            <span class="font-medium">{!! $this->highlightMatch($subject['code']) !!}</span>
                                            @endscope

                                            @scope('cell_name', $subject)
                                            {!! $this->renderSubjectName($subject) !!}
                                            @endscope

                                            @scope('cell_credits', $subject)
                                            {{ Subject::formatCredit($subject['credits']) }}
                                            @endscope

                                            @scope('cell_theory', $subject)
                                            {{ Subject::formatCredit($subject['theory']) }}
                                            @endscope

                                            @scope('cell_practice', $subject)
                                            {{ Subject::formatCredit($subject['practice']) }}
                                            @endscope

                                            @scope('cell_prerequisite_subjects', $subject)
                                            {!! $this->highlightMatch($subject['prerequisite_subjects']) !!}
                                            @endscope

                                            @scope('cell_prerequisite_subjects_codes', $subject)
                                            {!! $this->highlightMatch($subject['prerequisite_subjects_codes']) !!}
                                            @endscope

                                            @scope('cell_type', $subject)
                                            @php
                                                $typeLabel = match ($subject['type']) {
                                                    'required' => __('Required'),
                                                    'elective' => __('Elective'),
                                                    'pcbb' => __('Hardware Required'),
                                                    default => strtoupper((string) $subject['type']),
                                                };

                                                $typeClass = match ($subject['type']) {
                                                    'required' => 'badge-error',
                                                    'elective' => 'badge-success',
                                                    'pcbb' => 'badge-warning',
                                                    default => 'badge-neutral',
                                                };
                                            @endphp
                                            <x-badge
                                                :value="$typeLabel"
                                                class="{{ $typeClass }} text-white font-semibold badge-md whitespace-nowrap"
                                            />
                                            @endscope

                                            @scope('cell_learning_status', $subject)
                                            @php
                                                $status = $subject['learning_status'] ?? 'pending';
                                            @endphp

                                            @if($status === 'passed')
                                                <x-badge value="{{ __('Đạt') }}" class="badge-success text-white font-semibold badge-md" />
                                            @elseif($status === 'failed')
                                                <x-badge value="{{ __('Trượt') }}" class="badge-error text-white font-semibold badge-md" />
                                            @elseif($status === 'no_grade')
                                                <x-badge value="{{ __('Chưa có') }}" class="badge-error badge-dash font-semibold badge-md whitespace-nowrap" />
                                            @elseif($status === 'studying')
                                                <x-badge value="{{ __('Đang học') }}" class="badge-warning text-white font-semibold badge-md whitespace-nowrap" />
                                            @else
                                                <span class="text-gray-400 text-md italic">{{ __('Chưa học') }}</span>
                                            @endif
                                            @endscope

                                            @scope('cell_final_score', $subject)
                                            <div>
                                                @if($subject['final_score'] !== null)
                                                    <div class="flex flex-col items-center justify-center">
                                                        <span class="font-bold text-slate-700">{{ $subject['final_score'] }}</span>
                                                        @if(!empty($subject['passed_by_equivalent_code']))
                                                            <span class="text-[11px] text-fita2 font-medium whitespace-nowrap mt-1" title="Học thay bằng môn tương đương: {{ $subject['passed_by_equivalent_code'] }}">
                                                                ({{ $subject['passed_by_equivalent_code'] }})
                                                            </span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-gray-300">-</span>
                                                @endif
                                            </div>
                                            @endscope

                                            @scope('cell_note', $subject)
                                            {{ trim((string) ($subject['note'] ?? '')) !== '' ? $subject['note'] : '' }}
                                            @endscope

                                            @scope('expansion', $subject)
                                            @if(($subject['equivalents_count'] ?? 0) > 0)
                                                <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 my-2">
                                                    <div class="font-semibold mb-3">
                                                        {{ __('List of equivalent subjects for') }} <span class="text-fita2 font-bold">{{ $subject['name'] }} - {{ $subject['code'] }}:</span>
                                                    </div>
                                                    <div class="overflow-x-auto rounded border border-base-300 bg-white">
                                                        <table class="table md:text-[14px]">
                                                            <thead>
                                                            <tr>
                                                                <th class="w-10">{{ __('No.') }}</th>
                                                                <th>{{ __('Subject code') }}</th>
                                                                <th>{{ __('Subject name') }}</th>
                                                                <th class="w-16">{{ __('Credits') }}</th>
                                                                <th class="w-16 text-center">{{ __('Điểm') }}</th>
                                                                <th class="w-24 text-center">{{ __('Trạng thái') }}</th>
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            @foreach(($subject['equivalents'] ?? []) as $index => $equivalent)
                                                                <tr>
                                                                    <td>{{ $index + 1 }}</td>
                                                                    <td class="font-semibold">{{ $equivalent['code'] }}</td>
                                                                    <td>{{ $equivalent['name'] }}</td>
                                                                    <td>{{ Subject::formatCredit($equivalent['credits']) }}</td>
                                                                    <td class="text-center font-bold text-slate-700">
                                                                        {{ $equivalent['final_score'] ?? '-' }}
                                                                    </td>
                                                                    <td class="text-center">
                                                                        @if($equivalent['learning_status'] === 'passed')
                                                                            <x-badge value="{{ __('Đạt') }}" class="badge-success text-white font-semibold badge-md" />
                                                                        @elseif($equivalent['learning_status'] === 'failed')
                                                                            <x-badge value="{{ __('Trượt') }}" class="badge-error text-white font-semibold badge-md" />
                                                                        @elseif($equivalent['learning_status'] === 'no_grade')
                                                                            <x-badge value="{{ __('Chưa có') }}" class="badge-error badge-dash font-semibold badge-md whitespace-nowrap" />
                                                                        @elseif($equivalent['learning_status'] === 'studying')
                                                                            <x-badge value="{{ __('Đang học') }}" class="badge-warning text-white font-semibold badge-md whitespace-nowrap" />
                                                                        @else
                                                                            <span class="text-gray-400 text-md italic">{{ __('Chưa học') }}</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-sm text-gray-500 py-2">{{ __('No equivalent subjects.') }}</div>
                                            @endif
                                            @endscope

                                            <x-slot:empty>
                                                <div class="py-3 text-center text-gray-500">{{ __('No subjects.') }}</div>
                                            </x-slot:empty>
                                        </x-table>
                                    </div>
                                </div>

                                <div class="rounded-md border border-gray-200">
                                    @if($nextSemesterTimeline)
                                        <div class="flex flex-wrap items-center justify-between bg-fita2 rounded-t-md px-4 py-2 text-white select-none cursor-pointer" @click="toggle('table-semester-modal-next')">
                                            <div class="flex items-center gap-2">
                                            <span class="tooltip tooltip-right font-medium" x-bind:data-tip="isOpen('table-semester-modal-next') ? 'Thu gọn' : 'Mở rộng'">
                                            <x-icon
                                                name="o-chevron-down"
                                                class="w-5 h-5 cursor-pointer transition-transform"
                                                x-bind:class="isOpen('table-semester-modal-next') ? 'rotate-180' : ''"
                                            />
                                        </span>
                                                <h3 class="text-md md:text-lg cursor-pointer font-semibold select-none">{{ __('Next semester') }}: {{__('Semester')}} {{ data_get($nextSemesterTimeline, 'semester_no') }} {{ data_get($nextSemesterTimeline, 'semester_name')?'('.data_get($nextSemesterTimeline, 'semester_name').')':'' }}</h3>
                                            </div>
                                            <span
                                                class="text-md">{{ count(data_get($nextSemesterTimeline, 'subjects')) }} {{__('subject')}} • {{ Subject::formatCredit(data_get($nextSemesterTimeline, 'total_credits')) }} {{__('Credits ')}}</span>
                                        </div>
                                    @endif
                                    @if($nextSemesterTimeline)
                                        <div class="overflow-x-auto rounded border border-base-300 bg-white transition-all duration-300" x-show="isOpen('table-semester-modal-next')" x-collapse>
                                            <x-table
                                                :headers="$this->semesterHeaders()"
                                                :rows="$nextRows"
                                                wire:model="expanded"
                                                expandable
                                                expandable-condition="can_expand"
                                                striped
                                                class="bg-white
                                            md:text-[16px]!
                                            [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left [&_th]:md:text-[16px]!
                                            [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50 [&_th]:whitespace-wrap
                                            [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                                            [&_tbody_tr]:cursor-pointer [&_tbody_tr:hover]:bg-gray-200/50
                                            [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
                                        "
                                            >
                                                @scope('cell_no', $subject)
                                                <span class="select-none">{{ $subject['row_index'] }}</span>
                                                @endscope

                                                @scope('cell_code', $subject)
                                                <span class="font-medium">{!! $this->highlightMatch($subject['code']) !!}</span>
                                                @endscope

                                                @scope('cell_name', $subject)
                                                {!! $this->renderSubjectName($subject) !!}
                                                @endscope

                                                @scope('cell_credits', $subject)
                                                {{ Subject::formatCredit($subject['credits']) }}
                                                @endscope

                                                @scope('cell_theory', $subject)
                                                {{ Subject::formatCredit($subject['theory']) }}
                                                @endscope

                                                @scope('cell_practice', $subject)
                                                {{ Subject::formatCredit($subject['practice']) }}
                                                @endscope

                                                @scope('cell_prerequisite_subjects', $subject)
                                                {!! $this->highlightMatch($subject['prerequisite_subjects']) !!}
                                                @endscope

                                                @scope('cell_prerequisite_subjects_codes', $subject)
                                                {!! $this->highlightMatch($subject['prerequisite_subjects_codes']) !!}
                                                @endscope

                                                @scope('cell_type', $subject)
                                                @php
                                                    $typeLabel = match ($subject['type']) {
                                                        'required' => __('Required'),
                                                        'elective' => __('Elective'),
                                                        'pcbb' => __('Hardware Required'),
                                                        default => strtoupper((string) $subject['type']),
                                                    };

                                                    $typeClass = match ($subject['type']) {
                                                        'required' => 'badge-error',
                                                        'elective' => 'badge-success',
                                                        'pcbb' => 'badge-warning',
                                                        default => 'badge-neutral',
                                                    };
                                                @endphp
                                                <x-badge
                                                    :value="$typeLabel"
                                                    class="{{ $typeClass }} text-white font-semibold badge-md whitespace-nowrap"
                                                />
                                                @endscope

                                                @scope('cell_note', $subject)
                                                {{ trim((string) ($subject['note'] ?? '')) !== '' ? $subject['note'] : '' }}
                                                @endscope

                                                @scope('cell_learning_status', $subject)
                                                @php
                                                    $status = $subject['learning_status'] ?? 'pending';
                                                @endphp

                                                @if($status === 'passed')
                                                    <x-badge value="{{ __('Đạt') }}" class="badge-success text-white font-semibold badge-md" />
                                                @elseif($status === 'failed')
                                                    <x-badge value="{{ __('Trượt') }}" class="badge-error text-white font-semibold badge-md" />
                                                @elseif($status === 'no_grade')
                                                    <x-badge value="{{ __('Chưa có') }}" class="badge-error badge-dash font-semibold badge-md whitespace-nowrap" />
                                                @elseif($status === 'studying')
                                                    <x-badge value="{{ __('Đang học') }}" class="badge-warning text-white font-semibold badge-md whitespace-nowrap" />
                                                @else
                                                    <span class="text-gray-400 text-md italic">{{ __('Chưa học') }}</span>
                                                @endif
                                                @endscope

                                                @scope('cell_final_score', $subject)
                                                <div>
                                                    @if($subject['final_score'] !== null)
                                                        <div class="flex flex-col items-center justify-center">
                                                            <span class="font-bold text-slate-700">{{ $subject['final_score'] }}</span>
                                                            @if(!empty($subject['passed_by_equivalent_code']))
                                                                <span class="text-[11px] text-fita2 font-medium whitespace-nowrap mt-1" title="Học thay bằng môn tương đương: {{ $subject['passed_by_equivalent_code'] }}">
                                                                ({{ $subject['passed_by_equivalent_code'] }})
                                                            </span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </div>
                                                @endscope

                                                @scope('expansion', $subject)
                                                @if(($subject['equivalents_count'] ?? 0) > 0)
                                                    <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 my-2">
                                                        <div class="font-semibold mb-3">
                                                            {{ __('List of equivalent subjects for') }} <span class="text-fita2 font-bold">{{ $subject['name'] }} - {{ $subject['code'] }}:</span>
                                                        </div>
                                                        <div class="overflow-x-auto rounded border border-base-300 bg-white">
                                                            <table class="table md:text-[14px]">
                                                                <thead>
                                                                <tr>
                                                                    <th class="w-10">{{ __('No.') }}</th>
                                                                    <th>{{ __('Subject code') }}</th>
                                                                    <th>{{ __('Subject name') }}</th>
                                                                    <th class="w-16">{{ __('Credits') }}</th>
                                                                    <th class="w-16 text-center">{{ __('Điểm') }}</th>
                                                                    <th class="w-24 text-center">{{ __('Trạng thái') }}</th>
                                                                </tr>
                                                                </thead>
                                                                <tbody>
                                                                @foreach(($subject['equivalents'] ?? []) as $index => $equivalent)
                                                                    <tr>
                                                                        <td>{{ $index + 1 }}</td>
                                                                        <td class="font-semibold">{{ $equivalent['code'] }}</td>
                                                                        <td>{{ $equivalent['name'] }}</td>
                                                                        <td>{{ Subject::formatCredit($equivalent['credits']) }}</td>
                                                                        <td class="text-center font-bold text-slate-700">
                                                                            {{ $equivalent['final_score'] ?? '-' }}
                                                                        </td>
                                                                        <td class="text-center">
                                                                            @if($equivalent['learning_status'] === 'passed')
                                                                                <x-badge value="{{ __('Đạt') }}" class="badge-success text-white font-semibold badge-md" />
                                                                            @elseif($equivalent['learning_status'] === 'failed')
                                                                                <x-badge value="{{ __('Trượt') }}" class="badge-error text-white font-semibold badge-md" />
                                                                            @elseif($equivalent['learning_status'] === 'no_grade')
                                                                                <x-badge value="{{ __('Chưa có') }}" class="badge-error badge-dash font-semibold badge-md whitespace-nowrap" />
                                                                            @elseif($equivalent['learning_status'] === 'studying')
                                                                                <x-badge value="{{ __('Đang học') }}" class="badge-warning text-white font-semibold badge-md whitespace-nowrap" />
                                                                            @else
                                                                                <span class="text-gray-400 text-md italic">{{ __('Chưa học') }}</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="text-sm text-gray-500 py-2">{{ __('No equivalent subjects.') }}</div>
                                                @endif
                                                @endscope

                                                <x-slot:empty>
                                                    <div class="py-3 text-center text-gray-500">{{ __('No subjects.') }}</div>
                                                </x-slot:empty>
                                            </x-table>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <x-slot:actions>
                                <x-button label="{{ __('Close') }}" class="bg-fita2 text-white" wire:click="$wire.showSemesterTimelineModal = false" />
                            </x-slot:actions>
                        </x-modal>
                    @endif

                    <div class="relative min-h-60">
                        <div
                            wire:loading.delay.short
                            wire:target="programMajorSlug,selectedMajorSlug,intakeId,semesterNo,viewMode,search,typeFilter,statusFilter"
                            class="absolute inset-0 z-20 rounded-md bg-white/65 backdrop-blur-[2px] transition-all duration-300"
                        >
                            <div class="sticky top-[35vh] w-full flex flex-col items-center gap-2 mt-10">
                                <x-loading class="text-primary loading-lg" />
                                <span class="text-sm text-gray-600">{{ __('Loading data...') }}</span>
                            </div>
                        </div>

                        <div
                            wire:loading.class="opacity-60 pointer-events-none"
                            wire:loading.class.remove="opacity-100"
                            wire:target="programMajorSlug,selectedMajorSlug,intakeId,semesterNo,viewMode,search,typeFilter,statusFilter"
                            class="transition-opacity duration-150"
                        >
                            @if($viewMode === 'semester')
                                <div class="space-y-4">
                                    @forelse($semesterBlocks as $semesterBlock)
                                        <x-card shadow class="p-0!">
                                            <div class="flex items-center justify-between bg-fita2 rounded-t-md px-4 py-2 text-white select-none cursor-pointer" @click="toggle('table-semester-{{$semesterBlock['semester_no']}}')">
                                                <div class="flex items-center gap-2">
                                                <span class="tooltip tooltip-top font-medium" x-bind:data-tip="isOpen('table-semester-{{$semesterBlock['semester_no']}}') ? 'Thu gọn' : 'Mở rộng'">
                                                    <x-icon
                                                        name="o-chevron-down"
                                                        class="w-5 h-5 cursor-pointer transition-transform"
                                                        x-bind:class="isOpen('table-semester-{{$semesterBlock['semester_no']}}') ? 'rotate-180' : ''"
                                                    />
                                                </span>
                                                    <h3 class="text-md md:text-lg cursor-pointer font-semibold select-none">{{__('Semester')}} {{ $semesterBlock['semester_no'] }} {{ $semesterBlock['semester_name']? '('.$semesterBlock['semester_name'].')' :'' }}</h3>
                                                </div>
                                                <span
                                                    class="text-md">{{ count($semesterBlock['subjects']) }} {{__('subject')}} • {{ Subject::formatCredit($semesterBlock['total_credits']) }} {{__('Credits ')}}</span>
                                            </div>

                                            @if($semesterBlock['subjects']->isEmpty())
                                                <div class="text-sm text-gray-500 p-4">Không có môn học phù hợp với bộ lọc trong học kỳ này.</div>
                                            @else
                                                <div class="overflow-x-auto transition-all duration-300" x-show="isOpen('table-semester-{{$semesterBlock['semester_no']}}')" x-collapse>
                                                    <x-table
                                                        :headers="$this->semesterHeaders()"
                                                        :rows="$semesterBlock['subjects']"
                                                        wire:model="expanded"
                                                        expandable
                                                        expandable-condition="can_expand"
                                                        striped
                                                        class="bg-white
                                                    md:text-[16px]!
                                                    [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left [&_th]:md:text-[16px]!
                                                    [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50 [&_th]:whitespace-wrap
                                                    [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                                                    [&_tbody_tr]:cursor-pointer [&_tbody_tr:hover]:bg-gray-200/50
                                                    [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
                                                "
                                                    >
                                                        @scope('cell_no', $subject)
                                                        <span class="select-none">{{ $subject['row_index'] }}</span>
                                                        @endscope

                                                        @scope('cell_code', $subject)
                                                        <span class="font-medium">{!! $this->highlightMatch($subject['code']) !!}</span>
                                                        @endscope

                                                        @scope('cell_name', $subject)
                                                        {!! $this->renderSubjectName($subject) !!}
                                                        @endscope

                                                        @scope('cell_credits', $subject)
                                                        {{ Subject::formatCredit($subject['credits']) }}
                                                        @endscope

                                                        @scope('cell_theory', $subject)
                                                        {{ Subject::formatCredit($subject['theory']) }}
                                                        @endscope

                                                        @scope('cell_practice', $subject)
                                                        {{ Subject::formatCredit($subject['practice']) }}
                                                        @endscope

                                                        @scope('cell_prerequisite_subjects', $subject)
                                                        {!! $this->highlightMatch($subject['prerequisite_subjects']) !!}
                                                        @endscope

                                                        @scope('cell_prerequisite_subjects_codes', $subject)
                                                        {!! $this->highlightMatch($subject['prerequisite_subjects_codes']) !!}
                                                        @endscope

                                                        @scope('cell_type', $subject)
                                                        @php
                                                            $typeLabel = match ($subject['type']) {
                                                                'required' => __('Required'),
                                                                'elective' => __('Elective'),
                                                                'pcbb' => __('Hardware Required'),
                                                                default => strtoupper((string) $subject['type']),
                                                            };

                                                            $typeClass = match ($subject['type']) {
                                                                'required' => 'badge-error',
                                                                'elective' => 'badge-success',
                                                                'pcbb' => 'badge-warning',
                                                                default => 'badge-neutral',
                                                            };
                                                        @endphp
                                                        <x-badge
                                                            :value="$typeLabel"
                                                            class="{{ $typeClass }} text-white font-semibold badge-md whitespace-nowrap"
                                                        />
                                                        @endscope

                                                        @scope('cell_learning_status', $subject)
                                                        @php
                                                            $status = $subject['learning_status'] ?? 'pending';
                                                        @endphp

                                                        @if($status === 'passed')
                                                            <x-badge value="{{ __('Đạt') }}" class="badge-success text-white font-semibold badge-md" />
                                                        @elseif($status === 'failed')
                                                            <x-badge value="{{ __('Trượt') }}" class="badge-error text-white font-semibold badge-md" />
                                                        @elseif($status === 'no_grade')
                                                            <x-badge value="{{ __('Chưa có') }}" class="badge-error badge-dash font-semibold badge-md whitespace-nowrap" />
                                                        @elseif($status === 'studying')
                                                            <x-badge value="{{ __('Đang học') }}" class="badge-warning text-white font-semibold badge-md whitespace-nowrap" />
                                                        @else
                                                            <span class="text-gray-400 text-md italic">{{ __('Chưa học') }}</span>
                                                        @endif
                                                        @endscope

                                                        @scope('cell_final_score', $subject)
                                                        <div>
                                                            @if($subject['final_score'] !== null)
                                                                <div class="flex flex-col items-center justify-center">
                                                                    <span class="font-bold text-slate-700">{{ $subject['final_score'] }}</span>
                                                                    @if(!empty($subject['passed_by_equivalent_code']))
                                                                        <span class="text-[11px] text-fita2 font-medium whitespace-nowrap mt-1" title="Học thay bằng môn tương đương: {{ $subject['passed_by_equivalent_code'] }}">
                                                                        ({{ $subject['passed_by_equivalent_code'] }})
                                                                    </span>
                                                                    @endif
                                                                </div>
                                                            @else
                                                                <span class="text-gray-300">-</span>
                                                            @endif
                                                        </div>
                                                        @endscope

                                                        @scope('cell_note', $subject)
                                                        {{ trim((string) ($subject['note'] ?? '')) !== '' ? $subject['note'] : '' }}
                                                        @endscope

                                                        @scope('expansion', $subject)
                                                        @if(($subject['equivalents_count'] ?? 0) > 0)
                                                            <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 my-2">
                                                                <div class="font-semibold mb-3">
                                                                    {{ __('List of equivalent subjects for') }} <span class="text-fita2 font-bold">{{ $subject['name'] }} - {{ $subject['code'] }}:</span>
                                                                </div>
                                                                <div class="overflow-x-auto rounded border border-base-300 bg-white">
                                                                    <table class="table md:text-[14px]">
                                                                        <thead>
                                                                        <tr>
                                                                            <th class="w-10">{{ __('No.') }}</th>
                                                                            <th>{{ __('Subject code') }}</th>
                                                                            <th>{{ __('Subject name') }}</th>
                                                                            <th class="w-16">{{ __('Credits') }}</th>
                                                                            <th class="w-16 text-center">{{ __('Điểm') }}</th>
                                                                            <th class="w-24 text-center">{{ __('Trạng thái') }}</th>
                                                                        </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                        @foreach(($subject['equivalents'] ?? []) as $index => $equivalent)
                                                                            <tr>
                                                                                <td>{{ $index + 1 }}</td>
                                                                                <td class="font-semibold">{{ $equivalent['code'] }}</td>
                                                                                <td>{{ $equivalent['name'] }}</td>
                                                                                <td>{{ Subject::formatCredit($equivalent['credits']) }}</td>
                                                                                <td class="text-center font-bold text-slate-700">
                                                                                    {{ $equivalent['final_score'] ?? '-' }}
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    @if($equivalent['learning_status'] === 'passed')
                                                                                        <x-badge value="{{ __('Đạt') }}" class="badge-success text-white font-semibold badge-md" />
                                                                                    @elseif($equivalent['learning_status'] === 'failed')
                                                                                        <x-badge value="{{ __('Trượt') }}" class="badge-error text-white font-semibold badge-md" />
                                                                                    @elseif($equivalent['learning_status'] === 'no_grade')
                                                                                        <x-badge value="{{ __('Chưa có') }}" class="badge-error badge-dash font-semibold badge-md whitespace-nowrap" />
                                                                                    @elseif($equivalent['learning_status'] === 'studying')
                                                                                        <x-badge value="{{ __('Đang học') }}" class="badge-warning text-white font-semibold badge-md whitespace-nowrap" />
                                                                                    @else
                                                                                        <span class="text-gray-400 text-md italic">{{ __('Chưa học') }}</span>
                                                                                    @endif
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="text-sm text-gray-500 py-2">{{ __('No equivalent subjects.') }}</div>
                                                        @endif
                                                        @endscope
                                                    </x-table>
                                                </div>
                                            @endif
                                        </x-card>
                                    @empty
                                        <x-card shadow>
                                            <div class="text-sm text-gray-500">Không có dữ liệu môn học theo học kỳ.</div>
                                        </x-card>
                                    @endforelse
                                </div>
                            @else
                                <div class="space-y-4">
                                    @forelse($groupBlocks as $groupBlock)
                                        <x-card shadow class="p-0!">
                                            <div class="flex flex-wrap items-center justify-between gap-2 bg-fita2 rounded-t-md px-4 py-2 text-white select-none cursor-pointer" @click="toggle('table-semester-{{$groupBlock['group_name']}}')">
                                                <div class="flex items-center gap-2">
                                                <span class="tooltip tooltip-top font-medium" x-bind:data-tip="isOpen('table-semester-{{$groupBlock['group_name']}}') ? 'Thu gọn' : 'Mở rộng'">
                                                    <x-icon
                                                        name="o-chevron-down"
                                                        class="w-5 h-5 cursor-pointer transition-transform"
                                                        x-bind:class="isOpen('table-semester-{{$groupBlock['group_name']}}') ? 'rotate-180' : ''"
                                                    />
                                                </span>
                                                    <h3 class="text-md md:text-lg font-semibold cursor-pointer select-none">{{ $groupBlock['group_name'] }}</h3>
                                                </div>
                                                <div class="text-md">
                                                    {{ $groupBlock['total_subjects'] }} {{__('subject')}} • {{ Subject::formatCredit($groupBlock['total_credits']) }}
                                                    {{__('Credits ')}}
                                                </div>
                                            </div>

                                            <div class="overflow-x-auto transition-all duration-300" x-show="isOpen('table-semester-{{$groupBlock['group_name']}}')" x-collapse>
                                                <x-table
                                                    :headers="$this->groupHeaders()"
                                                    :rows="$groupBlock['subjects']"
                                                    wire:model="expanded"
                                                    expandable
                                                    expandable-condition="can_expand"
                                                    striped
                                                    @click.stop="if ($event.target.closest('a, button, input, select')) return; const row = $event.target.closest('tr'); if (row && row.dataset.rowId) { toggleExpand(parseInt(row.dataset.rowId)); }"
                                                    class="
                                        bg-white md:text-[16px]!
                                        [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left [&_th]:md:text-[16px]!
                                        [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                                        [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                                        [&_tbody_tr]:cursor-pointer [&_tbody_tr:hover]:bg-gray-200/50
                                        [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
                                    "
                                                >
                                                    @scope('cell_no', $subject)
                                                    {{ $subject['row_index'] }}
                                                    @endscope

                                                    @scope('cell_semester_no', $subject)
                                                    HK {{ $subject['semester_no'] }}
                                                    @endscope

                                                    @scope('cell_code', $subject)
                                                    <span class="font-medium">{!! $this->highlightMatch($subject['code']) !!}</span>
                                                    @endscope

                                                    @scope('cell_name', $subject)
                                                    {!! $this->renderSubjectName($subject) !!}
                                                    @endscope

                                                    @scope('cell_credits', $subject)
                                                    {{ Subject::formatCredit($subject['credits']) }}
                                                    @endscope

                                                    @scope('cell_theory', $subject)
                                                    {{ Subject::formatCredit($subject['theory']) }}
                                                    @endscope

                                                    @scope('cell_practice', $subject)
                                                    {{ Subject::formatCredit($subject['practice']) }}
                                                    @endscope

                                                    @scope('cell_prerequisite_subjects', $subject)
                                                    {!! $this->highlightMatch($subject['prerequisite_subjects']) !!}
                                                    @endscope

                                                    @scope('cell_prerequisite_subjects_codes', $subject)
                                                    {!! $this->highlightMatch($subject['prerequisite_subjects_codes']) !!}
                                                    @endscope

                                                    @scope('cell_type', $subject)
                                                    @php
                                                        $typeLabel = match ($subject['type']) {
                                                            'required' => __('Required'),
                                                            'elective' => __('Elective'),
                                                            'pcbb' => __('Hardware Required'),
                                                            default => strtoupper((string) $subject['type']),
                                                        };

                                                        $typeClass = match ($subject['type']) {
                                                            'required' => 'badge-error',
                                                            'elective' => 'badge-success',
                                                            'pcbb' => 'badge-warning',
                                                            default => 'badge-neutral',
                                                        };
                                                    @endphp
                                                    <x-badge
                                                        :value="$typeLabel"
                                                        class="{{ $typeClass }} text-white font-semibold badge-md whitespace-nowrap"
                                                    />
                                                    @endscope

                                                    @scope('cell_learning_status', $subject)
                                                    @php
                                                        $status = $subject['learning_status'] ?? 'pending';
                                                    @endphp

                                                    @if($status === 'passed')
                                                        <x-badge value="{{ __('Đạt') }}" class="badge-success text-white font-semibold badge-md" />
                                                    @elseif($status === 'failed')
                                                        <x-badge value="{{ __('Trượt') }}" class="badge-error text-white font-semibold badge-md" />
                                                    @elseif($status === 'no_grade')
                                                        <x-badge value="{{ __('Chưa có') }}" class="badge-error badge-dash font-semibold badge-md whitespace-nowrap" />
                                                    @elseif($status === 'studying')
                                                        <x-badge value="{{ __('Đang học') }}" class="badge-warning text-white font-semibold badge-md whitespace-nowrap" />
                                                    @else
                                                        <span class="text-gray-400 text-md italic">{{ __('Chưa học') }}</span>
                                                    @endif
                                                    @endscope

                                                    @scope('cell_final_score', $subject)
                                                    <div>
                                                        @if($subject['final_score'] !== null)
                                                            <div class="flex flex-col items-center justify-center">
                                                                <span class="font-bold text-slate-700">{{ $subject['final_score'] }}</span>
                                                                @if(!empty($subject['passed_by_equivalent_code']))
                                                                    <span class="text-[11px] text-fita2 font-medium whitespace-nowrap mt-1" title="Học thay bằng môn tương đương: {{ $subject['passed_by_equivalent_code'] }}">
                                                                        ({{ $subject['passed_by_equivalent_code'] }})
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <span class="text-gray-300">-</span>
                                                        @endif
                                                    </div>
                                                    @endscope

                                                    @scope('cell_note', $subject)
                                                    {{ trim((string) ($subject['note'] ?? '')) !== '' ? $subject['note'] : '' }}
                                                    @endscope

                                                    @scope('expansion', $subject)
                                                    @if(($subject['equivalents_count'] ?? 0) > 0)
                                                        <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 my-2">
                                                            <div class="font-semibold text-primary mb-3">
                                                                {{ __('Equivalent subjects for') }} {{ $subject['code'] }} - {{ $subject['name'] }}
                                                            </div>
                                                            <div class="overflow-x-auto rounded border border-base-300 bg-white">
                                                                <table class="table md:text-[14px]">
                                                                    <thead>
                                                                    <tr>
                                                                        <th class="w-10">{{ __('No.') }}</th>
                                                                        <th>{{ __('Subject code') }}</th>
                                                                        <th>{{ __('Subject name') }}</th>
                                                                        <th class="w-16">{{ __('Credits') }}</th>
                                                                        <th class="w-16 text-center">{{ __('Điểm') }}</th>
                                                                        <th class="w-24 text-center">{{ __('Trạng thái') }}</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    @foreach(($subject['equivalents'] ?? []) as $index => $equivalent)
                                                                        <tr>
                                                                            <td>{{ $index + 1 }}</td>
                                                                            <td class="font-semibold">{{ $equivalent['code'] }}</td>
                                                                            <td>{{ $equivalent['name'] }}</td>
                                                                            <td>{{ Subject::formatCredit($equivalent['credits']) }}</td>
                                                                            <td class="text-center font-bold text-slate-700">
                                                                                {{ $equivalent['final_score'] ?? '-' }}
                                                                            </td>
                                                                            <td class="text-center">
                                                                                @if($equivalent['learning_status'] === 'passed')
                                                                                    <x-badge value="{{ __('Đạt') }}" class="badge-success text-white font-semibold badge-md" />
                                                                                @elseif($equivalent['learning_status'] === 'failed')
                                                                                    <x-badge value="{{ __('Trượt') }}" class="badge-error text-white font-semibold badge-md" />
                                                                                @elseif($equivalent['learning_status'] === 'no_grade')
                                                                                    <x-badge value="{{ __('Chưa có') }}" class="badge-error badge-dash font-semibold badge-md whitespace-nowrap" />
                                                                                @elseif($equivalent['learning_status'] === 'studying')
                                                                                    <x-badge value="{{ __('Đang học') }}" class="badge-warning text-white font-semibold badge-md whitespace-nowrap" />
                                                                                @else
                                                                                    <span class="text-gray-400 text-md italic">{{ __('Chưa học') }}</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="text-sm text-gray-500 py-2">{{ __('No equivalent subjects.') }}</div>
                                                    @endif
                                                    @endscope
                                                </x-table>
                                            </div>
                                        </x-card>
                                    @empty
                                        <x-card shadow>
                                            <div class="text-sm text-gray-500">Không có dữ liệu môn học theo nhóm môn.</div>
                                        </x-card>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    </div>

                </div> @endif
        </div>
    </div>
    <div id="mary-global-tooltip" class="fixed hidden pointer-events-none" style="z-index: 99999;">
        <div class="relative bg-black text-white text-sm font-medium px-3 py-1.5 rounded-md shadow-lg">
            <span id="mary-tooltip-text"></span>
            <div class="absolute left-1/2 -translate-x-1/2 w-3 h-3 bg-black rotate-45 rounded-sm" style="bottom: -5px;"></div>
        </div>
    </div>

    <script>
        if (!window.maryTooltipInitialized) {
            window.maryTooltipInitialized = true;
            window.maryNoteMergeInitialized = true;
            window.maryNoteMergeBusy = false;
            window.maryNoteMergeScheduled = false;

            const normalizeNoteText = (value) => (value || '').replace(/\s+/g, ' ').trim().toLowerCase();
            const getTableCells = (row) => Array.from(row.children).filter((cell) => cell.matches('td, th'));

            const attachNoteMergeObserver = () => {
                const root = document.querySelector('.ctdt');
                if (!root) return;

                if (window.maryNoteMergeObservedRoot === root) return;

                if (window.maryNoteMergeObserver) {
                    window.maryNoteMergeObserver.disconnect();
                }

                window.maryNoteMergeObservedRoot = root;
                window.maryNoteMergeObserver = new MutationObserver(() => scheduleNoteCellMerge());
                window.maryNoteMergeObserver.observe(root, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class'],
                });
            };

            const getDirectBodyRows = (table) => Array.from(table.tBodies || []).flatMap((tbody) => Array.from(tbody.rows || []));

            const hasVisibleExpansionRow = (row) => {
                const nextRow = row.nextElementSibling;

                return !!(
                    nextRow
                    && nextRow.tagName === 'TR'
                    && !nextRow.classList.contains('hidden')
                    && nextRow.querySelector('td[colspan]')
                );
            };

            const mergeAdjacentNoteCells = () => {
                const root = document.querySelector('.ctdt');
                if (!root) return;

                window.maryNoteMergeBusy = true;

                try {
                    Array.from(root.querySelectorAll('table')).forEach((table) => {
                        const headerRow = table.tHead?.rows?.[0];
                        const headers = headerRow ? Array.from(headerRow.cells || []) : [];
                        const noteIndex = headers.findIndex((header) => {
                            const text = normalizeNoteText(header.textContent);
                            return text === 'note' || text === 'ghi chú' || text === 'ghi chu' || text.includes('note');
                        });

                        if (noteIndex < 0) return;

                        const rows = getDirectBodyRows(table)
                            .filter((row) => getTableCells(row)[noteIndex]);

                        if (rows.length < 2) return;

                        rows.forEach((row) => {
                            const cell = getTableCells(row)[noteIndex];
                            if (!cell) return;

                            cell.style.display = '';
                            cell.rowSpan = 1;
                            cell.removeAttribute('data-note-merged');
                        });

                        let currentRows = [];
                        let currentValue = null;

                        const flush = () => {
                            if (currentRows.length <= 1) {
                                currentRows = [];
                                currentValue = null;
                                return;
                            }

                            const firstCell = getTableCells(currentRows[0])[noteIndex];
                            if (firstCell) {
                                firstCell.rowSpan = currentRows.length;
                                firstCell.setAttribute('data-note-merged', '1');
                            }

                            currentRows.slice(1).forEach((row) => {
                                const cell = getTableCells(row)[noteIndex];
                                if (!cell) return;

                                cell.style.display = 'none';
                                cell.setAttribute('data-note-merged', '1');
                            });

                            currentRows = [];
                            currentValue = null;
                        };

                        rows.forEach((row) => {
                            const cell = getTableCells(row)[noteIndex];
                            if (!cell) {
                                flush();
                                return;
                            }

                            const value = normalizeNoteText(cell.textContent);
                            if (value === '') {
                                flush();
                                return;
                            }

                            if (hasVisibleExpansionRow(row)) {
                                flush();
                                return;
                            }

                            if (currentRows.length === 0) {
                                currentRows = [row];
                                currentValue = value;
                                return;
                            }

                            if (value === currentValue) {
                                currentRows.push(row);
                                return;
                            }

                            flush();
                            currentRows = [row];
                            currentValue = value;
                        });

                        flush();
                    });
                } finally {
                    window.maryNoteMergeBusy = false;
                }
            };

            const scheduleNoteCellMerge = () => {
                attachNoteMergeObserver();

                if (window.maryNoteMergeBusy || window.maryNoteMergeScheduled) return;

                window.maryNoteMergeScheduled = true;
                requestAnimationFrame(() => {
                    window.maryNoteMergeScheduled = false;
                    mergeAdjacentNoteCells();
                });
            };

            document.addEventListener('mouseover', (e) => {
                const targetSvg = e.target.closest('svg');
                if (!targetSvg) return;

                const clickAttr = targetSvg.getAttribute('@click') || targetSvg.getAttribute('x-on:click') || '';
                if (!clickAttr.includes('toggleExpand')) return;

                const rect = targetSvg.getBoundingClientRect();
                const isClosed = (targetSvg.getAttribute('class') || '').includes('rotate');

                const tooltipElement = document.getElementById('mary-global-tooltip');
                const textElement = document.getElementById('mary-tooltip-text');

                if (tooltipElement && textElement) {
                    textElement.textContent = isClosed ? 'Các học phần tương đương' : 'Thu gọn';
                    tooltipElement.style.left = `${rect.left + (rect.width / 2)}px`;
                    tooltipElement.style.top = `${rect.top - 10}px`;
                    tooltipElement.style.transform = 'translate(-50%, -100%)';
                    tooltipElement.classList.remove('hidden');
                }
            });

            document.addEventListener('mouseout', (e) => {
                const targetSvg = e.target.closest('svg');
                if (!targetSvg) return;

                const clickAttr = targetSvg.getAttribute('@click') || targetSvg.getAttribute('x-on:click') || '';
                if (!clickAttr.includes('toggleExpand')) return;

                const tooltipElement = document.getElementById('mary-global-tooltip');
                if (tooltipElement) {
                    tooltipElement.classList.add('hidden');
                    tooltipElement.style.left = '-9999px';
                }
            });

            document.addEventListener('livewire:init', scheduleNoteCellMerge);
            document.addEventListener('livewire:navigated', scheduleNoteCellMerge);
            document.addEventListener('livewire:morph.updated', scheduleNoteCellMerge);
            window.addEventListener('load', scheduleNoteCellMerge);

            scheduleNoteCellMerge();
        }
    </script>
</div>
