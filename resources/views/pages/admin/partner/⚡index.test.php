<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('admin.partner.index')
        ->assertStatus(200);
});
