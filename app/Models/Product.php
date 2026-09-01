<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'barcode',
        'sku',
        'brand',
        'image',
        'description',
        'availability',
        'is_active',
    ];

    protected $attributes = [
        'availability' => 'unknown',
        'is_active' => true,
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * علاقة مباشرة للسعر الحالي فقط (تعتمد على Price::scopeCurrent).
     * تُستخدم مثل: $product->currentPrice
     */
    public function currentPrice(): HasOne
    {
        return $this->hasOne(Price::class)->current();
    }
}
