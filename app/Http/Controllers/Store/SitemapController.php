<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * List every public category, brand and published product for search engines.
     */
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => now()],
            ['loc' => route('shop'), 'lastmod' => now()],
        ];

        foreach (Category::query()->active()->get(['slug', 'updated_at']) as $category) {
            $urls[] = ['loc' => route('category', ['category' => $category->slug]), 'lastmod' => $category->updated_at];
        }

        foreach (Brand::query()->active()->get(['slug', 'updated_at']) as $brand) {
            $urls[] = ['loc' => route('brand', ['brand' => $brand->slug]), 'lastmod' => $brand->updated_at];
        }

        foreach (Product::query()->published()->get(['slug', 'updated_at']) as $product) {
            $urls[] = ['loc' => route('product', ['product' => $product->slug]), 'lastmod' => $product->updated_at];
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
