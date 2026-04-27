<?php

use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    protected $listeners = [
        'confirmSave' => 'confirmSave',
        'confirmRemoveMenuItem' => 'confirmRemoveMenuItem',
        'confirmRemoveChildItem' => 'confirmRemoveChildItem',
        'confirmRemoveGrandChildItem' => 'confirmRemoveGrandChildItem',
    ];

    public $selectedTab = 'tab-vi';

    public array $data = [];
    public array $selectedSectionByLocale = [
        'vi' => 'main_menu',
        'en' => 'main_menu',
    ];

    #[Validate([
        'data.vi.menu_items' => 'array',
        'data.vi.menu_items.*.name' => 'required|string|max:255',
        'data.vi.menu_items.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
        'data.vi.menu_items.*.children' => 'array',
        'data.vi.menu_items.*.children.*.name' => 'required|string|max:255',
        'data.vi.menu_items.*.children.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
        'data.vi.menu_items.*.children.*.children' => 'array',
        'data.vi.menu_items.*.children.*.children.*.name' => 'required|string|max:255',
        'data.vi.menu_items.*.children.*.children.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
        'data.vi.top_menu_items' => 'array',
        'data.vi.top_menu_items.*.name' => 'required|string|max:255',
        'data.vi.top_menu_items.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
        'data.vi.top_menu_items.*.children' => 'array',
        'data.vi.top_menu_items.*.children.*.name' => 'required|string|max:255',
        'data.vi.top_menu_items.*.children.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],

        'data.en.menu_items' => 'array',
        'data.en.menu_items.*.name' => 'required|string|max:255',
        'data.en.menu_items.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
        'data.en.menu_items.*.children' => 'array',
        'data.en.menu_items.*.children.*.name' => 'required|string|max:255',
        'data.en.menu_items.*.children.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
        'data.en.menu_items.*.children.*.children' => 'array',
        'data.en.menu_items.*.children.*.children.*.name' => 'required|string|max:255',
        'data.en.menu_items.*.children.*.children.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
        'data.en.top_menu_items' => 'array',
        'data.en.top_menu_items.*.name' => 'required|string|max:255',
        'data.en.top_menu_items.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
        'data.en.top_menu_items.*.children' => 'array',
        'data.en.top_menu_items.*.children.*.name' => 'required|string|max:255',
        'data.en.top_menu_items.*.children.*.url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
    ], as: [
        'data.vi.menu_items.*.name' => 'tên menu (Tiếng Việt)',
        'data.vi.menu_items.*.url' => 'đường dẫn menu (Tiếng Việt)',
        'data.vi.menu_items.*.children.*.name' => 'tên menu cấp 2 (Tiếng Việt)',
        'data.vi.menu_items.*.children.*.url' => 'đường dẫn menu cấp 2 (Tiếng Việt)',
        'data.vi.menu_items.*.children.*.children.*.name' => 'tên menu cấp 3 (Tiếng Việt)',
        'data.vi.menu_items.*.children.*.children.*.url' => 'đường dẫn menu cấp 3 (Tiếng Việt)',
        'data.vi.top_menu_items.*.name' => 'tên menu phụ (Tiếng Việt)',
        'data.vi.top_menu_items.*.url' => 'đường dẫn menu phụ (Tiếng Việt)',
        'data.vi.top_menu_items.*.children.*.name' => 'tên menu phụ cấp 2 (Tiếng Việt)',
        'data.vi.top_menu_items.*.children.*.url' => 'đường dẫn menu phụ cấp 2 (Tiếng Việt)',
        'data.en.menu_items.*.name' => 'menu name (Tiếng Anh)',
        'data.en.menu_items.*.url' => 'menu URL (Tiếng Anh)',
        'data.en.menu_items.*.children.*.name' => 'tên menu cấp 2 (Tiếng Anh)',
        'data.en.menu_items.*.children.*.url' => 'đường dẫn menu cấp 2 (Tiếng Anh)',
        'data.en.menu_items.*.children.*.children.*.name' => 'tên menu cấp 3 (Tiếng Anh)',
        'data.en.menu_items.*.children.*.children.*.url' => 'đường dẫn menu cấp 3 (Tiếng Anh)',
        'data.en.top_menu_items.*.name' => 'tên menu phụ (Tiếng Anh)',
        'data.en.top_menu_items.*.url' => 'đường dẫn menu phụ (Tiếng Anh)',
        'data.en.top_menu_items.*.children.*.name' => 'tên menu phụ cấp 2 (Tiếng Anh)',
        'data.en.top_menu_items.*.children.*.url' => 'đường dẫn menu phụ cấp 2 (Tiếng Anh)',
    ])]
    public function mount()
    {
        $this->data = [
            'vi' => ['menu_items' => [], 'top_menu_items' => []],
            'en' => ['menu_items' => [], 'top_menu_items' => []],
        ];

        $page = Page::where('slug', 'dau-trang')->first();

        if ($page && $page->content_data) {
            $viData = $page->getTranslation('content_data', 'vi', false);
            $enData = $page->getTranslation('content_data', 'en', false);

            if (is_array($viData)) {
                $this->data['vi'] = array_merge(['menu_items' => [], 'top_menu_items' => []], $viData);
            }

            if (is_array($enData)) {
                $this->data['en'] = array_merge(['menu_items' => [], 'top_menu_items' => []], $enData);
            }
        }

        $this->ensureIdsForLocale('vi');
        $this->ensureIdsForLocale('en');
    }

    protected function ensureIdsForLocale(string $locale): void
    {
        foreach (($this->data[$locale]['menu_items'] ?? []) as $menuIndex => $menu) {
            if (empty($this->data[$locale]['menu_items'][$menuIndex]['id'])) {
                $this->data[$locale]['menu_items'][$menuIndex]['id'] = Str::random(8);
            }

            $this->data[$locale]['menu_items'][$menuIndex]['children'] = $this->data[$locale]['menu_items'][$menuIndex]['children'] ?? [];

            foreach (($menu['children'] ?? []) as $childIndex => $child) {
                if (empty($this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['id'])) {
                    $this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['id'] = Str::random(8);
                }

                $this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'] = $this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'] ?? [];

                foreach (($child['children'] ?? []) as $grandIndex => $grandChild) {
                    if (empty($this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'][$grandIndex]['id'])) {
                        $this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'][$grandIndex]['id'] = Str::random(8);
                    }
                }
            }
        }

        foreach (($this->data[$locale]['top_menu_items'] ?? []) as $topIndex => $topMenu) {
            if (empty($this->data[$locale]['top_menu_items'][$topIndex]['id'])) {
                $this->data[$locale]['top_menu_items'][$topIndex]['id'] = Str::random(8);
            }

            $this->data[$locale]['top_menu_items'][$topIndex]['children'] = $this->data[$locale]['top_menu_items'][$topIndex]['children'] ?? [];

            foreach (($topMenu['children'] ?? []) as $topChildIndex => $topChild) {
                if (empty($this->data[$locale]['top_menu_items'][$topIndex]['children'][$topChildIndex]['id'])) {
                    $this->data[$locale]['top_menu_items'][$topIndex]['children'][$topChildIndex]['id'] = Str::random(8);
                }
            }
        }
    }

    public function addTopMenuItem($locale)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $this->data[$locale]['top_menu_items'][] = [
            'id' => Str::random(8),
            'name' => '',
            'url' => '',
            'children' => [],
        ];

        $this->success('Đã thêm menu phụ mới thành công.');
    }

    public function removeTopMenuItem($locale, $topMenuId)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa menu phụ này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveTopMenuItem',
            'id' => [$locale, $topMenuId],
        ]);
    }
    #[On('confirmRemoveTopMenuItem')]
    public function confirmRemoveTopMenuItem($id)
    {
        [$locale, $topMenuId] = $id;
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $this->data[$locale]['top_menu_items'] = collect($this->data[$locale]['top_menu_items'] ?? [])
            ->reject(fn($item) => ($item['id'] ?? null) === $topMenuId)
            ->values()
            ->toArray();

        $this->success('Đã xóa menu phụ thành công.');
    }

    public function updateTopMenuOrder($locale, $orderedIds)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $items = collect($this->data[$locale]['top_menu_items'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $item = $items->firstWhere('id', $id);
            if ($item) {
                $newOrder[] = $item;
            }
        }

        $remaining = $items
            ->reject(fn($item) => in_array($item['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['top_menu_items'] = array_values(array_merge($newOrder, $remaining));
    }

    public function addTopChildItem($locale, $topMenuId)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $topIndex = collect($this->data[$locale]['top_menu_items'] ?? [])->search(fn($item) => ($item['id'] ?? null) === $topMenuId);
        if ($topIndex === false) {
            return;
        }

        $children = $this->data[$locale]['top_menu_items'][$topIndex]['children'] ?? [];
        $children[] = [
            'id' => Str::random(8),
            'name' => '',
            'url' => '',
        ];

        $this->data[$locale]['top_menu_items'][$topIndex]['children'] = $children;
        $this->success('Đã thêm menu phụ cấp 2 thành công.');
    }

    public function removeTopChildItem($locale, $topMenuId, $topChildId)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa menu phụ cấp 2 này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveTopChildItem',
            'id' => [$locale, $topMenuId, $topChildId],
        ]);
    }
    #[On('confirmRemoveTopChildItem')]
    public function confirmRemoveTopChildItem($id)
    {
        [$locale, $topMenuId, $topChildId] = $id;
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $topIndex = collect($this->data[$locale]['top_menu_items'] ?? [])->search(fn($item) => ($item['id'] ?? null) === $topMenuId);
        if ($topIndex === false) {
            return;
        }

        $this->data[$locale]['top_menu_items'][$topIndex]['children'] = collect($this->data[$locale]['top_menu_items'][$topIndex]['children'] ?? [])
            ->reject(fn($item) => ($item['id'] ?? null) === $topChildId)
            ->values()
            ->toArray();

        $this->success('Đã xóa menu phụ cấp 2 thành công.');
    }

    public function updateTopChildOrder($locale, $topMenuId, $orderedIds)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $topIndex = collect($this->data[$locale]['top_menu_items'] ?? [])->search(fn($item) => ($item['id'] ?? null) === $topMenuId);
        if ($topIndex === false) {
            return;
        }

        $children = collect($this->data[$locale]['top_menu_items'][$topIndex]['children'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $item = $children->firstWhere('id', $id);
            if ($item) {
                $newOrder[] = $item;
            }
        }

        $remaining = $children
            ->reject(fn($item) => in_array($item['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['top_menu_items'][$topIndex]['children'] = array_values(array_merge($newOrder, $remaining));
    }

    public function addMenuItem($locale)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $newItem = [
            'id' => Str::random(8),
            'name' => '',
            'url' => '',
            'children' => [],
        ];

        $this->data[$locale]['menu_items'][] = $newItem;

        $this->success('Đã thêm menu mới thành công.');
    }

    public function updatedSelectedSectionByLocaleVi($value): void
    {
        if (in_array($value, ['main_menu', 'top_sub_menu'], true)) {
            // Keep EN section in sync with VI selector (UI currently exposes one selector).
            $this->selectedSectionByLocale['en'] = $value;
        }
    }

    public function removeMenuItem($locale, $id)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa menu này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveMenuItem',
            'id' => [$locale, $id],
        ]);
    }

    public function confirmRemoveMenuItem($id)
    {
        [$locale, $menuId] = $id;

        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $this->data[$locale]['menu_items'] = collect($this->data[$locale]['menu_items'] ?? [])
            ->reject(fn($item) => ($item['id'] ?? null) === $menuId)
            ->values()
            ->toArray();

        $this->success('Đã xóa menu thành công.');
    }

    public function addChildItem($locale, $menuId)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $newChild = [
            'id' => Str::random(8),
            'name' => '',
            'url' => '',
            'children' => [],
        ];

        $index = collect($this->data[$locale]['menu_items'] ?? [])->search(fn($item) => ($item['id'] ?? null) === $menuId);

        if ($index !== false) {
            $children = $this->data[$locale]['menu_items'][$index]['children'] ?? [];
            $children[] = $newChild;
            $this->data[$locale]['menu_items'][$index]['children'] = $children;
        }

        $this->success('Đã thêm menu cấp 2 thành công.');
    }

    public function removeChildItem($locale, $menuId, $childId)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa menu cấp 2 này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveChildItem',
            'id' => [$locale, $menuId, $childId],
        ]);
    }

    public function confirmRemoveChildItem($id)
    {
        [$locale, $menuId, $childId] = $id;

        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $menuIndex = collect($this->data[$locale]['menu_items'] ?? [])->search(fn($item) => ($item['id'] ?? null) === $menuId);

        if ($menuIndex !== false) {
            $children = collect($this->data[$locale]['menu_items'][$menuIndex]['children'] ?? [])
                ->reject(fn($child) => ($child['id'] ?? null) === $childId)
                ->values()
                ->toArray();

            $this->data[$locale]['menu_items'][$menuIndex]['children'] = $children;
        }

        $this->success('Đã xóa menu cấp 2 thành công.');
    }

    public function addGrandChildItem($locale, $menuId, $childId)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $menuIndex = collect($this->data[$locale]['menu_items'] ?? [])->search(fn($item) => ($item['id'] ?? null) === $menuId);
        if ($menuIndex === false) {
            return;
        }

        $childIndex = collect($this->data[$locale]['menu_items'][$menuIndex]['children'] ?? [])->search(fn($child) => ($child['id'] ?? null) === $childId);
        if ($childIndex === false) {
            return;
        }

        $newChild = [
            'id' => Str::random(8),
            'name' => '',
            'url' => '',
        ];

        $children = $this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'] ?? [];
        $children[] = $newChild;
        $this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'] = $children;

        $this->success('Đã thêm menu cấp 3 thành công.');
    }

    public function removeGrandChildItem($locale, $menuId, $childId, $grandChildId)
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa menu cấp 3 này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveGrandChildItem',
            'id' => [$locale, $menuId, $childId, $grandChildId],
        ]);
    }

    public function confirmRemoveGrandChildItem($id)
    {
        [$locale, $menuId, $childId, $grandChildId] = $id;

        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $menuIndex = collect($this->data[$locale]['menu_items'] ?? [])->search(fn($item) => ($item['id'] ?? null) === $menuId);
        if ($menuIndex === false) {
            return;
        }

        $childIndex = collect($this->data[$locale]['menu_items'][$menuIndex]['children'] ?? [])->search(fn($child) => ($child['id'] ?? null) === $childId);
        if ($childIndex === false) {
            return;
        }

        $grandChildren = collect($this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'] ?? [])
            ->reject(fn($child) => ($child['id'] ?? null) === $grandChildId)
            ->values()
            ->toArray();

        $this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'] = $grandChildren;

        $this->success('Đã xóa menu cấp 3 thành công.');
    }

    public function updateMenuOrder($locale, $orderedIds)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $items = collect($this->data[$locale]['menu_items'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $item = $items->firstWhere('id', $id);
            if ($item) {
                $newOrder[] = $item;
            }
        }

        $remaining = $items
            ->reject(fn($item) => in_array($item['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['menu_items'] = array_values(array_merge($newOrder, $remaining));
    }

    public function updateChildOrder($locale, $menuId, $orderedIds)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $menuIndex = collect($this->data[$locale]['menu_items'] ?? [])
            ->search(fn($item) => ($item['id'] ?? null) === $menuId);

        if ($menuIndex === false) {
            return;
        }

        $children = collect($this->data[$locale]['menu_items'][$menuIndex]['children'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $child = $children->firstWhere('id', $id);
            if ($child) {
                $newOrder[] = $child;
            }
        }

        $remaining = $children
            ->reject(fn($child) => in_array($child['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['menu_items'][$menuIndex]['children'] = array_values(array_merge($newOrder, $remaining));
    }

    public function updateGrandChildOrder($locale, $menuId, $childId, $orderedIds)
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $menuIndex = collect($this->data[$locale]['menu_items'] ?? [])->search(fn($item) => ($item['id'] ?? null) === $menuId);
        if ($menuIndex === false) {
            return;
        }

        $childIndex = collect($this->data[$locale]['menu_items'][$menuIndex]['children'] ?? [])->search(fn($child) => ($child['id'] ?? null) === $childId);
        if ($childIndex === false) {
            return;
        }

        $grandChildren = collect($this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $item = $grandChildren->firstWhere('id', $id);
            if ($item) {
                $newOrder[] = $item;
            }
        }

        $remaining = $grandChildren
            ->reject(fn($item) => in_array($item['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['menu_items'][$menuIndex]['children'][$childIndex]['children'] = array_values(array_merge($newOrder, $remaining));
    }

    public function save()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng nhập đủ tên và link hợp lệ cho các menu.');
            throw $e;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn lưu cấu hình header không?',
            'icon' => 'question',
            'confirmButtonText' => 'Có lưu',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmSave',
        ]);
    }

    public function confirmSave()
    {
        $page = Page::updateOrCreate(
            ['slug' => 'dau-trang'],
            ['layout' => 'header_menu']
        );

        $page->setTranslations('content_data', $this->data);
        $page->save();

        $this->success('Lưu cấu hình header thành công!');
    }
};
?>

<div x-data="{
        storageKey: 'header_menu_open_state_v1',
        openByLocale: { vi: {}, en: {} },
        init() {
            this.loadOpenState();
        },
        loadOpenState() {
            try {
                const raw = localStorage.getItem(this.storageKey);
                const parsed = raw ? JSON.parse(raw) : {};
                this.openByLocale.vi = parsed.vi || {};
                this.openByLocale.en = parsed.en || {};
            } catch (e) {
                this.openByLocale = { vi: {}, en: {} };
            }
        },
        saveOpenState() {
            localStorage.setItem(this.storageKey, JSON.stringify(this.openByLocale));
        },
        ensureMenuOpenState(locale, id) {
            if (!this.openByLocale[locale]) this.openByLocale[locale] = {};
            if (this.openByLocale[locale][id] === undefined) {
                this.openByLocale[locale][id] = true;
                this.saveOpenState();
            }
        },
        isMenuOpen(locale, id) {
            return this.openByLocale?.[locale]?.[id] !== false;
        },
        toggleMenu(locale, id) {
            this.ensureMenuOpenState(locale, id);
            this.openByLocale[locale][id] = !this.openByLocale[locale][id];
            this.saveOpenState();
        }
    }"
>
    <x-slot:title>Cấu hình Menu tiêu đề</x-slot:title>
    <x-slot:breadcrumb><span>Cấu hình Menu tiêu đề </span></x-slot:breadcrumb>

    <x-header title="Cấu hình Menu tiêu đề" class="pb-3 mb-5! border-b border-gray-300"/>
    <div class="flex items-center gap-3 mb-4">
        <label for="selectedSectionByLocale.vi" class="font-semibold">Chọn menu cần chỉnh sửa:</label>
        <x-select
            wire:model.live="selectedSectionByLocale.vi"
            :options="[
                ['id' => 'main_menu', 'name' => 'Thanh menu chính'],
                ['id' => 'top_sub_menu', 'name' => 'Thanh menu phụ (phía trên)'],
            ]"
            option-value="id"
            option-label="name"
            class="w-50"
        />
    </div>
    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        <x-card class="col-span-10 p-3!">
            <x-tabs wire:model="selectedTab">
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                    @php $selectedSectionVi = $selectedSectionByLocale['vi'] ?? 'main_menu'; @endphp
                    <div class="space-y-4"
                         x-data="{
                             sortable: null,
                             initSortable() {
                                 if (this.sortable) this.sortable.destroy();
                                 if (!this.$refs.menuList) return;
                                 this.sortable = new Sortable(this.$refs.menuList, {
                                     animation: 150,
                                     handle: '.drag-menu-handle',
                                     onEnd: () => {
                                         let order = Array.from(this.$refs.menuList.children)
                                             .map(el => el.dataset.id)
                                             .filter(Boolean);
                                         $wire.updateMenuOrder('vi', order);
                                     }
                                 });
                             }
                         }"
                         x-init="$nextTick(() => initSortable())">
                        @if($selectedSectionVi === 'top_sub_menu')
                            <div class="border border-gray-300 rounded-lg bg-white shadow-sm p-3"
                                 x-data="{
                                 sortableTop: null,
                                 initTopSortable() {
                                     if (this.sortableTop) this.sortableTop.destroy();
                                     if (!this.$refs.topMenuListVi) return;
                                     this.sortableTop = new Sortable(this.$refs.topMenuListVi, {
                                         animation: 150,
                                         handle: '.drag-top-menu-handle-vi',
                                         onEnd: () => {
                                             let order = Array.from(this.$refs.topMenuListVi.children)
                                                 .map(el => el.dataset.id)
                                                 .filter(Boolean);
                                             $wire.updateTopMenuOrder('vi', order);
                                         }
                                     });
                                 }
                             }"
                                 x-init="$nextTick(() => initTopSortable())">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="font-semibold text-sm">Thanh menu phụ phía trên (bên trái nút tìm
                                        kiếm)</p>
                                    <x-button icon="o-plus" label="Thêm menu phụ"
                                              class="btn-sm bg-emerald-600 text-white" wire:click="addTopMenuItem('vi')"
                                              spinner="addTopMenuItem('vi')"/>
                                </div>
                                <div class="space-y-2" x-ref="topMenuListVi">
                                    @forelse($data['vi']['top_menu_items'] ?? [] as $topIndex => $topMenu)
                                        <div data-id="{{ $topMenu['id'] ?? '' }}"
                                             wire:key="vi-top-menu-{{ $topMenu['id'] ?? $topIndex }}"
                                             x-init="ensureMenuOpenState('vi', 'top-{{ $topMenu['id'] ?? $topIndex }}')">
                                            <div
                                                class="border border-gray-300 rounded-lg bg-white shadow-sm overflow-hidden">
                                                <div
                                                    class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                                    <x-icon name="o-bars-3"
                                                            class="drag-top-menu-handle-vi w-5 h-5 text-gray-400 cursor-move hover:text-gray-600"/>
                                                    <button type="button"
                                                            class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                            @click="toggleMenu('vi', 'top-{{ $topMenu['id'] ?? $topIndex }}')">
                                                        {{ $data['vi']['top_menu_items'][$topIndex]['name'] ? 'Menu phụ - ' . $data['vi']['top_menu_items'][$topIndex]['name'] : 'Menu phụ mới' }}
                                                    </button>
                                                    <div class="flex items-center gap-1">
                                                        <x-button icon="o-plus" label="Thêm menu cấp 2"
                                                                  class="btn-sm bg-emerald-600 text-white"
                                                                  wire:click="addTopChildItem('vi', '{{ $topMenu['id'] ?? '' }}')"/>
                                                        <x-button icon="o-trash" class="btn-ghost text-red-600"
                                                                  wire:click="removeTopMenuItem('vi', '{{ $topMenu['id'] ?? '' }}')"/>
                                                        <x-icon name="o-chevron-down"
                                                                class="w-5 h-5 cursor-pointer transition-transform"
                                                                x-bind:class="isMenuOpen('vi', 'top-{{ $topMenu['id'] ?? $topIndex }}') ? 'rotate-180' : ''"
                                                                @click="toggleMenu('vi', 'top-{{ $topMenu['id'] ?? $topIndex }}')"/>
                                                    </div>
                                                </div>

                                                <div x-show="isMenuOpen('vi', 'top-{{ $topMenu['id'] ?? $topIndex }}')"
                                                     x-collapse class="p-4 bg-white border-t border-gray-100">
                                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                                        <x-input label="Tên menu phụ"
                                                                 wire:model.live.debounce.500ms="data.vi.top_menu_items.{{$topIndex}}.name"
                                                                 placeholder="VD: E-learning" required/>
                                                        <x-input label="Link menu phụ"
                                                                 wire:model.live.debounce.500ms="data.vi.top_menu_items.{{$topIndex}}.url"
                                                                 placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                                 required/>
                                                    </div>
                                                    @if(!empty($topMenu['children'] ?? []))
                                                        <div class="mt-3 border-t pt-3 pl-3"
                                                             x-data="{ sortableTopChild: null, initTopChildSortable() { if (this.sortableTopChild) this.sortableTopChild.destroy(); this.sortableTopChild = new Sortable(this.$refs.topChildListVi, { animation: 150, handle: '.drag-top-child-handle-vi', onEnd: () => { let order = Array.from(this.$refs.topChildListVi.children).map(el => el.dataset.id).filter(Boolean); $wire.updateTopChildOrder('vi', '{{ $topMenu['id'] ?? '' }}', order); } }); } }"
                                                             x-init="$nextTick(() => initTopChildSortable())">
                                                            <div class="flex justify-between items-center mb-2">
                                                                <p class="font-semibold text-sm">Menu phụ cấp 2</p>
                                                            </div>
                                                            <div class="space-y-2" x-ref="topChildListVi">
                                                                @foreach($topMenu['children'] ?? [] as $topChildIndex => $topChild)
                                                                    <div data-id="{{ $topChild['id'] ?? '' }}"
                                                                         wire:key="vi-top-child-{{ $topMenu['id'] ?? $topIndex }}-{{ $topChild['id'] ?? $topChildIndex }}"
                                                                         class="grid grid-cols-1 lg:grid-cols-[auto_1fr_1fr_auto] gap-3 items-center border border-gray-200 rounded-lg p-2">
                                                                        <x-icon name="o-bars-3"
                                                                                class="drag-top-child-handle-vi w-5 h-5 text-gray-400 cursor-move hover:text-gray-600 mt-4"/>
                                                                        <x-input label="Tên menu phụ cấp 2"
                                                                                 wire:model.live.debounce.500ms="data.vi.top_menu_items.{{$topIndex}}.children.{{$topChildIndex}}.name"
                                                                                 placeholder="VD: Ho tro" required/>
                                                                        <x-input label="Link menu phụ cấp 2"
                                                                                 wire:model.live.debounce.500ms="data.vi.top_menu_items.{{$topIndex}}.children.{{$topChildIndex}}.url"
                                                                                 placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                                                 required/>
                                                                        <x-button icon="o-trash"
                                                                                  class="btn-ghost text-red-600 mt-4"
                                                                                  wire:click="removeTopChildItem('vi', '{{ $topMenu['id'] ?? '' }}', '{{ $topChild['id'] ?? '' }}')"/>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div
                                            class="border border-dashed border-gray-300 rounded-lg bg-gray-50 py-4 px-4 text-center text-gray-500">
                                            Chua co menu phu phia tren.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endif

                        @if($selectedSectionVi === 'main_menu')
                            <div class="border border-gray-300 rounded-lg bg-white shadow-sm p-3"
                                 x-data="{
                                 sortableMainVi: null,
                                 initMainSortable() {
                                     if (this.sortableMainVi) this.sortableMainVi.destroy();
                                     if (!this.$refs.menuList) return;
                                     this.sortableMainVi = new Sortable(this.$refs.menuList, {
                                         animation: 150,
                                         handle: '.drag-menu-handle',
                                         onEnd: () => {
                                             let order = Array.from(this.$refs.menuList.children)
                                                 .map(el => el.dataset.id)
                                                 .filter(Boolean);
                                             $wire.updateMenuOrder('vi', order);
                                         }
                                     });
                                 }
                             }"
                                 x-init="$nextTick(() => initMainSortable())">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="font-semibold text-sm">Thanh menu chính</p>
                                    <x-button icon="o-plus" label="Thêm menu" class="btn-sm bg-emerald-600 text-white"
                                              wire:click="addMenuItem('vi')" spinner="addMenuItem('vi')"/>
                                </div>
                                <div x-ref="menuList" class="space-y-4">
                                    @if(empty($data['vi']['menu_items'] ?? []))
                                        <div
                                            class="border border-dashed border-gray-300 rounded-lg bg-gray-50 py-10 px-6 text-center">
                                            <x-icon name="o-bars-3" class="w-8 h-8 mx-auto text-gray-400"/>
                                            <p class="mt-2 font-semibold text-gray-700">Chưa có menu nào</p>
                                            <p class="text-sm text-gray-500">Bấm "Thêm menu" để bắt đầu cấu hình header
                                                tiếng Việt.</p>
                                        </div>
                                    @endif
                                    @foreach($data['vi']['menu_items'] ?? [] as $menuIndex => $menu)
                                        <div data-id="{{ $menu['id'] }}" wire:key="vi-menu-{{ $menu['id'] }}"
                                             x-init="ensureMenuOpenState('vi', '{{ $menu['id'] }}')">
                                            <div
                                                class="border border-gray-300 rounded-lg bg-white shadow-sm overflow-hidden">
                                                <div
                                                    class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                                    <x-icon name="o-bars-3"
                                                            class="drag-menu-handle w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                                    <button type="button"
                                                            class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                            @click="toggleMenu('vi', '{{ $menu['id'] }}')">
                                                        {{ $data['vi']['menu_items'][$menuIndex]['name'] ? 'Menu - ' . $data['vi']['menu_items'][$menuIndex]['name'] : 'Menu mới' }}
                                                    </button>
                                                    <div class="flex items-center gap-1">
                                                        <x-button
                                                            icon="o-plus"
                                                            label="Thêm menu cấp 2"
                                                            class="btn-sm bg-emerald-600 text-white"
                                                            wire:click="addChildItem('vi', '{{ $menu['id'] }}')"
                                                            spinner="addChildItem('vi', '{{ $menu['id'] }}')"
                                                        />
                                                        <button type="button" class="btn btn-ghost btn-sm text-red-500"
                                                                wire:click="removeMenuItem('vi', '{{ $menu['id'] }}')">
                                                            <x-icon name="o-trash" class="w-4 h-4"/>
                                                        </button>
                                                        <x-icon name="o-chevron-down"
                                                                class="w-5 h-5 cursor-pointer transition-transform"
                                                                x-bind:class="isMenuOpen('vi', '{{ $menu['id'] }}') ? 'rotate-180' : ''"
                                                                @click="toggleMenu('vi', '{{ $menu['id'] }}')"/>
                                                    </div>
                                                </div>

                                                <div x-show="isMenuOpen('vi', '{{ $menu['id'] }}')" x-collapse
                                                     class="p-4 bg-white border-t border-gray-100">
                                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                                        <x-input
                                                            label="Tên menu"
                                                            wire:model.live.debounce.500ms="data.vi.menu_items.{{$menuIndex}}.name"
                                                            required
                                                            placeholder="Nhập tên menu, ví dụ: Trang chủ, Tin tức, Liên hệ,..."
                                                        />
                                                        <x-input
                                                            label="Link"
                                                            wire:model.live.debounce.500ms="data.vi.menu_items.{{$menuIndex}}.url"
                                                            placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                            required
                                                        />
                                                    </div>
                                                    @if(!empty($menu['children'] ?? []))
                                                        <div class="mt-3 border-t pt-3">

                                                            <div class="flex justify-between items-center mb-2">
                                                                <p class="font-semibold text-sm">Menu cấp 2</p>
                                                            </div>
                                                            <div class="space-y-2"
                                                                 x-data="{
                                                     sortable: null,
                                                     initSortable() {
                                                         if (this.sortable) this.sortable.destroy();
                                                         this.sortable = new Sortable(this.$refs.childList, {
                                                             animation: 150,
                                                             handle: '.drag-child-handle',
                                                             onEnd: () => {
                                                                 let order = Array.from(this.$refs.childList.children)
                                                                     .map(el => el.dataset.id)
                                                                     .filter(Boolean);
                                                                 $wire.updateChildOrder('vi', '{{ $menu['id'] }}', order);
                                                             }
                                                         });
                                                     }
                                                 }"
                                                                 x-init="$nextTick(() => initSortable())"
                                                                 x-ref="childList">
                                                                {{--                                                @if(empty($menu['children'] ?? []))--}}
                                                                {{--                                                    <div class="border border-dashed border-gray-300 rounded-lg bg-gray-50 py-6 px-4 text-center">--}}
                                                                {{--                                                        <p class="font-medium text-gray-700">Chưa có menu con</p>--}}
                                                                {{--                                                        <p class="text-sm text-gray-500">Bấm "Thêm menu con" để bổ sung mục con.</p>--}}
                                                                {{--                                                    </div>--}}
                                                                {{--                                                @endif--}}
                                                                @foreach($menu['children'] ?? [] as $childIndex => $child)
                                                                    <div data-id="{{ $child['id'] }}"
                                                                         wire:key="vi-child-{{ $menu['id'] }}-{{ $child['id'] }}"
                                                                         class="border border-gray-200 rounded-lg"
                                                                         x-data="{ openChild: true }">
                                                                        <div
                                                                            class="flex items-center gap-3 px-3 py-2 bg-gray-50 border-b border-gray-100">
                                                                            <x-icon name="o-bars-3"
                                                                                    class="drag-child-handle w-5 h-5 text-gray-400 cursor-move hover:text-gray-600"/>
                                                                            <button type="button"
                                                                                    class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                                                    @click="openChild = !openChild">
                                                                <span class="inline-flex items-center gap-2">
                                                                    {{ $data['vi']['menu_items'][$menuIndex]['children'][$childIndex]['name'] ? 'Menu con - ' . $data['vi']['menu_items'][$menuIndex]['children'][$childIndex]['name'] : 'Menu cấp 2 mới' }}
                                                                </span>
                                                                            </button>
                                                                            <x-button
                                                                                icon="o-plus"
                                                                                label="Thêm menu cấp 3"
                                                                                class="btn-sm bg-emerald-600 text-white"
                                                                                wire:click="addGrandChildItem('vi', '{{ $menu['id'] }}', '{{ $child['id'] }}')"
                                                                                spinner="addGrandChildItem('vi', '{{ $menu['id'] }}', '{{ $child['id'] }}')"
                                                                            />
                                                                            <x-button
                                                                                icon="o-trash"
                                                                                class="btn-ghost text-red-600"
                                                                                wire:click="removeChildItem('vi', '{{ $menu['id'] }}', '{{ $child['id'] }}')"
                                                                            />
                                                                            <x-icon name="o-chevron-down"
                                                                                    class="w-5 h-5 cursor-pointer transition-transform text-gray-500"
                                                                                    x-bind:class="openChild ? 'rotate-180' : ''"
                                                                                    @click="openChild = !openChild"/>
                                                                        </div>

                                                                        <div x-show="openChild" x-collapse class="p-3">
                                                                            <div
                                                                                class="grid grid-cols-1 lg:grid-cols-[1fr_1fr] gap-3 items-center">
                                                                                <x-input
                                                                                    label="Tên menu cấp 2"
                                                                                    wire:model.live.debounce.500ms="data.vi.menu_items.{{$menuIndex}}.children.{{$childIndex}}.name"
                                                                                    required
                                                                                    placeholder="Nhập tên menu cấp 2, ví dụ: Giới thiệu, Tin tức,..."
                                                                                />
                                                                                <x-input
                                                                                    label="Link menu con"
                                                                                    wire:model.live.debounce.500ms="data.vi.menu_items.{{$menuIndex}}.children.{{$childIndex}}.url"
                                                                                    placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                                                    required
                                                                                />
                                                                            </div>
                                                                            @if(!empty($child['children'] ?? []))
                                                                                <div class="mt-3 border-t pt-3 pl-3">
                                                                                    <div
                                                                                        class="flex justify-between items-center mb-2">
                                                                                        <p class="font-semibold text-sm">
                                                                                            Menu cấp 3</p>
                                                                                    </div>

                                                                                    <div class="space-y-2"
                                                                                         x-data="{
                                                                             sortable: null,
                                                                             initSortable() {
                                                                                 if (this.sortable) this.sortable.destroy();
                                                                                 this.sortable = new Sortable(this.$refs.grandChildListVi, {
                                                                                     animation: 150,
                                                                                     handle: '.drag-grandchild-handle-vi',
                                                                                     onEnd: () => {
                                                                                         let order = Array.from(this.$refs.grandChildListVi.children)
                                                                                             .map(el => el.dataset.id)
                                                                                             .filter(Boolean);
                                                                                         $wire.updateGrandChildOrder('vi', '{{ $menu['id'] }}', '{{ $child['id'] }}', order);
                                                                                     }
                                                                                 });
                                                                             }
                                                                         }"
                                                                                         x-init="$nextTick(() => initSortable())"
                                                                                         x-ref="grandChildListVi">
                                                                                        {{--                                                                @if(empty($child['children'] ?? []))--}}
                                                                                        {{--                                                                    <div class="border border-dashed border-gray-300 rounded-lg bg-gray-50 py-4 px-4 text-center">--}}
                                                                                        {{--                                                                        <p class="font-medium text-gray-700">Chưa có menu cấp 3</p>--}}
                                                                                        {{--                                                                        <p class="text-sm text-gray-500">Bấm "Thêm menu cấp 3" để bổ sung mục con.</p>--}}
                                                                                        {{--                                                                    </div>--}}
                                                                                        {{--                                                                @endif--}}
                                                                                        @foreach($child['children'] ?? [] as $grandChildIndex => $grandChild)
                                                                                            <div
                                                                                                data-id="{{ $grandChild['id'] }}"
                                                                                                wire:key="vi-grandchild-{{ $menu['id'] }}-{{ $child['id'] }}-{{ $grandChild['id'] }}"
                                                                                                class="grid grid-cols-1 lg:grid-cols-[auto_1fr_1fr_auto] gap-3 items-center">
                                                                                                <x-icon name="o-bars-3"
                                                                                                        class="drag-grandchild-handle-vi w-5 h-5 text-gray-400 cursor-move hover:text-gray-600 mt-4"/>
                                                                                                <x-input
                                                                                                    label="Tên menu cấp 3"
                                                                                                    wire:model.live.debounce.500ms="data.vi.menu_items.{{$menuIndex}}.children.{{$childIndex}}.children.{{$grandChildIndex}}.name"
                                                                                                    required
                                                                                                    placeholder="Nhập tên menu cấp 3"
                                                                                                />
                                                                                                <x-input
                                                                                                    label="Link menu cấp 3"
                                                                                                    wire:model.live.debounce.500ms="data.vi.menu_items.{{$menuIndex}}.children.{{$childIndex}}.children.{{$grandChildIndex}}.url"
                                                                                                    placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                                                                    required
                                                                                                />
                                                                                                <x-button
                                                                                                    icon="o-trash"
                                                                                                    class="btn-ghost text-red-600 mt-4"
                                                                                                    wire:click="removeGrandChildItem('vi', '{{ $menu['id'] }}', '{{ $child['id'] }}', '{{ $grandChild['id'] }}')"
                                                                                                />
                                                                                            </div>
                                                                                        @endforeach
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </x-tab>

                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                    @php $selectedSectionEn = $selectedSectionByLocale['vi'] ?? 'main_menu'; @endphp
                    <div class="space-y-4"
                         x-data="{
                             sortable: null,
                             initSortable() {
                                 if (this.sortable) this.sortable.destroy();
                                 if (!this.$refs.menuListEn) return;
                                 this.sortable = new Sortable(this.$refs.menuListEn, {
                                     animation: 150,
                                     handle: '.drag-menu-handle-en',
                                     onEnd: () => {
                                         let order = Array.from(this.$refs.menuListEn.children)
                                             .map(el => el.dataset.id)
                                             .filter(Boolean);
                                         $wire.updateMenuOrder('en', order);
                                     }
                                 });
                             }
                         }"
                         x-init="$nextTick(() => initSortable())">
                        @if($selectedSectionEn === 'top_sub_menu')
                            <div class="border border-gray-300 rounded-lg bg-white shadow-sm p-3"
                                 x-data="{
                                 sortableTop: null,
                                 initTopSortable() {
                                     if (this.sortableTop) this.sortableTop.destroy();
                                     if (!this.$refs.topMenuListEn) return;
                                     this.sortableTop = new Sortable(this.$refs.topMenuListEn, {
                                         animation: 150,
                                         handle: '.drag-top-menu-handle-en',
                                         onEnd: () => {
                                             let order = Array.from(this.$refs.topMenuListEn.children)
                                                 .map(el => el.dataset.id)
                                                 .filter(Boolean);
                                             $wire.updateTopMenuOrder('en', order);
                                         }
                                     });
                                 }
                             }"
                                 x-init="$nextTick(() => initTopSortable())">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="font-semibold text-sm">Thanh menu phụ phía trên (bên trái nút tìm kiếm)</p>
                                    <x-button icon="o-plus" label="Thêm menu phụ"
                                              class="btn-sm bg-emerald-600 text-white" wire:click="addTopMenuItem('en')"
                                              spinner="addTopMenuItem('en')"/>
                                </div>
                                <div class="space-y-2" x-ref="topMenuListEn">
                                    @forelse($data['en']['top_menu_items'] ?? [] as $topIndex => $topMenu)
                                        <div data-id="{{ $topMenu['id'] ?? '' }}"
                                             wire:key="en-top-menu-{{ $topMenu['id'] ?? $topIndex }}"
                                             x-init="ensureMenuOpenState('en', 'top-{{ $topMenu['id'] ?? $topIndex }}')">
                                            <div
                                                class="border border-gray-300 rounded-lg bg-white shadow-sm overflow-hidden">
                                                <div
                                                    class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                                    <x-icon name="o-bars-3"
                                                            class="drag-top-menu-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-600"/>
                                                    <button type="button"
                                                            class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                            @click="toggleMenu('en', 'top-{{ $topMenu['id'] ?? $topIndex }}')">
                                                        {{ $data['en']['top_menu_items'][$topIndex]['name'] ? 'Menu phụ - ' . $data['en']['top_menu_items'][$topIndex]['name'] : 'Menu phụ mới' }}
                                                    </button>
                                                    <div class="flex items-center gap-1">
                                                        <x-button icon="o-plus" label="Thêm menu cấp 2"
                                                                  class="btn-sm bg-emerald-600 text-white"
                                                                  wire:click="addTopChildItem('en', '{{ $topMenu['id'] ?? '' }}')"
                                                                spinner="addTopChildItem('en', '{{ $topMenu['id'] ?? '' }}')"
                                                        />
                                                        <x-button icon="o-trash" class="btn-ghost text-red-600"
                                                                  wire:click="removeTopMenuItem('en', '{{ $topMenu['id'] ?? '' }}')"/>
                                                        <x-icon name="o-chevron-down"
                                                                class="w-5 h-5 cursor-pointer transition-transform"
                                                                x-bind:class="isMenuOpen('en', 'top-{{ $topMenu['id'] ?? $topIndex }}') ? 'rotate-180' : ''"
                                                                @click="toggleMenu('en', 'top-{{ $topMenu['id'] ?? $topIndex }}')"/>
                                                    </div>
                                                </div>

                                                <div x-show="isMenuOpen('en', 'top-{{ $topMenu['id'] ?? $topIndex }}')"
                                                     x-collapse class="p-4 bg-white border-t border-gray-100">
                                                     <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                                         <x-input label="Tên menu phụ"
                                                                  wire:model.live.debounce.500ms="data.en.top_menu_items.{{$topIndex}}.name"
                                                                  placeholder="VD: E-learning" required/>
                                                         <x-input label="Link menu phụ"
                                                                  wire:model.live.debounce.500ms="data.en.top_menu_items.{{$topIndex}}.url"
                                                                  placeholder="/duong-dan hoặc https://example.com hoặc ###" required/>
                                                     </div>
                                                    @if(!empty($topMenu['children'] ?? []))
                                                    <div class="mt-3 border-t pt-3 pl-3"
                                                         x-data="{ sortableTopChild: null, initTopChildSortable() { if (this.sortableTopChild) this.sortableTopChild.destroy(); this.sortableTopChild = new Sortable(this.$refs.topChildListEn, { animation: 150, handle: '.drag-top-child-handle-en', onEnd: () => { let order = Array.from(this.$refs.topChildListEn.children).map(el => el.dataset.id).filter(Boolean); $wire.updateTopChildOrder('en', '{{ $topMenu['id'] ?? '' }}', order); } }); } }"
                                                         x-init="$nextTick(() => initTopChildSortable())">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <p class="font-semibold text-sm">Menu phụ cấp 2</p>
                                                        </div>
                                                        <div class="space-y-2" x-ref="topChildListEn">
                                                            @foreach($topMenu['children'] ?? [] as $topChildIndex => $topChild)
                                                                <div data-id="{{ $topChild['id'] ?? '' }}"
                                                                     wire:key="en-top-child-{{ $topMenu['id'] ?? $topIndex }}-{{ $topChild['id'] ?? $topChildIndex }}"
                                                                     class="grid grid-cols-1 lg:grid-cols-[auto_1fr_1fr_auto] gap-3 items-center border border-gray-200 rounded-lg p-2">
                                                                    <x-icon name="o-bars-3"
                                                                            class="drag-top-child-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-600 mt-4"/>
                                                                     <x-input label="Tên menu phụ cấp 2"
                                                                              wire:model.live.debounce.500ms="data.en.top_menu_items.{{$topIndex}}.children.{{$topChildIndex}}.name"
                                                                              placeholder="VD: Hỗ trợ" required/>
                                                                     <x-input label="Link menu phụ cấp 2"
                                                                              wire:model.live.debounce.500ms="data.en.top_menu_items.{{$topIndex}}.children.{{$topChildIndex}}.url"
                                                                              placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                                              required/>
                                                                    <x-button icon="o-trash"
                                                                              class="btn-ghost text-red-600 mt-4"
                                                                              wire:click="removeTopChildItem('en', '{{ $topMenu['id'] ?? '' }}', '{{ $topChild['id'] ?? '' }}')"/>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                        @endif
                                                </div>
                                            </div>
                                        </div>
                                     @empty
                                         <div
                                             class="border border-dashed border-gray-300 rounded-lg bg-gray-50 py-4 px-4 text-center text-gray-500">
                                             Chưa có menu phụ phía trên.
                                         </div>
                                     @endforelse
                                </div>
                            </div>
                        @endif

                        @if($selectedSectionEn === 'main_menu')
                            <div class="border border-gray-300 rounded-lg bg-white shadow-sm p-3"
                                 x-data="{
                                 sortableTop: null,
                                 initTopSortable() {
                                     if (this.sortableTop) this.sortableTop.destroy();
                                     if (!this.$refs.menuListEn) return;
                                     this.sortableTop = new Sortable(this.$refs.menuListEn, {
                                         animation: 150,
                                         handle: '.drag-menu-handle-en',
                                         onEnd: () => {
                                             let order = Array.from(this.$refs.menuListEn.children)
                                                 .map(el => el.dataset.id)
                                                 .filter(Boolean);
                                             $wire.updateMenuOrder('en', order);
                                         }
                                     });
                                 }
                             }"
                                 x-init="$nextTick(() => initTopSortable())">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="font-semibold text-sm">Thanh menu chính</p>
                                    <x-button icon="o-plus" label="Thêm menu" class="btn-sm bg-emerald-600 text-white"
                                              wire:click="addMenuItem('en')" spinner="addMenuItem('en')"/>
                                 </div>
                                 <div x-ref="menuListEn" class="space-y-4">
                                     @if(empty($data['en']['menu_items'] ?? []))
                                         <div
                                             class="border border-dashed border-gray-300 rounded-lg bg-gray-50 py-10 px-6 text-center">
                                             <x-icon name="o-bars-3" class="w-8 h-8 mx-auto text-gray-400"/>
                                             <p class="mt-2 font-semibold text-gray-700">Chưa có menu nào</p>
                                             <p class="text-sm text-gray-500">Bấm "Thêm menu" để bắt đầu cấu hình header tiếng Anh.</p>
                                         </div>
                                     @endif
                                     @foreach($data['en']['menu_items'] ?? [] as $menuIndex => $menu)
                                         <div data-id="{{ $menu['id'] }}" wire:key="en-menu-{{ $menu['id'] }}"
                                              x-init="ensureMenuOpenState('en', '{{ $menu['id'] }}')">
                                        <div
                                            class="border border-gray-300 rounded-lg bg-white shadow-sm overflow-hidden">
                                            <div
                                                class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                                <x-icon name="o-bars-3"
                                                        class="drag-menu-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                                <button type="button"
                                                        class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                        @click="toggleMenu('en', '{{ $menu['id'] }}')">
                                                    {{ $data['en']['menu_items'][$menuIndex]['name'] ? 'Menu - ' . $data['en']['menu_items'][$menuIndex]['name'] : 'Menu mới' }}
                                                </button>
                                                <div class="flex items-center gap-1">
                                                    <x-button
                                                        icon="o-plus"
                                                        label="Thêm menu cấp 2"
                                                        class="btn-sm bg-emerald-600 text-white"
                                                        wire:click="addChildItem('en', '{{ $menu['id'] }}')"
                                                        spinner="addChildItem('en', '{{ $menu['id'] }}')"
                                                    />
                                                    <button type="button" class="btn btn-ghost btn-sm text-red-500"
                                                            wire:click="removeMenuItem('en', '{{ $menu['id'] }}')">
                                                        <x-icon name="o-trash" class="w-4 h-4"/>
                                                    </button>
                                                    <x-icon name="o-chevron-down"
                                                            class="w-5 h-5 cursor-pointer transition-transform"
                                                            x-bind:class="isMenuOpen('en', '{{ $menu['id'] }}') ? 'rotate-180' : ''"
                                                            @click="toggleMenu('en', '{{ $menu['id'] }}')"/>
                                                </div>
                                            </div>

                                            <div x-show="isMenuOpen('en', '{{ $menu['id'] }}')" x-collapse
                                                 class="p-4 bg-white border-t border-gray-100">
                                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                                    <x-input
                                                        label="Tên menu"
                                                        wire:model.live.debounce.500ms="data.en.menu_items.{{$menuIndex}}.name"
                                                        required
                                                        placeholder="Nhập tên menu, ví dụ: Trang chủ, Tin tức, Liên hệ,..."
                                                    />
                                                    <x-input
                                                        label="Link"
                                                        wire:model.live.debounce.500ms="data.en.menu_items.{{$menuIndex}}.url"
                                                        placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                        required
                                                    />
                                                </div>
                                                @if(!empty($menu['children'] ?? []))
                                                 <div class="mt-3 border-t pt-3">
                                                     <div class="flex justify-between items-center mb-2">
                                                         <p class="font-semibold text-sm">Menu cấp 2</p>
                                                     </div>

                                                    <div class="space-y-2"
                                                         x-data="{
                                                     sortable: null,
                                                     initSortable() {
                                                         if (this.sortable) this.sortable.destroy();
                                                         this.sortable = new Sortable(this.$refs.childListEn, {
                                                             animation: 150,
                                                             handle: '.drag-child-handle-en',
                                                             onEnd: () => {
                                                                 let order = Array.from(this.$refs.childListEn.children)
                                                                     .map(el => el.dataset.id)
                                                                     .filter(Boolean);
                                                                 $wire.updateChildOrder('en', '{{ $menu['id'] }}', order);
                                                             }
                                                         });
                                                     }
                                                 }"
                                                          x-init="$nextTick(() => initSortable())"
                                                          x-ref="childListEn">
                                                          @foreach($menu['children'] ?? [] as $childIndex => $child)
                                                            <div data-id="{{ $child['id'] }}"
                                                                 wire:key="en-child-{{ $menu['id'] }}-{{ $child['id'] }}"
                                                                 class="border border-gray-200 rounded-lg"
                                                                 x-data="{ openChild: true }">
                                                                <div
                                                                    class="flex items-center gap-3 px-3 py-2 bg-gray-50 border-b border-gray-100">
                                                                    <x-icon name="o-bars-3"
                                                                            class="drag-child-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-600"/>
                                                                    <button type="button"
                                                                            class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                                                            @click="openChild = !openChild">
                                                                <span class="inline-flex items-center gap-2">
                                                                    {{ $data['en']['menu_items'][$menuIndex]['children'][$childIndex]['name'] ? 'Menu cấp 2 - ' . $data['en']['menu_items'][$menuIndex]['children'][$childIndex]['name'] : 'Menu cấp 2 mới' }}
                                                                </span>
                                                                    </button>
                                                                    <x-button
                                                                        icon="o-plus"
                                                                        label="Thêm menu cấp 3"
                                                                        class="btn-sm bg-emerald-600 text-white"
                                                                        wire:click="addGrandChildItem('en', '{{ $menu['id'] }}', '{{ $child['id'] }}')"
                                                                        spinner="addGrandChildItem('en', '{{ $menu['id'] }}', '{{ $child['id'] }}')"
                                                                    />
                                                                    <x-button
                                                                        icon="o-trash"
                                                                        class="btn-ghost text-red-600"
                                                                        wire:click="removeChildItem('en', '{{ $menu['id'] }}', '{{ $child['id'] }}')"
                                                                    />
                                                                    <x-icon name="o-chevron-down"
                                                                            class="w-5 h-5 cursor-pointer transition-transform text-gray-500"
                                                                            x-bind:class="openChild ? 'rotate-180' : ''"
                                                                            @click="openChild = !openChild"/>
                                                                </div>

                                                                <div x-show="openChild" x-collapse class="p-3">
                                                                     <div
                                                                         class="grid grid-cols-1 lg:grid-cols-[1fr_1fr] gap-3 items-center">
                                                                         <x-input
                                                                             label="Tên menu cấp 2"
                                                                             wire:model.live.debounce.500ms="data.en.menu_items.{{$menuIndex}}.children.{{$childIndex}}.name"
                                                                             required
                                                                             placeholder="Nhập tên menu cấp 2, ví dụ: Giới thiệu, Tin tức,..."
                                                                         />
                                                                         <x-input
                                                                             label="Link menu con"
                                                                             wire:model.live.debounce.500ms="data.en.menu_items.{{$menuIndex}}.children.{{$childIndex}}.url"
                                                                             placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                                             required
                                                                         />
                                                                     </div>
                                                                    @if(!empty($child['children'] ?? []))
                                                                    <div class="mt-3 border-t pt-3 pl-3">
                                                                         <div
                                                                             class="flex justify-between items-center mb-2">
                                                                             <p class="font-semibold text-sm">Menu cấp 3</p>
                                                                         </div>

                                                                        <div class="space-y-2"
                                                                             x-data="{
                                                                     sortable: null,
                                                                     initSortable() {
                                                                         if (this.sortable) this.sortable.destroy();
                                                                         this.sortable = new Sortable(this.$refs.grandChildListEn, {
                                                                             animation: 150,
                                                                             handle: '.drag-grandchild-handle-en',
                                                                             onEnd: () => {
                                                                                 let order = Array.from(this.$refs.grandChildListEn.children)
                                                                                     .map(el => el.dataset.id)
                                                                                     .filter(Boolean);
                                                                                 $wire.updateGrandChildOrder('en', '{{ $menu['id'] }}', '{{ $child['id'] }}', order);
                                                                             }
                                                                         });
                                                                     }
                                                                 }"
                                                                              x-init="$nextTick(() => initSortable())"
                                                                              x-ref="grandChildListEn">
                                                                              @foreach($child['children'] ?? [] as $grandChildIndex => $grandChild)
                                                                                <div data-id="{{ $grandChild['id'] }}"
                                                                                     wire:key="en-grandchild-{{ $menu['id'] }}-{{ $child['id'] }}-{{ $grandChild['id'] }}"
                                                                                     class="grid grid-cols-1 lg:grid-cols-[auto_1fr_1fr_auto] gap-3 items-center">
                                                                                    <x-icon name="o-bars-3"
                                                                                            class="drag-grandchild-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-600 mt-4"/>
                                                                                     <x-input
                                                                                         label="Tên menu cấp 3"
                                                                                         wire:model.live.debounce.500ms="data.en.menu_items.{{$menuIndex}}.children.{{$childIndex}}.children.{{$grandChildIndex}}.name"
                                                                                         required
                                                                                         placeholder="Nhập tên menu cấp 3"
                                                                                     />
                                                                                     <x-input
                                                                                         label="Link menu cấp 3"
                                                                                         wire:model.live.debounce.500ms="data.en.menu_items.{{$menuIndex}}.children.{{$childIndex}}.children.{{$grandChildIndex}}.url"
                                                                                         placeholder="/duong-dan hoặc https://example.com hoặc ###"
                                                                                         required
                                                                                     />
                                                                                    <x-button
                                                                                        icon="o-trash"
                                                                                        class="btn-ghost text-red-600 mt-4"
                                                                                        wire:click="removeGrandChildItem('en', '{{ $menu['id'] }}', '{{ $child['id'] }}', '{{ $grandChild['id'] }}')"
                                                                                    />
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                    @endif
                                                                </div>
                                                             </div>
                                                         @endforeach
                                                     </div>
                                                 </div>
                                                @endif
                                             </div>
                                         </div>
                                     </div>
                                     @endforeach
                                 </div>
                             </div>
                         @endif
                    </div>
                </x-tab>
            </x-tabs>
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="{{__('Action')}}" shadow separator progress-indicator="save">
            <x-button
                label="Lưu cấu hình"
                class="bg-primary text-white my-1 w-full"
                wire:click="save"
                wire:loading.attr="disabled"
                wire:target="save"
                spinner
            />
            {{--            <x-button label="Xem trang" link="{{route('client.home')}}" external class="bg-warning text-white my-1 w-full" />--}}
        </x-card>
    </div>
</div>



