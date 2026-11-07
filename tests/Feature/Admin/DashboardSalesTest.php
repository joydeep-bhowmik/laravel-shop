<?php

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(AdminRole::SuperAdmin->value);
    $this->actingAs($admin);
});

test('the sales chart has twelve month bars for the chosen year', function () {
    $series = Livewire::test('admin.dashboard')
        ->set('year', 2025)
        ->instance()
        ->series();

    expect($series)->toHaveCount(12)
        ->and($series[0]['label'])->toBe('Jan')
        ->and($series[11]['label'])->toBe('Dec');
});

test('each month bar adds up paid orders placed in that month of the year', function () {
    Order::factory()->paid()->create(['placed_at' => Carbon::create(2025, 1, 10, 12), 'total_paise' => 50000]);
    Order::factory()->paid()->create(['placed_at' => Carbon::create(2025, 1, 28, 9), 'total_paise' => 20000]);
    Order::factory()->paid()->create(['placed_at' => Carbon::create(2025, 3, 5, 9), 'total_paise' => 75000]);
    Order::factory()->create(['status' => OrderStatus::Cancelled, 'placed_at' => Carbon::create(2025, 3, 6, 9), 'total_paise' => 99900]);
    Order::factory()->paid()->create(['placed_at' => Carbon::create(2024, 3, 5, 9), 'total_paise' => 11100]);

    $series = Livewire::test('admin.dashboard')
        ->set('year', 2025)
        ->instance()
        ->series();

    expect($series[0]['total'])->toBe(70000)
        ->and($series[1]['total'])->toBe(0)
        ->and($series[2]['total'])->toBe(75000);
});

test('the year list runs from the current year back to the first order', function () {
    Order::factory()->paid()->create(['placed_at' => Carbon::create(2023, 6, 1, 9)]);

    $years = Livewire::test('admin.dashboard')->instance()->years();

    expect($years)->toBe([(int) now()->year, (int) now()->year - 1, (int) now()->year - 2, 2023]);
});
