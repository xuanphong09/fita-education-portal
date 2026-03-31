<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
class extends Component {
    //
};
?>

<div>
{{--  start - title  --}}
    <x-slot:title>
        {{ __('Dashboard') }}
    </x-slot:title>
{{--  end - title  --}}

{{-- start - breadcrumb --}}
    <x-slot:breadcrumb>
{{--        <a href="#" class="font-semibold text-slate-700">{{__('List of articles')}}</a>--}}
{{--        <span class="mx-1">/</span>--}}
{{--        <span>{{__('Add post')}}</span>--}}
    </x-slot:breadcrumb>
{{-- end - breadcrumb --}}

{{--    start - header--}}
    <x-header title="{{__('Dashboard')}}" class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"></x-header>
{{--    end - header--}}
    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">

        <x-card class="col-span-10 flex flex-col p-3!">
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="{{__('Action')}}" shadow separator progress-indicator="save">
            <x-button label="{{__('Save')}}" class="bg-primary text-white my-1 w-full" spinner/>
        </x-card>
    </div>
</div>
