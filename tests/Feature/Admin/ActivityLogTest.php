<?php

use App\Enums\AdminRole;
use App\Models\Product;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function recordActivityRow(string $description, string $createdAt): void
{
    DB::table('activity_log')->insert([
        'log_name' => 'default',
        'description' => $description,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

test('activity logging is off until an admin turns it on', function () {
    $product = Product::factory()->create();
    $product->update(['name' => 'Renamed product']);

    expect(app(SettingsService::class)->bool('activity.enabled'))->toBeFalse()
        ->and(Activity::query()->count())->toBe(0);
});

test('turning activity logging on records later changes', function () {
    app(SettingsService::class)->set('activity.enabled', '1');
    app()->forgetScopedInstances();

    $product = Product::factory()->create();
    $product->update(['name' => 'Renamed product']);

    expect(Activity::query()->count())->toBeGreaterThan(0);
});

test('the settings page saves the activity log switch', function () {
    $admin = User::factory()->create();
    $admin->assignRole(AdminRole::SuperAdmin->value);
    $this->actingAs($admin);

    Livewire::test('admin.settings.index')
        ->set('activityLogEnabled', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsService::class)->bool('activity.enabled'))->toBeTrue();
});

test('the activity log can be narrowed to a date range', function () {
    $admin = User::factory()->create();
    $admin->assignRole(AdminRole::SuperAdmin->value);
    $this->actingAs($admin);

    recordActivityRow('Old change', now()->subDays(10)->toDateTimeString());
    recordActivityRow('Recent change', now()->subDay()->toDateTimeString());

    Livewire::test('admin.activity.index')
        ->set('dateFrom', now()->subDays(3)->toDateString())
        ->set('dateTo', now()->toDateString())
        ->assertSee('Recent change')
        ->assertDontSee('Old change');
});

test('a reversed date range is swapped so it still returns the chosen days', function () {
    $admin = User::factory()->create();
    $admin->assignRole(AdminRole::SuperAdmin->value);
    $this->actingAs($admin);

    recordActivityRow('Old change', now()->subDays(10)->toDateTimeString());
    recordActivityRow('Recent change', now()->subDay()->toDateTimeString());

    Livewire::test('admin.activity.index')
        ->set('dateFrom', now()->toDateString())
        ->set('dateTo', now()->subDays(3)->toDateString())
        ->assertSee('Recent change')
        ->assertDontSee('Old change');
});

test('the activity log warns when logging is off', function () {
    $admin = User::factory()->create();
    $admin->assignRole(AdminRole::SuperAdmin->value);
    $this->actingAs($admin);

    Livewire::test('admin.activity.index')->assertSee('Activity logging is off');
});
