<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductSearchRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(ProductSearchRequest $request)
    {
        $validated = $request->validated();

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Product::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->with(['category', 'currentPrice']);

        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('brand', 'like', '%' . $search . '%');
            });
        }

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (!empty($validated['availability'])) {
            $query->where('availability', $validated['availability']);
        }

        [$column, $direction] = $this->resolveSort($validated['sort'] ?? 'name');
        $query->orderBy($column, $direction);

        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        abort_if(!$product->is_active, 404);

        $product->load('category');

        abort_if(!$product->category || !$product->category->is_active, 404);

        $product->load('currentPrice');

        return new ProductResource($product);
    }

        public function byBarcode(string $barcode)
    {
        $product = Product::query()
            ->where('barcode', $barcode)
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->with(['category', 'currentPrice'])
            ->first();

        abort_if(!$product, 404);

        return new ProductResource($product);
    }

    protected function resolveSort(string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return [$column, $direction];
    }
}
