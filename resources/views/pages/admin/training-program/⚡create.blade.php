<?php

use Livewire\Component;
use Mary\Traits\Toast;
use App\Models\Intake;
use App\Models\Major;
use App\Models\TrainingProgram;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use Toast;

    public string $name_vi = '';
    public string $name_en = '';
    public string $type_vi = '';
    public string $type_en = '';
    public string $level_vi = '';
    public string $level_en = '';
    public string $language_vi = '';
    public string $language_en = '';
    public ?int $duration_time = null;
    public ?int $major_id = null;
    public ?int $intake_id = null;
    public ?int $school_year_start = null;
    public ?int $school_year_end = null;
    public string $version = '';
    public int $total_credits = 0;
    public string $status = 'draft';
    public ?string $published_at = null;
    public string $notes = '';

    protected function rules(): array
    {
        return [
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type_vi' => ['required', 'string', 'max:100'],
            'type_en' => ['nullable', 'string', 'max:100'],
            'level_vi' => ['required', 'string', 'max:100'],
            'level_en' => ['nullable', 'string', 'max:100'],
            'language_vi' => ['required', 'string', 'max:100'],
            'language_en' => ['nullable', 'string', 'max:100'],
            'duration_time' => ['required', 'integer', 'min:1', 'max:20'],
            'major_id' => ['nullable', 'exists:majors,id'],
            'intake_id' => ['required', 'exists:intakes,id'],
            'school_year_start' => ['required', 'integer', 'min:2020', 'max:2100'],
            'school_year_end' => ['nullable', 'integer', 'min:2020', 'max:2100', 'gte:school_year_start'],
            'version' => [
                'required', 'string', 'max:20',
                Rule::unique('training_programs', 'version')->where(function ($q) {
                    $q->whereNull('deleted_at');

                    if ($this->intake_id) {
                        $q->where('intake_id', $this->intake_id);
                    }

                    if ($this->major_id) {
                        $q->where('major_id', $this->major_id);
                    } else {
                        $q->whereNull('major_id');
                    }
                }),
            ],
            'total_credits' => ['required', 'integer', 'min:0', 'max:300'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected $messages = [
        'name_vi.required' => 'Tên chương trình (Tiếng Việt) là bắt buộc.',
        'name_vi.max' => 'Tên chương trình (Tiếng Việt) không được vượt quá 255 ký tự.',
        'name_en.max' => 'Tên chương trình (Tiếng Anh) không được vượt quá 255 ký tự.',
        'type_vi.required' => 'Vui lòng nhập hình thức đào tạo (Tiếng Việt).',
        'level_vi.required' => 'Vui lòng nhập trình độ đào tạo (Tiếng Việt).',
        'language_vi.required' => 'Vui lòng nhập ngôn ngữ đào tạo (Tiếng Việt).',
        'duration_time.required' => 'Vui lòng nhập thời gian đào tạo.',
        'duration_time.integer' => 'Thời gian đào tạo phải là số nguyên.',
        'duration_time.min' => 'Thời gian đào tạo phải lớn hơn hoặc bằng 1.',
        'duration_time.max' => 'Thời gian đào tạo không được lớn hơn 20.',
        'major_id.exists' => 'Chuyên ngành không hợp lệ.',
        'intake_id.required' => 'Vui lòng chọn khóa.',
        'intake_id.exists' => 'Khóa không hợp lệ.',
        'school_year_start.integer' => 'Năm bắt đầu phải là số nguyên.',
        'school_year_start.required' => 'Năm bắt đầu là bắt buộc.',
        'school_year_start.min' => 'Năm bắt đầu không được nhỏ hơn 2020.',
        'school_year_start.max' => 'Năm bắt đầu không được lớn hơn 2100.',
        'school_year_end.integer' => 'Năm kết thúc phải là số nguyên.',
        'school_year_end.min' => 'Năm kết thúc không được nhỏ hơn 2020.',
        'school_year_end.max' => 'Năm kết thúc không được lớn hơn 2100.',
        'school_year_end.gte' => 'Năm kết thúc phải lớn hơn hoặc bằng năm bắt đầu.',
        'version.unique' => 'Phiên bản đã tồn tại trong cùng chuyên ngành/khóa.',
        'version.required' => 'Phiên bản là bắt buộc.',
        'version.max' => 'Phiên bản không được vượt quá 20 ký tự.',
        'total_credits.required' => 'Tổng số tín chỉ là bắt buộc.',
        'total_credits.integer' => 'Tổng số tín chỉ phải là số nguyên.',
        'total_credits.min' => 'Tổng số tín chỉ không được nhỏ hơn 0.',
        'total_credits.max' => 'Tổng số tín chỉ không được lớn hơn 300.',
        'status.required' => 'Trạng thái là bắt buộc.',
        'status.in' => 'Trạng thái không hợp lệ.',
        'published_at.date' => 'Thời gian xuất bản không hợp lệ.',
    ];

    public function mount(): void
    {
        $this->published_at = now()->format('Y-m-d\TH:i');
    }

    private function refreshVersion(): void
    {
        $intakeId = (int) ($this->intake_id ?? 0);
        $year = (int) ($this->school_year_start ?? 0);

        if ($intakeId <= 0 || $year <= 0) {
            $this->version = '';
            return;
        }

        $intakeName = Intake::query()->where('id', $intakeId)->value('name');

        $this->version = $intakeName ? trim((string) $intakeName) . ' - ' . (string) $year : '';
        $this->validateOnly('version');
    }

    public function updated($property): void
    {
        if (in_array($property, ['intake_id', 'school_year_start', 'major_id'], true)) {
            $this->refreshVersion();
        }

        $this->validateOnly($property);
    }

    public function getMajorOptionsProperty(): array
    {
        return Major::query()
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

    public function getIntakeOptionsProperty(): array
    {
        return Intake::query()->orderBy('name')->get(['id', 'name'])->toArray();
    }

    public function getStatusOptionsProperty(): array
    {
        return [
            ['id' => 'draft', 'name' => 'Nháp'],
            ['id' => 'published', 'name' => 'Đã xuất bản'],
            ['id' => 'archived', 'name' => 'Lưu trữ'],
        ];
    }

    public function save(): void
    {
        try {
            $this->validate();
            $this->refreshVersion();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra thông tin đã nhập.');
            throw $e;
        }

        $publishedAt = $this->status === 'published'
            ? ($this->published_at ?: now()->format('Y-m-d H:i:s'))
            : null;

        TrainingProgram::create([
            'name' => ['vi' => trim($this->name_vi), 'en' => trim($this->name_en)],
            'type' => ['vi' => trim($this->type_vi), 'en' => trim($this->type_en)],
            'level' => ['vi' => trim($this->level_vi), 'en' => trim($this->level_en)],
            'language' => ['vi' => trim($this->language_vi), 'en' => trim($this->language_en)],
            'duration_time' => $this->duration_time,
            'major_id' => $this->major_id,
            'intake_id' => $this->intake_id,
            'school_year_start' => $this->school_year_start,
            'school_year_end' => $this->school_year_end,
            'version' => trim($this->version),
            'total_credits' => $this->total_credits,
            'status' => $this->status,
            'published_at' => $publishedAt,
            'notes' => trim($this->notes),
        ]);

        $this->success('Tạo mới chương trình đào tạo thành công!', redirectTo: route('admin.training-program.index'));
    }
};
?>

<div>
    <x-slot:title>Tạo mới chương trình đào tạo</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.training-program.index') }}" class="font-semibold text-slate-700">Danh sách chương trình đào tạo</a>
        <span class="mx-1">/</span>
        <span>Tạo mới</span>
    </x-slot:breadcrumb>

    <x-header title="Tạo mới chương trình đào tạo"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"/>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">
            <x-card title="Thông tin chung" shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Tên (Tiếng Việt)" wire:model.live.debounce.400ms="name_vi" required placeholder="VD: Cử nhân Công nghệ phần mềm"/>
                    <x-input label="Tên (Tiếng Anh)" wire:model.live.debounce.400ms="name_en" placeholder="VD: Bachelor of Information Technology"/>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-input label="Hình thức đào tạo (Tiếng Việt)" wire:model.live.debounce.400ms="type_vi" required placeholder="VD: Chính quy"/>
                    <x-input label="Hình thức đào tạo (Tiếng Anh)" wire:model.live.debounce.400ms="type_en" placeholder="VD: Full-time"/>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-input label="Trình độ đào tạo (Tiếng Việt)" wire:model.live.debounce.400ms="level_vi" required placeholder="VD: Đại học"/>
                    <x-input label="Trình độ đào tạo (Tiếng Anh)" wire:model.live.debounce.400ms="level_en" placeholder="Vd: Undergraduate"/>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-input label="Ngôn ngữ đào tạo (Tiếng Việt)" wire:model.live.debounce.400ms="language_vi" required placeholder="VD: Tiếng Việt"/>
                    <x-input label="Ngôn ngữ đào tạo (Tiếng Anh)" wire:model.live.debounce.400ms="language_en" placeholder="VD: Vietnamese"/>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <x-select label="Chuyên ngành/Hướng chuyên sâu" wire:model.live.debounce.400ms="major_id" :options="$this->majorOptions" option-value="id" option-label="name" placeholder="{{ __('(General program)') }}" placeholder-value=""/>
                    <x-select label="Khóa" wire:model.live.debounce.400ms="intake_id" :options="$this->intakeOptions" option-value="id" option-label="name" required placeholder="Chọn khóa học"/>
                    <x-input label="Thời gian đào tạo (năm)" type="number" min="1" wire:model.live.debounce.400ms="duration_time" required placeholder="4"/>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <x-input label="Năm bắt đầu" type="number" wire:model.live.debounce.400ms="school_year_start" placeholder="2026"/>
                    <x-input label="Năm kết thúc" type="number" wire:model.live.debounce.400ms="school_year_end" placeholder="2030"/>
                    <x-input label="Tổng số tín chỉ " type="number" min="0" wire:model.live.debounce.400ms="total_credits" placeholder="131"/>
                </div>
            </x-card>


            <x-card title="Ghi chú" shadow class="p-3!">
                <x-textarea label="Ghi chú nội bộ" wire:model="notes" rows="5" />
            </x-card>
        </div>

        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">
            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button label="Lưu" class="bg-primary text-white w-full my-1" wire:click="save" spinner="save"/>
            </x-card>

            <x-card title="Xuất bản" shadow class="p-3!">
                <x-input label="Phiên bản" wire:model.live.debounce.300ms="version" placeholder="VD: K67 - 2022" required readonly/>
                <x-select label="Trạng thái" wire:model.live.debounce.300ms="status" :options="$this->statusOptions" option-value="id" option-label="name" />
                @if($status === 'published')
                    <div class="mt-4">
                        <x-datetime label="Thời gian xuất bản" wire:model="published_at" icon="o-calendar" type="datetime-local"/>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</div>
