<?php

use App\Models\Lecturer;
use App\Models\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads, Toast;
    public string $slug;
    public $selectedTab = 'tab-vi';

    public array $data = [];
    public $name;
    public $email;
    public $phone;
    public $academic_title;
    public $degree;
    public $academic_title_other;
    public $degree_other;
    public $introduction_vi;
    public $introduction_en;
    public $avatar;
    public $avatarUrl;

    public $academicTitleOptions = [
        ['id' => 'gs', 'name' => 'GS'],
        ['id' => 'pgs', 'name' => 'PGS'],
        ['id' => 'other', 'name' => 'Khác'],
    ];

    public $degreeOptions = [
        ['id' => 'cn', 'name' => 'CN'],
        ['id' => 'ths', 'name' => 'ThS'],
        ['id' => 'ts', 'name' => 'TS'],
        ['id' => 'tsk', 'name' => 'TSKH'],
        ['id' => 'other', 'name' => 'Khác'],
    ];

    public function rules()
    {
        return [
            'data.vi' => 'array',
            'data.vi.*.title' => 'required|string|max:255',
            'data.vi.*.content' => 'required|string',
            'data.en' => 'array',
            'data.en.*.title' => 'nullable|string|max:255',
            'data.en.*.content' => 'nullable|string',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'academic_title' => 'nullable|string|max:50',
            'degree' => 'nullable|string|max:50',
            'academic_title_other' => 'required_if:academic_title,other|nullable|string|max:255',
            'degree_other' => 'required_if:degree,other|nullable|string|max:255',
            'introduction_vi' => 'nullable|string',
            'introduction_en' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];

    }

    protected $messages = [
        'data.vi.*.title.required' => 'Tiêu đề khối (Tiếng Việt) không được để trống.',
        'data.vi.*.content.required' => 'Nội dung khối (Tiếng Việt) không được để trống.',
        'data.en.*.title.string' => 'Tiêu đề khối (Tiếng Anh) phải là chuỗi.',
        'data.en.*.title.max' => 'Tiêu đề khối (Tiếng Anh) không được vượt quá 255 ký tự.',
        'data.en.*.content.string' => 'Nội dung khối (Tiếng Anh) phải là chuỗi.',
        'data.en.*.content.max' => 'Nội dung khối (Tiếng Anh) không được vượt quá 255 ký tự.',
        'data.vi.*.title.string' => 'Tiêu đề khối (Tiếng Việt) phải là chuỗi.',
        'data.vi.*.title.max' => 'Tiêu đề khối (Tiếng Việt) không được vượt quá 255 ký tự.',
        'data.vi.*.content.string' => 'Nội dung khối (Tiếng Việt) phải là chuỗi.',
        'data.vi.*.content.max' => 'Nội dung khối (Tiếng Việt) không được vượt quá 255 ký tự.',
        'name.required' => 'Họ tên không được để trống.',
        'name.string' => 'Họ tên phải là chuỗi.',
        'name.max' => 'Họ tên không được vượt quá 255 ký tự.',
        'email.required' => 'Email không được để trống.',
        'email.email' => 'Email phải có định dạng hợp lệ.',
        'email.max' => 'Email không được vượt quá 255 ký tự.',
        'phone.string' => 'Số điện thoại phải là chuỗi.',
        'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
        'academic_title.string' => 'Học hàm phải là chuỗi.',
        'academic_title.max' => 'Học hàm không được vượt quá 50 ký tự.',
        'degree.string' => 'Học vị phải là chuỗi.',
        'degree.max' => 'Học vị không được vượt quá 50 ký tự.',
        'academic_title_other.required_if' => 'Học hàm khác là bắt buộc khi chọn "Khác".',
        'academic_title_other.string' => 'Học hàm (khác) phải là chuỗi.',
        'academic_title_other.max' => 'Học hàm (khác) không được vượt quá 255 ký tự.',
        'degree_other.required_if' => 'Học vị khác là bắt buộc khi chọn "Khác".',
        'degree_other.string' => 'Học vị (khác) phải là chuỗi.',
        'degree_other.max' => 'Học vị (khác) không được vượt quá 255 ký tự.',
//        'introduction_vi.required' => 'Nội dung giới thiệu (Tiếng Việt) không được để trống.',
        'introduction_vi.string' => 'Nội dung giới thiệu (Tiếng Việt) phải là chuỗi.',
        'introduction_en.string' => 'Nội dung giới thiệu (Tiếng Anh) phải là chuỗi.',
        'avatar.image' => 'File tải lên phải là hình ảnh.',
        'avatar.mimes' => 'Ảnh chỉ được định dạng jpg, jpeg hoặc png.',
        'avatar.max' => 'Ảnh đại diện không được lớn hơn 2MB.',
    ];

    public function mount()
    {
        $page = Page::where('slug', $this->slug)->first();
        $viData = [];
        $enData = [];

        // Cấu trúc mặc định cho 1 ngôn ngữ
        $baseStructure = [
        ];

        // Khởi tạo cho 2 ngôn ngữ
        $this->data = [
            'vi' => $baseStructure,
            'en' => $baseStructure
        ];
        if ($page && $page->content_data) {
            $viData = $page->getTranslation('content_data', 'vi', false);
            $enData = $page->getTranslation('content_data', 'en', false);

            if (is_array($viData)) {
                $this->data['vi'] = $viData['blocks'] ?? [];
            }
            if (is_array($enData)) {
                $this->data['en'] = $enData['blocks'] ?? [];
            }
        }

        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->lecturer->phone;
        $this->academic_title = $user->lecturer->academic_title;
        $this->degree = $user->lecturer->degree;
        $this->introduction_vi = $viData['introduction'] ?? '';
        $this->introduction_en = $enData['introduction'] ?? '';
        $this->applyAcademicTitleValue($user->lecturer->academic_title);
        $this->applyDegreeValue($user->lecturer->degree);
        $this->avatarUrl = $user->avatar ? asset($user->avatar) : asset('/assets/images/default-user-image.png');
//        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }
    protected function normalizeToken(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $value))) ?? '';
    }

    protected function applyAcademicTitleValue(?string $value): void
    {
        $token = $this->normalizeToken($value);

        if ($token === 'gs') {
            $this->academic_title = 'gs';
            $this->academic_title_other = null;
            return;
        }

        if ($token === 'pgs') {
            $this->academic_title = 'pgs';
            $this->academic_title_other = null;
            return;
        }

        if (filled($value)) {
            $this->academic_title = 'other';
            $this->academic_title_other = $value;
            return;
        }

        $this->academic_title = null;
        $this->academic_title_other = null;
    }

    protected function applyDegreeValue(?string $value): void
    {
        $token = $this->normalizeToken($value);

        if (in_array($token, ['cn', 'cunhan'], true)) {
            $this->degree = 'cn';
            $this->degree_other = null;
            return;
        }

        if (in_array($token, ['ths', 'ths'], true)) {
            $this->degree = 'ths';
            $this->degree_other = null;
            return;
        }

        if ($token === 'ts') {
            $this->degree = 'ts';
            $this->degree_other = null;
            return;
        }

        if (in_array($token, ['tsk', 'tskh'], true)) {
            $this->degree = 'tsk';
            $this->degree_other = null;
            return;
        }

        if (filled($value)) {
            $this->degree = 'other';
            $this->degree_other = $value;
            return;
        }

        $this->degree = null;
        $this->degree_other = null;
    }
    public function addBlock()
    {
        $uniqueId = Str::random(8);

        $newItem = [
            'id' => $uniqueId,
            'title' => '',
            'content' => ''
        ];

        $this->data['vi'][] = $newItem;
        $this->data['en'][] = $newItem;
        $this->success('Đã thêm khối mới! Kéo thả để sắp xếp lại vị trí nếu cần.');
    }

    public function updated($property)
    {
        $this->validateOnly($property);
    }

    public function updateOrder($orderedIds)
    {
        $newVi = [];
        $newEn = [];

        $viCollection = collect($this->data['vi'] ?? []);
        $enCollection = collect($this->data['en'] ?? []);

        foreach ($orderedIds as $id) {
            $itemVi = $viCollection->firstWhere('id', $id);
            $itemEn = $enCollection->firstWhere('id', $id);
            if ($itemVi) $newVi[] = $itemVi;
            if ($itemEn) $newEn[] = $itemEn;
        }

        $this->data['vi'] = $newVi;
        $this->data['en'] = $newEn;
//        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }

    public function removeDynamicBlock($id)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa khối văn bản này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveDynamicBlock',
            'id' => $id
        ]);
    }

    #[On('confirmRemoveDynamicBlock')]
    public function confirmRemoveDynamicBlock($id)
    {
        $this->data['vi'] = collect($this->data['vi'])->reject(fn($item) => $item['id'] === $id)->values()->toArray();
        $this->data['en'] = collect($this->data['en'])->reject(fn($item) => $item['id'] === $id)->values()->toArray();
        $this->success('Xóa khối văn bản thành công!');
//        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }

    public function preview()
    {
        $academicTitleValue = $this->academic_title === 'other'
            ? trim((string) $this->academic_title_other)
            : $this->academic_title;

        $degreeValue = $this->degree === 'other'
            ? trim((string) $this->degree_other)
            : $this->degree;

        $avatar = $this->avatar
            ? $this->avatar->temporaryUrl()
            : $this->avatarUrl;

        Cache::put('lecturer_preview_' . auth()->id() . '_' . $this->slug, [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'academic_title' => $academicTitleValue,
            'degree' => $degreeValue,
            'avatar' => $avatar,
            'introduction' => [
                'vi' => $this->introduction_vi,
                'en' => $this->introduction_en,
            ],
            'blocks' => [
                'vi' => $this->data['vi'] ?? [],
                'en' => $this->data['en'] ?? [],
            ],
        ], now()->addMinutes(30));

        $this->dispatch('open-preview', url: route('admin.preview.lecturer', ['slug' => $this->slug]));

        $this->success('Dữ liệu xem trước đã được lưu tạm, mở trang xem trước để kiểm tra.');
    }

    public function save()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại dữ liệu đã nhập.');
            throw $e;
        }
