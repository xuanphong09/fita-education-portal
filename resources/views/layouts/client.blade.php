<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="{{asset('assets/images/LogoKhoaCNTT.png')}}" />

    {{-- SEO Meta Tags -- truyền $post từ từng trang hoặc fallback về $title --}}
    @if(isset($seo))
        {{ $seo }}
    @else
        <x-seo :title="isset($title) ? $title : null"/>
    @endif

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
    <div class="bg-fita text-white text-sm py-2 lg:px-4 px-2 flex justify-between items-center h-8">

        {{-- Bên trái: Tên trường --}}
        <div class="flex items-center gap-3 lg:ms-10 text-[14px] uppercase">
            <a href="@if(app()->getLocale() == 'vi') https://vnua.edu.vn  @else https://eng.vnua.edu.vn/ @endif" class="">{{__('Vietnam National University of Agriculture')}}</a>
        </div>

        {{-- Bên phải: Link phụ (ICETAI, Sổ tay...) --}}
        <div class="flex items-center font-medium">
            <livewire:client.global-search />
            <span class="separator text-[18px] lg:ms-3 ms-2 lf:me-2 me-1 text-white">|</span>
            <livewire:language-switcher layout="client"/>
            @auth
                <span class="separator text-[18px] mx-2 text-white">|</span>

                <div class="dropdown dropdown-hover dropdown-end">

                    <div tabindex="0" role="button"
                         class="hover:cursor-pointer hover:opacity-90 hover:text-white
                             after:content-[''] after:inline-block after:align-[0.255em]
                             after:border-t-5 after:border-r-5 after:border-r-transparent
                             after:border-b-0 after:border-l-5 after:border-l-transparent
                             hover:after:border-t-white hidden lg:block">
                        {{ auth()->user()->name }}
                    </div>

                    <div tabindex="0" role="button"
                         class="hover:cursor-pointer hover:opacity-90 hover:text-white
                             after:content-[''] after:inline-block after:align-[0.255em]
                             after:border-t-5 after:border-r-5 after:border-r-transparent
                             after:border-b-0 after:border-l-5 after:border-l-transparent
                             hover:after:border-t-white lg:hidden">
                        <x-icon name="s-user-circle" class="lg:hidden"></x-icon>
                    </div>

                    <ul tabindex="0"
                        class="cursor-pointer before:absolute before:-top-3 before:left-0 before:w-full before:h-3 dropdown-content mt-1.5 w-64 bg-base-100 shadow-lg border border-gray-300 rounded-b-md  p-1 text-gray-700">

                        <li>
                            <a class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100" href="{{route('admin.dashboard')}}">
                                <x-icon name="o-wrench" class="w-5 h-5"/>
                                {{__('Admin Dashboard')}}
                            </a>
                        </li>
                        @if(auth() && auth()->user()->hasRole('giang_vien'))
                        <li>
                            @php
                                $myLecturer = auth()->user()->lecturer;
                                $profileUrl = $myLecturer
                                    ? route('client.lecturers.profile', ['slug' => $myLecturer->staff_code])
                                    : '#';
                            @endphp
                            <a
                                href="{{ $profileUrl }}"
                                class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100"
                            >
                                <x-icon name="o-document" class="w-5 h-5"/>
                                {{__('Personal website')}}
                            </a>
                        </li>
                        @endif

                        <li>
                            <a class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100">
                                <x-icon name="o-user" class="w-5 h-5"/>
                                {{__('Account')}}
                            </a>
                        </li>

                        <li class="border-t mt-1 pt-1">
                            <a class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100 text-red-600" href="{{route('handleLogout')}}">
                                <x-icon name="o-arrow-right-on-rectangle" class="w-5 h-5"/>
                                {{__('Logout')}}
                            </a>
                        </li>

                    </ul>
                </div>
            @endauth
        </div>
    </div>
    {{-- start end nav bar--}}
    {{-- start bottom nav bar--}}
    @php
        $trainingMajors = \App\Models\Major::query()
            ->whereHas('trainingPrograms', function ($query) {
                $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
            ->get(['id', 'name', 'slug']);
    @endphp

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
                        <x-button
                            class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"
                            label="{{__('Lecturers - Staff')}}"
                            link="{{route('client.lecturers.index')}}"
                        ></x-button>
                    </li>
{{--                    <li class="w-full">--}}
{{--                        <x-button--}}
{{--                            class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"--}}
{{--                            label="Khoa Công nghệ"--}}
{{--                        ></x-button>--}}
{{--                    </li>--}}
{{--                    <li class="w-full">--}}
{{--                        <x-button--}}
{{--                            class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"--}}
{{--                            label="Khoa Công nghệ thông tin và Truyền thông"--}}
{{--                        ></x-button>--}}
{{--                    </li>--}}
                </ul>
            </div>

            <div class="dropdown dropdown-hover h-full group">
                <x-button
                    link="{{ route('client.training-programs.major','cong-nghe-phan-mem') }}"
                    tabindex="0"
                    class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full group-hover:bg-fita2 group-hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"
                    responsive
                >
                    {{__('Programs')}}
                </x-button>

                <ul tabindex="0" class="text-black dropdown-content z-50 px-0 menu shadow-lg bg-base-100 rounded-b-box border border-gray-300 border-t-transparent w-max min-w-full max-h-80 overflow-auto">
{{--                    <li class="w-full">--}}
{{--                        <x-button--}}
{{--                            class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"--}}
{{--                            label="Chương trình đào tạo"--}}
{{--                            link="{{ route('client.training-programs.index') }}"--}}
{{--                        />--}}
{{--                    </li>--}}
                    @forelse($trainingMajors as $major)
                        @php
                            $majorLabel = $major->getTranslation('name', app()->getLocale(), false)
                                ?: $major->getTranslation('name', 'vi', false)
                                ?: $major->getTranslation('name', 'en', false)
                                ?: $major->slug;
                        @endphp
                        <li class="w-full">
                            <x-button
                                class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"
                                label="{{ $majorLabel }}"
                                link="{{ route('client.training-programs.major', $major) }}"
                            />
                        </li>
                    @empty
{{--                        <li class="px-5 py-3 text-sm text-gray-500">Chưa có CTĐT đã công bố</li>--}}
                    @endforelse
                </ul>
            </div>

            <x-button
                link="{{route('client.posts.index',['danh-muc' => 'tin-tuc'])}}"
                class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full hover:bg-fita2 hover:text-white uppercase font-barlow"
                responsive
            >
                {{__('News')}}
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

    <x-slot:sidebar drawer="main-drawer" collapsible class="client-menu bg-base-100 lg:bg-inherit lg:hidden pt-26 h-full! text-[15px] font-medium">
        {{-- MENU --}}
        @php
            $currentMajorRoute = request()->route('major');
            $currentMajorKey = is_object($currentMajorRoute)
                ? (string) data_get($currentMajorRoute, 'slug', data_get($currentMajorRoute, 'id'))
                : (string) $currentMajorRoute;
        @endphp
        <x-menu>
            <x-menu-item title="{{__('Home page')}}" link="{{route('client.home')}}" class="rounded-none hover:bg-fita hover:text-white" :active="request()->routeIs('client.home')"/>
            <x-menu-sub title="{{__('Introduction')}}" class="rounded-none hover:bg-fita! hover:text-white!" >
                <x-menu-item title="{{__('Faculty of Information Technology')}}" class="rounded-none hover:bg-fita hover:text-white" link="{{route('client.information')}}" :active="request()->routeIs('client.information')"/>
                <x-menu-item title="{{__('Lecturers - Staff')}}" class="rounded-none hover:bg-fita hover:text-white" link="{{route('client.information')}}" :active="request()->routeIs('client.information')"/>
            </x-menu-sub>
            <x-menu-item title="{{__('Posts')}}" link="{{route('client.posts.index')}}" class="rounded-none hover:bg-fita hover:text-white" :active="request()->routeIs('client.posts.index')"/>
            <x-menu-sub title="{{__('Training Programs')}}" class="rounded-none hover:bg-fita! hover:text-white!" :active="request()->routeIs('client.training-programs.*')">
{{--                <x-menu-item title="Tất cả chương trình đào tạo" class="rounded-none hover:bg-fita hover:text-white" link="{{route('client.training-programs.index')}}" :active="request()->routeIs('client.training-programs.index')"/>--}}
                @foreach($trainingMajors as $major)
                    @php
                        $majorLabel = $major->getTranslation('name', app()->getLocale(), false)
                            ?: $major->getTranslation('name', 'vi', false)
                            ?: $major->getTranslation('name', 'en', false)
                            ?: $major->slug;
                    @endphp
                    <x-menu-item
                        title="{{ $majorLabel }}"
                        class="rounded-none hover:bg-fita hover:text-white"
                        link="{{ route('client.training-programs.major', $major) }}"
                        :active="request()->routeIs('client.training-programs.major') && $currentMajorKey === (string) $major->getRouteKey()"
                    />
                @endforeach
            </x-menu-sub>
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
                    <h2 class="text-center text-[40px]/[50px] font-semibold">@if(isset($titleBreadcrumb)){{$titleBreadcrumb}}@endif</h2>
                    @if(isset($breadcrumb))
                        <div class="flex items-center gap-1 text-gray-500 justify-center w-full">
                            <a href="{{route('client.home')}}" wire:navigate class="whitespace-nowrap hover:text-fita font-semibold text-slate-700">{{__('Home page')}}</a>
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

        {{--  start footer --}}
        <livewire:footer/>
        <livewire:footer-copyright/>
        {{--  end footer --}}
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
