<?php

use App\Services\SettingsService;

test('dark mode is offered by default with a theme toggle in the header', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Toggle dark mode')
        ->assertSee('$flux.dark', false);
});

test('when dark mode is switched off the toggle and theme script are gone', function () {
    app(SettingsService::class)->set('appearance.dark_mode', '0');

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Toggle dark mode')
        ->assertDontSee('$flux.dark', false);
});