//        luu thong tin giang vien
        $academicTitleValue = $this->academic_title === 'other'
            ? trim((string)$this->academic_title_other)
            : $this->academic_title;

        $degreeValue = $this->degree === 'other'
            ? trim((string)$this->degree_other)
            : $this->degree;

        $user = auth()->user();
        $avatarPath = $user->avatar;
        if ($this->avatar) {
            // xóa avatar cũ
            if ($user->avatar && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
            }

            // upload avatar mới
            $avatarPath = '/storage/' . $this->avatar->store('uploads/avatars', 'public');
        }

        $user->name = $this->name;
        $user->email = $this->email;
        $user->avatar = $avatarPath;
        Lecturer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone' => $this->phone,
                'academic_title' => $academicTitleValue,
                'degree' => $degreeValue
            ]
        );
        $user->save();

        // Persist the page content_data translations (vi/en)
        $page = Page::firstOrNew(['slug' => $this->slug]);

        $page->content_data = [
            'vi' => [
                'introduction' => $this->introduction_vi,
                'blocks' => $this->data['vi'] ?? []
            ],
            'en' => [
                'introduction' => $this->introduction_en,
                'blocks' => $this->data['en'] ?? []
            ],
        ];


        $page->save();

        $this->success('Lưu cấu hình trang giảng viên thành công.');
    }
};
?>

