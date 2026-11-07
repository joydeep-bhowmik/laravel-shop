<?php

use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductCsvService;

function csvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'variants');
    file_put_contents($path, $contents);

    return $path;
}

test('export writes a header and one row per variant with rupee prices', function () {
    ProductVariant::factory()->create(['sku' => 'EXP-001', 'price_paise' => 129950, 'stock_quantity' => 7]);

    $rows = iterator_to_array(app(ProductCsvService::class)->export(), false);

    expect($rows[0])->toBe(ProductCsvService::HEADER)
        ->and($rows[1][0])->toBe('EXP-001')
        ->and($rows[1][3])->toBe('1299.50')
        ->and($rows[1][4])->toBe('7');
});

test('import updates price and sets stock for known skus and reports unknown ones', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'IMP-001', 'price_paise' => 10000, 'stock_quantity' => 3]);
    $path = csvFile("sku,price,stock\nIMP-001,199.00,8\nNOPE-999,5.00,1\n");

    $result = app(ProductCsvService::class)->import($path, User::factory()->create());

    expect($result['updated'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0])->toContain('SKU not found')
        ->and($variant->fresh()->price_paise)->toBe(19900)
        ->and($variant->fresh()->stock_quantity)->toBe(8);
});

test('import refuses a file without a sku column', function () {
    $path = csvFile("price,stock\n10,2\n");

    app(ProductCsvService::class)->import($path, User::factory()->create());
})->throws(InvalidArgumentException::class, 'sku');
