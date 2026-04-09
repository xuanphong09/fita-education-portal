<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

new
#[Layout('layouts.client')]
class extends Component {
    use Toast;

    public bool $hasPassword = false;
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $this->hasPassword = !empty($user->password);
    }

    public function changePassword(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $rules = [
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        $messages = [
            'new_password.required' => __('New password is required.'),
            'new_password.min' => __('New password must be at least 8 characters.'),
            'new_password.confirmed' => __('Password confirmation does not match.'),
        ];

        if ($this->hasPassword) {
            $rules['current_password'] = ['required', 'current_password'];
            $messages['current_password.required'] = __('Current password is required.');
            $messages['current_password.current_password'] = __('Current password is incorrect.');
        }

        try {
            $this->validate($rules, $messages);
        } catch (ValidationException $e) {
            if ($e->validator->errors()->has('current_password')) {
                $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
                $this->resetValidation();
                $this->error(__('Current password is incorrect.'));
            }

            throw $e;
        }

        $user->forceFill([
            'password' => Hash::make($this->new_password),
            'remember_token' => Str::random(60),
        ])->save();

        $this->hasPassword = true;
        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        $this->success(__('Password changed successfully.'));
    }
};
?>

<div class="container mx-auto max-w-6xl py-8 px-4 space-y-6">
    <x-slot:title>{{ __('Change Password') }}</x-slot:title>
    <x-slot:breadcrumb>
        <span class="whitespace-nowrap font-semibold text-slate-700">{{ __('Change Password') }}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{ __('Change Password') }}
    </x-slot:titleBreadcrumb>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <x-card class="shadow-md p-4 lg:col-span-3 h-fit">
            <x-client.account-sidebar/>
        </x-card>

        <x-card class="shadow-md p-6 lg:col-span-9">
            <form wire:submit.prevent="changePassword" class="space-y-0">
                @if($hasPassword)
                    <x-password
                        label="{{ __('Current password') }}"
                        wire:model.defer="current_password"
                        password-icon="o-lock-closed"
                        password-visible-icon="o-lock-open"
                        placeholder="••••••••"
                    />
                @endif

                <x-password
                    label="{{ __('New password') }}"
                    wire:model.defer="new_password"
                    password-icon="o-lock-closed"
                    password-visible-icon="o-lock-open"
                    placeholder="••••••••"
                    required
                />

                <x-password
                    label="{{ __('Confirm new password') }}"
                    wire:model.defer="new_password_confirmation"
                    password-icon="o-lock-closed"
                    password-visible-icon="o-lock-open"
                    placeholder="••••••••"
                    required
                />

                <div class="flex justify-center">
                    <x-button
                        label="{{ __('Update password') }}"
                        class="bg-fita text-white mt-4"
                        type="submit"
                        spinner="changePassword"
                    />
                </div>
            </form>
        </x-card>
    </div>
</div>


