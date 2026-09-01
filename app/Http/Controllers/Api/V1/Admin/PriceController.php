<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceRequest;
use App\Http\Requests\UpdatePriceRequest;
use App\Http\Resources\PriceAdminResource;
use App\Models\Price;
use App\Models\Product;
use App\Services\PriceService;

class PriceController extends Controller
{
    public function __construct(protected PriceService $priceService)
    {
    }

    public function index(Product $product)
    {
        $prices = $this->priceService->getPriceHistory($product);

        return PriceAdminResource::collection($prices);
    }

    public function current(Product $product)
    {
        $price = $this->priceService->getCurrentPrice($product);

        abort_if(!$price, 404);

        return new PriceAdminResource($price);
    }

    public function store(StorePriceRequest $request, Product $product)
    {
        $price = $this->priceService->createPrice($product, $request->validated(), $request->user());

        return (new PriceAdminResource($price))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product, Price $price)
    {
        abort_if($price->product_id !== $product->id, 404);

        return new PriceAdminResource($price);
    }

    public function update(UpdatePriceRequest $request, Product $product, Price $price)
    {
        $price = $this->priceService->updatePrice($product, $price, $request->validated(), $request->user());

        return new PriceAdminResource($price);
    }

    public function destroy(Product $product, Price $price)
    {
        $this->priceService->deletePrice($product, $price, request()->user());

        return response()->json(['message' => 'Price cancelled successfully.'], 200);
    }
}
