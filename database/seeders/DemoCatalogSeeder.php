<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Address;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductCatalogService;
use App\Services\ReviewService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Realistic demo store: categories, brands, 62 products with variants and images, customers, orders and coupons.
 *
 * Run on a fresh database only. Images are generated locally with GD so no network access is needed.
 */
class DemoCatalogSeeder extends Seeder
{
    /** @var array<int, string> */
    private const SIZES = ['S', 'M', 'L', 'XL'];

    /** @var array<string, string> colour name => hex */
    private const COLOURS = ['Red' => '#b91c1c', 'Blue' => '#1d4ed8', 'Black' => '#171717', 'White' => '#fafafa', 'Olive' => '#4d7c0f'];

    /** @var array<string, array{parent: string, children: array<int, string>}> */
    private const CATEGORY_TREE = [
        'Men' => ['Shirts', 'T-Shirts', 'Jeans'],
        'Women' => ['Kurtas', 'Sarees', 'Tops'],
        'Home & Living' => ['Kitchen', 'Decor'],
    ];

    /** @var array<int, string> */
    private const BRANDS = ['Aranya Threads', 'Kasba Co.', 'Mehr Home', 'Nirvana Studio', 'Saffron Lane', 'Tara & Co.'];

    /** @var array<string, array{adjectives: array<int, string>, nouns: array<int, string>, variable: bool}> */
    private const PRODUCT_LINES = [
        'Shirts' => ['adjectives' => ['Oxford', 'Linen', 'Checked', 'Slim-fit', 'Mandarin'], 'nouns' => ['Shirt', 'Button-down', 'Overshirt', 'Shirt Jacket'], 'variable' => true],
        'T-Shirts' => ['adjectives' => ['Everyday', 'Heavyweight', 'Printed', 'Ribbed', 'Relaxed'], 'nouns' => ['Tee', 'Crew Neck', 'Henley'], 'variable' => true],
        'Jeans' => ['adjectives' => ['Straight', 'Tapered', 'Relaxed', 'Stretch'], 'nouns' => ['Jeans', 'Chinos', 'Trousers'], 'variable' => true],
        'Kurtas' => ['adjectives' => ['Block-print', 'Chikankari', 'Handloom', 'Festive', 'Everyday'], 'nouns' => ['Kurta', 'Anarkali', 'Kurta Set'], 'variable' => true],
        'Sarees' => ['adjectives' => ['Banarasi', 'Kanjivaram', 'Chanderi', 'Bandhani'], 'nouns' => ['Saree', 'Silk Saree'], 'variable' => true],
        'Tops' => ['adjectives' => ['Wrap', 'Peplum', 'Cotton', 'Smocked'], 'nouns' => ['Top', 'Blouse', 'Tunic'], 'variable' => true],
        'Kitchen' => ['adjectives' => ['Cast-iron', 'Copper-base', 'Stainless', 'Non-stick'], 'nouns' => ['Kadai', 'Tawa', 'Pressure Pot', 'Pan Set'], 'variable' => false],
        'Decor' => ['adjectives' => ['Woven', 'Terracotta', 'Brass', 'Hand-painted'], 'nouns' => ['Wall Hanging', 'Vase', 'Table Lamp', 'Planter'], 'variable' => false],
    ];

    private int $sequence = 0;

    /** @var array<int, string> */
    private const FABRICS = ['Cotton', 'Linen', 'Silk', 'Denim', 'Khadi'];

