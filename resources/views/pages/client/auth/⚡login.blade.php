<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;

new
#[Layout('layouts.auth')]
class extends Component {
    use Toast;

    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    protected array $rules = [
        'email' => 'required|email',
        'password' => 'required',
    ];

    protected array $messages = [
        'email.required' => 'Email không được để trống',
        'email.email' => 'Email không hợp lệ',
        'password.required' => 'Mật khẩu không được để trống',
    ];

    private function getTranslatedMessages(): array
    {
        $locale = app()->getLocale();

        if ($locale === 'vi') {
            return [
                'email.required' => 'Email không được để trống',
                'email.email' => 'Email không hợp lệ',
                'password.required' => 'Mật khẩu không được để trống',
            ];
        }

        return [
            'email.required' => 'Email is required',
            'email.email' => 'Email is not valid',
            'password.required' => 'Password is required',
        ];
    }

    public function login()
    {
        $data = $this->validate($this->rules, $this->getTranslatedMessages());

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_active' => true], $this->remember)) {
            $locale = app()->getLocale();

            $user = User::where('email', $data['email'])->first();

            if ($user && Hash::check($data['password'], $user->password) && !$user->is_active) {

                $errorMsg = __('auth.inactive');
                $this->addError('fail', $errorMsg);
                $this->error($errorMsg);

                return;

            } else {
                $errorMsg = $locale === 'vi'
                    ? 'Email hoặc mật khẩu không đúng'
                    : 'Email or password is incorrect';

                $this->addError('fail', $errorMsg);
                $this->addError('email', ' ');
                $this->addError('password', ' ');
                $this->error($errorMsg);
                $this->reset('password'); // Xóa trắng ô password

                return;
            }
        }

        // 4. Regenerate session ngay sau khi đăng nhập thành công
        request()->session()->regenerate();

        // 5. Dùng full redirect sau login để tránh xung đột request khi navigate SPA
        return redirect()->route('client.home');
    }
    // Form login chỉ validate lúc submit để tránh race-condition request nền.
};
?>

<div class="min-h-screen flex items-center justify-center px-4">

    <x-slot:title>
        {{__('Login')}}
    </x-slot:title>

    <x-card class="w-full max-w-md shadow-xl p-8">

        {{-- logo --}}
        <div class="text-center mb-6">

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

        </div>
        <div class="text-center">
            @error('fail')
            <span class="text-error text-sm">{{ $message }}</span>
            @enderror
        </div>

        <form wire:submit.prevent="login" class="space-y-4 form-login">
            <x-input
                label="Email"
                wire:model.defer="email"
                placeholder="{{__('Enter your email')}}"
                icon="o-user"
            />

            <x-password
                label="{{__('Password')}}"
                wire:model.defer="password"
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
                label="{{__('Login')}}"
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
            label="{{__('Login with ST SSO')}}"
            class="w-full bg-white text-blue-500 border border-blue-500"
            link="/sso/login"
        />

    </x-card>

</div>
