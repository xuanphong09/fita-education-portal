<?php

use App\Models\Lecturer;
use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Str;

new
#[Layout('layouts.client')]
class extends Component {
    public string $slug;
    public $lecturer;
    public $pages;
    public $page_data;
    public ?string $academicTitleLabel = null;
    public ?string $degreeLabel = null;
    public ?string $seoTitle = null;
    public ?string $avatar = null;

    public function mount()
    {
        $this->lecturer = Lecturer::where('slug', $this->slug)->firstOrFail();
        $this->pages = Page::where('slug', $this->slug)->first();
        if($this->pages != null) {
            $this->page_data = $this->pages->getTranslation('content_data', app()->getLocale(), false);
        }
        $this->academicTitleLabel = $this->localizeAcademicTitle($this->lecturer->academic_title);
        $this->degreeLabel = $this->localizeDegree($this->lecturer->degree);
        $this->avatar = $this->lecturer->user?->avatar ? asset($this->lecturer->user->avatar) : asset('/assets/images/default-user-image.png');
    }

    private function localizeAcademicTitle(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $key = Str::lower(trim((string) $value));
        $translated = trans("lecturer.academic_title.$key");

        return $translated !== "lecturer.academic_title.$key"
            ? $translated
            : (string) $value;
    }

    private function localizeDegree(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $key = Str::lower(trim((string) $value));
        $translated = trans("lecturer.degree.$key");

        return $translated !== "lecturer.degree.$key"
            ? $translated
            : (string) $value;
    }

    public function getSeoMetaProperty(): array
    {
        $locale = app()->getLocale();
        if($this->pages !=null){
            $data = $this->pages->getTranslation('content_data', $locale, false);
            $htmlContent = $data['introduction'];
            $cleanText = html_entity_decode($htmlContent, ENT_QUOTES, 'UTF-8');
            $cleanText = strip_tags($cleanText);
            $cleanText = preg_replace('/\s+/u', ' ', $cleanText);
            $description = Str::limit(trim($cleanText), 200, '...');
        }
        $parts = array_filter([
            $this->academicTitleLabel,
            $this->degreeLabel,
            $this->lecturer->user?->name,
        ]);
        $title = trim(implode(' ', $parts)) . ' - '. __('Lecturers');

        return [
            'title' => $title,
            'description' => $description ?? '',
            'image' => $this->lecturer->user?->avatar ? asset($this->lecturer->user?->avatar) : null,
        ];
    }
};
?>

<div class="container mx-auto px-4 py-8">
        <x-slot:seo>
            @php
                $seo = $this->getSeoMetaProperty();
            @endphp
            <x-seo
                :title="$seo['title']"
                :description="$seo['description']"
                :image="$seo['image']"
            >
            </x-seo>
        </x-slot:seo>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="col-span-3 lg:col-span-1">
            <img src="{{ $this->avatar }}" alt="TS Phạm Quang Dũng"
                 class="w-full h-120 lg:h-125 rounded-lg object-cover">
        </div>
        <div class="col-span-3 lg:col-span-2">
            <div class="mb-4">
                <h1 class="text-4xl font-bold text-fita font-barlow font-bold uppercase">
                    @if($this->academicTitleLabel)
                        {{ $this->academicTitleLabel }}
                        @if(app()->getLocale() === 'vi')
                            ,
                        @endif
                    @endif
                    @if($this->degreeLabel)
                        {{ $this->degreeLabel }}
                    @endif
                    {{ $this->lecturer->user?->name }}</h1>
                {{--                <div class="text-[18px] mt-2">TS - Phó Trưởng khoa phụ trách</div>--}}
            </div>
            <div>
                <div>
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">
                        {{__('Introduction')}}</h2>
                    <div class="my-4 text-[16px]/[24px] max-w-none! prose">
                        @if($this->page_data['introduction'] ?? null)
                            {!! $this->page_data['introduction'] !!}
                        @else
                            <p>{{__('No introduction available.')}}</p>
                        @endif
                    </div>
                </div>
                <div>
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">
                        {{__('Contact')}}</h2>
                    <div class="my-4 text-[16px]/[24px]">
                        <p>{{__('Phone number')}}: {{$this->lecturer->phone ?? ''}}</p>
                        <p>Email: {{$this->lecturer->user?->email??''}}</p>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-3">
            <div>
                @foreach($this->page_data['blocks'] ?? [] as $index => $block)
                <div data-id="{{ $block['id'] }}" wire:key="vi-dyn-{{ $block['id'] }}">
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">
                        {{$block['title'] }}</h2>
                    <ul class="my-4 text-[16px]/[24px] max-w-none! prose">
                        {!! $block['content'] !!}
                    </ul>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>
