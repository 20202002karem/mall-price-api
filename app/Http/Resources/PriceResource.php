<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'discount' => $this->discount,
            'currency' => $this->currency,
            'effective_from' => $this->effective_from,
            'effective_until' => $this->effective_until,
        ];
    }
}
