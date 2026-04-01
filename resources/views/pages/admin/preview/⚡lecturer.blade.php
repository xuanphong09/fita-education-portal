<?php

use App\Models\Lecturer;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.client')]
class extends Component {
    public string $slug;
    public string $locale = 'vi';

    public ?Lecturer $lecturer = null;

    public string $name = '';
    public string $email = '';
    public ?string $phone = null;
    public ?string $avatar = null;
    public ?string $academicTitleLabel = null;
    public ?string $degreeLabel = null;
    public string $introduction = '';
    public array $blocks = [];

    private function cacheKey(): string
    {
        return 'lecturer_preview_' . auth()->id() . '_' . $this->slug;
    }

    public function mount(): void
    {
        $this->locale = app()->getLocale();
        $this->lecturer = Lecturer::query()->where('slug', $this->slug)->firstOrFail();

        $this->loadData();
    }

    public function switchLocale(string $locale): void
    {
        $this->locale = in_array($locale, ['vi', 'en'], true) ? $locale : 'vi';
        app()->setLocale($this->locale);
        $this->loadData();
    }

    private function loadData(): void
    {
        $data = Cache::get($this->cacheKey());

        if (is_array($data)) {
            $this->name = (string) ($data['name'] ?? '');
            $this->email = (string) ($data['email'] ?? '');
            $this->phone = $data['phone'] ?? null;
            $this->avatar = $data['avatar'] ?? asset('/assets/images/default-user-image.png');

            $this->academicTitleLabel = $this->localizeAcademicTitle($data['academic_title'] ?? null);
            $this->degreeLabel = $this->localizeDegree($data['degree'] ?? null);

            $this->introduction = (string) data_get($data, "introduction.{$this->locale}", '');
            $this->blocks = data_get($data, "blocks.{$this->locale}", []);
            return;
        }

        $this->loadFromDatabase();
    }

    private function loadFromDatabase(): void
    {
        $page = Page::query()->where('slug', $this->slug)->first();
        $pageData = $page?->getTranslation('content_data', $this->locale, false) ?? [];

        $this->name = (string) ($this->lecturer?->user?->name ?? '');
        $this->email = (string) ($this->lecturer?->user?->email ?? '');
        $this->phone = $this->lecturer?->phone;
        $this->avatar = $this->lecturer?->user?->avatar
            ? asset($this->lecturer->user->avatar)
            : asset('/assets/images/default-user-image.png');

        $this->academicTitleLabel = $this->localizeAcademicTitle($this->lecturer?->academic_title);
        $this->degreeLabel = $this->localizeDegree($this->lecturer?->degree);

        $this->introduction = (string) ($pageData['introduction'] ?? '');
        $this->blocks = is_array($pageData['blocks'] ?? null) ? $pageData['blocks'] : [];
    }

    private function localizeAcademicTitle(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $key = Str::lower(trim((string) $value));
        $translated = trans("lecturer.academic_title.$key");

        return $translated !== "lecturer.academic_title.$key" ? $translated : $value;
    }

    private function localizeDegree(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $key = Str::lower(trim((string) $value));
        $translated = trans("lecturer.degree.$key");

        return $translated !== "lecturer.degree.$key" ? $translated : $value;
    }
};
?>

<div>
    <x-slot:title>{{ $name ?: 'Chế độ xem trước' }}</x-slot:title>

    <div class="fixed top-0 left-0 right-0 z-[9999] bg-gray-900 text-white text-sm flex items-center justify-between px-4 py-2 shadow-lg print:hidden">
        <div class="flex items-center gap-3">
            <x-icon name="o-eye" class="w-4 h-4 text-yellow-400"/>
            <span class="font-medium text-yellow-400">Chế độ xem trước</span> —
            <span class="hidden lg:inline text-gray-300">{{ $name }}</span>
        </div>

        <div class="flex items-center gap-2">
            <button wire:click="switchLocale('vi')"
                    class="px-2 py-0.5 rounded text-xs {{ $locale === 'vi' ? 'bg-primary text-white' : 'bg-gray-700 hover:bg-gray-600' }}">
                🇻🇳 VI
            </button>
            <button wire:click="switchLocale('en')"
                    class="px-2 py-0.5 rounded text-xs {{ $locale === 'en' ? 'bg-primary text-white' : 'bg-gray-700 hover:bg-gray-600' }}">
                🇺🇸 EN
            </button>

            <span class="text-gray-600">|</span>

            <a href="{{ route('admin.lecturer.manager', ['slug' => $slug]) }}"
               class="flex items-center gap-1 px-3 py-1 bg-primary rounded text-xs hover:bg-primary/80 transition-all">
                <x-icon name="o-arrow-left" class="w-3 h-3"/>
                Chỉnh sửa
            </a>
        </div>
    </div>

    <div class="h-10"></div>

    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="col-span-3 lg:col-span-1">
                <img src="{{ $avatar }}" alt="{{ $name }}" class="w-full h-120 lg:h-125 rounded-lg object-cover">
            </div>

            <div class="col-span-3 lg:col-span-2">
                <div class="mb-4">
                    <h1 class="text-4xl font-bold text-fita font-barlow uppercase">
                        @if($academicTitleLabel)
                            {{ $academicTitleLabel }}
                            @if(app()->getLocale() === 'vi')
                                ,
                            @endif
                        @endif
                        @if($degreeLabel)
                            {{ $degreeLabel }}
                        @endif
                        {{ $name }}
                    </h1>
                </div>

                <div>
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">{{ __('Introduction') }}</h2>
                    <div class="my-4 text-[16px]/[24px] max-w-none! prose">
                        @if($introduction)
                            {!! $introduction !!}
                        @else
                            <p>{{ __('No introduction available.') }}</p>
                        @endif
                    </div>
                </div>

                <div>
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">{{ __('Contact') }}</h2>
                    <div class="my-4 text-[16px]/[24px]">
                        <p>{{ __('Phone number') }}: {{ $phone ?? '' }}</p>
                        <p>Email: {{ $email }}</p>
                    </div>
                </div>
            </div>

            <div class="col-span-3">
                @foreach($blocks as $block)
                    <div class="mb-6" wire:key="preview-block-{{ $block['id'] ?? md5(json_encode($block)) }}">
                        <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">
                            {{ $block['title'] ?? '' }}
                        </h2>
                        <div class="my-4 text-[16px]/[24px] max-w-none! prose">
                            {!! $block['content'] ?? '' !!}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

