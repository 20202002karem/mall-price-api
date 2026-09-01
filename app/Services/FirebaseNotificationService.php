<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class FirebaseNotificationService
{
    public function __construct(protected Messaging $messaging)
    {
    }

    public function sendPriceUpdate(string $productName, int $productId, bool $isNewPrice): void
    {
        try {
            $title = $isNewPrice ? 'سعر جديد' : 'تحديث سعر المنتج';
            $body = $isNewPrice
                ? "تم إضافة سعر جديد للمنتج {$productName}"
                : "تم تحديث سعر {$productName}";

            $message = CloudMessage::withTarget('topic', 'price_updates')
                ->withNotification(FirebaseNotification::create($title, $body))
                ->withData([
                    'type' => 'price_update',
                    'product_id' => (string) $productId,
                ]);

            $this->messaging->send($message);
        } catch (\Throwable $e) {
            Log::warning('Failed to send price update notification', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
