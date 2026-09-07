<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'brand' => $this->brand,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'description' => $this->description,
            'availability' => $this->availability,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'current_price' => $this->currentPrice ? new PriceResource($this->currentPrice) : null,
        ];
    }
}
