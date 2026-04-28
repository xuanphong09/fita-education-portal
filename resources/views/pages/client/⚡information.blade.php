<?php

use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.client')]
class extends Component {
    public array $pageData = [];
    public  $validColumns;
    public function mount()
    {
        // 1. Tìm bản ghi có slug là 'gioi-thieu' trong Database
        $page = Page::where('slug', 'gioi-thieu')->first();

        // 2. Nếu tìm thấy và có dữ liệu content_data
        if ($page && $page->content_data) {
            $locale = app()->getLocale();
            $translation = $page->getTranslation('content_data', $locale, false);
            $this->pageData = $translation ?: [];
        }

//        $validColumns = collect($pageData['block3Columns'] ?? [])->filter(function($column) {
//            return !empty(trim($column['title'] ?? '')) || !empty(trim($column['content'] ?? ''));
//        });

    }
};
?>

<div class="w-full max-w-330 mx-auto px-4 sm:px-6 lg:px-8">
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Introduction') }}
    </x-slot:title>
    {{--  end - title  --}}

    {{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
        {{--        <a href="#" class="font-semibold text-slate-700 hover:text-fita">{{__('List of articles')}}</a>--}}
        {{--        <span><x-icon name="s-chevron-right" class="w-4 h-4" /></span>--}}
        <span>{{__('General Introduction')}}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{__('Introduction')}}
    </x-slot:titleBreadcrumb>
    {{--     end - breadcrumb--}}
    @foreach($pageData['dynamicBlocks'] ?? [] as $index => $block)
        @switch($block['type'])
            @case('generalIntroduction')
                <div class="flex gap-13 flex-col lg:flex-row my-10">
                    <div class=" w-full lg:w-1/2">
                        <img src="{{ $pageData['dynamicBlocks'][$index]['data']['photo']!='' ? asset($pageData['dynamicBlocks'][$index]['data']['photo']): asset('assets/images/LogoKhoaCNTT.png') }}" class="object-cover h-110 mx-auto" alt="">
                    </div>
                    <div class=" w-full lg:w-1/2 space-y-4">
                        <div class="uppercase text-fita font-barlow mb-1">
                            <h2 class="font-semibold text-[18px]">{{__('About the')}}</h2>
                            <h2 class="font-bold text-[26px]/[28px] lg:text-[34px]/[36px]">{{__('Faculty of Information Technology')}}</h2>
                        </div>
                        <div class="text-[16px]/[24px] text-justify space-y-2 leading-relaxed">
                            @foreach(explode("\n", $pageData['dynamicBlocks'][$index]['data']['description'] ?? '') as $paragraph)
                                @if(trim($paragraph))
                                    <p>{{ $paragraph }}</p>
                                @endif
                            @endforeach
                        </div>

                    </div>
                </div>
                @break
            @case('block3Columns')
                @php
                    // Lọc bỏ những cột mà Admin chưa nhập gì cả
                    $validColumns = collect($pageData['dynamicBlocks'][$index]['data'] ?? [])->filter(function($column) {
                        return !empty(trim($column['title'] ?? '')) || !empty(trim($column['content'] ?? ''));
                    });
                @endphp
                @if($validColumns->isNotEmpty())
                    <div class="flex gap-6 lg:gap-10 my-10 flex-col lg:flex-row">

                        @foreach($validColumns as $column)
                            <div class="w-full lg:w-1/3 bg-gray-200 px-5 py-3 lg:px-7 lg:py-5 lg:min-h-95 hover:bg-fita hover:*:text-white">
                                <h3 class="text-fita font-barlow font-bold text-[20px]/[24px] lg:text-[24px]/[26px]">
                                    {{ $column['title'] ?? '' }}
                                </h3>

                                <div class="text-[16px]/[24px] text-justify space-y-2 mt-2 leading-relaxed">
                                    @foreach(explode("\n", $column['content'] ?? '') as $paragraph)
                                        @if(trim($paragraph))
                                            <p>{{ $paragraph }}</p>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                    </div>
                @endif
                @break
            @case('blockSingle')
                <div class="my-5">
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">{{$pageData['dynamicBlocks'][$index]['data']['title']}} </h2>
                    <div class="text-[16px]/[24px] text-justify space-y-3 mt-2">
                        @foreach(explode("\n", $pageData['dynamicBlocks'][$index]['data']['description'] ?? '') as $paragraph)
                            @if(trim($paragraph))
                                <p>{{ $paragraph }}</p>
                            @endif
                        @endforeach
                    </div>
                </div>
                @break
        @endswitch
    @endforeach
</div>
