<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->latest('updated_at')
            ->take(10)
            ->get(['name', 'price'])
            ->map(function (Product $product): array {
                return [
                    'name' => $product->name,
                    'price' => (float) $product->price,
                ];
            })
            ->values();

        if ($products->isEmpty()) {
            $products = collect([
                ['name' => 'Sample Product', 'price' => 0],
            ]);
        }

        return view('owner.products.index', [
            'products' => $products,
        ]);
    }
}
