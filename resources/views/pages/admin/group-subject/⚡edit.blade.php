<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;
use App\Models\GroupSubject;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use Toast;

    public int    $id;
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

    public function mount(int $id): void
    {
        $this->id = $id;
        $group    = GroupSubject::findOrFail($id);

        $this->name_vi        = $group->getTranslation('name', 'vi', false)        ?? '';
        $this->name_en        = $group->getTranslation('name', 'en', false)        ?? '';
        $this->description_vi = $group->getTranslation('description', 'vi', false) ?? '';
        $this->description_en = $group->getTranslation('description', 'en', false) ?? '';
        $this->sort_order     = (int) $group->sort_order;
        $this->is_active      = (bool) $group->is_active;
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

        $this->dispatch('modal:confirm', [
            'title'             => 'Bạn có chắc muốn lưu thay đổi không?',
            'icon'              => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText'  => 'Hủy',
            'method'            => 'confirmSave',
        ]);
    }

    #[On('confirmSave')]
    public function confirmSave(): void
    {
        GroupSubject::findOrFail($this->id)->update([
            'name'        => ['vi' => trim($this->name_vi),        'en' => trim($this->name_en)],
            'description' => ['vi' => trim($this->description_vi), 'en' => trim($this->description_en)],
            'sort_order'  => $this->sort_order,
            'is_active'   => $this->is_active,
        ]);

        $this->success('Cập nhật nhóm môn học thành công!', redirectTo: route('admin.group-subject.index'));
    }
};
?>

<div>
    <x-slot:title>Chỉnh sửa nhóm môn học</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.group-subject.index') }}" class="font-semibold text-slate-700">Nhóm môn học</a>
        <span class="mx-1">/</span>
        <span>Chỉnh sửa</span>
    </x-slot:breadcrumb>

    <x-header title="Chỉnh sửa nhóm môn học"
              subtitle="{{ $name_vi }}"
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
                        required
                    />
                    <x-input
                        label="Tên (Tiếng Anh)"
                        wire:model.live.debounce.400ms="name_en"
                    />
                </div>
            </x-card>

            {{-- Mô tả --}}
            <x-card title="Mô tả" shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-textarea
                        label="Mô tả (Tiếng Việt)"
                        wire:model="description_vi"
                        rows="4"
                    />
                    <x-textarea
                        label="Mô tả (Tiếng Anh)"
                        wire:model="description_en"
                        rows="4"
                    />
                </div>
            </x-card>

            {{-- Danh sách môn học thuộc nhóm (chỉ đọc) --}}
            <x-card title="Môn học trong nhóm" shadow class="p-3!">
                @php
                    $subjects = \App\Models\Subject::where('group_subject_id', $id)
                        ->orderBy('code')
                        ->get();
                @endphp

                @if($subjects->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="w-24">Mã môn</th>
                                    <th>Tên môn học</th>
                                    <th class="w-20 text-center">TC</th>
                                    <th class="w-20 text-center">LT/TH</th>
                                    <th class="w-24 text-center">Trạng thái</th>
                                    <th class="w-20 text-center">Sửa</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subjects as $subject)
                                    <tr class="hover:bg-gray-50">
                                        <td class="font-mono font-semibold text-primary">{{ $subject->code }}</td>
                                        <td>
                                            <div>{{ $subject->getTranslation('name', 'vi', false) ?: '—' }}</div>
                                            <div class="text-xs text-gray-400">{{ $subject->getTranslation('name', 'en', false) ?: 'No EN' }}</div>
                                        </td>
                                        <td class="text-center">{{ $subject->credits }}</td>
                                        <td class="text-center">{{ $subject->credits_theory }}/{{ $subject->credits_practice }}</td>
                                        <td class="text-center">
                                            @if($subject->is_active)
                                                <x-badge value="Hoạt động" class="badge-success badge-sm whitespace-nowrap" />
                                            @else
                                                <x-badge value="Tắt" class="badge-error badge-sm" />
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <x-button
                                                icon="o-pencil"
                                                class="btn-xs btn-ghost text-primary"
                                                tooltip="Chỉnh sửa môn học"
                                                link="{{ route('admin.subject.edit', $subject->id) }}"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-sm text-gray-500 py-2">Chưa có môn học nào được gán vào nhóm này.</div>
                @endif
            </x-card>

        </div>

        {{-- ======================== Sidebar ======================== --}}
        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">

            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button
                    label="Lưu thay đổi"
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

            <x-card title="Thống kê" shadow class="p-3!">
                <div class="text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Số môn học:</span>
                        <x-badge :value="$subjects->count() . ' môn'" class="badge-neutral badge-sm" />
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Đang hoạt động:</span>
                        <x-badge :value="$subjects->where('is_active', true)->count() . ' môn'" class="badge-success badge-sm" />
                    </div>
                </div>
            </x-card>

        </div>
    </div>
</div>

