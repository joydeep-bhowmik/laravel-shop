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

test('the pie chart counts orders per status with their share of the total', function () {
    Order::factory()->count(2)->create(['status' => OrderStatus::Pending, 'placed_at' => Carbon::create(2025, 4, 2, 9)]);
    Order::factory()->create(['status' => OrderStatus::Delivered, 'placed_at' => Carbon::create(2025, 4, 3, 9)]);

    $breakdown = Livewire::test('admin.dashboard')
        ->set('year', 2025)
        ->instance()
        ->statusBreakdown();

    $pending = collect($breakdown)->firstWhere('label', 'Pending');
    $delivered = collect($breakdown)->firstWhere('label', 'Delivered');

    expect($pending['count'])->toBe(2)
        ->and(round($pending['percent'], 1))->toBe(66.7)
        ->and($delivered['count'])->toBe(1)
        ->and(collect($breakdown)->sum('count'))->toBe(3);
});

test('a period with no orders gives an empty breakdown and a grey gradient', function () {
    $component = Livewire::test('admin.dashboard')->set('year', 2019);

    expect($component->instance()->statusBreakdown())->toBe([])
        ->and($component->instance()->statusGradient())->toBe('#e4e4e7 0% 100%');
});

test('the range filter counts orders between the two dates instead of the year', function () {
    Order::factory()->create(['status' => OrderStatus::Delivered, 'placed_at' => Carbon::create(2025, 4, 2, 9)]);
    Order::factory()->create(['status' => OrderStatus::Delivered, 'placed_at' => Carbon::create(2025, 5, 10, 9)]);

    $breakdown = Livewire::test('admin.dashboard')
        ->set('year', 2025)
        ->set('month', -1)
        ->set('from', '2025-04-01')
        ->set('to', '2025-04-30')
        ->instance()
        ->statusBreakdown();

    expect(collect($breakdown)->sum('count'))->toBe(1);
});

test('the month filter limits the pie chart to that month', function () {
    Order::factory()->create(['status' => OrderStatus::Delivered, 'placed_at' => Carbon::create(2025, 4, 3, 9)]);
    Order::factory()->create(['status' => OrderStatus::Delivered, 'placed_at' => Carbon::create(2025, 5, 3, 9)]);

    $breakdown = Livewire::test('admin.dashboard')
        ->set('year', 2025)
        ->set('month', 4)
        ->instance()
        ->statusBreakdown();

    expect(collect($breakdown)->sum('count'))->toBe(1);
});
