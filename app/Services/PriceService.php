<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PriceService
{
    public function getCurrentPrice(Product $product): ?Price
    {
        return $product->prices()->current()->first();
    }

    public function getPriceHistory(Product $product)
    {
        return $product->prices()->orderBy('effective_from', 'desc')->get();
    }


public function createPrice(Product $product, array $data, ?User $admin): Price
{
    if (!$product->is_active) {
        throw ValidationException::withMessages([
            'product' => 'Cannot create a price for an inactive product.',
        ]);
    }

    $effectiveFrom = Carbon::parse($data['effective_from']);

    $effectiveUntil = !empty($data['effective_until'])
        ? Carbon::parse($data['effective_until'])
        : null;

    // التأكد من صحة الفترة
    $this->assertValidPeriod($effectiveFrom, $effectiveUntil);

    $wasNewPrice = false;

    $price = DB::transaction(function () use (
        $product,
        $data,
        $admin,
        $effectiveFrom,
        $effectiveUntil,
        &$wasNewPrice
    ) {
        /*
         * السعر الحالي المفتوح فقط:
         *
         * effective_from <= الآن
         * effective_until = NULL
         *
         * هذا هو السعر الذي يسمح النظام باستبداله
         * تلقائيًا عند إنشاء سعر جديد الآن أو مستقبلًا.
         */
        $currentOpenPrice = $product->prices()
            ->whereNull('effective_until')
            ->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')
            ->first();

        /*
         * إذا لم يوجد سعر حالي مفتوح فهذا يعتبر إنشاء
         * أول سعر/سعر جديد مستقل.
         */
        $wasNewPrice = $currentOpenPrice === null;

        /*
         * إذا كان السعر الجديد يبدأ الآن أو في المستقبل،
         * يمكن إغلاق السعر الحالي المفتوح تلقائيًا.
         *
         * أما إذا كان السعر الجديد في الماضي فلا نغلق
         * السعر الحالي لأن هذا سيؤدي إلى تداخل تاريخي.
         */
        $canReplaceCurrentPrice =
            $currentOpenPrice !== null &&
            $effectiveFrom->gte(now());

        /*
         * افحص التداخل.
         *
         * إذا كان لدينا سعر حالي سيتم استبداله،
         * نستثنيه من فحص التداخل لأنه ليس تعارضًا حقيقيًا:
         *
         * old: 2026-08-16 -> ∞
         * new: 2026-09-16 -> ∞
         *
         * القديم سيصبح:
         * 2026-08-16 -> 2026-09-16
         */
        $conflict = $this->findOverlap(
            $product,
            $effectiveFrom,
            $effectiveUntil,
            $canReplaceCurrentPrice
                ? $currentOpenPrice->id
                : null
        );

        if ($conflict) {
            throw ValidationException::withMessages([
                'effective_from' =>
                    'The price period overlaps with an existing price period.',
            ]);
        }

        /*
         * بعد نجاح فحص التداخل فقط نغلق السعر الحالي.
         *
         * مهم:
         * هذا داخل Transaction، لذلك إذا حدث أي خطأ بعد ذلك
         * سيتم Rollback ولن يبقى السعر القديم مغلقًا.
         */
        if ($canReplaceCurrentPrice) {
            $oldValues = [
                'effective_until' => optional(
                    $currentOpenPrice->effective_until
                )->toDateTimeString(),
            ];

            $currentOpenPrice->effective_until = $effectiveFrom;
            $currentOpenPrice->save();

            $this->logAudit(
                $admin,
                'price_replaced',
                $currentOpenPrice,
                $oldValues,
                [
                    'effective_until' =>
                        $effectiveFrom->toDateTimeString(),
                ]
            );
        }

        /*
         * إنشاء السعر الجديد.
         */
        $price = Price::create([
            'product_id' => $product->id,
            'price' => $data['price'],
            'old_price' => $canReplaceCurrentPrice
                ? $currentOpenPrice->price
                : null,
            'discount' => $data['discount'] ?? null,
            'currency' => $data['currency'] ?? 'ILS',
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'updated_by' => $admin?->id,
        ]);

        /*
         * تسجيل عملية إنشاء السعر.
         */
        $this->logAudit(
            $admin,
            'price_created',
            $price,
            null,
            $price->only([
                'product_id',
                'price',
                'old_price',
                'discount',
                'currency',
                'effective_from',
                'effective_until',
            ])
        );

        return $price;
    });

    /*
     * Notification بعد نجاح الـ Transaction بالكامل.
     */
    try {
        $this->notificationService->sendPriceUpdate(
            $product->name,
            $product->id,
            $wasNewPrice
        );
    } catch (\Throwable $e) {
        Log::warning(
            'Notification dispatch failed after successful price creation',
            [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]
        );
    }

    return $price;
}
    public function updatePrice(Product $product, Price $price, array $data, ?User $admin): Price
    {
        $this->assertPriceBelongsToProduct($product, $price);

        if (!$product->is_active) {
            throw ValidationException::withMessages([
                'product' => 'Cannot modify a price for an inactive product.',
            ]);
        }

        $hasStarted = $price->hasStarted();

        if ($hasStarted && (array_key_exists('price', $data) || array_key_exists('effective_from', $data))) {
            throw ValidationException::withMessages([
                'price' => 'Cannot modify the price value or start date of an already-effective price. Create a new price instead.',
            ])->status(409);
        }

        return DB::transaction(function () use ($product, $price, $data, $admin) {
            $oldValues = $price->only(['price', 'discount', 'currency', 'effective_from', 'effective_until']);

            $effectiveFrom = isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : $price->effective_from;
            $effectiveUntil = array_key_exists('effective_until', $data)
                ? (!empty($data['effective_until']) ? Carbon::parse($data['effective_until']) : null)
                : $price->effective_until;

            $this->assertValidPeriod($effectiveFrom, $effectiveUntil);

            $conflict = $this->findOverlap($product, $effectiveFrom, $effectiveUntil, $price->id);

            if ($conflict) {
                throw ValidationException::withMessages([
                    'effective_from' => 'The updated price period overlaps with an existing price period.',
                ]);
            }

            $price->fill([
                'price' => $data['price'] ?? $price->price,
                'discount' => array_key_exists('discount', $data) ? $data['discount'] : $price->discount,
                'currency' => $data['currency'] ?? $price->currency,
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
                'updated_by' => $admin?->id,
            ]);
            $price->save();

            $this->logAudit($admin, 'price_updated', $price, $oldValues, $price->only([
                'price', 'discount', 'currency', 'effective_from', 'effective_until',
            ]));

            return $price;
        });
    }

    public function deletePrice(Product $product, Price $price, ?User $admin): void
    {
        $this->assertPriceBelongsToProduct($product, $price);

        if ($price->hasStarted()) {
            throw ValidationException::withMessages([
                'price' => 'Historical or currently active prices cannot be deleted.',
            ])->status(409);
        }

        DB::transaction(function () use ($product, $price, $admin) {
            $oldValues = $price->only(['price', 'effective_from', 'effective_until']);
            $priceId = $price->id;

            $price->delete();

            AuditLog::create([
                'user_id' => $admin?->id,
                'action' => 'price_cancelled',
                'model_type' => Price::class,
                'model_id' => $priceId,
                'old_values' => $oldValues,
                'new_values' => null,
            ]);
        });
    }


    protected function findOverlap(
    Product $product,
    Carbon $newStart,
    ?Carbon $newEnd,
    ?int $excludePriceId = null
): ?Price {
    return $product->prices()
        /*
         * في حالة استبدال السعر الحالي فقط،
         * نستثني ذلك السعر من فحص التداخل.
         */
        ->when(
            $excludePriceId !== null,
            fn ($q) => $q->where('id', '!=', $excludePriceId)
        )

        /*
         * شرط:
         *
         * existing.start < new.end
         *
         * إذا كانت newEnd = NULL فهذا يعني ∞،
         * وبالتالي لا نحتاج لهذا الشرط.
         */
        ->when(
            $newEnd !== null,
            fn ($q) => $q->where(
                'effective_from',
                '<',
                $newEnd
            )
        )

        /*
         * شرط:
         *
         * new.start < existing.end
         *
         * إذا كانت existing.end = NULL
         * فهذا يعني أن الفترة مفتوحة إلى ∞.
         */
        ->where(function ($q) use ($newStart) {
            $q->whereNull('effective_until')
                ->orWhere(
                    'effective_until',
                    '>',
                    $newStart
                );
        })

        ->first();
}
    protected function assertValidPeriod(Carbon $from, ?Carbon $until): void
    {
        if ($until && $until->lte($from)) {
            throw ValidationException::withMessages([
                'effective_until' => 'The effective until must be after effective from.',
            ]);
        }
    }

    protected function assertPriceBelongsToProduct(Product $product, Price $price): void
    {
        abort_if($price->product_id !== $product->id, 404);
    }

    protected function logAudit(?User $admin, string $action, Price $price, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id' => $admin?->id,
            'action' => $action,
            'model_type' => Price::class,
            'model_id' => $price->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
