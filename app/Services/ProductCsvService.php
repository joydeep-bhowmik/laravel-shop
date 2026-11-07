<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Models\ProductVariant;
use App\Models\User;
use Generator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ProductCsvService
{
    /**
     * Columns written by export() and expected by import().
     */
    public const HEADER = ['sku', 'product', 'variant', 'price', 'stock'];

    public function __construct(private InventoryService $inventory) {}

    /**
     * Yield one CSV row per variant, with prices in rupees.
     *
     * @return Generator<int, array<int, string>>
     */
    public function export(): Generator
    {
        yield self::HEADER;

        $variants = ProductVariant::query()
            ->with('product')
            ->orderBy('product_id')
            ->orderBy('position')
            ->cursor();

        foreach ($variants as $variant) {
            yield [
                $variant->sku,
                $variant->product->name,
                (string) $variant->name,
                number_format($variant->price_paise / 100, 2, '.', ''),
                (string) $variant->stock_quantity,
            ];
        }
    }

    /**
     * Update price and stock for existing variants matched by SKU.
     *
     * Blank price or stock cells are left alone. Stock is set to the value in the file and every change is recorded.
     *
     * @return array{updated: int, errors: array<int, string>}
     *
     * @throws InvalidArgumentException When the file is missing a sku column.
     */
    public function import(string $path, User $actor): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('The CSV file could not be read.');
        }

        try {
            $header = array_map(fn (?string $column): string => strtolower(trim((string) $column)), fgetcsv($handle) ?: []);
            $skuIndex = array_search('sku', $header, true);

            if ($skuIndex === false) {
                throw new InvalidArgumentException('The CSV must include a "sku" column.');
            }

            $priceIndex = array_search('price', $header, true);
            $stockIndex = array_search('stock', $header, true);
            $updated = 0;
            $errors = [];
            $line = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                $sku = trim((string) ($row[$skuIndex] ?? ''));

                if ($sku === '') {
                    continue;
                }

                try {
                    DB::transaction(function () use ($sku, $row, $priceIndex, $stockIndex, $actor, &$updated): void {
                        $variant = ProductVariant::query()->where('sku', $sku)->firstOrFail();

                        if ($priceIndex !== false && trim((string) ($row[$priceIndex] ?? '')) !== '') {
                            $variant->update(['price_paise' => MoneyFormatter::toPaise((string) $row[$priceIndex])]);
                        }

                        if ($stockIndex !== false && trim((string) ($row[$stockIndex] ?? '')) !== '') {
                            $target = (int) $row[$stockIndex];
                            $delta = $target - $variant->stock_quantity;

                            if ($delta !== 0) {
                                $this->inventory->adjust($variant, $delta, StockMovementReason::Adjustment, 'CSV import', $actor);
                            }
                        }

                        $updated++;
                    });
                } catch (Throwable $exception) {
                    $errors[] = "Line {$line} ({$sku}): ".$this->describe($exception);
                }
            }

            return ['updated' => $updated, 'errors' => $errors];
        } finally {
            fclose($handle);
        }
    }

    /**
     * A short, user-facing message for a row-level failure.
     */
    private function describe(Throwable $exception): string
    {
        return $exception instanceof ModelNotFoundException
            ? 'SKU not found.'
            : $exception->getMessage();
    }
}
