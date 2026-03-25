<?php

use App\Models\GroupSubject;
use App\Models\Subject;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $code = '';
    public string $name_vi = '';
    public string $name_en = '';
    public int|string|null $group_subject_id = null;
    public int|string|null $credits = 0;
    public int|string|null $credits_theory = 0;
    public int|string|null $credits_practice = 0;
    public bool $is_active = true;
    public array $prerequisite_subject_ids = [];

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('subjects', 'code')],
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'group_subject_id' => ['nullable', 'integer', 'exists:group_subjects,id'],
            'credits' => ['required', 'integer', 'min:0', 'max:20'],
            'credits_theory' => ['required', 'integer', 'min:0', 'max:20'],
            'credits_practice' => ['required', 'integer', 'min:0', 'max:20'],
            'is_active' => ['boolean'],
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
            ->map(fn (GroupSubject $group) => [
                'id' => $group->id,
                'name' => $group->getTranslation('name', 'vi', false) ?: ('#' . $group->id),
            ])
            ->toArray();
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
            'group_subject_id' => !blank($this->group_subject_id) ? (int) $this->group_subject_id : null,
            'credits' => (int) $this->credits,
            'credits_theory' => (int) $this->credits_theory,
            'credits_practice' => (int) $this->credits_practice,
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

        $subject = Subject::query()->create($this->payload());

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
                        type="number"
                        min="0"
                        wire:model.live.debounce.300ms="credits"
                        required
                        placeholder="Nhập sô tín chỉ của môn học"
                    />
                    <x-input
                        label="Tín chỉ lý thuyết"
                        type="number"
                        min="0"
                        wire:model.live.debounce.300ms="credits_theory"
                        required
                        placeholder="Nhập số tín chỉ lý thuyết"
                    />
                    <x-input
                        label="Tín chỉ thực hành"
                        type="number"
                        min="0"
                        wire:model.live.debounce.300ms="credits_practice"
                        required
                        placeholder="Nhập số tín chỉ thực hành"
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



