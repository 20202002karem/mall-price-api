<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'price',
        'old_price',
        'discount',
        'currency',
        'effective_from',
        'effective_until',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('effective_from', '<=', $now)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $now);
            });
    }

    public function isCurrent(): bool
    {
        $now = now();

        return $this->effective_from <= $now
            && ($this->effective_until === null || $this->effective_until > $now);
    }

    public function hasStarted(): bool
    {
        return $this->effective_from <= now();
    }
}
