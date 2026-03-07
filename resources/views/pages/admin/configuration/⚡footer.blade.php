<?php

use App\Models\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    protected $listeners = [
        'confirmSave' => 'confirmSave',
        'confirmRemoveSocial' => 'confirmRemoveSocial',
        'confirmRemoveQuickLink' => 'confirmRemoveQuickLink',
    ];

    public $selectedTab = 'tab-vi';

    public $iconOptions = [
        ['id' => 'facebook', 'name' => 'Facebook'],
        ['id' => 'instagram', 'name' => 'Instagram'],
        ['id' => 'youtube', 'name' => 'Youtube'],
        ['id' => 'tiktok', 'name' => 'Tiktok'],
        ['id' => 'globe', 'name' => 'Website Khác'],
    ];

    public array $data = [];

    #[Validate([
        'data.vi.contact.address' => 'required|string|max:255',
        'data.vi.contact.phone' => 'required|string|max:100',
        'data.vi.contact.email' => 'required|email|max:255',
        'data.vi.quick_links' => 'array',
        'data.vi.quick_links.*.name' => 'required|string|max:255',
        'data.vi.quick_links.*.url' => 'required|url|max:255',
        'data.vi.socials' => 'array',
        'data.vi.socials.*.icon' => 'required|string|max:50',
        'data.vi.socials.*.name' => 'required|string|max:255',
        'data.vi.socials.*.url' => 'required|url|max:255',

    ], as: [
        'data.vi.contact.address' => 'địa chỉ liên hệ',
        'data.vi.contact.phone' => 'số điện thoại liên hệ',
        'data.vi.contact.email' => 'email liên hệ',
        'data.vi.quick_links.*.name' => 'tên hiển thị',
        'data.vi.quick_links.*.url' => 'đường dẫn (URL)',
        'data.vi.socials.*.icon' => 'icon của mạng xã hội',
        'data.vi.socials.*.name' => 'tên hiển thị',
        'data.vi.socials.*.url' => 'đường dẫn (URL)',

    ],
        message: [
            'data.vi.contact.address.required' => 'Vui lòng nhập địa chỉ liên hệ.',
            'data.vi.contact.phone.required' => 'Vui lòng nhập số điện thoại liên hệ.',
            'data.vi.contact.email.required' => 'Vui lòng nhập email liên hệ.',
            'data.vi.quick_links.*.name.required' => 'Vui lòng nhập tên hiển thị.',
            'data.vi.quick_links.*.url.required' => 'Vui lòng nhập đường dẫn (URL).',
            'data.vi.socials.*.icon.required' => 'Vui lòng chọn icon.',
            'data.vi.socials.*.name.required' => 'Vui lòng nhập tên hiển thị',
            'data.vi.socials.*.url.required' => 'Vui lòng nhập đường dẫn (URL).',


        ]
    )]
    public function mount()
    {
        $page = Page::where('slug', 'chan-trang')->first();
        // Cấu trúc mặc định cho 1 ngôn ngữ
        $baseStructure = [
            'contact' => [
                'address' => '',
                'phone' => '',
                'email' => '',
            ],
            'quick_links' => [], // Mảng chứa các link "Thông tin"
            'socials' => [],     // Mảng chứa các link "Mạng xã hội"
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
                $this->data['vi'] = $viData ?? [];
            }
            if (is_array($enData)) {
                $this->data['en'] = $enData ?? [];
            }
        }
        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }

    public function save()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng điền đầy đủ thông tin.');
            throw $e;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn lưu cấu hình trang chân trang không?',
            'icon' => 'question',
            'confirmButtonText' => 'Có lưu!',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmSave'
        ]);
    }

    public function confirmSave()
    {
        $page = Page::updateOrCreate(
            ['slug' => 'chan-trang'],
            ['layout' => 'footer_page']
        );

        $page->setTranslations('content_data', $this->data);
        $page->save();

        $this->success('Lưu cấu hình trang Giới thiệu thành công!');
    }

    public function updated()
    {
        $this->validate();
        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }

    public function addQuickLink()
    {
        $newItem = [
            'id' => Str::random(8),
            'name' => '',
            'url' => ''
        ];
        $this->data['vi']['quick_links'][] = $newItem;
        $this->data['en']['quick_links'][] = $newItem;
        $this->success('Thêm thông tin thành công!');
    }

    public function removeQuickLink($id)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa thông tin này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveQuickLink',
            'id' => $id
        ]);
    }

    public function confirmRemoveQuickLink($id)
    {
        $this->data['vi']['quick_links'] = collect($this->data['vi']['quick_links'])->reject(fn($item) => $item['id'] === $id)->values()->toArray();
        $this->data['en']['quick_links'] = collect($this->data['en']['quick_links'])->reject(fn($item) => $item['id'] === $id)->values()->toArray();
        $this->success('Xóa thông tin thành công!');
        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }

    public function addSocial()
    {
        $newItem = [
            'id' => Str::random(8),
            'icon' => '',
            'name' => '',
            'url' => ''
        ];
        $this->data['vi']['socials'][] = $newItem;
        $this->data['en']['socials'][] = $newItem;
        $this->success('Thêm mạng xã hội thành công!');
        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }

    public function removeSocial($id)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa mạng xã hội này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveSocial',
            'id' => $id
        ]);
    }

    public function confirmRemoveSocial($id)
    {
        $this->data['vi']['socials'] = collect($this->data['vi']['socials'])->reject(fn($item) => $item['id'] === $id)->values()->toArray();
        $this->data['en']['socials'] = collect($this->data['en']['socials'])->reject(fn($item) => $item['id'] === $id)->values()->toArray();
        $this->success('Xóa mạng xã hội thành công!');
        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }

    public function updateOrder($type, $orderedIds)
    {
        $newVi = [];
        $newEn = [];

        $viCollection = collect($this->data['vi'][$type] ?? []);
        $enCollection = collect($this->data['en'][$type] ?? []);

        foreach ($orderedIds as $id) {
            $itemVi = $viCollection->firstWhere('id', $id);
            $itemEn = $enCollection->firstWhere('id', $id);
            if ($itemVi) $newVi[] = $itemVi;
            if ($itemEn) $newEn[] = $itemEn;
        }

        $this->data['vi'][$type] = $newVi;
        $this->data['en'][$type] = $newEn;
        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
    }

    public function preview()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng điền đầy đủ thông tin.');
            throw $e;
        }
        Cache::put('preview_footer_data', $this->data, now()->addMinutes(15));
        $this->dispatch('open-new-tab',
            url: route('admin.preview.header-footer')
        );
    }
};
?>

