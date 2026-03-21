<?php

use Livewire\Component;
use Mary\Traits\Toast;
use App\Models\GroupSubject;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use Toast;

    public string $name_vi         = '';
    public string $name_en         = '';
    public string $description_vi  = '';
    public string $description_en  = '';
    public int    $sort_order      = 0;
    public bool   $is_active       = true;

    protected function rules(): array
    {
        return [
            'name_vi'        => ['required', 'string', 'max:255'],
            'name_en'        => ['nullable', 'string', 'max:255'],
            'description_vi' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'sort_order'     => ['required', 'integer', 'min:0'],
            'is_active'      => ['boolean'],
        ];
    }

    protected $messages = [
        'name_vi.required' => 'Tên nhóm môn học (Tiếng Việt) không được để trống.',
        'name_vi.max'      => 'Tên nhóm không được vượt quá 255 ký tự.',
        'sort_order.min'   => 'Thứ tự phải lớn hơn hoặc bằng 0.',
    ];

    public function mount(): void
    {
        // gợi ý sort_order kế tiếp
        $this->sort_order = (int) GroupSubject::max('sort_order') + 1;
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    public function save(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin đã nhập.');
            throw $e;
        }

        GroupSubject::create([
            'name'        => ['vi' => trim($this->name_vi),        'en' => trim($this->name_en)],
            'description' => ['vi' => trim($this->description_vi), 'en' => trim($this->description_en)],
            'sort_order'  => $this->sort_order,
            'is_active'   => $this->is_active,
        ]);

        $this->success('Tạo nhóm môn học thành công!', redirectTo: route('admin.group-subject.index'));
    }
};
?>

<div>
    <x-slot:title>Tạo nhóm môn học</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.group-subject.index') }}" class="font-semibold text-slate-700">Nhóm môn học</a>
        <span class="mx-1">/</span>
        <span>Tạo mới</span>
    </x-slot:breadcrumb>

    <x-header title="Tạo nhóm môn học mới"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"/>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        {{-- ======================== Main form ======================== --}}
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">

            {{-- Tên --}}
            <x-card title="Tên nhóm môn học" shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Tên (Tiếng Việt)"
                        wire:model.live.debounce.400ms="name_vi"
                        placeholder="VD: Khối kiến thức đại cương"
                        required
                    />
                    <x-input
                        label="Tên (Tiếng Anh)"
                        wire:model.live.debounce.400ms="name_en"
                        placeholder="VD: General Education"
                    />
                </div>
            </x-card>

            {{-- Mô tả --}}
            <x-card title="Mô tả" shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-textarea
                        label="Mô tả (Tiếng Việt)"
                        wire:model="description_vi"
                        placeholder="Mô tả ngắn về nhóm môn học..."
                        rows="4"
                    />
                    <x-textarea
                        label="Mô tả (Tiếng Anh)"
                        wire:model="description_en"
                        placeholder="Short description..."
                        rows="4"
                    />
                </div>
            </x-card>

        </div>

        {{-- ======================== Sidebar ======================== --}}
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">

            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button
                    label="Lưu nhóm"
                    class="bg-primary text-white w-full my-1"
                    wire:click="save"
                    spinner="save"
                />
                <x-button
                    label="Trở lại"
                    class="bg-warning text-white w-full my-1"
                    link="{{ route('admin.group-subject.index') }}"
                />
            </x-card>

            <x-card title="Cài đặt" shadow class="p-3!">
                <x-input
                    label="Thứ tự hiển thị"
                    type="number"
                    min="0"
                    wire:model="sort_order"
                    hint="Số nhỏ hơn hiện trên trên"
                />
                <div class="mt-4">
                    <x-toggle
                        label="Kích hoạt"
                        wire:model="is_active"
                        class="toggle-primary"
                    />
                </div>
            </x-card>

        </div>
    </div>
</div>

