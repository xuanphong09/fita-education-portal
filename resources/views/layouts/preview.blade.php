<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <title>{{ isset($title) ? $title.' | ' . __('Faculty of Information Technology') : __('Faculty of Information Technology') }}</title>
    <link rel="icon" type="image/png" href="{{asset('assets/images/LogoKhoaCNTT.png')}}" />

    {{-- TinyMCE --}}
    {{--    <script src="{{ asset('assets/js/tinymce/tinymce.min.js') }}" referrerpolicy="origin"></script>--}}

    {{-- Sortable.js --}}
    {{--    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.1/Sortable.min.js"></script>--}}
    {{-- Vite --}}
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe-lightbox.umd.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen antialiased bg-gray-50 text-black text-[15px]">
{{-- start nav bar--}}
<div class="sticky top-0 z-50 w-full">
    {{-- start top nav bar--}}
    <div class="bg-fita text-white text-sm py-2 px-4 flex justify-between items-center h-8">

        {{-- Bên trái: Tên trường --}}
        <div class="flex items-center gap-3 ms-10 text-[14px]">
            <a href="@if(app()->getLocale() == 'vi') https://vnua.edu.vn  @else https://eng.vnua.edu.vn/ @endif" class="">{{__('Vietnam National University of Agriculture')}}</a>
        </div>

        {{-- Bên phải: Link phụ (ICETAI, Sổ tay...) --}}
        <div class="flex items-center font-medium">
            <livewire:client.global-search />
            <span class="separator text-[18px] ms-3 me-2 text-white">|</span>
            <livewire:language-switcher layout="client"/>
        </div>
    </div>
    {{-- start end nav bar--}}
    {{-- start bottom nav bar--}}
    <x-nav full-width class="h-19 bg-white text-white content-center shadow [&>div]:py-0! [&>div]:h-full! hidden lg:block flex-none">

        {{--  start navbar right  --}}
        <x-slot:brand class="">
            <livewire:logo layout="client"/>
        </x-slot:brand>
        {{--  end navbar right  --}}

        {{--  start navbar left  --}}
        <x-slot:actions class="gap-0!">
            <div class="dropdown dropdown-hover h-full group">
                <x-button
                    link="{{route('client.information')}}"
                    tabindex="0"
                    class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full group-hover:bg-fita2 group-hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"
                    responsive
                >
                    {{__('Introduction')}}
                </x-button>

                <ul tabindex="0" class="text-black dropdown-content z-50 px-0  menu shadow-lg bg-base-100 rounded-b-box border border-gray-300 border-t-transparent w-max min-w-full">
                    <li class="w-full">
                        <x-button
                            class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"
                            label="{{__('Faculty of Information Technology')}}"
                            link="{{route('client.information')}}"
                        ></x-button>
                    </li>
                    <li class="w-full">
                        <x-button
                            class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"
                            label="Khoa Công nghệ"
                        ></x-button>
                    </li>
                    <li class="w-full">
                        <x-button
                            class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"
                            label="Khoa Công nghệ thông tin và Truyền thông"
                        ></x-button>
                    </li>
                </ul>
            </div>

            {{--            <x-button--}}
            {{--                link="###"--}}
            {{--                class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full hover:bg-fita2 hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"--}}
            {{--                responsive--}}
            {{--            >--}}
            {{--                Giới thiệu--}}
            {{--            </x-button>--}}
            <x-button
                link="###"
                class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full hover:bg-fita2 hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"
                responsive
            >
                Đào tạo
            </x-button>
            <x-button
                link="###"
                class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full hover:bg-fita2 hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"
                responsive
            >
                Đào tạo
            </x-button>
            <x-button
                link="###"
                class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full hover:bg-fita2 hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"
                responsive
            >
                Đào tạo
            </x-button>
            <x-button
                link="###"
                class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full hover:bg-fita2 hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"
                responsive
            >
                Đào tạo
            </x-button>
        </x-slot:actions>
        {{--  end navbar left  --}}
    </x-nav>
    {{-- start bottom nav bar--}}

    <x-nav sticky class="lg:hidden">
        <x-slot:brand>
            <livewire:logo layout="client"/>
        </x-slot:brand>
        <x-slot:actions>
            <label for="main-drawer" class="lg:hidden mr-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>
</div>
{{-- end nav bar--}}
{{-- start main layout --}}
<x-main with-nav full-width>

    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit lg:hidden pt-26 h-full! text-[15px] font-medium">
        {{-- MENU --}}
        <x-menu activate-by-route>
            <x-menu-item title="Hello" link="###" class="rounded-none hover:bg-fita hover:text-white"/>
            <div class="[&_summary]:hover:bg-fita! [&_summary]:hover:text-white! [&_summary]:rounded-none!">
                <x-menu-sub title="Settings" class="">
                    <x-menu-item title="Wifi" link="####" class="rounded-none hover:bg-fita hover:text-white"/>
                    <x-menu-item title="Archives" link="####" class="rounded-none  hover:bg-fita hover:text-white"/>
                </x-menu-sub>
            </div>
        </x-menu>
    </x-slot:sidebar>


    {{--  start content   --}}
    <x-slot:content class="p-0! bg-slate-100 flex flex-col">
        {{-- start breadcrumb --}}
        @if(isset($breadcrumb) || isset($titleBreadcrumb))
            <div class="bg-white px-6 py-6 relative overflow-hidden min-h-20">
                <div class="absolute inset-0 z-0">
                    <div class="absolute inset-0 bg-slate-200 opacity-65"></div>
                    <img
                        src="{{asset('assets/images/backgrounds/pager-bg.png')}}"
                        alt="Background"
                        class="w-full h-full object-cover object-center"
                    />
                </div>
                <div class="relative z-20">
                    <h2 class="text-center text-[40px]/[50px]">@if(isset($titleBreadcrumb)){{$titleBreadcrumb}}@endif</h2>
                    @if(isset($breadcrumb))
                        <div class="flex items-center gap-1 text-gray-500 justify-center w-full">
                            <a href="{{route('client.home')}}" wire:navigate class="hover:text-fita font-semibold text-slate-700">{{__('Home page')}}</a>
                            <span><x-icon name="s-chevron-right" class="w-4 h-4" /></span>
                            {{$breadcrumb}}
                        </div>
                    @endif
                </div>

                <h2 class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[12vw]/[12vw] md:text-[8vw]/[8vw] tracking-[0.15em] lg:tracking-[0.3em] text-fita opacity-[0.07] font-extrabold pointer-events-none whitespace-nowrap z-10 w-full text-center">
                    FITA - VNUA
                </h2>

            </div>
        @endif
        {{-- end breadcrumb --}}

        {{-- start slot content --}}
        <div class="pb-10 flex-1">
            {{ $slot }}
        </div>
        {{-- end slot content --}}

    </x-slot:content>
    {{--  end content   --}}

</x-main>
{{-- end main layout --}}

<x-toast class="z-50" />
<div x-data="{ show: false }" @scroll.window="show = window.pageYOffset > 300">
    <x-button
        icon="s-arrow-up"
        class="btn-circle bg-fita font-extrabold text-white fixed bottom-8 right-4 z-50 shadow-lg hover:bg-fita2"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
    />
</div>

{{-- start scripts--}}
{{-- end scripts--}}
@livewireScripts
</body>
</html>
