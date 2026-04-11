<?php

use Livewire\Component;

new class extends Component
{
    public string $layout ='';
};
?>

<a href=" @if($this->layout == "admin"){{route('admin.dashboard')}} @else {{route('client.home')}} @endif" class="flex items-center ms-5 gap-3 @if($this->layout == "footer") m-0! @endif" wire:navigate>
    <img src="{{asset('assets/images/FITA.png')}}" class="size-12 @if($this->layout == "footer") size-16! @endif rounded-[50%] object-cover" alt="Logo" />
    <div class="md:flex flex-col ms-2 wow fadeInDown @if(app()->getLocale() == 'en' && $this->layout == "client") hidden @endif">
        <h1 class="font-extrabold tracking-wider uppercase text-white md:text-[20px]/[26px] text-[16px]/[20px] font-barlow whitespace-nowrap @if($this->layout == "client") text-fita! @endif">{{__('Faculty of Information Technology')}}</h1>
        <h1 class="font-bold tracking-wider uppercase md:text-[16px]/[20px] text-[13px]/[16px] text-white font-barlow @if(app()->getLocale() == 'en') text-[15px]/[17px] @endif @if($this->layout == "client") text-black! @endif">{{__('Khoa Công nghệ Thông tin')}}</h1>
    </div>
</a>
