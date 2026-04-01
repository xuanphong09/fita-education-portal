<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Page;
use Livewire\Attributes\Validate;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

new class extends Component {
    use WithFileUploads, Toast;

    protected $listeners = [
        'confirmRemoveBlock' => 'confirmRemoveBlock',
        'confirmSave' => 'confirmSave'
    ];
    public $selectedTab = 'tab-vi';

    // Bỏ validation cứng ở đây để linh hoạt cho các khối
    public array $data = [];

    #[Validate([
        'data.vi.dynamicBlocks.*.data.description' => 'required_if:data.vi.dynamicBlocks.*.type,generalIntroduction,blockSingle|nullable|string',
        'data.vi.dynamicBlocks.*.data.title' => 'required_if:data.vi.dynamicBlocks.*.type,blockSingle|nullable|string',
        'data.vi.dynamicBlocks.*.data.*.title' => 'required_if:data.vi.dynamicBlocks.*.type,block3Columns|nullable|string',
        'data.vi.dynamicBlocks.*.data.*.content' => 'required_if:data.vi.dynamicBlocks.*.type,block3Columns|nullable|string',
//        'data.vi.dynamicBlocks.*.data.photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',

    ], as: [
        'data.vi.dynamicBlocks.*.data.description' => 'mô tả ngắn',
        'data.vi.dynamicBlocks.*.data.*.title' => 'tiêu đề cột',
        'data.vi.dynamicBlocks.*.data.*.content' => 'nội dung cột',
        'data.vi.dynamicBlocks.*.data.photo' => 'hình ảnh',
    ],
        message: [
            'data.vi.dynamicBlocks.*.data.description.required_if' => 'Trường này là bắt buộc, không được để trống.',
            'data.vi.dynamicBlocks.*.data.title.required_if' => 'Trường này là bắt buộc, không được để trống.',
            'data.vi.dynamicBlocks.*.data.*.title.required_if' => 'Bạn chưa nhập tiêu đề cho cột.',
            'data.vi.dynamicBlocks.*.data.*.content.required_if' => 'Bạn chưa nhập nội dung cho cột.',
            'data.vi.dynamicBlocks.*.data.photo.image' => 'File phải là hình ảnh.',
            'data.vi.dynamicBlocks.*.data.photo.mimes' => 'Hình ảnh phải có định dạng jpeg, png, jpg hoặc webp.',
            'data.vi.dynamicBlocks.*.data.photo.max' => 'Hình ảnh không được vượt quá 2MB.',
        ]
    )]
    public function mount()
    {
        $page = Page::where('slug', 'gioi-thieu')->first();

        // Cấu trúc mặc định rỗng
        $baseStructure = [
            'dynamicBlocks' => []
        ];

        $this->data = ['vi' => $baseStructure, 'en' => $baseStructure];

        if ($page && $page->content_data) {
            $viData = $page->getTranslation('content_data', 'vi', false);
            $enData = $page->getTranslation('content_data', 'en', false);

            if (is_array($viData)) {
                $this->data['vi']['dynamicBlocks'] = $viData['dynamicBlocks'] ?? [];
            }
            if (is_array($enData)) {
                $this->data['en']['dynamicBlocks'] = $enData['dynamicBlocks'] ?? [];
            }
        }
    }

    public function updated($property)
    {
        // 1. Nếu đang thao tác với ô upload ảnh trong mảng động
        if (str_contains($property, '.data.photo')) {

            // Dùng hàm data_get của Laravel để lôi file vừa chọn ra xem thử
            $file = data_get($this, $property);

            // Nếu nó là file thật (không phải chuỗi đường dẫn cũ) thì mới kiểm tra đuôi và dung lượng
            if ($file && !is_string($file)) {
                $this->validateOnly($property, [
                    $property => 'image|mimes:jpeg,png,jpg,webp|max:2048'
                ], [], [
                    $property => 'hình ảnh'
                ]);
            }

        } // 2. Nếu thao tác với các ô text bình thường
        else {
            $this->validate();
        }
        $this->syncToPreviewCache();
    }

    // ==========================================
    // THÊM - XÓA - SẮP XẾP KHỐI ĐỘNG
    // ==========================================

    public function addBlock($type)
    {
        $uniqueId = Str::random(8);

        $newItem = [
            'id' => $uniqueId,
            'type' => $type,
            'data' => []
        ];

        // Khởi tạo dữ liệu mẫu cho từng loại khối
        if ($type === 'generalIntroduction') {
            $newItem['data'] = ['photo' => '', 'description' => ''];
        } elseif ($type === 'block3Columns') {
            $newItem['data'] = [
                ['title' => '', 'content' => ''],
                ['title' => '', 'content' => ''],
                ['title' => '', 'content' => ''],
            ];
        } elseif ($type === 'blockSingle') {
            $newItem['data'] = ['title' => '', 'description' => ''];
        }

        $this->data['vi']['dynamicBlocks'][] = $newItem;
        $this->data['en']['dynamicBlocks'][] = $newItem;
        $this->success('Đã thêm khối mới! Kéo thả để sắp xếp lại vị trí nếu cần.');
    }

    public function removeDynamicBlock($id)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa khối này không?',
            'icon' => 'warning',
            'confirmButtonText' => 'Có xóa!',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveBlock',
            'id' => $id
        ]);
    }

    public function confirmRemoveBlock($id)
    {
        $this->data['vi']['dynamicBlocks'] = collect($this->data['vi']['dynamicBlocks'])->reject(fn($item) => $item['id'] === $id)->values()->toArray();
        $this->data['en']['dynamicBlocks'] = collect($this->data['en']['dynamicBlocks'])->reject(fn($item) => $item['id'] === $id)->values()->toArray();
        $this->syncToPreviewCache();
        $this->success('Khối đã được xóa thành công!');
    }

    public function updateOrder($orderedIds)
    {
        $newVi = [];
        $newEn = [];

        $viCollection = collect($this->data['vi']['dynamicBlocks'] ?? []);
        $enCollection = collect($this->data['en']['dynamicBlocks'] ?? []);

        foreach ($orderedIds as $id) {
            $itemVi = $viCollection->firstWhere('id', $id);
            $itemEn = $enCollection->firstWhere('id', $id);
            if ($itemVi) $newVi[] = $itemVi;
            if ($itemEn) $newEn[] = $itemEn;
        }

        $this->data['vi']['dynamicBlocks'] = $newVi;
        $this->data['en']['dynamicBlocks'] = $newEn;
        $this->syncToPreviewCache();
    }

    // ==========================================
    // LƯU DỮ LIỆU
    // ==========================================

    public function save()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng điền đầy đủ thông tin.');
            throw $e;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn lưu cấu hình trang giới thiệu không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmSave'
        ]);
    }

    public function confirmSave()
    {
        // 1. Quét qua các khối Tiếng Việt để xử lý upload ảnh (nếu có)
        foreach ($this->data['vi']['dynamicBlocks'] as $index => $block) {
            if ($block['type'] === 'generalIntroduction') {
                $photo = $block['data']['photo'] ?? null;

                // Nếu người dùng vừa chọn file mới (UploadedFile)
                if ($photo instanceof UploadedFile) {
                    $path = $photo->store('uploads/pages', 'public');

                    // Ghi đè đường dẫn chuỗi vào mảng của cả 2 ngôn ngữ
                    $this->data['vi']['dynamicBlocks'][$index]['data']['photo'] = '/storage/' . $path;
                    $this->data['en']['dynamicBlocks'][$index]['data']['photo'] = '/storage/' . $path;
                }
            }
        }

        // 2. Lưu vào Database
        $page = Page::updateOrCreate(
            ['slug' => 'gioi-thieu'],
            ['layout' => 'introduction_page']
        );

        $page->setTranslations('content_data', $this->data);
        $page->save();

        $this->success('Lưu cấu hình trang Giới thiệu thành công!');
    }

    protected function syncToPreviewCache()
    {
        $previewData = [];

        $previewData = $this->data;

        // 3. Quét qua 2 ngôn ngữ để tìm và biến Object Ảnh thành Link Tạm (String)
        foreach (['vi', 'en'] as $lang) {
            if (!empty($previewData[$lang]['dynamicBlocks'])) {
                foreach ($previewData[$lang]['dynamicBlocks'] as $index => $block) {

                    if ($block['type'] === 'generalIntroduction') {
                        $photo = $block['data']['photo'] ?? null;

                        // Nếu phát hiện nó là File vừa upload (Object) chứ không phải chuỗi đường dẫn cũ
                        if ($photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {

                            // Lấy đường dẫn xem trước tạm thời của Livewire và gán đè vào
                            $previewData[$lang]['dynamicBlocks'][$index]['data']['photo'] = $photo->temporaryUrl();

                        }
                    }

                }
            }
        }

        Cache::put('preview_intro_data', $previewData, now()->addMinutes(15));
    }

    public function preview()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng điền đầy đủ thông tin.');
            throw $e;
        }
        $this->syncToPreviewCache();
        $this->dispatch('open-new-tab',
            url: route('admin.preview.introduction')
        );
    }
};
?>

