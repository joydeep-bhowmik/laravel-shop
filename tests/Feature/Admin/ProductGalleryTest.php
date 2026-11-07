<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->actingAs($this->manager);
});

function formWithUploads(array $files)
{
    return Livewire::test('admin.products.form')->set('uploads', $files);
}

test('picked images appear as pending gallery items in the order they were added', function () {
    $component = formWithUploads([
        UploadedFile::fake()->image('first.jpg'),
        UploadedFile::fake()->image('second.jpg'),
    ]);

    expect($component->get('galleryOrder'))->toHaveCount(2)
        ->and($component->get('pendingImages'))->toHaveCount(2)
        ->and($component->get('uploads'))->toBe([]);
});

test('a pending image can be removed before saving', function () {
    $component = formWithUploads([UploadedFile::fake()->image('keep.jpg'), UploadedFile::fake()->image('drop.jpg')]);
    [$keep, $drop] = $component->get('galleryOrder');

    $component->call('removeImage', $drop);

    expect($component->get('galleryOrder'))->toBe([$keep])
        ->and($component->get('pendingImages'))->not->toHaveKey($drop);
});

test('dragging images changes the order they are saved in', function () {
    $category = Category::factory()->create();
    $component = formWithUploads([UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]);
    [$first, $second] = $component->get('galleryOrder');

    $component->call('reorderGallery', $second, 0);

    expect($component->get('galleryOrder'))->toBe([$second, $first]);

    $component
        ->set('name', 'Gallery Test Shirt')
        ->set('category_id', $category->id)
        ->set('variants.0.sku', 'GAL-001')
        ->set('variants.0.price', '499.00')
        ->set('variants.0.stock', '3')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('name', 'Gallery Test Shirt')->firstOrFail();
    $saved = $product->getMedia('gallery')->sortBy('order_column')->values();

    expect($saved)->toHaveCount(2)
        ->and($saved[0]->file_name)->toBe('b.jpg')
        ->and($saved[1]->file_name)->toBe('a.jpg');
});

test('removing a saved image deletes it from the product', function () {
    $product = Product::factory()->create();
    $product->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('gallery');
    $media = $product->getMedia('gallery')->first();

    Livewire::test('admin.products.form', ['product' => $product->id])
        ->call('removeImage', 'm'.$media->id);

    expect(Media::query()->find($media->id))->toBeNull();
});
