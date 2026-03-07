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
        $previewData = \Illuminate\Support\Facades\Cache::get('preview_intro_data');
        $locale = app()->getLocale();
        $this->pageData= $previewData[$locale] ?? [];
    }
};
?>

<div class="w-[90%] lg:w-330 mx-auto">
    {{--  start - title  --}}
    <x-slot:title>
        {{__('Preview the introduction page') }}
    </x-slot:title>
    {{--  end - title  --}}

    {{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
        {{--        <a href="#" class="font-semibold text-slate-700 hover:text-fita">{{__('List of articles')}}</a>--}}
        {{--        <span><x-icon name="s-chevron-right" class="w-4 h-4" /></span>--}}
        <span>{{__('General Introduction')}}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{__('Preview the introduction page')}}
    </x-slot:titleBreadcrumb>
    {{--     end - breadcrumb--}}
    @if(empty($pageData))
        <div class="text-center py-20">
            <p class="text-gray-500 text-lg">{{__('No data to preview')}}</p>
        </div>
    @endif

    @foreach($pageData['dynamicBlocks'] ?? [] as $index => $block)
        @switch($block['type'])
            @case('generalIntroduction')
                <div class="flex gap-13 flex-col lg:flex-row my-10">
                    <div class=" w-full lg:w-1/2">
                        <img src="{{ $pageData['dynamicBlocks'][$index]['data']['photo']!='' ? asset($pageData['dynamicBlocks'][$index]['data']['photo']): asset('assets/images/LogoKhoaCNTT.png') }}" class="object-cover h-110" alt="">
                    </div>
                    <div class=" w-full lg:w-1/2 space-y-4">
                        <div class="uppercase text-fita font-barlow mb-1">
                            <h2 class="font-semibold text-[18px]">{{__('About the')}}</h2>
                            <h2 class="font-bold text-[26px]/[28px] lg:text-[34px]/[36px]">{{__('Faculty of Information Technology')}}</h2>
                        </div>
                        <div class="text-[16px]/[24px] text-justify space-y-2">
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
                            <div class="w-full lg:w-1/3 bg-gray-200 px-7 py-5 lg:h-95 hover:bg-fita hover:*:text-white">
                                <h3 class="text-fita font-barlow font-bold text-[20px]/[24px] lg:text-[24px]/[26px]">
                                    {{ $column['title'] ?? '' }}
                                </h3>

                                <div class="text-[16px]/[24px] text-justify space-y-2 mt-2">
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
