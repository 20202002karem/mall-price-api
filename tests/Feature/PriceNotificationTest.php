<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PriceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(): Product
    {
        $category = Category::create(['name' => 'مواد غذائية', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'سكر أبيض',
            'is_active' => true,
        ]);
    }

    public function test_successful_price_creation_triggers_notification(): void
    {
        $this->mock(FirebaseNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendPriceUpdate')->once();
        });

        $product = $this->makeProduct();
        $service = app(PriceService::class);

        $service->createPrice($product, [
            'price' => 10.00,
            'effective_from' => now()->toDateTimeString(),
        ], User::factory()->create());
    }

    public function test_failed_price_creation_does_not_trigger_notification(): void
    {
        $this->mock(FirebaseNotificationService::class, function ($mock) {
            $mock->shouldNotReceive('sendPriceUpdate');
        });

        $product = $this->makeProduct();
        $service = app(PriceService::class);

        Price::create([
            'product_id' => $product->id,
            'price' => 5.00,
            'effective_from' => now()->subDays(10),
            'effective_until' => now()->subDays(5),
        ]);

        $this->expectException(ValidationException::class);

        // فترة تتداخل مع سعر تاريخي مغلق => يجب أن يفشل قبل إنشاء أي سعر جديد
        $service->createPrice($product, [
            'price' => 12.00,
            'effective_from' => now()->subDays(8)->toDateTimeString(),
        ], User::factory()->create());
    }

    public function test_notification_failure_does_not_prevent_price_creation(): void
    {
        $this->mock(FirebaseNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendPriceUpdate')->once()->andThrow(new \Exception('Firebase down'));
        });

        $product = $this->makeProduct();
        $service = app(PriceService::class);

        $price = $service->createPrice($product, [
            'price' => 10.00,
            'effective_from' => now()->toDateTimeString(),
        ], User::factory()->create());

        $this->assertNotNull($price->id);
        $this->assertDatabaseHas('prices', ['id' => $price->id, 'price' => 10.00]);
    }
}
