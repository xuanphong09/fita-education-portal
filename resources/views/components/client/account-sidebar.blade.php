<div class="space-y-2">
    <a
        href="{{ route('client.account') }}"
        wire:navigate
        wire:current.exact="bg-fita text-white hover:bg-fita!"
        class="flex items-center gap-2 rounded-lg px-3 py-2 transition hover:bg-gray-100 text-gray-700"
    >
        <x-icon name="o-user-circle" class="w-5 h-5"/>
        <span>{{ __('Profile Information') }}</span>
    </a>

    <a
        href="{{ route('client.account.password') }}"
        wire:navigate
        wire:current.exact="bg-fita text-white hover:bg-fita!"
        class="flex items-center gap-2 rounded-lg px-3 py-2 transition hover:bg-gray-100 text-gray-700"
    >
        <x-icon name="o-key" class="w-5 h-5"/>
        <span>{{ __('Change Password') }}</span>
    </a>
</div>


