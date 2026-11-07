<?php

use App\Enums\AdminRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(AdminRole::SuperAdmin->value);
    $this->actingAs($superAdmin);
});

test('clicking a column sorts ascending, then flips direction on the second click', function () {
    User::factory()->create(['name' => 'Zara Zulu']);
    User::factory()->create(['name' => 'Asha Alpha']);

    Livewire::test('admin.users.index')
        ->call('sortBy', 'name')
        ->assertSeeInOrder(['Asha Alpha', 'Zara Zulu'])
        ->call('sortBy', 'name')
        ->assertSeeInOrder(['Zara Zulu', 'Asha Alpha']);
});

test('an unknown sort key is ignored rather than used in the query', function () {
    User::factory()->create(['name' => 'Asha Alpha']);

    Livewire::test('admin.users.index')
        ->set('sort', 'password')
        ->assertSee('Asha Alpha')
        ->assertOk();
});

test('the date range keeps only users created on the chosen days', function () {
    User::factory()->create(['name' => 'Old Member', 'created_at' => now()->subDays(40)]);
    User::factory()->create(['name' => 'New Member', 'created_at' => now()->subDay()]);

    Livewire::test('admin.users.index')
        ->set('dateFrom', now()->subDays(7)->toDateString())
        ->set('dateTo', now()->toDateString())
        ->assertSee('New Member')
        ->assertDontSee('Old Member');
});
