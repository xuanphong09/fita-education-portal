<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.client')]
class extends Component {
    //
};
?>

<div>
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Search') }}
    </x-slot:title>
    {{--  end - title  --}}
</div>
