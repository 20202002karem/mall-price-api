<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'brand' => $this->brand,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'description' => $this->description,
            'availability' => $this->availability,
            'is_active' => $this->is_active,
            'category' => new CategoryAdminResource($this->whenLoaded('category')),
            'current_price' => $this->whenLoaded('currentPrice'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
