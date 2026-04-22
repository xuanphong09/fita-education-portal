<?php

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\Major;
use App\Models\ProgramMajor;
use App\Models\Intake;
use App\Models\TrainingProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new class extends Component {
    use WithPagination, Toast;

    public array $sortBy = ['column' => 'updated_at', 'direction' => 'desc'];
    public int $perPage = 10;
    #[Url(as: 'search')]
    public string $search = '';
    public ?int $program_major_id = null;
    public ?int $major_id = null;
    public ?int $intake_id = null;
    public string $filterStatus = '';

    public bool $modalDuplicate = false;
    public ?int $duplicateFromId = null;
    public string $duplicate_name_vi = '';
    public string $duplicate_name_en = '';
    public ?int $duplicate_program_major_id = null;
    public ?int $duplicate_major_id = null;
    public ?int $duplicate_intake_id = null;
    public ?int $duplicate_school_year_start = null;
    public ?int $duplicate_school_year_end = null;
    public string $duplicate_version = '';
    public string $duplicate_status = 'draft';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProgramMajorId(): void
    {
        // Khi thay đổi ngành, xóa bộ lọc chuyên ngành cũ
        $this->major_id = null;
        $this->resetPage();
    }

    public function updatedMajorId(): void
    {
        $this->resetPage();
    }

    public function updatedIntakeId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->program_major_id = null;
        $this->major_id = null;
        $this->intake_id = null;
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function openDuplicateModal(int $id): void
    {
        $program = TrainingProgram::findOrFail($id);

        $this->duplicateFromId = $id;
        $this->duplicate_name_vi = (string)($program->getTranslation('name', 'vi', false) ?? '');
        $this->duplicate_name_en = (string)($program->getTranslation('name', 'en', false) ?? '');
        $this->duplicate_program_major_id = $program->program_major_id;
        $this->duplicate_major_id = $program->major_id;
        $this->duplicate_intake_id = $program->intake_id;
        $this->duplicate_school_year_start = $program->school_year_start;
        $this->duplicate_school_year_end = $program->school_year_end;
        $this->duplicate_status = 'draft';
        $this->resetErrorBag();
        $this->refreshDuplicateVersion();
        $this->modalDuplicate = true;
    }

    public function updatedDuplicateProgramMajorId(): void
    {
        // Khi thay đổi ngành, xóa chuyên ngành cũ và cập nhật version
        $this->duplicate_major_id = null;
        $this->refreshDuplicateVersion();
    }

    public function updatedDuplicateIntakeId(): void
    {
        $this->refreshDuplicateVersion();
    }

    public function updatedDuplicateSchoolYearStart(): void
    {
        $this->refreshDuplicateVersion();
    }

    private function refreshDuplicateVersion(): void
    {
        $intakeId = (int)($this->duplicate_intake_id ?? 0);
        $year = (int)($this->duplicate_school_year_start ?? 0);

        if ($intakeId <= 0 || $year <= 0) {
            $this->duplicate_version = '';
            return;
        }

        $intakeName = Intake::query()->where('id', $intakeId)->value('name');
        $this->duplicate_version = $intakeName ? trim((string)$intakeName) . ' - ' . (string)$year : '';
    }

    public function rules(): array
    {
        return [
            'duplicate_name_vi' => ['required', 'string', 'max:255'],
            'duplicate_name_en' => ['nullable', 'string', 'max:255'],
            'duplicate_program_major_id' => ['nullable', 'exists:program_majors,id'],
            'duplicate_major_id' => ['nullable', 'exists:majors,id'],
//            'duplicate_intake_id' => ['required', 'exists:intakes,id'],
            'duplicate_school_year_start' => ['required', 'integer', 'min:2020', 'max:2100'],
            'duplicate_school_year_end' => ['nullable', 'integer', 'min:2020', 'max:2100', 'gte:duplicate_school_year_start'],
//            'duplicate_version' => [
//                'required',
//                'string',
//                'max:20',
//                Rule::unique('training_programs', 'version')->where(function ($q) {
//                    $q->whereNull('deleted_at');
//
//                    if ($this->duplicate_intake_id) {
//                        $q->where('intake_id', $this->duplicate_intake_id);
//                    }
//
//                    if ($this->duplicate_program_major_id) {
//                        $q->where('program_major_id', $this->duplicate_program_major_id);
//                    } else {
//                        $q->whereNull('program_major_id');
//                    }
//
//                    if ($this->duplicate_major_id) {
//                        $q->where('major_id', $this->duplicate_major_id);
//                    } else {
//                        $q->whereNull('major_id');
//                    }
//                }),
//            ],
            'duplicate_intake_id' => [
                'required',
                'exists:intakes,id',
                Rule::unique('training_programs', 'intake_id')->where(function ($q) {
                    $q->whereNull('deleted_at');

                    if ($this->duplicate_program_major_id) {
                        $q->where('program_major_id', $this->duplicate_program_major_id);
                    } else {
                        $q->whereNull('program_major_id');
                    }

                    if ($this->duplicate_major_id) {
                        $q->where('major_id', $this->duplicate_major_id);
                    } else {
                        $q->whereNull('major_id');
                    }
                }),
            ],
            'duplicate_status' => ['required', 'in:draft,published,archived'],
        ];
    }

    protected $messages = [
        'duplicate_name_vi.required' => 'Tên chương trình (Tiếng Việt) là bắt buộc.',
        'duplicate_name_vi.string' => 'Tên chương trình (Tiếng Việt) phải là một chuỗi.',
        'duplicate_name_vi.max' => 'Tên chương trình (Tiếng Việt) không được vượt quá 255 ký tự.',
        'duplicate_name_en.string' => 'Tên chương trình (Tiếng Anh) phải là một chuỗi.',
        'duplicate_name_en.max' => 'Tên chương trình (Tiếng Anh) không được vượt quá 255 ký tự.',
        'duplicate_program_major_id.exists' => 'Ngành không tồn tại.',
        'duplicate_major_id.exists' => 'Chuyên ngành không tồn tại.',
        'duplicate_intake_id.required' => 'Khóa là bắt buộc.',
        'duplicate_intake_id.exists' => 'Khóa không tồn tại.',
        'duplicate_school_year_start.required' => 'Năm bắt đầu là bắt buộc.',
        'duplicate_school_year_start.integer' => 'Năm bắt đầu phải là một số nguyên.',
        'duplicate_school_year_start.min' => 'Năm bắt đầu không được nhỏ hơn 2020.',
        'duplicate_school_year_start.max' => 'Năm bắt đầu không được lớn hơn 2100.',
        'duplicate_school_year_end.integer' => 'Năm kết thúc phải là một số nguyên.',
        'duplicate_school_year_end.min' => 'Năm kết thúc không được nhỏ hơn 2020.',
        'duplicate_school_year_end.max' => 'Năm kết thúc không được lớn hơn 2100.',
        'duplicate_school_year_end.gte' => 'Năm kết thúc phải lớn hơn hoặc bằng năm bắt đầu.',
//        'duplicate_version.required' => 'Phiên bản là bắt buộc.',
//        'duplicate_version.string' => 'Phiên bản phải là một chuỗi.',
//        'duplicate_version.max' => 'Phiên bản không được vượt quá 20 ký tự.',
//        'duplicate_version.unique' => 'Phiên bản đã tồn tại trong cùng ngành/chuyên ngành/khóa. Vui lòng chọn phiên bản khác hoặc thay đổi ngành/chuyên ngành/khóa.',
        'duplicate_intake_id.unique' => 'Phiên bản đã tồn tại trong cùng ngành/chuyên ngành/khóa.',
        'duplicate_status.required' => 'Trạng thái là bắt buộc.',
        'duplicate_status.in' => 'Trạng thái không hợp lệ. Phải là draft, published hoặc archived.',
    ];

    public function confirmDuplicate(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra thông tin đã nhập.');
            throw $e;
        }

        $sourceProgram = TrainingProgram::with('semesters.subjects')->findOrFail($this->duplicateFromId);

        DB::transaction(function () use ($sourceProgram) {
            $newProgram = TrainingProgram::create([
                'version' => $this->duplicate_version,
                'status' => $this->duplicate_status,
                'program_major_id' => $this->duplicate_program_major_id,
                'major_id' => $this->duplicate_major_id,
                'intake_id' => $this->duplicate_intake_id,
                'type' => $sourceProgram->type,
                'level' => $sourceProgram->level,
                'language' => $sourceProgram->language,
                'duration_time' => $sourceProgram->duration_time,
                'school_year_start' => $this->duplicate_school_year_start,
                'school_year_end' => $this->duplicate_school_year_end,
                'total_credits' => $sourceProgram->total_credits,
                'name' => [
                    'vi' => $this->duplicate_name_vi,
                    'en' => $this->duplicate_name_en,
                ],
                'notes' => $sourceProgram->notes,
                'published_at' => $this->duplicate_status === 'published' ? now() : null,
            ]);

            $subjects = $sourceProgram->semesters->flatMap(fn($semester) => $semester->subjects)->unique('id')->values();

            foreach ($sourceProgram->semesters as $semester) {
                $newSemester = $newProgram->semesters()->create([
                    'semester_no' => $semester->semester_no,
                    'semester_name' => $semester->semester_name,
                    'total_credits' => $semester->total_credits,
                    'start_date' => $semester->start_date,
                    'end_date' => $semester->end_date,
                ]);

                foreach ($semester->subjects as $subject) {
                    $newSemester->subjects()->attach($subject->id, [
                        'type' => $subject->pivot->type,
                        'notes' => $subject->pivot->notes,
                        'order' => $subject->pivot->order,
                    ]);
                }
            }

            foreach ($subjects as $subject) {
                $prerequisites = \App\Models\SubjectPrerequisite::where('training_program_id', $sourceProgram->id)
                    ->where('subject_id', $subject->id)
                    ->pluck('prerequisite_subject_id')
                    ->toArray();

                if (!empty($prerequisites)) {
                    \App\Models\SubjectPrerequisite::syncForProgramSubject($newProgram->id, $subject->id, $prerequisites);
                }

            }
        });

        $this->modalDuplicate = false;
        $this->resetDuplicateForm();
        $this->resetPage();

        $this->success('Đã nhân bản chương trình đào tạo thành công.');
    }

    protected function resetDuplicateForm(): void
    {
        $this->duplicateFromId = null;
        $this->duplicate_name_vi = '';
        $this->duplicate_name_en = '';
        $this->duplicate_program_major_id = null;
        $this->duplicate_major_id = null;
        $this->duplicate_intake_id = null;
        $this->duplicate_school_year_start = null;
        $this->duplicate_school_year_end = null;
        $this->duplicate_version = '';
        $this->duplicate_status = 'draft';
    }

    public function getHasActiveFiltersProperty(): bool
    {
        return trim($this->search) !== ''
            || !is_null($this->program_major_id)
            || !is_null($this->major_id)
            || !is_null($this->intake_id)
            || $this->filterStatus !== '';
    }

    public function getProgramsProperty()
    {
        $search = trim($this->search);

        $query = TrainingProgram::query()
            ->with(['programMajor', 'major', 'intake'])
            ->withCount('semesters');

        if ($search !== '') {
            $keyword = '%' . $search . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('version', 'like', $keyword)
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$keyword])
                    ->orWhereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ?", [$keyword]);
            });
        }

        if ($this->program_major_id) {
            $query->where('program_major_id', $this->program_major_id);
        }

        if ($this->major_id) {
            $query->where('major_id', $this->major_id);
        }

        if ($this->intake_id) {
            $query->where('intake_id', $this->intake_id);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $query->orderBy(...array_values($this->sortBy));

        return $query->paginate($this->perPage);
    }

    public function getIntakeOptionsProperty(): array
    {
        return Intake::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getMajorOptionsProperty(): array
    {
        $query = Major::query();

        // Nếu đã chọn ngành, lọc chỉ lấy chuyên ngành thuộc ngành đó
        if ($this->program_major_id) {
            $query->where('program_major_id', $this->program_major_id);
        }

        return $query
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get(['id', 'name', 'slug'])
            ->map(function (Major $major) {
                return [
                    'id' => $major->id,
                    'name' => $major->getTranslation('name', app()->getLocale(), false)
                        ?: $major->getTranslation('name', 'vi', false)
                            ?: $major->getTranslation('name', 'en', false)
                                ?: $major->slug,
                ];
            })
            ->toArray();
    }

    public function getProgramMajorOptionsProperty(): array
    {
        return ProgramMajor::query()
            ->where('is_active', true)
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get(['id', 'name', 'slug'])
            ->map(function (ProgramMajor $programMajor) {
                return [
                    'id' => $programMajor->id,
                    'name' => $programMajor->getTranslation('name', app()->getLocale(), false)
                        ?: $programMajor->getTranslation('name', 'vi', false)
                            ?: $programMajor->getTranslation('name', 'en', false)
                                ?: $programMajor->slug,
                ];
            })
            ->toArray();
    }

    public function getDuplicateMajorOptionsProperty(): array
    {
        $query = Major::query();

        // Nếu đã chọn ngành, lọc chỉ lấy chuyên ngành thuộc ngành đó
        if ($this->duplicate_program_major_id) {
            $query->where('program_major_id', $this->duplicate_program_major_id);
        }

        return $query
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get(['id', 'name', 'slug'])
            ->map(function (Major $major) {
                return [
                    'id' => $major->id,
                    'name' => $major->getTranslation('name', app()->getLocale(), false)
                        ?: $major->getTranslation('name', 'vi', false)
                            ?: $major->getTranslation('name', 'en', false)
                                ?: $major->slug,
                ];
            })
            ->toArray();
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-8'],
            ['key' => 'version', 'label' => 'Phiên bản', 'class' => 'w-15'],
            ['key' => 'name', 'label' => 'Tên chương trình', 'sortable' => false],
            ['key' => 'scope', 'label' => 'Phạm vi', 'sortable' => false],
            ['key' => 'total_credits', 'label' => 'Tổng TC', 'class' => 'w-10'],
            ['key' => 'status', 'label' => 'Trạng thái', 'class' => 'w-34'],
            ['key' => 'actions', 'label' => 'Hành động', 'sortable' => false, 'class' => 'w-24'],
        ];
    }

    public function delete(int $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc chắn muốn xóa chương trình đào tạo này?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmDelete',
            'id' => $id,
        ]);
    }

    #[On('confirmDelete')]
    public function confirmDelete(int $id): void
    {
        $program = TrainingProgram::findOrFail($id);
        $program->delete();

        $this->success('Đã chuyển CTDT vào thùng rác.');
    }
};
?>

