<?php

use App\Models\GroupSubject;
use App\Models\Subject;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithFileUploads;

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

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'regex:/^([A-Za-z0-9]+\/\s*)*[A-Za-z0-9]+\/?$/', Rule::unique('subjects', 'code')],
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'group_subject_id' => ['nullable', 'integer', 'exists:group_subjects,id'],
            'credits' => $this->decimalRules('Tổng tín chỉ'),
            'credits_theory' => $this->decimalRules('Tín chỉ lý thuyết'),
            'credits_practice' => $this->decimalRules('Tín chỉ thực hành'),
            'is_active' => ['boolean'],
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
            ->map(fn (GroupSubject $group) => [
                'id' => $group->id,
                'name' => $group->getTranslation('name', 'vi', false) ?: ('#' . $group->id),
            ])
            ->toArray();
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
            'group_subject_id' => !blank($this->group_subject_id) ? (int) $this->group_subject_id : null,
            'credits' => $this->toDecimal($this->credits),
            'credits_theory' => $this->toDecimal($this->credits_theory),
            'credits_practice' => $this->toDecimal($this->credits_practice),
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
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin môn học.');
            throw $e;
        }

        $syllabusPath = null;
        $syllabusOriginalName = null;

        if ($this->syllabus_file) {
            $syllabusPath = $this->syllabus_file->store('uploads/subjects/syllabi', 'local');
            $syllabusOriginalName = (string) $this->syllabus_file->getClientOriginalName();
        }

        Subject::query()->create(array_merge($this->payload(), [
            'syllabus_path' => $syllabusPath,
            'syllabus_original_name' => $syllabusOriginalName,
        ]));

        $this->success('Tạo môn học thành công!', redirectTo: route('admin.subject.index'));
    }
};
?>

<div>
    <x-slot:title>Tạo môn học</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.subject.index') }}" class="font-semibold text-slate-700">Danh sách môn học</a>
        <span class="mx-1">/</span>
        <span>Tạo mới</span>
    </x-slot:breadcrumb>

    <x-header title="Tạo môn học mới"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"/>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">
            <x-card shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Mã môn học"
                        wire:model.live.debounce.300ms="code"
                        placeholder="VD: IT202"
                        required
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
                        placeholder="VD: Cơ sở dữ liệu"
                        required
                    />
                    <x-input
                        label="Tên (Tiếng Anh)"
                        wire:model.live.debounce.300ms="name_en"
                        placeholder="VD: Database Systems"
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <x-input
                        label="Tổng tín chỉ"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.300ms="credits"
                        required
                        placeholder="VD: 1,5"
                    />
                    <x-input
                        label="Tín chỉ lý thuyết"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.300ms="credits_theory"
                        required
                        placeholder="VD: 1,0"
                    />
                    <x-input
                        label="Tín chỉ thực hành"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.300ms="credits_practice"
                        required
                        placeholder="VD: 0,5"
                    />
                </div>
                @error('credits_error')
                    <div class="mt-2 text-xs text-red-500">
                        {{ $message }}
                    </div>
                @enderror
                <div class="mt-3 text-xs text-gray-500">
                    Gợi ý: tổng LT + TH bằng tổng tín chỉ của môn học.
                </div>

                <div class="mt-4">
                    <x-file
                        label="Đề cương môn học (PDF)"
                        wire:model.live="syllabus_file"
                        accept=".pdf,application/pdf"
                        hint="Tối đa 10MB"
                    />
                </div>
            </x-card>
        </div>

        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">
            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button
                    label="Lưu môn học"
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
        </div>

    </div>
</div>