    public function run(): void
    {
        if (Product::query()->exists()) {
            $this->command?->warn('Demo catalog already present. Skipping. Run migrate:fresh first to reseed.');

            return;
        }

        $sizeValues = $this->attributeValues('Size', self::SIZES, variant: true);
        $colourValues = $this->attributeValues('Colour', array_keys(self::COLOURS), variant: true, hex: self::COLOURS);
        $fabricValues = $this->attributeValues('Fabric', self::FABRICS, variant: false);

        $brands = collect(self::BRANDS)->map(fn (string $name) => Brand::query()->create([
            'name' => $name,
            'description' => fake()->sentence(),
            'is_active' => true,
        ]));

        $leafCategories = [];

        foreach (self::CATEGORY_TREE as $parentName => $children) {
            $parent = Category::query()->create([
                'name' => $parentName,
                'description' => "Everything in {$parentName}.",
                'position' => count($leafCategories),
                'is_active' => true,
            ]);

            foreach ($children as $position => $childName) {
                $leafCategories[$childName] = Category::query()->create([
                    'parent_id' => $parent->id,
                    'name' => $childName,
                    'description' => "Shop {$childName} across our brands.",
                    'position' => $position,
                    'is_active' => true,
                ]);
            }
        }

        $catalog = app(ProductCatalogService::class);
        $products = [];

        foreach (self::PRODUCT_LINES as $categoryName => $line) {
            for ($index = 0; $index < 8 && $categoryName !== 'Jeans'; $index++) {
                $products[] = $this->makeProduct($catalog, $leafCategories[$categoryName], $line, $index, $brands, $sizeValues, $colourValues, $fabricValues);
            }
        }

        // Jeans get six, to reach 54 products overall.
        for ($index = 0; $index < 6; $index++) {
            $products[] = $this->makeProduct($catalog, $leafCategories['Jeans'], self::PRODUCT_LINES['Jeans'], $index, $brands, $sizeValues, $colourValues, $fabricValues);
        }

        $this->makeDrafts($catalog, $leafCategories['Tops'], $brands);
        $this->markDeals(collect($products)->take(4));
        $this->addReviews(collect($products)->take(18));
        $this->makeBanners();
        $this->makePincodes();
        $this->makeCustomers($products);
        $this->makeCoupons();
    }

    /**
     * @param  array{adjectives: array<int, string>, nouns: array<int, string>, variable: bool}  $line
     * @param  Collection<int, Brand>  $brands
     */
    private function makeProduct(ProductCatalogService $catalog, Category $category, array $line, int $index, $brands, array $sizeValues, array $colourValues, array $fabricValues): Product
    {
        $adjective = $line['adjectives'][$index % count($line['adjectives'])];
        $noun = $line['nouns'][intdiv($index, count($line['adjectives'])) % count($line['nouns'])];
        $name = "{$adjective} {$noun} ".($index + 1);
        $sequence = ++$this->sequence;
        $skuPrefix = 'DEMO'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $basePrice = random_int(49900, 249900);
        $variants = [];

        if ($line['variable']) {
            $colours = collect($colourValues)->shuffle()->take(random_int(2, 3))->values();
            $useSizes = in_array($category->name, ['Shirts', 'T-Shirts', 'Jeans', 'Kurtas', 'Tops'], true);

            foreach ($colours as $colour) {
                $sizes = $useSizes ? $sizeValues : [null];

                foreach ($sizes as $size) {
                    $options = array_values(array_filter([$size, $colour]));
                    $sku = $skuPrefix.'-'.Str::upper(Str::substr($colour->value, 0, 3)).($size ? '-'.$size->value : '');

                    $variants[] = [
                        'id' => null,
                        'sku' => $sku,
                        'name' => implode(' / ', array_map(fn ($value) => $value->value, $options)),
                        'price_paise' => $basePrice + ($size?->value === 'XL' ? 5000 : 0),
                        'compare_at_price_paise' => random_int(0, 1) ? (int) round($basePrice * 1.25) : null,
                        'stock_quantity' => random_int(0, 4) === 0 ? 0 : random_int(3, 40),
                        'low_stock_threshold' => 5,
                        'weight_grams' => random_int(150, 900),
                        'is_default' => $variants === [],
                        'attribute_value_ids' => array_map(fn ($value) => $value->id, $options),
                    ];
                }
            }
        } else {
            $variants[] = [
                'id' => null,
                'sku' => $skuPrefix.'-001',
                'name' => null,
                'price_paise' => $basePrice,
                'compare_at_price_paise' => random_int(0, 1) ? (int) round($basePrice * 1.2) : null,
                'stock_quantity' => random_int(0, 4) === 0 ? 0 : random_int(3, 40),
                'low_stock_threshold' => 5,
                'weight_grams' => random_int(200, 2500),
                'is_default' => true,
                'attribute_value_ids' => [],
            ];
        }

        $product = $catalog->save(null, [
            'name' => $name,
            'category_id' => $category->id,
            'brand_id' => $brands[$index % $brands->count()]->id,
            'type' => $line['variable'] && count($variants) > 1 ? ProductType::Variable->value : ProductType::Simple->value,
            'status' => ProductStatus::Published->value,
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(2, true),
            'meta_title' => $name,
            'meta_description' => fake()->sentence(16),
            'hsn_code' => fake()->numerify('62####'),
            'gst_rate' => $line['variable'] ? 5 : 18,
            'attribute_value_ids' => [$fabricValues[array_rand($fabricValues)]->id],
            'variants' => $variants,
        ]);

        $this->attachImages($product, $name);

        return $product;
    }

