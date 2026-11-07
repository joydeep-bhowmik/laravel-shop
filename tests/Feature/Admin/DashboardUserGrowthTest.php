<?php

use App\Enums\AdminRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create(['created_at' => Carbon::create(2022, 1, 1)]);
    $admin->assignRole(AdminRole::SuperAdmin->value);
    $this->actingAs($admin);
});

test('user growth counts new customers per month and a running total', function () {
    User::factory()->create(['created_at' => Carbon::create(2024, 12, 20, 10)]);
    User::factory()->count(2)->create(['created_at' => Carbon::create(2025, 1, 2, 10)]);
    User::factory()->create(['created_at' => Carbon::create(2025, 3, 5, 10)]);

    $growth = Livewire::test('admin.dashboard')
        ->set('year', 2025)
        ->instance()
        ->userGrowth();

    expect($growth)->toHaveCount(12)
        ->and($growth[0]['new'])->toBe(2)
        ->and($growth[0]['total'])->toBe(3)
        ->and($growth[1]['new'])->toBe(0)
        ->and($growth[2]['new'])->toBe(1)
        ->and($growth[2]['total'])->toBe(4)
        ->and($growth[11]['total'])->toBe(4);
});

test('staff accounts are left out of the growth chart', function () {
    $staff = User::factory()->create(['created_at' => Carbon::create(2025, 2, 1, 10)]);
    $staff->assignRole(AdminRole::Support->value);

    $growth = Livewire::test('admin.dashboard')
        ->set('year', 2025)
        ->instance()
        ->userGrowth();

    expect(collect($growth)->sum('new'))->toBe(0);
});
