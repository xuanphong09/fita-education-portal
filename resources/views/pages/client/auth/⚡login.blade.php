<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;
new
#[Layout('layouts.auth')]
class extends Component {
    use Toast;

    public $email = '';
    public $password = '';
    public $remember = false;

    #[Validate([
        'email' => 'required|email',
        'password' => 'required'
    ],
    as: [
        'email' => 'Email',
        'password' => 'Mật khẩu'
    ],
     message: [
         'email.required' => 'Email không được để trống',
         'email.email' => 'Email không hợp lệ',
         'password.required' => 'Mật khẩu không được để trống',
     ]
    )]

    public function login()
    {
        $data = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        if (!Auth::attempt($data, $this->remember)) {
            $this->addError('fail', 'Email hoặc mật khẩu không đúng');
            $this->addError('email',' ');
            $this->addError('password',' ');
            $this->error('Email hoặc mật khẩu không đúng');
            $this->reset('email', 'password');
            return;
        }

        session()->regenerate();

        $this->success('Đăng nhập thành công!',
            redirectTo: route('client.home')
        );
    }

    public function updated($property)
    {
        $this->validateOnly($property);
    }
};
?>

<div class="min-h-screen flex items-center justify-center px-4">

    <x-slot:title>
        Đăng nhập
    </x-slot:title>

    <x-card class="w-full max-w-md shadow-xl p-8">

        {{-- logo --}}
        <div class="text-center mb-6">

            <div class="flex justify-center gap-4 mb-4">
                <img src="{{asset('assets/images/Logo Học viện.png')}}" class="w-16 h-16 object-contain">
                <img src="{{asset('assets/images/FITA.png')}}" class="w-16 h-16 object-contain">
            </div>

            <h2 class="font-semibold text-xl">
                {{__('Vietnam National University of Agriculture')}}
            </h2>

            <p class="text-gray-900 font-medium text-lg">
                {{__('Faculty of Information Technology')}}
            </p>

        </div>
        <div class="text-center">
            @error('fail')
            <span class="text-error text-sm">{{ $message }}</span>
            @enderror
        </div>

        <form wire:submit="login" class="space-y-4 form-login">
            <x-input
                label="Email"
                wire:model.live.debounce.500ms="email"
                placeholder="Nhập email của bạn"
                icon="o-user"
            />

            <x-password
                label="{{__('Password')}}"
                wire:model.live.debounce.500ms="password"
                password-icon="o-lock-closed"
                password-visible-icon="o-lock-open"
                placeholder="••••••••"
            />

            <div class="flex items-center justify-between">

                <label class="flex items-center gap-2">
                    <x-checkbox wire:model="remember" class="checkbox-primary checkbox-sm"/>
                    <span class="text-gray-600 text-[15px]">
                        {{__('Remember password')}}
                    </span>
                </label>

                <a href="#" class="text-[15px] text-blue-500 hover:underline">
                    {{__('Forgot password?')}}
                </a>

            </div>


            <x-button
                label="Đăng nhập"
                class="w-full bg-fita text-white"
                type="submit"
                spinner="login"
            />

        </form>


        {{-- divider --}}
        <div class="flex items-center my-4">

            <div class="flex-1 border-t"></div>

            <span class="px-3 text-gray-500 text-sm">
                {{__('Or log in using')}}
            </span>

            <div class="flex-1 border-t"></div>

        </div>


        <x-button
            label="Hệ thống ST Single Sign-On"
            class="w-full bg-white text-blue-500 border border-blue-500"
            link="/sso/login"
        />

    </x-card>

</div>
