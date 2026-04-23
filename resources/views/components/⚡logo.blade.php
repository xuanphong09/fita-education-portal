<?php

use Livewire\Component;

new class extends Component
{
    public string $layout ='';
};
?>

<a href=" @if($this->layout == "admin"){{route('admin.dashboard')}} @else {{route('client.home')}} @endif" class="flex items-center ms-5 gap-3 @if($this->layout == "footer") m-0! @endif ms-0!" wire:navigate>
    <img src="{{asset('assets/images/FITA.png')}}" class="size-12 @if($this->layout == "footer") size-16! @endif @if($this->layout == "admin") size-14! ms-5 @endif rounded-[50%] object-cover shadow-md" alt="Logo"
         x-data="{ isScrolled: false }"
         @scroll.window="isScrolled = (window.pageYOffset > 50)"
         :class="isScrolled ? 'size-13' : 'md:size-18 size-13'"
    />
    <div class="xl:flex hidden flex-col ms-2 wow fadeInDown @if($this->layout == "footer")flex! @endif @if(app()->getLocale() == 'en' && $this->layout == "client") ms-0! @endif">
        <h1 class="font-bold tracking-wider uppercase text-white font-barlow overflow-hidden @if($this->layout == "client") text-fita! @endif @if($this->layout == "admin") md:text-[20px]/[26px]! text-[18px]/[24px]! @endif"
            x-data="{ isScrolled: false }"
            @scroll.window="isScrolled = (window.pageYOffset > 50)"
            :class="isScrolled ? 'md:text-[20px]/[26px] text-[19px]/[24px]' : 'md:text-[26px]/[32px] text-[19px]/[24px]'"
        >{{__('Faculty of Information Technology')}}</h1>
        <h1 class="font-semibold tracking-wider uppercase overflow-hidde md:text-[16px]/[20px] text-[13px]/[16px] text-white font-barlow @if(app()->getLocale() == 'en') text-[15px]/[17px] @endif @if($this->layout == "client") text-black! @endif @if($this->layout == "admin") md:text-[16px]/[20px]! text-[13px]/[16px]! @endif"
            x-data="{ isScrolled: false }"
            @scroll.window="isScrolled = (window.pageYOffset > 50)"
            :class="isScrolled ? 'md:text-[16px]/[20px] text-[13px]/[16px]' : 'md:text-[20px]/[24px] text-[13px]/[16px]'"
        >{{__('Khoa Công nghệ Thông tin')}}</h1>
    </div>
</a>
