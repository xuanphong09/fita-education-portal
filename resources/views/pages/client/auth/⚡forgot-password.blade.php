<?php

use Illuminate\Support\Facades\Password;
use Mary\Traits\Toast;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.auth')]
class extends Component {
    use Toast;

    public string $email = '';

    public function sendResetLink(): mixed
    {
        $data = $this->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        if ($status === Password::RESET_THROTTLED) {
            $this->warning('Bạn vừa yêu cầu trước đó. Vui lòng thử lại sau ít phút.');
            return null;
        }

        // Phản hồi chung để tránh lộ email có tồn tại trong hệ thống hay không.
        $this->success('Nếu email tồn tại trong hệ thống, chúng tôi đã gửi liên kết đặt lại mật khẩu.');
        $this->reset('email');

        return null;
    }
};
?>

<div class="min-h-screen flex items-center justify-center px-4">
    <x-slot:title>
        {{ __('Quên mật khẩu') }}
    </x-slot:title>

    <x-card class="w-full max-w-md shadow-xl p-8 space-y-4">
        <div class="text-center mb-6 relative">

            <div class="flex justify-center gap-4 mb-4">
                <img src="{{asset('assets/images/Logo Học viện.png')}}" alt="Logo Học viện"
                     class="w-16 h-16 object-contain">
                <img src="{{asset('assets/images/FITA.png')}}" alt="FITA logo" class="w-16 h-16 object-contain">
            </div>

            <h2 class="font-semibold text-xl whitespace-nowrap">
                {{__('Vietnam National University of Agriculture')}}
            </h2>

            <p class="text-gray-900 font-medium text-lg">
                {{__('Faculty of Information Technology')}}
            </p>
            <div class="absolute -top-5 -left-4 p-2">
                <x-button class="btn-ghost text-fita btn-xs" link="{{route('login')}}" tooltip="Trang đăng nhập" icon="o-arrow-uturn-left"></x-button>
            </div>
        </div>
        <div class="">
{{--        <h1 class="text-xl font-semibold text-center">{{ __('Quên mật khẩu') }}</h1>--}}
        <p class="text-md text-gray-600 text-center">
            {{__('Enter your email address to receive a password reset link.')}}
        </p>

        <form wire:submit.prevent="sendResetLink" class="space-y-4">
            <x-input
                label="Email"
                wire:model.defer="email"
                type="email"
                icon="o-envelope"
                placeholder="name@example.com"
                required
            />

            <x-button
                label="{{ __('Submit a request') }}"
                class="w-full bg-fita text-white"
                type="submit"
                spinner="sendResetLink"
            />
        </form>
        </div>
        <div class="flex items-center my-4">

            <div class="flex-1 border-t"></div>

            <span class="px-3 text-gray-500 text-sm">
                {{__('Or log in using')}}
            </span>

            <div class="flex-1 border-t"></div>

        </div>


        <a
            class="w-full bg-white text-blue-500 border border-blue-500 btn hover:bg-blue-50"
            href="{{route('sso.redirect')}}"
        >
            {{__('Login with ST SSO')}}
        </a>
{{--        <div class="text-center text-sm">--}}
{{--            <div class="flex-1 border-t my-4"></div>--}}
{{--            <a href="{{ route('login') }}" class="w-full bg-white text-blue-500 border border-blue-500 btn hover:bg-blue-50">--}}
{{--                {{ __('Quay lại đăng nhập') }}--}}
{{--            </a>--}}
{{--        </div>--}}
    </x-card>
</div>

