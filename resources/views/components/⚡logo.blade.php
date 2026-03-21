<?php

use Livewire\Component;

new class extends Component
{
    public string $layout ='';
};
?>

<a href="{{route('client.home')}}" class="flex items-center gap-3 ms-5 @if($this->layout == "footer") m-0! @endif" wire:navigate>
    <img src="{{asset('assets/images/FITA.png')}}" class="size-12 @if($this->layout == "footer") size-16! @endif rounded-[50%] object-cover" alt="Logo" />
    <div class="d-flex flex-column ms-2 d-none d-md-block d-xxl-block wow fadeInDown">
        <h1 class="font-semibold tracking-wider uppercase text-white md:text-[20px]/[24px] text-[16px]/[20px] @if($this->layout == "client") text-fita! @endif">{{__('Faculty of Information Technology')}}</h1>
        <h1 class="font-semibold tracking-wider md:text-[16px]/[20px] text-[13px]/[16px] text-white @if($this->layout == "client") text-black! @endif">{{__('Khoa Công nghệ Thông tin')}}</h1>
    </div>
</a>
