<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Price;
use App\Models\Product;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PriceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PriceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PriceService();
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeCategory(): Category
{
    return Category::firstOrCreate(
        ['name' => 'مواد غذائية'],
        ['is_active' => true]
    );
}
    protected function makeProduct(
    array $attrs = [],
    ?Category $category = null
): Product {
    $category ??= $this->makeCategory();

    return Product::create(array_merge([
        'category_id' => $category->id,
        'name' => 'منتج',
        'is_active' => true,
    ], $attrs));
}

    public function test_get_current_price_returns_null_when_none_exists(): void
    {
        $product = $this->makeProduct();

        $this->assertNull($this->service->getCurrentPrice($product));
    }

    public function test_create_price_throws_on_overlap(): void
    {
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => '2026-08-10', 'effective_until' => '2026-08-20']);

        $this->expectException(ValidationException::class);

        $this->service->createPrice($product, [
            'price' => 12,
            'effective_from' => '2026-08-15',
        ], null);
    }

    public function test_create_price_succeeds_for_touching_boundary(): void
    {
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => '2026-08-10', 'effective_until' => '2026-08-20']);

        $price = $this->service->createPrice($product, [
            'price' => 12,
            'effective_from' => '2026-08-20',
        ], null);

        $this->assertEquals(12, $price->price);
    }
}
