<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PriceResource;
use App\Models\Product;
use App\Services\PriceService;

class PriceController extends Controller
{
    public function __construct(protected PriceService $priceService)
    {
    }

    public function current(Product $product)
    {
        abort_if(!$product->is_active, 404);

        $price = $this->priceService->getCurrentPrice($product);

        if (!$price) {
            return response()->json(['data' => null], 200);
        }

        return new PriceResource($price);
    }
}