<div x-data x-on:open-preview.window="window.open($event.detail.url, '_blank')">
    <x-slot:title>Quản lý trang giảng viên</x-slot:title>
    <x-slot:breadcrumb><span>Quản lý trang giảng viên</span></x-slot:breadcrumb>
    <x-header title="Quản lý trang giảng viên" class="pb-3 mb-5! border-b border-gray-300"></x-header>
    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        <x-card class="col-span-10 flex flex-col p-3! relative">

            {{-- NÚT THÊM KHỐI MỚI NẰM GÓC PHẢI --}}
            <div class="absolute top-1 right-2 z-4">
                <x-button icon="o-plus" class="btn-sm text-white bg-[#059669]" label="Thêm khối động"
                          spinner="addBlock" wire:click="addBlock"/>
            </div>

            <x-tabs wire:model="selectedTab">

                {{-- ================= TAB TIẾNG VIỆT ================= --}}
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Thông tin giảng viên
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="px-4 pb-4 bg-white border-t border-gray-100">
                            <x-input label="Họ tên" wire:model.live.debounce.500ms="name"
                                     required/>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-input label="Email" wire:model.live.debounce.500ms="email"
                                         required/>
                                <x-input label="Số điện thoại" wire:model.live.debounce.500ms="phone"/>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-select
                                        label="Học hàm"
                                        wire:model.live.debounce.300ms="academic_title"
                                        :options="$academicTitleOptions"
                                        placeholder="Chọn học hàm"
                                    />
                                    @if($academic_title === 'other')
                                        <x-input label="Học hàm (khác)" wire:model.live.debounce.300ms="academic_title_other"
                                                 placeholder="Nhập học hàm khác"/>
                                    @endif
                                </div>

                                <div>
                                    <x-select
                                        label="Học vị"
                                        wire:model.live.debounce.300ms="degree"
                                        :options="$degreeOptions"
                                        placeholder="Chọn học vị"
                                    />
                                    @if($degree === 'other')
                                        <x-input label="Học vị (khác)" wire:model.live.debounce.300ms="degree_other"
                                                 placeholder="Nhập học vị khác"/>
                                    @endif
                                </div>
                            </div>
                            <x-file
                                wire:model="avatar"
                                accept="image/png, image/jpeg" label="Ảnh đại diện"
                                change-text="Thay đổi ảnh">
                                <img src="{{ $avatar ? $avatar->temporaryUrl() : $avatarUrl }}" class="size-32 rounded-lg object-cover"
                                     alt="ảnh đại diện"/>
                            </x-file>
                        </div>

                    </div>
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden my-4">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Khối - Giới thiệu
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="px-4 pb-4 bg-white border-t border-gray-100">
                            <x-editor
                                label="Nội dung (Tiếng Việt)"
                                wire:model.live.debounce.500ms="introduction_vi"
                                rows="4" required
                                :config="config('tinymce_block')"
                                class="h-full"
                                folder="uploads/posts/editor"
                                placeholder="Nhập nội dung cho khối này"
                            />
                        </div>

                    </div>
                    <div x-data="{
                            sortable: null,
                            initSortable() {
                                if (this.sortable) this.sortable.destroy();
                                this.sortable = new Sortable(this.$refs.sortableList, {
                                    animation: 150,
                                    handle: '.drag-handle',
                                    onEnd: () => {
                                        let order = Array.from(this.$refs.sortableList.children)
                                            .map(el => el.dataset.id)
                                            .filter(Boolean);
                                        $wire.updateOrder(order);
                                    }
                                });
                            }
                        }" x-init="$nextTick(() => initSortable())">

                        <div x-ref="sortableList" class="space-y-4 mt-2">

                            @foreach($data['vi'] ?? [] as $index => $block)
                                <div data-id="{{ $block['id'] }}" wire:key="vi-dyn-{{ $block['id'] }}">
                                    <div x-data="{ open: true }"
                                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                                        {{-- HEADER KHỐI --}}
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3"
                                                    class="drag-handle w-5 h-5 text-gray-400 cursor-move hover:text-gray-600"/>

                                            <button type="button"
                                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                    @click="open = !open">
                                                {{--                                                📝 Khối Văn bản Đơn--}}
                                                {{$data['vi'][$index]['title'] ? 'Khối - ' . $data['vi'][$index]['title'] : ' 📝 Khối Văn bản Đơn'}}
                                            </button>

                                            <div class="flex items-center gap-1">
                                                <button type="button" class="btn btn-ghost btn-sm text-red-500"
                                                        wire:click="removeDynamicBlock('{{ $block['id'] }}')"

                                                >
                                                    <x-icon name="o-trash" class="w-4 h-4"/>
                                                </button>
                                                <x-icon name="o-chevron-down"
                                                        class="w-5 h-5 cursor-pointer transition-transform"
                                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                                            </div>
                                        </div>

                                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                                        <div x-show="open" x-collapse class="px-4 pb-4 bg-white border-t border-gray-100">
                                            <div class="space-y-2">
                                                <x-input label="Tiêu đề (Tiếng Việt)"
                                                         wire:model.live.debounce.500ms="data.vi.{{ $index }}.title"
                                                         required
                                                         placeholder="Nhập tiêu đề cho khối này"
                                                />
                                                <x-editor
                                                    label="Nội dung (Tiếng Việt)"
                                                    wire:model.live.debounce.500ms="data.vi.{{ $index }}.content"
                                                    rows="4" required
                                                    :config="config('tinymce_block')"
                                                    class="h-full"
                                                    folder="uploads/posts/editor"
                                                    placeholder="Nhập nội dung cho khối này"
                                                />
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            @endforeach

