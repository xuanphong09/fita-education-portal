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
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.1/Sortable.min.js"></script>
    {{-- Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen antialiased bg-gray-50 text-black text-[15px]!">

{{-- start nav bar--}}
<x-nav sticky full-width class="h-19 bg-mauchudao text-white flex-none content-center [&>div]:py-0! [&>div]:h-full!">
{{--  start navbar right  --}}
    <x-slot:brand class="">
        <label for="main-drawer" class="lg:hidden mr-3">
            <x-icon name="o-bars-3" class="cursor-pointer" />
        </label>

       <livewire:logo/>
    </x-slot:brand>
{{--  end navbar right  --}}

{{--  start navbar left  --}}
    <x-slot:actions>
        {{--        <x-button label="Messages" icon="o-envelope" link="###" class="btn-ghost btn-sm" responsive />--}}
        {{--        <x-button label="Notifications" icon="o-bell" link="###" class="btn-ghost btn-sm" responsive />--}}
{{--        <livewire:language-switcher />--}}
        <x-dropdown position="bottom-end" right no-x-anchor class="btn-ghost rounded-btn px-0 py-0 hover:bg-white/5">

            <x-slot:trigger>
                <div class="flex items-center gap-3 px-3 py-2 me-10 cursor-pointer">
                    {{-- Avatar --}}
                    <x-avatar :image="auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->name).'&background=random'" class="w-8! h-8! border border-white/10" />

                    {{-- Tên User (Chỉ hiện trên PC) --}}
                    <div class="hidden md:flex flex-col items-start text-left leading-tight">
                        <span class="font-semibold text-white truncate max-w-25">
                            {{ auth()->user()->name ?? 'Guest' }}
                        </span>
                        <span class="text-[13px] text-gray-400">{{ auth()->user()->email ?? 'Guest' }}</span>
                    </div>
                </div>
            </x-slot:trigger>

            <div class="font-normal text-white">
                {{-- Items --}}
                <x-menu-item title="My profile" icon="o-user" link="/profile" />
                <x-menu-item title="Messages" icon="o-envelope" link="/messages">
                    <x-slot:suffix>
                        <span class="badge badge-warning badge-sm text-white text-xs">New</span>
                    </x-slot:suffix>
                </x-menu-item>
                <x-menu-item title="Settings" icon="o-cog-6-tooth" link="/settings" />

                <x-menu-separator />

{{--                <x-menu-item title="Logout" icon="o-power" action="{{ route('logout') }}" no-wire-navigate class="text-red-500 hover:bg-red-50 hover:text-red-600 text-sm" />--}}
            </div>
        </x-dropdown>
    </x-slot:actions>
{{--  end navbar left  --}}


</x-nav>
{{-- end nav bar--}}

{{-- start main layout --}}
<x-main with-nav full-width>
    {{-- start sidebar --}}
    <x-slot:sidebar collapse-text="Thu nhỏ"  drawer="main-drawer" collapsible class="admin-menu bg-mauchudao text-slate-50">
        <x-menu>
            {{-- start logo sidebar mobile --}}
            <div class="p-4 flex justify-between items-center lg:hidden">
                <div class="flex items-center gap-3">
                    <img src="{{asset('assets/images/FITA.png')}}" class="size-12 rounded-[50%] opacity-80" alt="">
                </div>

                <label for="main-drawer" class="lg:hidden text-gray-400 hover:text-white cursor-pointer transition">
                    <x-icon name="o-x-mark" class="w-6 h-6" />
                </label>
            </div>
            {{-- end logo sidebar mobile --}}

            <x-menu-item title="Trang chủ" icon="o-home" link="{{route('admin.dashboard')}}" :active="request()->routeIs('admin.dashboard')"/>
{{--            <x-menu-item title="Trang chủ" icon="o-home" link="#" route="home"/>--}}
{{--            <x-menu-item title="Danh sách bài viết" icon="o-document" link="#"/>--}}
{{--            <x-menu-item title="Danh sách bài viết" icon="o-document" link="#" :active="request()->routeIs('posts.*')"/>--}}
            <x-menu-sub title="{{__('Page configuration')}}" icon="o-document">
                <x-menu-item title="{{__('Introduction page')}}" link="{{route('admin.configuration.introduction')}}" :active="request()->routeIs('admin.configuration.introduction')" />
            </x-menu-sub>
            <x-menu-sub title="{{__('Interface configuration')}}" icon="o-cog-6-tooth">
                <x-menu-item title="{{__('Footer')}}" link="{{route('admin.configuration.footer')}}" :active="request()->routeIs('admin.configuration.footer')"/>
            </x-menu-sub>
            <x-menu-sub title="{{__('User management')}}" icon="o-users">
                <x-menu-item title="{{__('User list')}}" link="{{route('admin.user.user-list')}}" :active="request()->routeIs('admin.user.*')"/>
                <x-menu-item title="{{__('Roles and Permissions')}}" link="{{route('admin.role.index')}}" :active="request()->routeIs('admin.role.*')"/>
            </x-menu-sub>
        </x-menu>
    </x-slot:sidebar>
    {{-- end sidebar --}}

    {{--  start content   --}}
    <x-slot:content class="p-0! bg-slate-100 flex flex-col">

        {{-- start breadcrumb --}}
        @if(isset($breadcrumb))
            <div class="bg-white px-6 py-3 shadow-sm border-b border-gray-200 mb-6 flex justify-between items-center sticky top-0 z-1">
                <div class="flex items-center gap-2 text-gray-500">
                    <a href="{{route('admin.dashboard')}}" wire:navigate><x-icon name="o-home" class="w-5 h-5" /></a>
                    <span class="mx-1">/</span>
                    {{$breadcrumb}}
                </div>
            </div>
        @endif
        {{-- end breadcrumb --}}

        {{-- start slot content --}}
        <div class="px-6 pb-10 flex-1">
            {{ $slot }}
        </div>
        {{-- end slot content --}}

        {{--  start footer --}}
        <livewire:footer-copyright layout="admin"/>
        {{--  end footer --}}
    </x-slot:content>
    {{--  end content   --}}

</x-main>
{{-- end main layout --}}

<x-toast class="z-50" />

{{-- start scripts--}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
{{-- end scripts--}}

{{-- start madal noty confirm--}}
<script>
    // Lắng nghe sự kiện từ Livewire bắn ra
    document.addEventListener('livewire:init', () => {
        Livewire.on('modal:confirm', (event) => {
            // Lấy data từ mảng event (Livewire 4 trả về array)
            const data = event[0];

            Swal.fire({
                title: data.title || 'Bạn chắc chắn chứ?',
                icon: data.icon || 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: data.confirmButtonText || 'Có',
                cancelButtonText: data.cancelButtonText || 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch(data.method, { id: data.id });
                }
            });
        });
    });

        document.addEventListener('open-new-tab', event => {
            window.open(event.detail.url, '_blank');
    });
</script>
<div x-data="{ show: false }" @scroll.window="show = window.pageYOffset > 300">
    <x-button
        icon="s-arrow-up"
        class="btn-circle bg-fita font-extrabold text-white fixed bottom-8 right-8 z-50 shadow-lg hover:bg-fita2"
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
{{-- end madal noty confirm--}}
@livewireScripts
</body>
</html>
