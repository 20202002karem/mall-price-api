<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductAdminResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        // $query = Product::query()->with('category');
        $query = Product::query()->with([
    'category',
    'currentPrice',
]);

        // if ($request->filled('search')) {
        //     $query->where('name', 'like', '%' . $request->input('search') . '%');
        // }
        if ($request->filled('search')) {
    $search = $request->input('search');

    $query->where(function ($q) use ($search) {
        $q->where('name', 'like', '%' . $search . '%')
            ->orWhere('barcode', 'like', '%' . $search . '%')
            ->orWhere('sku', 'like', '%' . $search . '%')
            ->orWhere('brand', 'like', '%' . $search . '%');
    });
}
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('availability')) {
            $query->where('availability', $request->input('availability'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $products = $query->orderBy('name')->paginate($perPage);

        return ProductAdminResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }

        $product = Product::create($data);
        // $product->load('category');
        $product->load([
    'category',
    'currentPrice',
]);

        return (new ProductAdminResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product)
    {
        // $product->load('category');
        $product->load([
    'category',
    'currentPrice',
]);

        return new ProductAdminResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }
        $product->update($data);
        // $product->load('category');
        $product->load([
    'category',
    'currentPrice',
]);

        return new ProductAdminResource($product);
    }

    public function destroy(Product $product)
    {
        if (!$product->is_active) {
            return response()->json([
                'message' => 'Product is already deactivated.',
            ], 200);
        }

        $product->update(['is_active' => false]);

        return response()->json([
            'message' => 'Product deactivated successfully.',
        ], 200);
    }

    public function activate(Product $product)
    {
        if ($product->is_active) {
            return response()->json([
                'message' => 'Product is already active.',
            ], 200);
        }

        $product->update(['is_active' => true]);

        return response()->json([
            'message' => 'Product activated successfully.',
        ], 200);
    }

    public function deactivate(Product $product)
    {
        return $this->destroy($product);
    }
}
