<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Mary\Traits\Toast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new
#[Layout('layouts.auth')]
class extends Component {
    use Toast;

    public string $token = '';
    #[Locked]
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function savePassword(): mixed
    {
        $data = $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu chưa khớp.',
        ]);

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $this->password_confirmation,
                'token' => $data['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->success('Thiết lập mật khẩu thành công. Vui lòng đăng nhập lại.', redirectTo: route('login'));
            return null;
        }

        $this->addError('email', __($status));
        $this->error(__($status));
        return null;
    }
};
?>

<div class="min-h-screen flex items-center justify-center px-4">
    <x-slot:title>
        {{ __('Thiết lập mật khẩu') }}
    </x-slot:title>

    <x-card class="w-full max-w-md shadow-xl p-8 space-y-4">
        <h1 class="text-xl font-semibold text-center">{{ __('Thiết lập mật khẩu') }}</h1>

        @if (session('status'))
            <div class="rounded border border-green-200 bg-green-50 text-green-700 px-3 py-2 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <form wire:submit.prevent="savePassword" class="space-y-2">
            <input type="hidden" wire:model="token">

            <div class="mt-4">
                @error('email')
                <p class="mt-1 text-sm text-error text-center">{{ $message }}</p>
                @enderror
                <label class="text-sm font-medium">Email: <span class="text-gray-700">{{ $email }}</span></label>

            </div>

            <x-password
                label="{{ __('Mật khẩu mới') }}"
                wire:model.defer="password"
                password-icon="o-lock-closed"
                password-visible-icon="o-lock-open"
                placeholder="••••••••"
                required
            />

            <x-password
                label="{{ __('Xác nhận mật khẩu') }}"
                wire:model.defer="password_confirmation"
                password-icon="o-lock-closed"
                password-visible-icon="o-lock-open"
                placeholder="••••••••"
                required
            />

            <x-button
                label="{{ __('Lưu mật khẩu') }}"
                class="w-full bg-fita text-white"
                type="submit"
                spinner="savePassword"
            />
        </form>
    </x-card>
</div>