    /**
     * @param  Collection<int, Brand>  $brands
     */
    private function makeDrafts(ProductCatalogService $catalog, Category $category, $brands): void
    {
        foreach (['Unreleased Linen Top', 'Coming Soon Wrap'] as $name) {
            $catalog->save(null, [
                'name' => $name,
                'category_id' => $category->id,
                'brand_id' => $brands->first()->id,
                'type' => ProductType::Simple->value,
                'status' => ProductStatus::Draft->value,
                'gst_rate' => 5,
                'variants' => [[
                    'id' => null,
                    'sku' => strtoupper(Str::slug($name)),
                    'name' => null,
                    'price_paise' => 99900,
                    'compare_at_price_paise' => null,
                    'stock_quantity' => 10,
                    'low_stock_threshold' => 2,
                    'weight_grams' => 200,
                    'is_default' => true,
                    'attribute_value_ids' => [],
                ]],
            ]);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function markDeals($products): void
    {
        $products->each(fn (Product $product) => $product->update(['deal_ends_at' => Carbon::now()->addDays(2)->addHours(random_int(0, 20))]));
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function addReviews($products): void
    {
        $reviews = app(ReviewService::class);

        foreach ($products as $product) {
            foreach (range(1, random_int(1, 4)) as $_) {
                $reviewer = User::factory()->create();
                $review = ProductReview::factory()->for($product)->for($reviewer)->pending()->create();
                $reviews->approve($review);
            }
        }
    }

    private function makeBanners(): void
    {
        $slides = [
            ['title' => 'Monsoon edit', 'subtitle' => 'Breathable cottons and linens for the rains.', 'cta_label' => 'Shop Men', 'cta_url' => '/category/men', 'colour' => '#0f766e'],
            ['title' => 'Festive kurtas', 'subtitle' => 'Chikankari and block prints, made to last.', 'cta_label' => 'Shop Women', 'cta_url' => '/category/women', 'colour' => '#9a3412'],
            ['title' => 'Home that feels lived in', 'subtitle' => 'Kitchen staples and decor from artisan partners.', 'cta_label' => 'Shop Home', 'cta_url' => '/category/home-living', 'colour' => '#3730a3'],
        ];

        foreach ($slides as $position => $slide) {
            $banner = Banner::query()->create([
                'title' => $slide['title'],
                'subtitle' => $slide['subtitle'],
                'cta_label' => $slide['cta_label'],
                'cta_url' => $slide['cta_url'],
                'position' => $position,
                'is_active' => true,
            ]);

            $banner->addMedia($this->placeholderImage($slide['title'], $slide['colour'], 1600, 800))
                ->usingFileName('banner-'.$position.'.png')
                ->toMediaCollection('image');
        }
    }

    private function makePincodes(): void
    {
        $rows = [
            ['400001', 'Mumbai', 'Maharashtra', true, true, 2],
            ['400050', 'Mumbai', 'Maharashtra', true, true, 3],
            ['411001', 'Pune', 'Maharashtra', true, true, 4],
            ['110001', 'New Delhi', 'Delhi', true, true, 4],
            ['560001', 'Bengaluru', 'Karnataka', true, true, 5],
            ['600001', 'Chennai', 'Tamil Nadu', true, false, 5],
            ['700001', 'Kolkata', 'West Bengal', true, true, 6],
            ['785001', 'Jorhat', 'Assam', false, false, 0],
        ];

        foreach ($rows as [$code, $city, $state, $serviceable, $cod, $days]) {
            Pincode::query()->create([
                'code' => $code,
                'city' => $city,
                'state' => $state,
                'is_serviceable' => $serviceable,
                'cod_available' => $cod,
                'delivery_days' => $days,
            ]);
        }
    }

    /**
     * @param  array<int, Product>  $products
     */
    private function makeCustomers(array $products): void
    {
        $customers = User::factory()->count(12)->create();

        foreach ($customers as $customer) {
            Address::factory()->default()->for($customer)->create([
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400050',
            ]);
        }

        $statuses = array_merge(
            array_fill(0, 10, OrderStatus::Delivered),
            array_fill(0, 5, OrderStatus::Shipped),
            array_fill(0, 5, OrderStatus::Paid),
            array_fill(0, 3, OrderStatus::Pending),
            array_fill(0, 2, OrderStatus::Cancelled),
        );

        foreach ($statuses as $status) {
            $customer = $customers->random();
            $address = $customer->addresses()->first();
            $order = Order::query()->create([
                'order_number' => 'ORD-'.strtoupper(Str::random(8)),
                'user_id' => $customer->id,
                'status' => $status,
                'payment_status' => in_array($status, [OrderStatus::Pending, OrderStatus::Cancelled], true) ? PaymentStatus::Pending : PaymentStatus::Captured,
                'payment_method' => PaymentMethod::Razorpay,
                'shipping_method' => 'standard',
                'shipping_state' => 'Maharashtra',
                'shipping_address' => $address->toOrderSnapshot(),
                'subtotal_paise' => 0,
                'total_paise' => 0,
                'placed_at' => Carbon::now()->subDays(random_int(0, 60)),
                'paid_at' => $status === OrderStatus::Pending ? null : now()->subDays(random_int(0, 59)),
            ]);

            $subtotal = 0;

            foreach (collect($products)->random(random_int(1, 3)) as $product) {
                $variant = ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->firstOrFail();
                $quantity = random_int(1, 2);
                $lineTotal = $variant->price_paise * $quantity;
                $subtotal += $lineTotal;

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'unit_price_paise' => $variant->price_paise,
                    'quantity' => $quantity,
                    'line_total_paise' => $lineTotal,
                    'gst_rate' => $product->gst_rate,
                    'tax_paise' => 0,
                ]);
            }

            $order->update(['subtotal_paise' => $subtotal, 'total_paise' => $subtotal]);
        }
    }

    private function makeCoupons(): void
    {
        Coupon::factory()->create(['code' => 'WELCOME10', 'description' => '10% off your first order', 'type' => 'percentage', 'value' => 10, 'per_user_limit' => 1]);
        Coupon::factory()->fixed(10000)->create(['code' => 'FLAT100', 'description' => '₹100 off orders above ₹999', 'min_order_paise' => 99900, 'per_user_limit' => 2]);
    }

    /**
     * @param  array<string, string>|null  $hex
     * @return array<int, AttributeValue>
     */
    private function attributeValues(string $name, array $values, bool $variant, ?array $hex = null): array
    {
        $attribute = Attribute::query()->create([
            'name' => $name,
            'is_variant' => $variant,
            'position' => $variant ? 0 : 1,
        ]);

        return collect($values)->map(fn (string $value, int $position) => AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => $value,
            'slug' => Str::slug($value),
            'color_hex' => $hex[$value] ?? null,
            'position' => $position,
        ]))->all();
    }

    private function attachImages(Product $product, string $label): void
    {
        foreach ([['#e4e4e7', 'front'], ['#27272a', 'detail']] as [$colour, $view]) {
            $product->addMedia($this->placeholderImage("{$label} ({$view})", $colour, 800, 800))
                ->usingFileName(Str::slug($label).'-'.$view.'.png')
                ->toMediaCollection('gallery');
        }
    }

    /**
     * A simple generated product or banner image, so demo data needs no network access.
     */
    private function placeholderImage(string $label, string $hex, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');
        $background = imagecolorallocate($image, $red, $green, $blue);
        $ink = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledellipse($image, (int) ($width * 0.7), (int) ($height * 0.35), (int) ($width * 0.4), (int) ($height * 0.4), imagecolorallocatealpha($image, 255, 255, 255, 90));
        imagestring($image, 5, 24, (int) ($height * 0.85), Str::limit($label, 60, ''), $ink);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.Str::uuid().'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }
}