<div>
    <x-slot:title>{{ __('Footer configuration') }}</x-slot:title>
    <x-slot:breadcrumb><span>{{__('Footer configuration')}}</span></x-slot:breadcrumb>
    <x-header title="{{__('Footer configuration')}}" class="pb-3 mb-5! border-b border-gray-300"></x-header>
    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        <x-card class="col-span-10 flex flex-col p-3!">
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
                                Địa chỉ liên hệ
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input label="Địa chỉ" wire:model.live.debounce.500ms="data.vi.contact.address"
                                     required/>
                            <x-input label="Số điện thoại" wire:model.live.debounce.500ms="data.vi.contact.phone"
                                     required/>
                            <x-input label="Email" wire:model.live.debounce.500ms="data.vi.contact.email" required/>
                        </div>

                    </div>
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden my-4 relative">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Thông tin
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
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
                                                $wire.updateOrder('quick_links',order);
                                            }
                                        });
                                    }
                                }" x-init="$nextTick(() => initSortable())">
                                <div x-ref="sortableList">
                                    @foreach($data['vi']['quick_links'] ?? [] as $index => $block)
                                        <div data-id="{{$block['id']}}" wire:key="vi-qkl-{{ $block['id'] }}"
                                             class="flex items-center gap-3 mb-4 shadow-sm px-2 pb-2 border border-gray-400 rounded-lg bg-gray-50">
                                            <x-icon name="o-bars-3"
                                                    class="drag-handle w-5 h-5 text-gray-400 cursor-move hover:text-gray-600 mt-3"/>
                                            <div class="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-0 lg:gap-3 ">
                                                <x-input label="Tên hiển thị"
                                                         wire:model.live.debounce.500ms="data.vi.quick_links.{{$index}}.name"
                                                         required/>
                                                <x-input label="Đường dẫn (URL)"
                                                         wire:model.live.debounce.500ms="data.vi.quick_links.{{$index}}.url"
                                                         required/>
                                            </div>
                                            <button type="button" class="btn btn-ghost btn-sm text-red-500 mt-3"
                                                    wire:click="removeQuickLink('{{ $block['id'] }}')">
                                                <x-icon name="o-trash" class="w-4 h-4"/>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
                        <div class="absolute top-1.5 right-10 z-4">
                            <x-button icon="o-plus" class="btn-sm text-white bg-[#059669]" label="Thêm"
                                      spinner="addQuickLink" x-bind:class="open ? '' : 'hidden'"
                                      wire:click="addQuickLink"/>
                        </div>
                    </div>
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden my-4 relative">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Mạng xã hội (Kết nối)
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
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
                                                $wire.updateOrder('socials', order);
                                            }
                                        });
                                    }
                                }" x-init="$nextTick(() => initSortable())">
                                <div x-ref="sortableList">
                                    @foreach($data['vi']['socials'] ?? [] as $index => $block)
                                        <div data-id="{{ $block['id'] }}" wire:key="vi-sol-{{ $block['id'] }}"
                                             class="flex items-center gap-3 mb-4 shadow-sm px-2 pb-2 border border-gray-400 rounded-lg bg-gray-50">
                                            <x-icon name="o-bars-3"
                                                    class="drag-handle w-5 h-5 text-gray-400 cursor-move hover:text-gray-600 mt-3"/>
                                            <div
                                                class="flex-1 grid grid-cols-1 lg:grid-cols-[1fr_2fr_2fr] gap-0 lg:gap-3">
                                                <x-select
                                                    label="Icon"
                                                    wire:model="data.vi.socials.{{$index}}.icon"
                                                    :options="$iconOptions"
                                                    placeholder="Chọn icon"
                                                    placeholder-value="0"
                                                    required
                                                />
                                                <x-input label="Tên hiển thị"
                                                         wire:model.live.debounce.500ms="data.vi.socials.{{$index}}.name"
                                                         required/>
                                                <x-input label="Đường dẫn (URL)"
                                                         wire:model.live.debounce.500ms="data.vi.socials.{{$index}}.url"
                                                         required/>
                                            </div>
                                            <button type="button" class="btn btn-ghost btn-sm text-red-500 mt-3"
                                                    wire:click="removeSocial('{{ $block['id'] }}')">
                                                <x-icon name="o-trash" class="w-4 h-4"/>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
                        <div class="absolute top-1.5 right-10 z-4">
                            <x-button icon="o-plus" class="btn-sm text-white bg-[#059669]" label="Thêm"
                                      spinner="addSocial" x-bind:class="open ? '' : 'hidden'" wire:click="addSocial"/>
                        </div>
                    </div>
                </x-tab>

                {{-- ================= TAB TIẾNG ANH ================= --}}
                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Địa chỉ liên hệ
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                            <x-input label="Địa chỉ" wire:model.live.debounce.500ms="data.en.contact.address"
                                     required/>
                            <x-input label="Số điện thoại" wire:model.live.debounce.500ms="data.en.contact.phone"
                                     required/>
                            <x-input label="Email" wire:model.live.debounce.500ms="data.en.contact.email" required/>
                        </div>

                    </div>
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden my-4 relative">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Thông tin
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
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
                                                $wire.updateOrder('quick_links',order);
                                            }
                                        });
                                    }
                                }" x-init="$nextTick(() => initSortable())">
                                <div x-ref="sortableList">
                                    @foreach($data['vi']['quick_links'] ?? [] as $index => $block)
                                        <div data-id="{{$block['id']}}" wire:key="vi-qkl-{{ $block['id'] }}"
                                             class="flex items-center gap-3 mb-4 shadow-sm px-2 pb-2 border border-gray-400 rounded-lg bg-gray-50">
                                            <x-icon name="o-bars-3"
                                                    class="drag-handle w-5 h-5 text-gray-400 cursor-move hover:text-gray-600 mt-3"/>
                                            <div class="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-0 lg:gap-3 ">
                                                <x-input label="Tên hiển thị"
                                                         wire:model.live.debounce.500ms="data.en.quick_links.{{$index}}.name"
                                                         required/>
                                                <x-input label="Đường dẫn (URL)"
                                                         wire:model.live.debounce.500ms="data.en.quick_links.{{$index}}.url"
                                                         required/>
                                            </div>
{{--                                            <button type="button" class="btn btn-ghost btn-sm text-red-500 mt-3"--}}
{{--                                                    wire:click="removeQuickLink('{{ $block['id'] }}')">--}}
{{--                                                <x-icon name="o-trash" class="w-4 h-4"/>--}}
{{--                                            </button>--}}
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
{{--                        <div class="absolute top-1.5 right-10 z-4">--}}
{{--                            <x-button icon="o-plus" class="btn-sm text-white bg-[#059669]" label="Thêm"--}}
{{--                                      spinner="addQuickLink" x-bind:class="open ? '' : 'hidden'"--}}
{{--                                      wire:click="addQuickLink"/>--}}
{{--                        </div>--}}
                    </div>
                    <div x-data="{ open: true }"
                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden my-4 relative">

                        {{-- HEADER KHỐI --}}
                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                            <button type="button"
                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                    @click="open = !open">
                                Mạng xã hội (Kết nối)
                            </button>

                            <div class="flex items-center gap-1">
                                <x-icon name="o-chevron-down"
                                        class="w-5 h-5 cursor-pointer transition-transform"
                                        x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                        </div>

                        {{-- NỘI DUNG FORM NHẬP LIỆU THEO TYPE --}}
                        <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
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
                                                $wire.updateOrder('socials', order);
                                            }
                                        });
                                    }
                                }" x-init="$nextTick(() => initSortable())">
                                <div x-ref="sortableList">
                                    @foreach($data['vi']['socials'] ?? [] as $index => $block)
                                        <div data-id="{{ $block['id'] }}" wire:key="vi-sol-{{ $block['id'] }}"
                                             class="flex items-center gap-3 mb-4 shadow-sm px-2 pb-2 border border-gray-400 rounded-lg bg-gray-50">
                                            <x-icon name="o-bars-3"
                                                    class="drag-handle w-5 h-5 text-gray-400 cursor-move hover:text-gray-600 mt-3"/>
                                            <div
                                                class="flex-1 grid grid-cols-1 lg:grid-cols-[1fr_2fr_2fr] gap-0 lg:gap-3">
                                                <x-select
                                                    label="Icon"
                                                    wire:model="data.en.socials.{{$index}}.icon"
                                                    :options="$iconOptions"
                                                    placeholder="Chọn icon"
                                                    placeholder-value="0"
                                                    required
                                                />
                                                <x-input label="Tên hiển thị"
                                                         wire:model.live.debounce.500ms="data.en.socials.{{$index}}.name"
                                                         required/>
                                                <x-input label="Đường dẫn (URL)"
                                                         wire:model.live.debounce.500ms="data.en.socials.{{$index}}.url"
                                                         required/>
                                            </div>
{{--                                            <button type="button" class="btn btn-ghost btn-sm text-red-500 mt-3"--}}
{{--                                                    wire:click="removeSocial('{{ $block['id'] }}')">--}}
{{--                                                <x-icon name="o-trash" class="w-4 h-4"/>--}}
{{--                                            </button>--}}
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
{{--                        <div class="absolute top-1.5 right-10 z-4">--}}
{{--                            <x-button icon="o-plus" class="btn-sm text-white bg-[#059669]" label="Thêm"--}}
{{--                                      spinner="addSocial" x-bind:class="open ? '' : 'hidden'" wire:click="addSocial"/>--}}
{{--                        </div>--}}
                    </div>
                </x-tab>

            </x-tabs>
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="{{__('Action')}}" shadow separator progress-indicator="save">
            <x-button label="Lưu cấu hình" class="bg-primary text-white my-1 w-full" wire:click="save"
                      wire:loading.attr="disabled" wire:target="save" spinner/>
            <x-button label="Xem trang" link="{{route('client.home')}}" external
                      class="bg-warning text-white my-1 w-full"/>
            <x-button label="Xem trước" wire:click="preview" wire:loading.attr="disabled" wire:target="preview"
                      class="bg-success text-white my-1 w-full" spinner/>
        </x-card>
    </div>
</div>
