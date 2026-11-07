<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $this->actingAs($manager);
});

test('images picked for a variant are pending on that variant only', function () {
    $component = Livewire::test('admin.products.form')
        ->call('addVariant');

    $rows = collect($component->get('variants'));
    $first = $rows[0]['key'];
    $second = $rows[1]['key'];

    $component->set("variantUploads.{$second}", [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]);

    expect($component->get("variantOrder.{$second}"))->toHaveCount(2)
        ->and($component->get("variantOrder.{$first}") ?? [])->toBe([])
        ->and($component->get("variantUploads.{$second}"))->toBe([]);
});

test('a pending variant image can be removed before saving', function () {
    $component = Livewire::test('admin.products.form');
    $row = collect($component->get('variants'))[0]['key'];

    $component->set("variantUploads.{$row}", [UploadedFile::fake()->image('keep.jpg'), UploadedFile::fake()->image('drop.jpg')]);
    [$keep, $drop] = $component->get("variantOrder.{$row}");

    $component->call('removeVariantImage', $row, $drop);

    expect($component->get("variantOrder.{$row}"))->toBe([$keep]);
});

test('variant images are saved on the variant in the order they were dragged to', function () {
    $category = Category::factory()->create();

    $component = Livewire::test('admin.products.form')
        ->set('name', 'Variant Gallery Shirt')
        ->set('category_id', $category->id)
        ->set('variants.0.sku', 'VG-001')
        ->set('variants.0.price', '299.00')
        ->set('variants.0.stock', '2');

    $row = collect($component->get('variants'))[0]['key'];
    $component->set("variantUploads.{$row}", [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')]);
    [$first, $second] = $component->get("variantOrder.{$row}");

    $component->call('reorderVariantImage', "{$row}|{$second}", 0)
        ->call('save')
        ->assertHasNoErrors();

    $variant = ProductVariant::query()->where('sku', 'VG-001')->firstOrFail();
    $saved = $variant->getMedia('images')->sortBy('order_column')->values();

    expect($saved)->toHaveCount(2)
        ->and($saved[0]->file_name)->toBe('two.jpg')
        ->and($saved[1]->file_name)->toBe('one.jpg');
});

test('a saved variant image can be deleted from the variant', function () {
    $product = Product::factory()->variable()->create();
    $variant = ProductVariant::factory()->for($product)->default()->create();
    $variant->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('images');
    $media = $variant->getMedia('images')->first();

    Livewire::test('admin.products.form', ['product' => $product->id])
        ->call('removeVariantImage', (string) $variant->id, 'm'.$media->id);

    expect(Media::query()->find($media->id))->toBeNull();
});
