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
    <div class="bg-fita text-white text-sm py-2 lg:px-4 px-2 flex items-center h-8 justify-between relative">
        @php
            $topHeaderPage = \App\Models\Page::query()->where('slug', 'dau-trang')->first();
            $topHeaderRaw = [];

            if ($topHeaderPage && $topHeaderPage->content_data) {
                $topHeaderRaw = $topHeaderPage->getTranslation('content_data', app()->getLocale(), false)
                    ?: $topHeaderPage->getTranslation('content_data', 'vi', false)
                    ?: $topHeaderPage->getTranslation('content_data', 'en', false)
                    ?: [];
            }

            $topHeaderMenuItems = collect(is_array($topHeaderRaw) ? ($topHeaderRaw['top_menu_items'] ?? []) : [])
                ->map(function ($item) {
                    $children = collect($item['children'] ?? [])
                        ->map(fn($child) => [
                            'name' => trim((string) ($child['name'] ?? '')),
                            'url' => trim((string) ($child['url'] ?? '')),
                        ])
                        ->filter(fn($child) => !empty($child['name']) && !empty($child['url']))
                        ->values()
                        ->all();

                    return [
                        'name' => trim((string) ($item['name'] ?? '')),
                        'url' => trim((string) ($item['url'] ?? '')),
                        'children' => $children,
                    ];
                })
                ->filter(fn($item) => !empty($item['name']) && (!empty($item['url']) || !empty($item['children'])))
                ->values();
        @endphp

        {{-- Bên trái: Tên trường --}}
        <div class="
                    items-center gap-3 lg:ms-10 text-[14px] uppercase whitespace-nowrap
                    @if(app()->getLocale() == 'en') hidden lg:flex @else flex @endif
                ">
            <a href="@if(app()->getLocale() == 'vi') https://vnua.edu.vn @else https://eng.vnua.edu.vn/ @endif">
                {{ __('Vietnam National University of Agriculture') }}
            </a>
        </div>

        {{-- Bên phải: Link phụ (ICETAI, Sổ tay...) --}}
        <div class="flex items-center font-medium @if(app()->getLocale() == 'en') flex-1 justify-end @endif">
{{--            <div class="hidden md:block">--}}
{{--                <div class="dropdown dropdown-hover dropdown-end">--}}

