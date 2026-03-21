<?php

use App\Models\Intake;
use App\Models\Major;
use App\Models\TrainingProgram;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.client')]
class extends Component {
    use WithPagination;

    #[Url(as: 'tim')]
    public string $search = '';

    #[Url(as: 'nganh')]
    public ?int $majorId = null;

    #[Url(as: 'khoa')]
    public ?int $intakeId = null;

    #[Url(as: 'ctdt')]
    public ?int $selectedProgramId = null;

    #[Url(as: 'hoc-ky')]
    public ?int $semesterNo = null;

    #[Url(as: 'kieu')]
    public string $viewMode = 'semester';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMajorId(): void
    {
        $this->resetAfterFilterChanged();
    }

    public function updatedIntakeId(): void
    {
        $this->resetAfterFilterChanged();
    }

    protected function resetAfterFilterChanged(): void
    {
        $this->selectedProgramId = null;
        $this->semesterNo = null;
        $this->resetPage();
    }

    public function selectProgram(int $programId): void
    {
        $this->selectedProgramId = $programId;
        $this->semesterNo = null;
    }

    public function updatedSelectedProgramId(): void
    {
        $this->semesterNo = null;
    }

    public function setViewMode(string $mode): void
    {
        if (!in_array($mode, ['semester', 'group'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->majorId = null;
        $this->intakeId = null;
        $this->selectedProgramId = null;
        $this->semesterNo = null;
        $this->viewMode = 'semester';
        $this->resetPage();
    }

    protected function localizedName(mixed $model, string $field = 'name'): string
    {
        if (!$model) {
            return 'N/A';
        }

        if (method_exists($model, 'getTranslation')) {
            $locale = app()->getLocale();

            return trim((string) ($model->getTranslation($field, $locale, false)
                ?: $model->getTranslation($field, 'vi', false)
                ?: $model->getTranslation($field, 'en', false)
                ?: 'N/A'));
        }

        return trim((string) data_get($model, $field, 'N/A')) ?: 'N/A';
    }

    public function with(): array
    {
        $search = trim(preg_replace('/\s+/u', ' ', $this->search) ?? '');

        $programBaseQuery = TrainingProgram::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['major', 'intake']);

        if ($this->majorId) {
            $programBaseQuery->where('major_id', $this->majorId);
        }

        if ($this->intakeId) {
            $programBaseQuery->where('intake_id', $this->intakeId);
        }

        if ($search !== '') {
            $keyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';

            $programBaseQuery->where(function ($query) use ($keyword) {
                $query->where('version', 'like', $keyword)
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'", [$keyword]);
            });
        }

        $programs = (clone $programBaseQuery)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(8);

        if ($this->selectedProgramId) {
            $isValidSelectedProgram = (clone $programBaseQuery)
                ->whereKey($this->selectedProgramId)
                ->exists();

            if (!$isValidSelectedProgram) {
                $this->selectedProgramId = null;
                $this->semesterNo = null;
            }
        }

        if (!$this->selectedProgramId && $programs->isNotEmpty()) {
            $this->selectedProgramId = (int) $programs->first()->id;
        }

        $activeProgram = null;
        $semesterBlocks = collect();
        $groupBlocks = collect();

        if ($this->selectedProgramId) {
            $activeProgram = TrainingProgram::query()
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
                                    ->with('groupSubject')
                                    ->orderBy('program_semester_subjects.order');
                            }]);
                    },
                ])
                ->find($this->selectedProgramId);

            if ($activeProgram) {
                $semesterCollection = $activeProgram->semesters;

                if ($this->semesterNo) {
                    $semesterCollection = $semesterCollection
                        ->where('semester_no', $this->semesterNo)
                        ->values();
                }

                $semesterBlocks = $semesterCollection
                    ->map(function ($semester) {
                        $subjects = $semester->subjects
                            ->map(function ($subject) use ($semester) {
                                return [
                                    'id' => (int) $subject->id,
                                    'code' => (string) $subject->code,
                                    'name' => $this->localizedName($subject),
                                    'credits' => (int) ($subject->credits ?? 0),
                                    'credits_theory' => (int) ($subject->credits_theory ?? 0),
                                    'credits_practice' => (int) ($subject->credits_practice ?? 0),
                                    'type' => (string) ($subject->pivot->type ?? 'required'),
                                    'order' => (int) ($subject->pivot->order ?? 0),
                                    'notes' => (string) ($subject->pivot->notes ?? ''),
                                    'semester_no' => (int) $semester->semester_no,
                                    'group_name' => $subject->groupSubject
                                        ? $this->localizedName($subject->groupSubject)
                                        : __('Uncategorized Group'),
                                    'group_sort_order' => (int) ($subject->groupSubject->sort_order ?? 9999),
                                ];
                            })
                            ->sortBy('order')
                            ->values();

                        return [
                            'semester_no' => (int) $semester->semester_no,
                            'total_credits' => (int) $subjects->sum('credits'),
                            'subjects' => $subjects,
                        ];
                    })
                    ->values();

                $groupBlocks = $semesterBlocks
                    ->flatMap(fn ($semesterBlock) => $semesterBlock['subjects'])
                    ->groupBy('group_name')
                    ->map(function ($subjects, $groupName) {
                        $sorted = collect($subjects)
                            ->sortBy([['semester_no', 'asc'], ['order', 'asc']])
                            ->values();

                        return [
                            'group_name' => (string) $groupName,
                            'group_sort_order' => (int) ($sorted->first()['group_sort_order'] ?? 9999),
                            'total_subjects' => (int) $sorted->count(),
                            'total_credits' => (int) $sorted->sum('credits'),
                            'subjects' => $sorted,
                        ];
                    })
                    ->sortBy('group_sort_order')
                    ->values();
            }
        }

        $majorOptions = Major::query()
            ->whereHas('trainingPrograms', function ($query) {
                $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get(['id', 'name']);

        $intakeOptions = Intake::query()
            ->whereHas('trainingPrograms', function ($query) {
                $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->orderByDesc('name')
            ->get(['id', 'name']);

        return [
            'programs' => $programs,
            'activeProgram' => $activeProgram,
            'semesterBlocks' => $semesterBlocks,
            'groupBlocks' => $groupBlocks,
            'majorOptions' => $majorOptions,
            'intakeOptions' => $intakeOptions,
        ];
    }
};
?>

<div class="container mx-auto px-4 py-8">
    <x-slot:title>{{ __('Training Programs') }}</x-slot:title>

    <x-slot:breadcrumb>
        <span>{{ __('Training Programs') }}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{ __('Training Programs') }}
    </x-slot:titleBreadcrumb>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-4 space-y-4">
            <x-card title="Bộ lọc tìm kiếm" shadow>
                <div class="space-y-3">
                    <x-input
                        label="Tìm chương trình"
                        placeholder="Tên CTĐT, mã, phiên bản..."
                        wire:model.live.debounce.400ms="search"
                        icon="o-magnifying-glass"
                        clearable
                    />

                    <div>
                        <label class="label"><span class="label-text">{{ __('Major') }}</span></label>
                        <select class="select select-bordered w-full" wire:model.live="majorId">
                            <option value="">{{ __('All majors') }}</option>
                            @foreach($majorOptions as $major)
                                <option value="{{ $major->id }}">{{ $this->localizedName($major) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label"><span class="label-text">Khóa</span></label>
                        <select class="select select-bordered w-full" wire:model.live="intakeId">
                            <option value="">Tất cả khóa</option>
                            @foreach($intakeOptions as $intake)
                                <option value="{{ $intake->id }}">{{ $intake->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-button label="Xóa bộ lọc" class="btn-outline btn-sm" wire:click="resetFilters" />
                </div>
            </x-card>

            <x-card title="Danh sách CTĐT" shadow>
                <div class="space-y-3">
                    @forelse($programs as $program)
                        @php
                            $programName = $program->getTranslation('name', app()->getLocale(), false)
                                ?: $program->getTranslation('name', 'vi', false)
                                ?: $program->getTranslation('name', 'en', false)
                                ?: 'N/A';
                        @endphp

                        <button
                            type="button"
                            wire:click="selectProgram({{ $program->id }})"
                            class="w-full text-left border rounded-lg p-3 transition hover:bg-gray-50 {{ $selectedProgramId === $program->id ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white' }}"
                        >
                            <div class="font-semibold">{{ $programName }}</div>
                            <div class="text-xs text-gray-600 mt-1">
                                {{ $program->version }} • {{ optional($program->intake)->name ?? 'N/A' }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $program->major ? $this->localizedName($program->major) : __('General program') }}
                            </div>
                        </button>
                    @empty
                        <div class="text-sm text-gray-500">Không có chương trình đào tạo phù hợp.</div>
                    @endforelse
                </div>

                @if($programs->hasPages())
                    <div class="mt-4">{{ $programs->links() }}</div>
                @endif
            </x-card>
        </div>

        <div class="lg:col-span-8 space-y-4">
            @if(!$activeProgram)
                <x-card shadow>
                    <div class="text-center py-10 text-gray-500">
                        Vui lòng chọn một chương trình đào tạo để xem chi tiết.
                    </div>
                </x-card>
            @else
                @php
                    $programTitle = $activeProgram->getTranslation('name', app()->getLocale(), false)
                        ?: $activeProgram->getTranslation('name', 'vi', false)
                        ?: $activeProgram->getTranslation('name', 'en', false)
                        ?: 'N/A';
                @endphp

                <x-card shadow>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-2xl font-bold">{{ $programTitle }}</h2>
                            <div class="text-sm text-gray-600 mt-1">
                                {{ $activeProgram->major ? $this->localizedName($activeProgram->major) : __('General program') }}
                                • {{ optional($activeProgram->intake)->name ?? 'N/A' }}
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-badge value="{{ $activeProgram->version }}" class="badge-primary" />
                            <x-badge value="{{ $activeProgram->total_credits }} tín chỉ" class="badge-outline" />
                        </div>
                    </div>
                </x-card>

                <x-card shadow>
                    <div class="flex flex-wrap items-center gap-3 justify-between">
                        <div class="join">
                            <button
                                type="button"
                                wire:click="setViewMode('semester')"
                                class="join-item btn btn-sm {{ $viewMode === 'semester' ? 'btn-primary text-white' : 'btn-ghost' }}"
                            >
                                Theo học kỳ
                            </button>
                            <button
                                type="button"
                                wire:click="setViewMode('group')"
                                class="join-item btn btn-sm {{ $viewMode === 'group' ? 'btn-primary text-white' : 'btn-ghost' }}"
                            >
                                Theo nhóm môn
                            </button>
                        </div>

                        <div class="w-full sm:w-56">
                            <label class="label"><span class="label-text">Lọc học kỳ</span></label>
                            <select class="select select-bordered w-full" wire:model.live="semesterNo">
                                <option value="">Tất cả học kỳ</option>
                                @foreach($activeProgram->semesters as $semester)
                                    <option value="{{ $semester->semester_no }}">Học kỳ {{ $semester->semester_no }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </x-card>

                @if($viewMode === 'semester')
                    <div class="space-y-4">
                        @forelse($semesterBlocks as $semesterBlock)
                            <x-card shadow>
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-lg font-semibold">Học kỳ {{ $semesterBlock['semester_no'] }}</h3>
                                    <span class="text-sm text-gray-500">{{ $semesterBlock['total_credits'] }} tín chỉ</span>
                                </div>

                                @if($semesterBlock['subjects']->isEmpty())
                                    <div class="text-sm text-gray-500">Không có môn học trong học kỳ này.</div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="table table-sm">
                                            <thead>
                                            <tr>
                                                <th>Mã môn</th>
                                                <th>Tên môn</th>
                                                <th>Nhóm môn</th>
                                                <th>Tín chỉ</th>
                                                <th>Loại</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($semesterBlock['subjects'] as $subject)
                                                <tr>
                                                    <td class="font-medium">{{ $subject['code'] }}</td>
                                                    <td>{{ $subject['name'] }}</td>
                                                    <td>{{ $subject['group_name'] }}</td>
                                                    <td>
                                                        {{ $subject['credits'] }}
                                                        <span class="text-xs text-gray-500">(LT/TH: {{ $subject['credits_theory'] }}/{{ $subject['credits_practice'] }})</span>
                                                    </td>
                                                    <td>
                                                        <x-badge
                                                            value="{{ $subject['type'] === 'required' ? 'Bắt buộc' : 'Tự chọn' }}"
                                                            class="{{ $subject['type'] === 'required' ? 'badge-success' : 'badge-warning' }} badge-sm"
                                                        />
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
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
                            <x-card shadow>
                                <div class="flex flex-wrap items-center justify-between mb-3 gap-2">
                                    <h3 class="text-lg font-semibold">{{ $groupBlock['group_name'] }}</h3>
                                    <div class="text-sm text-gray-500">
                                        {{ $groupBlock['total_subjects'] }} môn • {{ $groupBlock['total_credits'] }} tín chỉ
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Học kỳ</th>
                                            <th>Mã môn</th>
                                            <th>Tên môn</th>
                                            <th>Tín chỉ</th>
                                            <th>Loại</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($groupBlock['subjects'] as $subject)
                                            <tr>
                                                <td>HK {{ $subject['semester_no'] }}</td>
                                                <td class="font-medium">{{ $subject['code'] }}</td>
                                                <td>{{ $subject['name'] }}</td>
                                                <td>{{ $subject['credits'] }}</td>
                                                <td>
                                                    <x-badge
                                                        value="{{ $subject['type'] === 'required' ? 'Bắt buộc' : 'Tự chọn' }}"
                                                        class="{{ $subject['type'] === 'required' ? 'badge-success' : 'badge-warning' }} badge-sm"
                                                    />
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </x-card>
                        @empty
                            <x-card shadow>
                                <div class="text-sm text-gray-500">Không có dữ liệu môn học theo nhóm môn.</div>
                            </x-card>
                        @endforelse
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>


