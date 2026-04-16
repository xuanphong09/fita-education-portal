<?php

use App\Models\Major;
use App\Models\ProgramMajor;
use App\Models\Subject;
use App\Models\TrainingProgram;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.client')]
class extends Component {
    public ?Major $major = null;

    #[Url(as: 'nganh')]
    public ?string $programMajorSlug = null;

    #[Url(as: 'chuyen-nganh')]
    public ?string $selectedMajorSlug = null;

    #[Url(as: 'phien-ban')]
    public ?string $version = null;

    #[Url(as: 'hoc-ky')]
    public ?int $semesterNo = null;

    #[Url(as: 'kieu')]
    public string $viewMode = 'semester';

    #[Url(as: 'tim')]
    public string $search = '';

    #[Url(as: 'loai')]
    public string $typeFilter = '';

    public array $expanded = [];
    public bool $showSemesterTimelineModal = true;
    public bool $pendingOpenSemesterTimelineModal = true;

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

    public function mount(): void
    {
        if ($this->selectedMajorSlug) {
            $selectedMajor = Major::query()->where('slug', $this->selectedMajorSlug)->first();
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

        $this->version = null;
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
        $this->version = null;
        $this->semesterNo = null;
        $this->expanded = [];
        $this->showSemesterTimelineModal = false;
        $this->pendingOpenSemesterTimelineModal = true;

        $this->redirectToCanonicalMajorUrl($selectedMajor);
    }

    protected function redirectToCanonicalMajorUrl(Major $major): void
    {
        if ((string) request()->query('chuyen-nganh', '') === (string) $major->slug) {
            return;
        }

        $params = [
            'chuyen-nganh' => (string) $major->slug,
            'nganh' => $major->programMajor?->slug,
            'phien-ban' => $this->version,
            'hoc-ky' => $this->semesterNo,
            'kieu' => $this->viewMode !== 'semester' ? $this->viewMode : null,
            'tim' => trim($this->search) !== '' ? $this->search : null,
            'loai' => $this->typeFilter !== '' ? $this->typeFilter : null,
        ];

        $this->redirectRoute('client.training-programs.major', array_filter($params, fn ($value) => $value !== null), navigate: true);
    }

    public function updatedVersion(): void
    {
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

    public function semesterHeaders(): array
    {
        return [
            ['key' => 'no', 'label' => __('No.'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'code', 'label' => __('Subject code'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'name', 'label' => __('Subject name'), 'sortable' => false, 'class' => 'w-70'],
            ['key' => 'credits', 'label' => __('Credits'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'theory', 'label' => __('Theory'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'practice', 'label' => __('Practice'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'prerequisite_subjects', 'label' => __('Prerequisite subjects'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'prerequisite_subjects_codes', 'label' => __('PS codes'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'type', 'label' => __('Type'), 'sortable' => false,],
            ['key' => 'note', 'label' => __('Note'), 'sortable' => false],
        ];
    }

    public function groupHeaders(): array
    {
        return [
            ['key' => 'no', 'label' => __('No.'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'semester_no', 'label' => __('Semester'), 'sortable' => false],
            ['key' => 'code', 'label' => __('Subject code'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'name', 'label' => __('Subject name'), 'sortable' => false, 'class' => 'w-70'],
            ['key' => 'credits', 'label' => __('Credits'), 'sortable' => false, 'class' => 'w-1'],
            ['key' => 'theory', 'label' => __('Theory'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'practice', 'label' => __('Practice'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'prerequisite_subjects', 'label' => __('Prerequisite subjects'), 'sortable' => false, 'class' => 'w-16'],
            ['key' => 'prerequisite_subjects_codes', 'label' => __('PS codes'), 'sortable' => false, 'class' => 'w-6'],
            ['key' => 'type', 'label' => __('Type'), 'sortable' => false,],
            ['key' => 'note', 'label' => __('Note'), 'sortable' => false],
        ];
    }

    public function with(): array
    {
        $programMajorOptions = ProgramMajor::query()
            ->where('is_active', true)
            ->whereHas('majors.trainingPrograms', function ($query) {
                $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->ordered()
            ->get();

        $majorOptions = Major::query()
            ->where('is_active', true)
            ->when($this->programMajorSlug, function ($query) {
                $query->whereHas('programMajor', fn ($programMajorQuery) => $programMajorQuery->where('slug', $this->programMajorSlug));
            })
            ->whereHas('trainingPrograms', function ($query) {
                $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->ordered()
            ->get(['id', 'name', 'slug', 'program_major_id']);

        $selectedMajor = $this->selectedMajorSlug
            ? $majorOptions->firstWhere('slug', $this->selectedMajorSlug)
            : null;

        $this->major = $selectedMajor;

        if (!$this->major) {
            return [
                'programs' => collect(),
                'versionOptions' => [],
                'activeProgram' => null,
                'semesterBlocks' => collect(),
                'groupBlocks' => collect(),
                'currentSemesterTimeline' => null,
                'nextSemesterTimeline' => null,
                'programMajorOptions' => $programMajorOptions,
                'majorOptions' => $majorOptions,
            ];
        }

        $normalizedKeyword = $this->normalizeSearchText($this->search);

        $majorId = (int) $this->major->id;

        $programs = TrainingProgram::query()
            ->where('major_id', $majorId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['major', 'intake'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $versionOptions = $programs
            ->pluck('version')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->map(fn ($value) => ['code' => (string) $value, 'name' => (string) $value])
            ->toArray();

        $version_tmp = $programs
            ->pluck('version')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->values();

        if ($this->version && !$version_tmp->contains($this->version)) {
            $this->version = null;
            $this->semesterNo = null;
        }

        $activeProgram = null;
        $semesterBlocks = collect();
        $groupBlocks = collect();
        $currentSemesterTimeline = null;
        $nextSemesterTimeline = null;

        if ($this->version) {
            $activeProgram = TrainingProgram::query()
                ->where('major_id', $majorId)
                ->where('version', $this->version)
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->with([
                    'major',
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
                ->first();
        }

        if ($activeProgram) {
            $today = now()->startOfDay();

            $timelineSemesters = $activeProgram->semesters
                ->filter(function ($semester) {
                    if (!$this->formatSemesterTimeline($semester)) {
                        return false;
                    }

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

            // Open modal only after the newly selected data has been fully recalculated.
            if ($this->pendingOpenSemesterTimelineModal) {
                $this->showSemesterTimelineModal = (bool) $currentSemesterTimeline;
                $this->pendingOpenSemesterTimelineModal = false;
            }

            $semesterCollection = $activeProgram->semesters;

            if ($this->semesterNo) {
                $semesterCollection = $semesterCollection
                    ->where('semester_no', $this->semesterNo)
                    ->values();
            }

            $semesterBlocks = $semesterCollection
                ->map(function ($semester) use ($activeProgram, $normalizedKeyword) {
                    $subjects = $semester->subjects
                        ->map(function ($subject) use ($semester, $activeProgram) {
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

                            $equivalents = $subject->equivalents
                                ->filter(fn ($equivalent) => (int) ($equivalent->pivot->training_program_id ?? 0) === (int) $activeProgram->id)
                                ->values();

                            $equivalentItems = $equivalents
                                ->map(fn ($equivalent) => [
                                    'id' => (int) $equivalent->id,
                                    'code' => (string) $equivalent->code,
                                    'name' => $this->localizedName($equivalent),
                                    'credits' => (float) ($equivalent->credits ?? 0),
                                    'credits_theory' => (float) ($equivalent->credits_theory ?? 0),
                                    'credits_practice' => (float) ($equivalent->credits_practice ?? 0),
                                ])
                                ->values();

                            $subjectNameVi = trim((string) $subject->getTranslation('name', 'vi', false));
                            $subjectNameEn = trim((string) $subject->getTranslation('name', 'en', false));

                            return [
                                'id' => (int) $subject->id,
                                'code' => (string) $subject->code,
                                'name' => $this->localizedName($subject),
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
                            ];
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
                        'total_credits' => (float) $subjects->sum('credits'),
                        'subjects' => $subjects,
                    ];
                })
                ->values();

            if ($normalizedKeyword !== '') {
                $semesterBlocks = $semesterBlocks
                    ->filter(fn ($block) => $block['subjects']->isNotEmpty())
                    ->values();
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
            'versionOptions' => $versionOptions,
            'activeProgram' => $activeProgram,
            'semesterBlocks' => $semesterBlocks,
            'groupBlocks' => $groupBlocks,
            'currentSemesterTimeline' => $currentSemesterTimeline,
            'nextSemesterTimeline' => $nextSemesterTimeline,
            'programMajorOptions' => $programMajorOptions,
            'majorOptions' => $majorOptions,
        ];
    }
};
?>

<div>
    <x-slot:title>{{ __('Training Programs') }} - {{ __('Specialized') }} {{ $this->specializationLabel }}</x-slot:title>

{{--    <x-slot:breadcrumb>--}}
{{--        <a href="{{ route('client.training-programs.index') }}" class="hover:text-fita whitespace-nowrap">{{ __('Training Programs') }}</a>--}}
{{--        <span><x-icon name="s-chevron-right" class="w-4 h-4" /></span>--}}
{{--        <span class="whitespace-nowrap line-clamp-1">{{ $this->majorLabel }}</span>--}}
{{--    </x-slot:breadcrumb>--}}

{{--    <x-slot:titleBreadcrumb>--}}
{{--        <span class="text-[35px]/[44px]">CTĐT chuyên ngành {{ $this->majorLabel }}</span>--}}
{{--    </x-slot:titleBreadcrumb>--}}
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
                    {{ $this->specializationLabel ? __('Specialized training program ') . ' ' . $this->specializationLabel : __('Training Programs') }}
                </h2>
                <div class="flex items-center gap-1 text-gray-500 justify-center w-full">
                    <a href="{{route('client.home')}}" wire:navigate class="whitespace-nowrap hover:text-fita font-semibold text-slate-700">{{__('Home page')}}</a>
                    <span><x-icon name="s-chevron-right" class="w-4 h-4" /></span>
                    <span class="whitespace-nowrap line-clamp-1">{{ $this->specializationLabel ?: __('Training Programs') }}</span>
                </div>
            </div>

            <h2 class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[12vw]/[12vw] md:text-[8vw]/[8vw] tracking-[0.15em] lg:tracking-[0.3em] text-fita opacity-[0.07] font-extrabold pointer-events-none whitespace-nowrap z-10 w-full text-center">
                FITA - VNUA
            </h2>

        </div>
    </div>
    <div class="container mx-auto px-4 py-8">
        <div class="space-y-4">
            <x-card shadow>
                <div class="grid grid-cols-5 gap-4">
                    <div class="w-full md:col-span-2 col-span-5">
                        <x-select
                            label="{{__('Major')}}"
                            wire:model.live="programMajorSlug"
                            placeholder="{{ __('No major selected') }}"
                            :options="$programMajorOptions->map(fn ($item) => [
                            'value' => $item->slug,
                            'label' => $this->localizedName($item),
                        ])->values()->toArray()"
                            option-value="value"
                            option-label="label"
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
                            :disabled="!$this->programMajorSlug || $majorOptions->isEmpty()"
                        />
                    </div>

                    <div class="w-full md:col-span-1 col-span-5">
                        <x-select
                            label="{{__('Intake')}}"
                            wire:model.live.debounce.300ms="version"
                            :options="$versionOptions"
                            option-value="code"
                            option-label="code"
                            placeholder="{{ $this->selectedMajorSlug ? __('No intake selected') : __('Select specialization first') }}"
                            :disabled="!$this->selectedMajorSlug || empty($versionOptions)"
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
                        @if(!$this->programMajorSlug || !$this->selectedMajorSlug || !$this->version)
                            {{ __('Please select major, specialization, and intake to view the training program.') }}
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
                    $majorCode = $activeProgram->major?->programMajor?->code ?: 'N/A';
                @endphp

                <x-card shadow>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-2xl font-bold">{{ $programTitle }}</h2>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 md:text-[16px] text-gray-800">
                                <div><span class="font-medium">{{__('Level of Education')}}:</span> {{ $programLevel }}</div>
                                <div><span class="font-medium">{{__('Code')}}:</span> {{ $majorCode }}</div>
                                <div><span class="font-medium">{{__('Type of Education')}}:</span> {{ $programType }}</div>
                                <div><span class="font-medium">{{__('Duration time')}}:</span> {{ $programDuration }}</div>
                                <div class="sm:col-span-2"><span class="font-medium">{{__('Language')}}:</span> {{ $programLanguage }}</div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 md:text-[16px]">
                            <x-badge value="{{ $activeProgram->version }}" class="badge-md bg-fita2 text-white" />
                            <x-badge value="{{ Subject::formatCredit($activeProgram->total_credits) }} {{__('Credits ')}}" class="badge-outline badge-md" />
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
                </x-card>

                @if($currentSemesterTimeline)
                    @php
                        $title = __('Semester timeline');

                        if ($this->majorLabel && $this->specializationLabel) {
                            if ($this->majorLabel === $this->specializationLabel) {
                                $title .= ' - ' . __('Major/Specialized') . ' ' . $this->majorLabel;
                            } else {
                                $title .= ' - ' . __('Major') . ' ' . $this->majorLabel;
                                $title .= ' - ' . __('Specialized') . ' ' . $this->specializationLabel;
                            }
                        }
                    @endphp
                    <x-modal wire:model="showSemesterTimelineModal" :title="$title"
                         separator class="modalDisplaySemesterTimeline"
                    >
                        @php
                            $buildSemesterRows = function ($semester) use ($activeProgram) {
                                if (!$semester) {
                                    return collect();
                                }

                                return collect($semester->subjects ?? [])
                                    ->sortBy(fn ($subject) => (int) ($subject->pivot->order ?? 0))
                                    ->values()
                                    ->map(function ($subject, $index) use ($activeProgram) {
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

                                        $equivalents = collect($subject->equivalents ?? [])
                                            ->filter(fn ($equivalent) => (int) ($equivalent->pivot->training_program_id ?? 0) === (int) $activeProgram->id)
                                            ->values();

                                        $equivalentItems = $equivalents
                                            ->map(fn ($equivalent) => [
                                                'id' => (int) $equivalent->id,
                                                'code' => (string) $equivalent->code,
                                                'name' => $this->localizedName($equivalent),
                                                'credits' => (float) ($equivalent->credits ?? 0),
                                                'credits_theory' => (float) ($equivalent->credits_theory ?? 0),
                                                'credits_practice' => (float) ($equivalent->credits_practice ?? 0),
                                            ])
                                            ->values();

                                        return [
                                            'id' => (int) $subject->id,
                                            'row_index' => $index + 1,
                                            'code' => (string) $subject->code,
                                            'name' => $this->localizedName($subject),
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
                                        ];
                                    });
                            };

                            $currentRows = $buildSemesterRows($currentSemesterTimeline);
                            $nextRows = $buildSemesterRows($nextSemesterTimeline);
                        @endphp

                        <div class="space-y-4 md:text-[16px] py-0 px-1 max-h-[70vh] overflow-y-auto pr-1">
                            <div class="rounded-md bg-fita2">
                                <div class="flex flex-wrap items-center justify-between mb-3 bg-fita2 rounded-t-md px-4 pt-2 text-white">
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ __('Current semester') }}: {{__('Semester')}} {{ data_get($currentSemesterTimeline, 'semester_no') }} {{ data_get($currentSemesterTimeline, 'semester_name')?'('.data_get($currentSemesterTimeline, 'semester_name').')':'' }}</h3>
{{--                                        <div class="text-sm text-white/90">{{ $this->formatSemesterTimeline($currentSemesterTimeline) ?: __('') }}</div>--}}
                                    </div>
                                    <span
                                        class="text-md">{{ count(data_get($currentSemesterTimeline, 'subjects')) }} {{__('subject')}} • {{ Subject::formatCredit(data_get($currentSemesterTimeline, 'total_credits')) }} {{__('Credits ')}}</span>
                                </div>

                                <div class="mt-3 overflow-x-auto rounded border border-primary/20 bg-white">
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
                                        {!! $this->highlightMatch($subject['name']) !!}
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
                                        {{ trim((string) ($subject['note'] ?? '')) !== '' ? $subject['note'] : '—' }}
                                        @endscope

                                        @scope('expansion', $subject)
                                        @if(($subject['equivalents_count'] ?? 0) > 0)
                                            <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 my-2">
                                                <div class="font-semibold mb-3">
                                                    {{ __('List of equivalent subjects for') }} <span class="text-fita2 font-bold">{{ $subject['name'] }} - {{ $subject['code'] }}:</span>
                                                </div>
                                                <div class="overflow-x-auto rounded border border-base-300 bg-white">
                                                    <table class="table md:text-[16px]">
                                                        <thead>
                                                        <tr>
                                                            <th class="w-14">{{ __('No.') }}</th>
                                                            <th>{{ __('Subject code') }}</th>
                                                            <th>{{ __('Subject name') }}</th>
                                                            <th class="w-24">{{ __('Credits') }}</th>
                                                            <th class="w-20">{{ __('Theory') }}</th>
                                                            <th class="w-20">{{ __('Practice') }}</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        @foreach(($subject['equivalents'] ?? []) as $index => $equivalent)
                                                            <tr>
                                                                <td>{{ $index + 1 }}</td>
                                                                <td class="font-semibold">{{ $equivalent['code'] }}</td>
                                                                <td>{{ $equivalent['name'] }}</td>
                                                                <td>{{ Subject::formatCredit($equivalent['credits']) }}</td>
                                                                <td>{{ Subject::formatCredit($equivalent['credits_theory'] ?? 0) }}</td>
                                                                <td>{{ Subject::formatCredit($equivalent['credits_practice'] ?? 0) }}</td>
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

                            <div class="rounded-md border border-gray-200 bg-white">
                                @if($nextSemesterTimeline)
                                    <div class="flex flex-wrap items-center justify-between mb-3 bg-fita2 rounded-t-md px-4 py-2 text-white">
                                        <div>
                                            <h3 class="text-lg font-semibold">{{ __('Next semester') }}: {{__('Semester')}} {{ data_get($nextSemesterTimeline, 'semester_no') }} {{ data_get($nextSemesterTimeline, 'semester_name')?'('.data_get($nextSemesterTimeline, 'semester_name').')':'' }}</h3>
{{--                                            <div class="text-sm text-white/90">{{ $this->formatSemesterTimeline($nextSemesterTimeline) ?: __('') }}</div>--}}
                                        </div>
                                        <span
                                            class="text-md">{{ count(data_get($nextSemesterTimeline, 'subjects')) }} {{__('subject')}} • {{ Subject::formatCredit(data_get($currentSemesterTimeline, 'total_credits')) }} {{__('Credits ')}}</span>
                                    </div>
                                @endif
                                @if($nextSemesterTimeline)
                                    <div class="mt-3 overflow-x-auto rounded border border-base-300 bg-white">
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
                                            {!! $this->highlightMatch($subject['name']) !!}
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
                                            {{ trim((string) ($subject['note'] ?? '')) !== '' ? $subject['note'] : '—' }}
                                            @endscope

                                            @scope('expansion', $subject)
                                            @if(($subject['equivalents_count'] ?? 0) > 0)
                                                <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 my-2">
                                                    <div class="font-semibold mb-3">
                                                        {{ __('List of equivalent subjects for') }} <span class="text-fita2 font-bold">{{ $subject['name'] }} - {{ $subject['code'] }}:</span>
                                                    </div>
                                                    <div class="overflow-x-auto rounded border border-base-300 bg-white">
                                                        <table class="table md:text-[16px]">
                                                            <thead>
                                                            <tr>
                                                                <th class="w-14">{{ __('No.') }}</th>
                                                                <th>{{ __('Subject code') }}</th>
                                                                <th>{{ __('Subject name') }}</th>
                                                                <th class="w-24">{{ __('Credits') }}</th>
                                                                <th class="w-20">{{ __('Theory') }}</th>
                                                                <th class="w-20">{{ __('Practice') }}</th>
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            @foreach(($subject['equivalents'] ?? []) as $index => $equivalent)
                                                                <tr>
                                                                    <td>{{ $index + 1 }}</td>
                                                                    <td class="font-semibold">{{ $equivalent['code'] }}</td>
                                                                    <td>{{ $equivalent['name'] }}</td>
                                                                    <td>{{ Subject::formatCredit($equivalent['credits']) }}</td>
                                                                    <td>{{ Subject::formatCredit($equivalent['credits_theory'] ?? 0) }}</td>
                                                                    <td>{{ Subject::formatCredit($equivalent['credits_practice'] ?? 0) }}</td>
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
                        wire:target="programMajorSlug,selectedMajorSlug,version,semesterNo,viewMode,search,typeFilter"
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
                        wire:target="programMajorSlug,selectedMajorSlug,version,semesterNo,viewMode,search,typeFilter"
                        class="transition-opacity duration-150"
                    >
                        @if($viewMode === 'semester')
                            <div class="space-y-4">
                                @forelse($semesterBlocks as $semesterBlock)
                                    <x-card shadow class="p-0!">
                                        <div class="flex items-center justify-between mb-3 bg-fita2 rounded-t-md px-4 py-2 text-white">
                                            <div>
                                                <h3 class="text-lg font-semibold">{{__('Semester')}} {{ $semesterBlock['semester_no'] }}</h3>
                                                @if(!empty($semesterBlock['timeline']))
                                                    <div class="text-sm text-white/90">{{ $semesterBlock['timeline'] }}</div>
                                                @endif
                                            </div>
                                            <span
                                                class="text-md">{{ count($semesterBlock['subjects']) }} {{__('subject')}} • {{ Subject::formatCredit($semesterBlock['total_credits']) }} {{__('Credits ')}}</span>
                                        </div>

                                        @if($semesterBlock['subjects']->isEmpty())
                                            <div class="text-sm text-gray-500">Không có môn học trong học kỳ này.</div>
                                        @else
                                            <div class="overflow-x-auto">
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
                                                    {!! $this->highlightMatch($subject['name']) !!}
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

                                                    @scope('expansion', $subject)
                                                    @if(($subject['equivalents_count'] ?? 0) > 0)
                                                        <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 my-2">
                                                            <div class="font-semibold mb-3">
                                                                {{ __('List of equivalent subjects for') }} <span class="text-fita2 font-bold">{{ $subject['name'] }} - {{ $subject['code'] }}:</span>
                                                            </div>
                                                            <div class="overflow-x-auto rounded border border-base-300 bg-white">
                                                                <table class="table md:text-[16px]">
                                                                    <thead>
                                                                    <tr>
                                                                        <th class="w-14">{{ __('No.') }}</th>
                                                                        <th>{{ __('Subject code') }}</th>
                                                                        <th>{{ __('Subject name') }}</th>
                                                                        <th class="w-24">{{ __('Credits') }}</th>
                                                                        <th class="w-20">{{ __('Theory') }}</th>
                                                                        <th class="w-20">{{ __('Practice') }}</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    @foreach(($subject['equivalents'] ?? []) as $index => $equivalent)
                                                                        <tr>
                                                                            <td>{{ $index + 1 }}</td>
                                                                            <td class="font-semibold">{{ $equivalent['code'] }}</td>
                                                                            <td>{{ $equivalent['name'] }}</td>
                                                                            <td>{{ Subject::formatCredit($equivalent['credits']) }}</td>
                                                                            <td>{{ Subject::formatCredit($equivalent['credits_theory'] ?? 0) }}</td>
                                                                            <td>{{ Subject::formatCredit($equivalent['credits_practice'] ?? 0) }}</td>
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
                                        <div class="flex flex-wrap items-center justify-between mb-3 gap-2 bg-fita2 rounded-t-md px-4 py-2 text-white">
                                            <h3 class="text-lg font-semibold">{{ $groupBlock['group_name'] }}</h3>
                                            <div class="text-md">
                                                {{ $groupBlock['total_subjects'] }} {{__('subject')}} • {{ Subject::formatCredit($groupBlock['total_credits']) }}
                                                {{__('Credits ')}}
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto">
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
                                                {!! $this->highlightMatch($subject['name']) !!}
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
                                                {{ trim((string) ($subject['note'] ?? '')) !== '' ? $subject['note'] : '—' }}
                                                @endscope

                                                @scope('expansion', $subject)
                                                @if(($subject['equivalents_count'] ?? 0) > 0)
                                                    <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 my-2">
                                                        <div class="font-semibold text-primary mb-3">
                                                            {{ __('Equivalent subjects for') }} {{ $subject['code'] }} - {{ $subject['name'] }}
                                                        </div>
                                                        <div class="overflow-x-auto rounded border border-base-300 bg-white">
                                                            <table class="table md:text-[16px]">
                                                                <thead>
                                                                <tr>
                                                                    <th class="w-14">{{ __('No.') }}</th>
                                                                    <th>{{ __('Subject code') }}</th>
                                                                    <th>{{ __('Subject name') }}</th>
                                                                    <th class="w-24">{{ __('Credits') }}</th>
                                                                    <th class="w-20">{{ __('Theory') }}</th>
                                                                    <th class="w-20">{{ __('Practice') }}</th>
                                                                </tr>
                                                                </thead>
                                                                <tbody>
                                                                @foreach(($subject['equivalents'] ?? []) as $index => $equivalent)
                                                                    <tr>
                                                                        <td>{{ $index + 1 }}</td>
                                                                        <td class="font-semibold">{{ $equivalent['code'] }}</td>
                                                                        <td>{{ $equivalent['name'] }}</td>
                                                                        <td>{{ Subject::formatCredit($equivalent['credits']) }}</td>
                                                                        <td>{{ Subject::formatCredit($equivalent['credits_theory'] ?? 0) }}</td>
                                                                        <td>{{ Subject::formatCredit($equivalent['credits_practice'] ?? 0) }}</td>
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
            @endif
        </div>
    </div>
</div>