{{--                            @if(empty($data['vi']))--}}
{{--                                <div class="text-center py-10 text-gray-400 border border-dashed rounded-lg bg-gray-50">--}}
{{--                                    Chưa có khối nào. Vui lòng bấm "Thêm khối động" ở góc phải.--}}
{{--                                </div>--}}
{{--                            @endif--}}

                        </div>
                    </div>
                </x-tab>

                {{-- ================= TAB TIẾNG ANH ================= --}}
                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden mb-4">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Khối - Giới thiệu
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="px-4 pb-4 bg-white border-t border-gray-100">
                            <x-editor
                                label="Nội dung (Tiếng Anh )"
                                wire:model.live.debounce.500ms="introduction_en"
                                rows="4" required
                                :config="config('tinymce_block')"
                                class="h-full"
                                folder="uploads/posts/editor"
                                placeholder="Nhập nội dung cho khối này"
                            />
                        </div>

                    </div>
                    <div class="space-y-4 mt-6">
                        @foreach($data['en'] ?? [] as $index => $block)
                            <div data-id="{{ $block['id'] }}" wire:key="en-dyn-{{ $block['id'] }}">
                                <div x-data="{ open: true }"
                                     class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                                    {{-- HEADER KHỐI --}}
                                    <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                        <x-icon name="o-bars-3"
                                                class="drag-handle w-5 h-5 text-gray-400 cursor-move hover:text-gray-600"/>

                                        <button type="button"
                                                class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                @click="open = !open">
                                            {{--                                                📝 Khối Văn bản Đơn--}}
                                            {{$data['en'][$index]['title'] ? 'Khối - ' . $data['en'][$index]['title'] : ' 📝 Khối Văn bản Đơn'}}
                                        </button>

                                        <div class="flex items-center gap-1">
                                            <button type="button" class="btn btn-ghost btn-sm text-red-500"
                                                    wire:click="removeDynamicBlock('{{ $block['id'] }}')"

                                            >
                                                <x-icon name="o-trash" class="w-4 h-4"/>
                                            </button>
                                            <x-icon name="o-chevron-down"
                                                    class="w-5 h-5 cursor-pointer transition-transform"
                                                    x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                                        </div>
                                    </div>

                                    {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                                    <div x-show="open" x-collapse class="px-4 pb-4 bg-white border-t border-gray-100">
                                        <div class="space-y-2">
                                            <x-input label="Tiêu đề (Tiếng Anh)"
                                                     wire:model.live.debounce.500ms="data.en.{{ $index }}.title"
                                                     required
                                                     placeholder="Nhập tiêu đề cho khối này"
                                            />
                                            <x-editor
                                                label="Nội dung (Tiếng Anh)"
                                                wire:model.live.debounce.500ms="data.en.{{ $index }}.content"
                                                rows="4" required
                                                :config="config('tinymce_block')"
                                                class="h-full"
                                                folder="uploads/posts/editor"
                                                placeholder="Nhập nội dung cho khối này"
                                            />
                                        </div>
                                    </div>

                                </div>
                            </div>
                        @endforeach
{{--                        @if(empty($data['en']))--}}
{{--                            <div class="text-center py-10 text-gray-400 border border-dashed rounded-lg bg-gray-50">--}}
{{--                                Chưa có khối nào. Vui lòng bấm "Thêm khối động" ở góc phải.--}}
{{--                            </div>--}}
{{--                        @endif--}}
                    </div>

                </x-tab>

            </x-tabs>
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="{{__('Action')}}" shadow separator progress-indicator="save">
            <x-button label="Lưu cấu hình" class="bg-primary text-white my-1 w-full" wire:click="save"
                      wire:loading.attr="disabled" wire:target="save" spinner/>
            <x-button label="Xem trang" link="{{ route('client.lecturers.profile', ['slug' => $slug]) }}" external
                      class="bg-info text-white my-1 w-full"/>
            <x-button label="Xem trước" wire:click="preview" wire:loading.attr="disabled" wire:target="preview"
                      class="bg-warning text-white my-1 w-full" spinner/>
        </x-card>
    </div>
</div>
