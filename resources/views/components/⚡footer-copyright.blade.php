<?php

use Livewire\Component;

new class extends Component
{
    public string $layout ='';
};
?>

<div class=" bg-fita border-t border-gray-200 px-8 text-center lg:text-left lg:px-6 py-3 text-white gap-3 flex justify-center items-center @if($this->layout == "admin") bg-mauchudao! @endif">
    <div class="w-[90%] lg:w-330">
        {{__("Copyright")}}
        <a href="{{route('client.home')}}" class="text-white hover:text-limitless-teal font-medium underline underline-offset-4 transition">:
            {{__('Faculty of Information Technology')}},
        </a>
        <a href="@if(app()->getLocale() == 'vi') https://vnua.edu.vn  @else https://eng.vnua.edu.vn/@endif" class="text-white underline underline-offset-4 hover:text-limitless-teal font-medium transition">
            {{__('Vietnam National University of Agriculture')}}
        </a>
    </div>
</div>