{{--                    <div tabindex="0" role="button"--}}
{{--                         class="hover:cursor-pointer hover:opacity-90 hover:text-white hover:font-semibold font-normal text-slate-200--}}
{{--                                 after:content-[''] after:inline-block after:align-[0.255em]--}}
{{--                                 after:border-t-5 after:border-r-5 after:border-r-transparent--}}
{{--                                 after:border-b-0 after:border-l-5 after:border-l-transparent--}}
{{--                                 hover:after:border-t-white">--}}
{{--                        {{ __('Tools') }}--}}
{{--                    </div>--}}

{{--                    <ul tabindex="0"--}}
{{--                        class="cursor-pointer before:absolute before:-top-3 before:left-0 before:w-full before:h-3 dropdown-content mt-1.5 w-64 bg-base-100 shadow-lg border border-gray-300 rounded-b-md text-gray-700">--}}
{{--                        <li>--}}
{{--                            <a class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100" target="_blank" href="https://st-dse.vnua.edu.vn:6896/">--}}
{{--                                {{__('ST-Chatbot supports students')}}--}}
{{--                            </a>--}}
{{--                        </li>--}}
{{--                    </ul>--}}
{{--                </div>--}}
{{--                <span class="separator text-[18px] lg:ms-3 ms-2 lf:me-2 me-2 text-white">|</span>--}}
{{--                <a href="{{route('client.contact')}}" class="font-normal text-slate-200 hover:text-white hover:font-semibold">{{__('Contact')}}</a>--}}
{{--                <span class="separator text-[18px] lg:ms-3 ms-2 lf:me-2 me-2 text-white">|</span>--}}
{{--            </div>--}}
            @foreach($topHeaderMenuItems as $topMenuItem)

                @if(!empty($topMenuItem['children']))
                    <div class="dropdown dropdown-hover dropdown-end hidden md:block">
                        <div tabindex="0" role="button"
                             class="hover:cursor-pointer hover:opacity-90 hover:text-white hover:font-semibold font-normal text-slate-200
                                 after:content-[''] after:inline-block after:align-[0.255em]
                                 after:border-t-5 after:border-r-5 after:border-r-transparent
                                 after:border-b-0 after:border-l-5 after:border-l-transparent
                                 hover:after:border-t-white">
                            {{ $topMenuItem['name'] }}
                        </div>

                        <ul tabindex="0"
                            class="client-top-menu-level-2 cursor-pointer before:absolute before:-top-3 before:left-0 before:w-full before:h-3 dropdown-content mt-1.5 w-64 bg-base-100 shadow-lg border border-gray-300 rounded-b-md text-gray-700">
                            @foreach($topMenuItem['children'] as $topChild)
                                <li>
                                    <a href="{{ $topChild['url'] }}" class="block px-4 py-2 hover:bg-gray-100">
                                        {{ $topChild['name'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <a href="{{ $topMenuItem['url'] }}" class="hidden md:block font-normal text-slate-200 hover:text-white hover:font-semibold">
                        {{ $topMenuItem['name'] }}
                    </a>
                @endif
                <span class="separator text-[18px] lg:ms-3 ms-2 lf:me-2 me-2 text-white hidden md:inline">|</span>
            @endforeach
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
                        class="cursor-pointer before:absolute before:-top-3 before:left-0 before:w-full before:h-3 dropdown-content mt-1.5 w-46 bg-base-100 shadow-lg border border-gray-300 rounded-b-md text-gray-700">
                        @unlessrole('sinh_vien')
                            <li>
                                <a class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100" href="{{route('admin.dashboard')}}">
                                    <x-icon name="o-wrench" class="w-5 h-5"/>
                                    {{__('Admin Dashboard')}}
                                </a>
                            </li>
                        @endunlessrole
                        @if(auth() && auth()->user()->hasRole('giang_vien'))
                        <li>
                            @php
                                $myLecturer = auth()->user()->lecturer;
                                $profileUrl = $myLecturer
                                    ? route('client.lecturers.profile', ['slug' => $myLecturer->slug])
                                    : '#';
                            @endphp
                            <a
                                href="{{ $profileUrl }}"
                                class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100"
                            >
                                <x-icon name="o-document-text" class="w-5 h-5"/>
                                {{__('Personal website')}}
                            </a>
                        </li>
                        @endif

                        <li>
                            <a class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100" href="{{ route('client.account') }}">
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
        $headerPage = \App\Models\Page::query()->where('slug', 'dau-trang')->first();
        $rawHeaderData = [];

        if ($headerPage && $headerPage->content_data) {
            $rawHeaderData = $headerPage->getTranslation('content_data', app()->getLocale(), false)
                ?: $headerPage->getTranslation('content_data', 'vi', false)
                ?: $headerPage->getTranslation('content_data', 'en', false)
                ?: [];
        }

        $headerMenuItems = collect(is_array($rawHeaderData) ? ($rawHeaderData['menu_items'] ?? []) : [])
            ->map(function ($item) {
                $children = collect($item['children'] ?? [])
                    ->map(function ($child) {
                        $grandChildren = collect($child['children'] ?? [])
                            ->filter(fn($grand) => !empty(trim($grand['name'] ?? '')) && !empty(trim($grand['url'] ?? '')))
                            ->map(fn($grand) => [
                                'id' => $grand['id'] ?? null,
                                'name' => trim((string) ($grand['name'] ?? '')),
                                'url' => trim((string) ($grand['url'] ?? '')),
                            ])
                            ->values()
                            ->all();

                        return [
                            'id' => $child['id'] ?? null,
                            'name' => trim((string) ($child['name'] ?? '')),
                            'url' => trim((string) ($child['url'] ?? '')),
                            'children' => $grandChildren,
                        ];
                    })
                    ->filter(fn($child) => !empty($child['name']) && (!empty($child['url']) || !empty($child['children'])))
                    ->values()
                    ->all();

                return [
                    'id' => $item['id'] ?? null,
                    'name' => trim((string) ($item['name'] ?? '')),
                    'url' => trim((string) ($item['url'] ?? '')),
                    'children' => $children,
                ];
            })
            ->filter(fn($item) => !empty($item['name']) && (!empty($item['url']) || !empty($item['children'])))
            ->values();

        $useDynamicHeader = $headerMenuItems->isNotEmpty();

        $isAbsoluteUrl = fn (?string $url): bool => filled($url) && preg_match('/^(https?:)?\/\//i', trim($url)) === 1;
        $isAbsoluteExternalUrl = function (?string $url) use ($isAbsoluteUrl): bool {
            if (!$isAbsoluteUrl($url)) {
                return false;
            }

            $normalizedUrl = trim((string) $url);

            // parse_url() needs a scheme for protocol-relative links like //example.com
            if (str_starts_with($normalizedUrl, '//')) {
                $normalizedUrl = request()->getScheme() . ':' . $normalizedUrl;
            }

            $targetHost = parse_url($normalizedUrl, PHP_URL_HOST);

            if (!filled($targetHost)) {
                return true;
            }

            return strcasecmp((string) $targetHost, request()->getHost()) !== 0;
        };

        $trainingMajors = collect();

        if (!$useDynamicHeader) {
            $trainingMajors = \App\Models\Major::query()
                ->whereHas('trainingPrograms', function ($query) {
                    $query->where('status', 'published')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })
                ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), slug) asc")
                ->get(['id', 'name', 'slug']);
        }
    @endphp

    <x-nav full-width class="bg-white text-white content-center shadow [&>div]:py-0! [&>div]:h-full! hidden lg:block flex-none"
           x-data="{ isScrolled: false }"
           @scroll.window="isScrolled = (window.pageYOffset > 50)"
           x-bind:class="isScrolled ? 'h-15' : 'md:h-20 h-15'"
    >

        {{--  start navbar right  --}}
        <x-slot:brand>
            <livewire:logo layout="client"/>
        </x-slot:brand>
        {{--  end navbar right  --}}

        {{--  start navbar left  --}}
        <x-slot:actions class="gap-0!">
            @if($useDynamicHeader)
                @foreach($headerMenuItems as $item)
                    @if(!empty($item['children']))
                        <div class="dropdown dropdown-hover h-full group auto-flip-bottom">
                            <x-button
                                tabindex="0"
                                :link="!str_starts_with($item['url'], '#') ? $item['url'] : ''"
                                :no-wire-navigate="$isAbsoluteExternalUrl($item['url'])"
                                class="btn-ghost text-black text-[18px]/[60px] border-transparent font-medium rounded-none h-full group-hover:bg-fita2 group-hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"
                                responsive
                                x-data="{ isScrolled: false }"
                                @scroll.window="isScrolled = (window.pageYOffset > 50)"
                                x-bind:class="isScrolled ? 'text-[18px]/[60px]' : 'text-[18px]/[78px]'"
                            >
                                {{ $item['name'] }}
                            </x-button>

                            <ul tabindex="0" class="client-menu-level-2 py-1 text-black dropdown-content z-50 px-0 menu shadow-lg bg-base-100 rounded-b-box border border-gray-300 border-t-transparent w-max min-w-full">
                                @foreach($item['children'] as $child)
                                    <li class="w-full">
                                        @if(!empty($child['children']))
                                            <div class="dropdown dropdown-hover dropdown-right w-full p-0! auto-flip">
                                                <x-button
                                                    :link="!str_starts_with($child['url'], '#') ? $child['url'] : ''"
                                                    :no-wire-navigate="$isAbsoluteExternalUrl($child['url'])"
                                                    class="btn-ghost text-black text-[15px] w-full py-4 px-5 border-transparent font-medium rounded-none flex justify-between hover:bg-fita hover:text-white focus:bg-fita focus:text-white active:bg-fita active:text-white whitespace-nowrap after:content-[''] after:ml-2 after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"
                                                    label="{{ $child['name'] }}"
                                                    x-on:click.prevent="$event.currentTarget.blur()"
                                                />
                                                <ul class="client-menu-level-3 px-0! m-0! py-1 menu dropdown-content z-50 rounded-b-box bg-base-100 shadow-lg border border-gray-300 w-max min-w-full">
                                                    @foreach($child['children'] as $grand)
                                                        <li>
                                                            <x-button
                                                                class="btn-ghost text-black text-[14px] py-3 px-4 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white focus:bg-fita focus:text-white active:bg-fita active:text-white whitespace-nowrap"
                                                                label="{{ $grand['name'] }}"
                                                                link="{{ $grand['url'] }}"
                                                                :no-wire-navigate="$isAbsoluteExternalUrl($grand['url'])"
                                                            />
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @else
                                            <x-button
                                                class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"
                                                label="{{ $child['name'] }}"
                                                link="{{ $child['url'] }}"
                                                :no-wire-navigate="$isAbsoluteExternalUrl($child['url'])"
                                            />
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <x-button
                            link="{{ $item['url'] }}"
                            :no-wire-navigate="$isAbsoluteExternalUrl($item['url'])"
                            class="btn-ghost text-black text-[18px]/[60px] border-transparent font-medium rounded-none h-full hover:bg-fita2 hover:text-white uppercase font-barlow"
                            responsive
                            x-data="{ isScrolled: false }"
                            @scroll.window="isScrolled = (window.pageYOffset > 50)"
                            x-bind:class="isScrolled ? 'text-[18px]/[60px]' : 'text-[18px]/[79px]'"
                        >
                            {{ $item['name'] }}
                        </x-button>
                    @endif
                @endforeach
            @else
{{--                <div class="dropdown dropdown-hover h-full group">--}}
{{--                    <x-button--}}
{{--                        tabindex="0"--}}
{{--                        class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full group-hover:bg-fita2 group-hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"--}}
{{--                        responsive--}}
{{--                    >--}}
{{--                        {{__('Introduction')}}--}}
{{--                    </x-button>--}}

{{--                    <ul tabindex="0" class="text-black dropdown-content z-50 px-0  menu shadow-lg bg-base-100 rounded-b-box border border-gray-300 border-t-transparent w-max min-w-full">--}}
{{--                        <li class="w-full">--}}
{{--                            <x-button--}}
{{--                                class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"--}}
{{--                                label="{{__('Faculty of Information Technology')}}"--}}
{{--                                link="{{route('client.information')}}"--}}
{{--                            ></x-button>--}}
{{--                            <x-button--}}
{{--                                class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"--}}
{{--                                label="{{__('Lecturers - Staff')}}"--}}
{{--                                link="{{route('client.lecturers.index')}}"--}}
{{--                            ></x-button>--}}
{{--                            <x-button--}}
{{--                                link="{{route('client.contact')}}"--}}
{{--                                class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"--}}
{{--                                label="{{__('Contact')}}"--}}
{{--                            >--}}
{{--                            </x-button>--}}
{{--                        </li>--}}
{{--                    </ul>--}}
{{--                </div>--}}

{{--                <div class="dropdown dropdown-hover h-full group">--}}
{{--                    <x-button--}}
{{--                        tabindex="0"--}}
{{--                        class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full group-hover:bg-fita2 group-hover:text-white uppercase font-barlow after:content-[''] after:inline-block after:align-[0.255em] after:border-t-[0.3em] after:border-r-[0.3em] after:border-r-transparent after:border-b-0 after:border-l-[0.3em] after:border-l-transparent"--}}
{{--                        responsive--}}
{{--                    >--}}
{{--                        {{__('Programs')}}--}}
{{--                    </x-button>--}}

{{--                    <ul tabindex="0" class="text-black dropdown-content z-50 px-0 menu shadow-lg bg-base-100 rounded-b-box border border-gray-300 border-t-transparent w-max min-w-full max-h-80 overflow-auto">--}}
{{--                        @forelse($trainingMajors as $major)--}}
{{--                            @php--}}
{{--                                $majorLabel = $major->getTranslation('name', app()->getLocale(), false)--}}
{{--                                    ?: $major->getTranslation('name', 'vi', false)--}}
{{--                                    ?: $major->getTranslation('name', 'en', false)--}}
{{--                                    ?: $major->slug;--}}
{{--                            @endphp--}}
{{--                            <li class="w-full">--}}
{{--                                <x-button--}}
{{--                                    class="btn-ghost text-black text-[15px] py-4 px-5 border-transparent justify-start font-medium rounded-none hover:bg-fita hover:text-white whitespace-nowrap"--}}
{{--                                    label="{{ $majorLabel }}"--}}
{{--                                    link="{{ route('client.training-programs.major', $major) }}"--}}
{{--                                />--}}
{{--                            </li>--}}
{{--                        @empty--}}
{{--                        @endforelse--}}
{{--                    </ul>--}}
{{--                </div>--}}

{{--                <x-button--}}
{{--                    link="{{route('client.posts.index',['danh-muc' => 'tin-tuc'])}}"--}}
{{--                    class="btn-ghost text-black text-[18px]/[76px] border-transparent font-medium rounded-none h-full hover:bg-fita2 hover:text-white uppercase font-barlow"--}}
{{--                    responsive--}}
{{--                >--}}
{{--                    {{__('News')}}--}}
{{--                </x-button>--}}
            @endif
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
            $currentMajorKey = (string) request()->query('chuyen-nganh', '');
        @endphp
        <x-menu>
            <x-menu-item title="{{__('Home page')}}" link="{{route('client.home')}}" class="rounded-none hover:bg-fita hover:text-white" :active="request()->routeIs('client.home')"/>

            @if($useDynamicHeader)
                @foreach($headerMenuItems as $item)
                    @if(!empty($item['children']))
                        <x-menu-sub title="{{ $item['name'] }}" class="rounded-none hover:bg-fita! hover:text-white!">
                            @foreach($item['children'] as $child)
                                @if(!empty($child['children']))
                                    <x-menu-sub title="{{ $child['name'] }}" class="rounded-none hover:bg-fita! hover:text-white!">
                                        @foreach($child['children'] as $grand)
                                            <x-menu-item
                                                title="{{ $grand['name'] }}"
                                                class="rounded-none hover:bg-fita hover:text-white"
                                                link="{{ $grand['url'] }}"
                                                :no-wire-navigate="$isAbsoluteExternalUrl($grand['url'])"
                                            />
                                        @endforeach
                                    </x-menu-sub>
                                @else
                                    <x-menu-item
                                        title="{{ $child['name'] }}"
                                        class="rounded-none hover:bg-fita hover:text-white"
                                        link="{{ $child['url'] }}"
                                        :no-wire-navigate="$isAbsoluteExternalUrl($child['url'])"
                                    />
                                @endif
                            @endforeach
                        </x-menu-sub>
                    @else
                        <x-menu-item
                            title="{{ $item['name'] }}"
                            link="{{ $item['url'] }}"
                            :no-wire-navigate="$isAbsoluteExternalUrl($item['url'])"
                            class="rounded-none hover:bg-fita hover:text-white"
                        />
                    @endif
                @endforeach
            @else
                <x-menu-sub title="{{__('Introduction')}}" class="rounded-none hover:bg-fita! hover:text-white!" >
                    <x-menu-item title="{{__('Faculty of Information Technology')}}" class="rounded-none hover:bg-fita hover:text-white" link="{{route('client.information')}}" :active="request()->routeIs('client.information')"/>
                    <x-menu-item title="{{__('Lecturers - Staff')}}" class="rounded-none hover:bg-fita hover:text-white" link="{{route('client.lecturers.index')}}" :active="request()->routeIs('client.lecturers.index')"/>
                    <x-menu-item title="{{__('Contact')}}" link="{{route('client.contact')}}" class="rounded-none hover:bg-fita hover:text-white" :active="request()->routeIs('client.contact')"/>
                </x-menu-sub>
                <x-menu-item title="{{__('News')}}" link="{{route('client.posts.index', ['danh-muc'=>'tin-tuc'])}}" class="rounded-none hover:bg-fita hover:text-white" :active="request()->routeIs('client.posts.index')"/>
                <x-menu-sub title="{{__('Training Programs')}}" class="rounded-none hover:bg-fita! hover:text-white!" :active="request()->routeIs('client.training-programs.*')">
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
                            link="{{ route('client.training-programs.major', ['chuyen-nganh' => $major->slug, 'nganh' => $major->programMajor?->slug]) }}"
                            :active="request()->routeIs('client.training-programs.major') && $currentMajorKey === (string) $major->slug"
                        />
                    @endforeach
                </x-menu-sub>
            @endif
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
                    <h2 class="text-center text-[40px]/[50px] font-semibold uppercase">@if(isset($titleBreadcrumb)){{$titleBreadcrumb}}@endif</h2>
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
<script>
    document.addEventListener('livewire:navigated', () => {

        // 1. LẬT MENU NGANG (Dành cho cấp 3 trở lên)
        const flipHorizontal = document.querySelectorAll('.auto-flip');
        flipHorizontal.forEach(dropdown => {
            dropdown.addEventListener('mouseenter', function() {
                const rect = this.getBoundingClientRect();
                const menuWidth = 250; // Độ rộng dự trù
                const spaceOnRight = window.innerWidth - rect.right;

                if (spaceOnRight < menuWidth) {
                    this.classList.remove('dropdown-right');
                    this.classList.add('dropdown-left');
                } else {
                    this.classList.remove('dropdown-left');
                    this.classList.add('dropdown-right');
                }
            });
        });

        // 2. LẬT MENU DỌC (Dành cho cấp 2 rớt từ thanh Nav xuống)
        const flipVertical = document.querySelectorAll('.auto-flip-bottom');
        flipVertical.forEach(dropdown => {
            dropdown.addEventListener('mouseenter', function() {
                const rect = this.getBoundingClientRect();
                const menuWidth = 250; // Độ rộng dự trù của menu cấp 2

                // Đo từ mép trái của nút bấm đến hết màn hình bên phải
                const spaceOnRight = window.innerWidth - rect.left;

                // Nếu không đủ chỗ bung sang phải -> ép nó canh lề phải (dropdown-end)
                if (spaceOnRight < menuWidth) {
                    this.classList.add('dropdown-end');
                } else {
                    this.classList.remove('dropdown-end');
                }
            });
        });

    });
</script>
</body>
</html>
