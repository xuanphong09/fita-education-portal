<?php

use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public function mount(): void
    {
        if (! session()->has('sync_password_warning')) {
            return;
        }

        $this->toast(
            type: 'warning',
            title: __('You have not synchronized your scores from the training management system.'),
            description: session('sync_password_warning'),
            position: 'toast-top toast-end',
            icon: 'o-exclamation-triangle',
            css: 'alert-warning cursor-pointer',
            timeout: 8000,
//            redirectTo: route('client.account')
        );
    }

};
?>

<div>
    {{-- Order your soul. Reduce your wants. - Augustine --}}
</div>
