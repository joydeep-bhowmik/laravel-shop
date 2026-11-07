<?php

use App\Enums\ProductStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\StorefrontCatalog;
use Livewire\Livewire;

test('the home page shows banners, categories and new arrivals', function () {
    $category = Category::factory()->create(['name' => 'Kurtas']);
    $product = Product::factory()->for($category, 'category')->create(['name' => 'Block Print Kurta']);
    ProductVariant::factory()->default()->for($product)->create();
    Banner::factory()->create(['title' => 'Monsoon Edit']);

    app(StorefrontCatalog::class)->home(); // warm the cache path

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Monsoon Edit')
        ->assertSee('Kurtas')
        ->assertSee('Block Print Kurta');
});

test('the category page lists products from its subcategories', function () {
    $parent = Category::factory()->create(['name' => 'Women', 'slug' => 'women']);
    $child = Category::factory()->childOf($parent)->create(['name' => 'Sarees']);
    $product = Product::factory()->for($child, 'category')->create(['name' => 'Chanderi Saree Deluxe']);
    ProductVariant::factory()->default()->for($product)->create();

    $this->get(route('category', ['category' => 'women']))
        ->assertOk()
        ->assertSee('Chanderi Saree Deluxe');
});

test('the brand page filters to that brand', function () {
    $brand = Brand::factory()->create(['name' => 'Mehr Home', 'slug' => 'mehr-home']);
    $mine = Product::factory()->for($brand)->create(['name' => 'Brass Vase']);
    $other = Product::factory()->create(['name' => 'Woven Basket']);
    ProductVariant::factory()->default()->for($mine)->create();
    ProductVariant::factory()->default()->for($other)->create();

    $this->get(route('brand', ['brand' => 'mehr-home']))
        ->assertOk()
        ->assertSee('Brass Vase')
        ->assertDontSee('Woven Basket');
});

test('the shop page shows only published products', function () {
    $live = Product::factory()->create(['name' => 'Visible Tee']);
    $draft = Product::factory()->draft()->create(['name' => 'Hidden Tee']);
    ProductVariant::factory()->default()->for($live)->create();
    ProductVariant::factory()->default()->for($draft)->create();

    $this->get(route('shop'))
        ->assertOk()
        ->assertSee('Visible Tee')
        ->assertDontSee('Hidden Tee');
});

test('the search page returns matching products for the query string', function () {
    $match = Product::factory()->create(['name' => 'Linen Overshirt']);
    ProductVariant::factory()->default()->for($match)->create();

    $this->get(route('search', ['q' => 'linen']))
        ->assertOk()
        ->assertSee('Linen Overshirt');
});

test('an unpublished product page is not found', function () {
    $draft = Product::factory()->draft()->create();

    $this->get(route('product', ['product' => $draft->slug]))->assertNotFound();
});

test('the product page renders with structured data and canonical link', function () {
    $product = Product::factory()->create(['name' => 'Khadi Kurta', 'status' => ProductStatus::Published]);
    ProductVariant::factory()->default()->for($product)->create(['price_paise' => 129900]);

    $this->get(route('product', ['product' => $product->slug]))
        ->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"price":"1299.00"', false)
        ->assertSee('rel="canonical"', false);
});

test('the sitemap lists public categories and products', function () {
    $category = Category::factory()->create(['slug' => 'home-decor']);
    $product = Product::factory()->create(['slug' => 'brass-lamp']);
    $draft = Product::factory()->draft()->create(['slug' => 'secret-draft']);

    $response = $this->get(route('sitemap'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('category', ['category' => $category->slug]), false)
        ->assertSee(route('product', ['product' => $product->slug]), false)
        ->assertDontSee('secret-draft');
});

test('the header search box suggests matching products', function () {
    $product = Product::factory()->create(['name' => 'Terracotta Planter']);
    ProductVariant::factory()->default()->for($product)->create();

    Livewire::test('store.search-box')
        ->set('term', 'terracotta')
        ->assertSee('Terracotta Planter');
});

test('choosing an option switches to the matching variant and its price', function () {
    $product = Product::factory()->variable()->create(['name' => 'Oxford Shirt']);
    $size = Attribute::factory()->variant()->create(['name' => 'Size']);
    $medium = AttributeValue::factory()->for($size, 'attribute')->create(['value' => 'M']);
    $large = AttributeValue::factory()->for($size, 'attribute')->create(['value' => 'L']);

    $mediumVariant = ProductVariant::factory()->for($product)->default()->create(['price_paise' => 99900, 'sku' => 'OX-M']);
    $mediumVariant->attributeValues()->attach($medium);
    $largeVariant = ProductVariant::factory()->for($product)->create(['price_paise' => 109900, 'sku' => 'OX-L']);
    $largeVariant->attributeValues()->attach($large);

    Livewire::test('store.product', ['productId' => $product->id])
        ->assertSet('variantId', $mediumVariant->id)
        ->call('chooseOption', $size->id, $large->id)
        ->assertSet('variantId', $largeVariant->id)
        ->assertSee('1,099.00');
});

test('the delivery check on the product page reports the estimated date', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->default()->for($product)->create();
    Pincode::factory()->create(['code' => '411001', 'city' => 'Pune', 'state' => 'Maharashtra', 'delivery_days' => 4]);

    Livewire::test('store.product', ['productId' => $product->id])
        ->set('pincode', '411001')
        ->call('checkPincode')
        ->assertSee('Delivers to Pune, Maharashtra');
});

test('a guest submitting a review is sent to log in', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->default()->for($product)->create();

    Livewire::test('store.product', ['productId' => $product->id])
        ->set('reviewRating', 5)
        ->call('submitReview')
        ->assertRedirect(route('login'));
});

test('a signed-in customer review is saved for moderation', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->default()->for($product)->create();
    $customer = User::factory()->create();

    $this->actingAs($customer);

    Livewire::test('store.product', ['productId' => $product->id])
        ->set('reviewRating', 4)
        ->set('reviewTitle', 'Solid buy')
        ->call('submitReview')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $product->id,
        'user_id' => $customer->id,
        'rating' => 4,
        'is_approved' => false,
    ]);
});

test('recently viewed products are remembered across pages', function () {
    $first = Product::factory()->create(['name' => 'First Viewed']);
    $second = Product::factory()->create(['name' => 'Second Viewed']);
    ProductVariant::factory()->default()->for($first)->create();
    ProductVariant::factory()->default()->for($second)->create();

    $this->get(route('product', ['product' => $first->slug]))->assertOk();
    $this->get(route('product', ['product' => $second->slug]))
        ->assertOk()
        ->assertSee('Recently viewed')
        ->assertSee('First Viewed');
});