<div>
    <x-slot:title>Quản lý chương trình đào tạo</x-slot:title>

    <x-slot:breadcrumb>
        <span>Quản lý chương trình đào tạo</span>
    </x-slot:breadcrumb>

    <x-header title="Quản lý chương trình đào tạo"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm theo phiên bản hoặc tên..."
                wire:model.live.debounce.300ms="search"
                clearable
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-trash" class="btn-ghost" label="Thùng rác"
                      link="{{ route('admin.training-program.trash') }}"/>
            <x-button icon="o-plus" class="btn-primary text-white" label="Tạo mới"
                      link="{{ route('admin.training-program.create') }}"/>
        </x-slot:actions>
    </x-header>
    <div class="flex flex-wrap gap-3 mb-4">
        <x-select
            placeholder="Lọc theo ngành"
            placeholder-value=""
            wire:model.live="program_major_id"
            :options="$this->programMajorOptions"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />

        <x-select
            placeholder="{{ __('Filter by major') }}"
            placeholder-value=""
            wire:model.live="major_id"
            :options="$this->majorOptions"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />

        <x-select
            placeholder="Lọc theo khóa"
            placeholder-value=""
            wire:model.live="intake_id"
            :options="$this->intakeOptions"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        <x-select
            wire:model.live="filterStatus"
            placeholder="Tất cả trạng thái"
            placeholder-value=""
            :options="[
                ['id'=>'draft',     'name'=>'Nháp'],
                ['id'=>'published', 'name'=>'Đã đăng'],
                ['id'=>'archived',  'name'=>'Lưu trữ'],
            ]"
            option-value="id"
            option-label="name"
            class="select-md w-48"
        />
        @if($this->hasActiveFilters)
            <x-button
                label="Xóa bộ lọc"
                icon="o-funnel"
                class="btn-outline btn-error"
                wire:click="resetFilters"
                spinner="resetFilters"
            />
        @endif
    </div>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->programs"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[5, 10, 20, 50]"
            per-page="perPage"
            with-pagination
            wire:loading.class="opacity-50 pointer-events-none select-none"
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
                [&_tr:hover]:bg-gray-100 [&_tr:nth-child(2n)]:bg-gray-100/30!
            "
        >
            @scope('cell_id', $program)
            {{ ($this->programs->currentPage() - 1) * $this->programs->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_name', $program)
            <div class="font-semibold">{{ $program->getTranslation('name', 'vi', false) ?: '—' }}</div>
            <div class="text-sm text-gray-400">{{ $program->getTranslation('name', 'en', false) ?: '' }}</div>
            @endscope

            @scope('cell_scope', $program)
            <div class="text-sm">
                <div><span
                        class="text-gray-500"> Ngành: </span> {{ $program->programMajor?->getTranslation('name', app()->getLocale(), false) ?: $program->programMajor?->getTranslation('name', 'vi', false) ?: $program->programMajor?->getTranslation('name', 'en', false) ?: __('General') }}
                </div>
                <div><span
                        class="text-gray-500"> Chuyên ngành: </span> {{ $program->major?->getTranslation('name', app()->getLocale(), false) ?: $program->major?->getTranslation('name', 'vi', false) ?: $program->major?->getTranslation('name', 'en', false) ?: __('General') }}
                </div>
                <div><span class="text-gray-500">Khóa:</span> {{ $program->intake?->name ?? '—' }}</div>
            </div>
            @endscope

            @scope('cell_version', $program)
            <div class="font-semibold">{{$program->version}} </div>
            @endscope

            @scope('cell_total_credits', $program)
            <div>{{$program->total_credits . ' TC'}}</div>
            @endscope

            @scope('cell_status', $program)
            @if($program->status === 'published')
                <x-badge value="Đã xuất bản" class="badge-success badge-md text-white font-semibold"/>
            @elseif($program->status === 'archived')
                <x-badge value="Lưu trữ" class="badge-error badge-md text-white font-semibold"/>
            @else
                <x-badge value="Nháp" class="badge-ghost badge-md text-black font-semibold"/>
            @endif
            @endscope

            @scope('cell_actions', $program)
            <div class="flex space-x-2">
                <x-button icon="o-calendar-days" class="btn-sm btn-ghost text-info" tooltip="Học kỳ & môn học"
                          link="{{ route('admin.training-program.semesters', $program->id) }}"/>
                <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary" tooltip="Chỉnh sửa"
                          link="{{ route('admin.training-program.edit', $program->id) }}"/>
                <x-button icon="o-document-duplicate" class="btn-sm btn-ghost text-success" tooltip="Nhân bản"
                          wire:click="openDuplicateModal({{ $program->id }})"
                          spinner="openDuplicateModal({{ $program->id }})"/>
                <x-button icon="o-trash" class="btn-sm btn-ghost text-danger" tooltip="Xóa"
                          wire:click="delete({{ $program->id }})" spinner="delete({{ $program->id }})"/>
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-5">
                    <x-icon name="o-academic-cap" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Chưa có chương trình đào tạo nào.</p>
                </div>
            </x-slot:empty>

            <x-pagination :rows="$this->programs" wire:model.live="perPage"/>
        </x-table>

        <div wire:loading.flex
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>

    <!-- Duplicate Modal -->
    <x-modal wire:model="modalDuplicate" title="Nhân bản chương trình đào tạo" separator
             class="modalDuplicateTrainingProgram">
        <div class="space-y-2 py-2 px-1 max-h-[70vh] overflow-y-auto pr-1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-2">
                <x-input
                    label="Tên chương trình (Tiếng Việt)"
                    placeholder="Nhập tên tiếng Việt"
                    wire:model="duplicate_name_vi"
                />

                <x-input
                    label="Tên chương trình (Tiếng Anh)"
                    placeholder="Nhập tên tiếng Anh"
                    wire:model="duplicate_name_en"
                />

                <x-select
                    label="Ngành"
                    placeholder="Chọn ngành"
                    placeholder-value=""
                    wire:model.live="duplicate_program_major_id"
                    :options="$this->programMajorOptions"
                    option-value="id"
                    option-label="name"
                />

                <x-select
                    label="Chuyên ngành"
                    placeholder="Chọn chuyên ngành"
                    placeholder-value=""
                    wire:model.live="duplicate_major_id"
                    :options="$this->duplicateMajorOptions"
                    option-value="id"
                    option-label="name"
                    :disabled="!$this->duplicate_program_major_id"
                />

                <x-select
                    label="Khóa"
                    placeholder="Chọn khóa"
                    wire:model.live="duplicate_intake_id"
                    :options="$this->intakeOptions"
                    option-value="id"
                    option-label="name"
                />

                <x-input
                    label="Năm bắt đầu"
                    type="number"
                    min="2020"
                    max="2100"
                    wire:model.live="duplicate_school_year_start"
                />

                <x-input
                    label="Năm kết thúc"
                    type="number"
                    min="2020"
                    max="2100"
                    wire:model="duplicate_school_year_end"
                />

{{--                <x-input--}}
{{--                    label="Phiên bản (tự động tạo)"--}}
{{--                    placeholder="Phiên bản sẽ tự động sinh ra"--}}
{{--                    wire:model="duplicate_version"--}}
{{--                    readonly--}}
{{--                />--}}

                <x-select
                    label="Trạng thái"
                    wire:model="duplicate_status"
                    :options="[
                        ['id' => 'draft', 'name' => 'Nháp'],
                        ['id' => 'published', 'name' => 'Đã đăng'],
                        ['id' => 'archived', 'name' => 'Lưu trữ'],
                    ]"
                    option-value="id"
                    option-label="name"
                />
            </div>
            <div class="text-sm text-gray-500 bg-blue-50 p-3 rounded">
                <strong>Lưu ý:</strong> Chương trình sẽ được nhân bản toàn bộ bao gồm:
                <ul class="list-disc list-inside mt-1">
                    <li>Tất cả học kỳ</li>
                    <li>Tất cả môn học và loại môn</li>
                    <li>Môn tiên quyết</li>
                    <li>Môn tương đương</li>
                </ul>
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Hủy" class="btn-ghost" @click="$wire.modalDuplicate = false"/>
            <x-button label="Nhân bản" class="btn-primary text-white" wire:click="confirmDuplicate"
                      spinner="confirmDuplicate"/>
        </x-slot:actions>
    </x-modal>
</div>