<div>
    <x-slot:title>{{ __('Configure introduction page') }}</x-slot:title>
    <x-slot:breadcrumb><span>{{__('Configure introduction page')}}</span></x-slot:breadcrumb>
    <x-header title="{{__('Configure introduction page')}}" class="pb-3 mb-5! border-b border-gray-300"></x-header>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        <x-card class="col-span-10 flex flex-col p-3! relative">

            {{-- NÚT THÊM KHỐI MỚI NẰM GÓC PHẢI --}}
            <div class="absolute top-1 right-2 z-4">
                <x-dropdown right>
                    <x-slot:trigger>
                        <x-button icon="o-plus" class="btn-sm text-white bg-[#059669]" label="Thêm khối động"
                                  spinner="addBlock"/>
                    </x-slot:trigger>
                    <x-popover position="left-start" offset="20">
                        <x-slot:trigger class="w-full">
                            <x-menu-item title="Khối Giới thiệu (Ảnh + Chữ)"
                                         wire:click="addBlock('generalIntroduction')"
                                         class="active:bg-gray-300"/>
                        </x-slot:trigger>
                        <x-slot:content class="border border-gray-200 w-50! ">
                            <img src="{{asset('/assets/images/wireframe/img-text.png')}}" alt="">
                        </x-slot:content>
                    </x-popover>
                    <x-popover position="left-start" offset="20">
                        <x-slot:trigger class="w-full">
                            <x-menu-item title="Khối Danh sách 3 cột" wire:click="addBlock('block3Columns')"
                                         class="active:bg-gray-300 w-full"/>
                        </x-slot:trigger>
                        <x-slot:content class="border border-gray-200 w-60!">
                            <img src="{{asset('/assets/images/wireframe/3-block.png')}}" alt="">
                        </x-slot:content>
                    </x-popover>
                    <x-popover position="left-start" offset="20">
                        <x-slot:trigger class="w-full">
                            <x-menu-item title="Khối Đơn (Chữ)" wire:click="addBlock('blockSingle')"
                                         class="active:bg-gray-300"/>
                        </x-slot:trigger>
                        <x-slot:content class="border border-gray-200 w-60! ">
                            <img src="{{asset('/assets/images/wireframe/single-text.png')}}" alt="">
                        </x-slot:content>
                    </x-popover>
                </x-dropdown>
            </div>

            <x-tabs wire:model="selectedTab">

                {{-- ================= TAB TIẾNG VIỆT ================= --}}
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">

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

                        <div x-ref="sortableList" class="space-y-4 mt-6">

                            @foreach($data['vi']['dynamicBlocks'] ?? [] as $index => $block)
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
                                                @if($block['type'] === 'generalIntroduction')
                                                    🖼️ Khối Giới thiệu (Ảnh & Chữ)
                                                @elseif($block['type'] === 'block3Columns')
                                                    📊 Khối Danh sách 3 cột
                                                @elseif($block['type'] === 'blockSingle')
                                                    📝 Khối Văn bản Đơn
                                                @endif
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
                                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                                            @switch($block['type'])

                                                @case('generalIntroduction')
                                                    <div class="space-y-4">
                                                        <x-file
                                                            wire:model.live="data.vi.dynamicBlocks.{{ $index }}.data.photo"
                                                            accept="image/png, image/jpeg" label="Hình ảnh"
                                                            change-text="Thay đổi ảnh">
                                                            @php $photoVal = $data['vi']['dynamicBlocks'][$index]['data']['photo'] ?? ''; @endphp
                                                            <img
                                                                src="{{ is_string($photoVal) && $photoVal != '' ? asset($photoVal) : asset('/assets/images/LogoKhoaCNTT.png') }}"
                                                                class="h-32 rounded-lg object-cover"/>
                                                        </x-file>
                                                        <x-textarea label="Mô tả ngắn"
                                                                    wire:model.live.debounce.500ms="data.vi.dynamicBlocks.{{ $index }}.data.description"
                                                                    rows="4" required/>
                                                    </div>
                                                    @break

                                                @case('block3Columns')
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                        @foreach($block['data'] as $colIndex => $col)
                                                            <div class="p-3 bg-gray-50 border rounded-lg space-y-3 border-gray-400">
                                                                <h4 class="font-bold">Cột {{ $colIndex + 1 }}</h4>
                                                                <x-input label="Tiêu đề"
                                                                         wire:model.live.debounce.500ms="data.vi.dynamicBlocks.{{ $index }}.data.{{ $colIndex }}.title"
                                                                         required/>
                                                                <x-textarea label="Nội dung"
                                                                            wire:model.live.debounce.500ms="data.vi.dynamicBlocks.{{ $index }}.data.{{ $colIndex }}.content"
                                                                            rows="4" required/>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    @break

                                                @case('blockSingle')
                                                    <div class="space-y-4">
                                                        <x-input label="Tiêu đề"
                                                                 wire:model.live.debounce.500ms="data.vi.dynamicBlocks.{{ $index }}.data.title"
                                                                 required/>
                                                        <x-textarea label="Nội dung"
                                                                    wire:model.live.debounce.500ms="data.vi.dynamicBlocks.{{ $index }}.data.description"
                                                                    rows="4" required/>
                                                    </div>
                                                    @break

                                            @endswitch
                                        </div>

                                    </div>
                                </div>
                            @endforeach

                            @if(empty($data['vi']['dynamicBlocks']))
                                <div class="text-center py-10 text-gray-400 border border-dashed rounded-lg bg-gray-50">
                                    Chưa có khối nào. Vui lòng bấm "Thêm khối động" ở góc phải.
                                </div>
                            @endif

                        </div>
                    </div>
                </x-tab>

                {{-- ================= TAB TIẾNG ANH ================= --}}
                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">

                    <div class="space-y-4 mt-6">
                        @foreach($data['en']['dynamicBlocks'] ?? [] as $index => $block)
                            <div wire:key="en-dyn-{{ $block['id'] }}">
                                <div x-data="{ open: true }"
                                     class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                                    <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                        <button type="button"
                                                class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                @click="open = !open">
                                            @if($block['type'] === 'generalIntroduction')
                                                🖼️ General Introduction (Photo & Text)
                                            @elseif($block['type'] === 'block3Columns')
                                                📊 3 Columns Block
                                            @elseif($block['type'] === 'blockSingle')
                                                📝 Single Text Block
                                            @endif
                                        </button>
                                        <x-icon name="o-chevron-down"
                                                class="w-5 h-5 cursor-pointer transition-transform"
                                                x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                                    </div>

                                    <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                                        @switch($block['type'])

                                            @case('generalIntroduction')
                                                <div class="space-y-4">
                                                    <p class="text-sm text-gray-500 italic">* Hình ảnh đã được đồng bộ
                                                        với Tiếng Việt.</p>
                                                    <x-textarea label="Mô tả ngắn (EN)"
                                                                wire:model.live.debounce.500ms="data.en.dynamicBlocks.{{ $index }}.data.description"
                                                                rows="4"/>
                                                </div>
                                                @break

                                            @case('block3Columns')
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                    @foreach($block['data'] as $colIndex => $col)
                                                        <div class="p-3 bg-gray-50 border rounded-lg space-y-3">
                                                            <h4 class="font-bold">Column {{ $colIndex + 1 }}</h4>
                                                            <x-input label="Title"
                                                                     wire:model.live.debounce.500ms="data.en.dynamicBlocks.{{ $index }}.data.{{ $colIndex }}.title"/>
                                                            <x-textarea label="Content"
                                                                        wire:model.live.debounce.500ms="data.en.dynamicBlocks.{{ $index }}.data.{{ $colIndex }}.content"
                                                                        rows="4"/>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                @break

                                            @case('blockSingle')
                                                <div class="space-y-4">
                                                    <x-input label="Title (EN)"
                                                             wire:model.live.debounce.500ms="data.en.dynamicBlocks.{{ $index }}.data.title"/>
                                                    <x-textarea label="Description (EN)"
                                                                wire:model.live.debounce.500ms="data.en.dynamicBlocks.{{ $index }}.data.description"
                                                                rows="4"/>
                                                </div>
                                                @break

                                        @endswitch
                                    </div>

                                </div>
                            </div>
                        @endforeach
                            @if(empty($data['en']['dynamicBlocks']))
                                <div class="text-center py-10 text-gray-400 border border-dashed rounded-lg bg-gray-50">
                                    Chưa có khối nào. Vui lòng bấm "Thêm khối động" ở góc phải.
                                </div>
                            @endif
                    </div>

                </x-tab>

            </x-tabs>
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="{{__('Action')}}" shadow separator progress-indicator="save">
            <x-button label="Lưu cấu hình" class="bg-primary text-white my-1 w-full" wire:click="save"
                      wire:loading.attr="disabled" wire:target="save" spinner/>
            <x-button label="Xem trang" link="{{route('client.information')}}" external
                      class="bg-warning text-white my-1 w-full"/>
            <x-button label="Xem trước" wire:click="preview" wire:loading.attr="disabled" wire:target="preview"
                      class="bg-success text-white my-1 w-full" spinner/>
        </x-card>
    </div>
</div>
