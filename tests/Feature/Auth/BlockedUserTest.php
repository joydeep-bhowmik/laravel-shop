<?php

use App\Models\User;

test('blocked users cannot sign in', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_blocked' => true])->save();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a session belonging to a user who has been blocked is logged out', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_blocked' => true])->save();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
