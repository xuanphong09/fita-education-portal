<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <title>{{ isset($title) ? $title.' | ' . __('Faculty of Information Technology') : __('Faculty of Information Technology') }}</title>
    <link rel="icon" type="image/png" href="{{asset('assets/images/LogoKhoaCNTT.png')}}" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/login.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<style>
    .login-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .login-wrapper .card-body {
        padding: 20px;
    }

    .login-wrapper .card {
        margin-bottom: 0;
    }

    .login-wrapper .login-image-wrapper {
        padding: 20px 20px 0 20px;
        text-align: center;
    }
    .login-wrapper .login-image {
        width: 400px;
    }
    .login-wrapper .login-image-wrapper .line {
        margin: 10px auto 20px auto;
        background: #e5e5e5;
        width: 50px;
        height: 1px;
    }
    .login-wrapper .login-form {
        margin: 0 auto;
    }

    @media screen and (max-width: 678px) {
        .login-wrapper {
            max-width: 420px;
        }

        .login-wrapper .login-image {
            width: 250px;
        }
    }

</style>
<body class="min-h-screen antialiased bg-gray-50 text-black text-[15px]">

{{-- start main layout --}}
<x-main with-nav full-width>
    {{--  start content   --}}
    <x-slot:content class="p-0! bg-slate-100 flex flex-col">

        {{-- start slot content --}}
        <div class="flex-1">
            {{ $slot }}
        </div>
        {{-- end slot content --}}

        {{--  start footer --}}
{{--        <livewire:footer-copyright/>--}}
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
