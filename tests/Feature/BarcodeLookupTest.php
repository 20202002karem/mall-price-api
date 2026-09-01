<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Price;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BarcodeLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeCategory(array $attrs = []): Category
    {
        return Category::create(array_merge(['name' => 'مواد غذائية', 'is_active' => true], $attrs));
    }

    // 1. barcode lookup returns product
    public function test_barcode_lookup_returns_product(): void
    {
        $category = $this->makeCategory();
        Product::create([
            'category_id' => $category->id,
            'name' => 'سكر أبيض',
            'barcode' => '6291041500213',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/products/barcode/6291041500213');

        $response->assertOk();
        $response->assertJsonPath('data.name', 'سكر أبيض');
        $response->assertJsonPath('data.barcode', '6291041500213');
    }

    // 2. barcode lookup returns current price
    public function test_barcode_lookup_returns_current_price(): void
    {
        $category = $this->makeCategory();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
            'barcode' => '111222333',
            'is_active' => true,
        ]);
        Price::create(['product_id' => $product->id, 'price' => 6.00, 'effective_from' => now()->subDay()]);

        $response = $this->getJson('/api/v1/products/barcode/111222333');

        $response->assertOk();
        $response->assertJsonPath('data.current_price.price', '6.00');
    }

    // 3. barcode lookup does not return future price
    public function test_barcode_lookup_does_not_return_future_price(): void
    {
        $category = $this->makeCategory();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
            'barcode' => '444555666',
            'is_active' => true,
        ]);
        Price::create(['product_id' => $product->id, 'price' => 10.00, 'effective_from' => now()->addDays(3)]);

        $response = $this->getJson('/api/v1/products/barcode/444555666');

        $response->assertOk();
        $response->assertJsonPath('data.current_price', null);
    }

    // 4. barcode lookup does not return expired price
    public function test_barcode_lookup_does_not_return_expired_price(): void
    {
        $category = $this->makeCategory();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
            'barcode' => '777888999',
            'is_active' => true,
        ]);
        Price::create([
            'product_id' => $product->id,
            'price' => 10.00,
            'effective_from' => now()->subDays(10),
            'effective_until' => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/v1/products/barcode/777888999');

        $response->assertOk();
        $response->assertJsonPath('data.current_price', null);
    }

    // 5. barcode lookup returns 404 for unknown barcode
    public function test_barcode_lookup_returns_404_for_unknown_barcode(): void
    {
        $response = $this->getJson('/api/v1/products/barcode/NOT-EXIST');

        $response->assertStatus(404);
    }

    // 6. barcode lookup returns 404 for inactive product
    public function test_barcode_lookup_returns_404_for_inactive_product(): void
    {
        $category = $this->makeCategory();
        Product::create([
            'category_id' => $category->id,
            'name' => 'معطل',
            'barcode' => '123123123',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/products/barcode/123123123');

        $response->assertStatus(404);
    }

    // 7. barcode lookup returns 404 for product in inactive category
    public function test_barcode_lookup_returns_404_for_product_in_inactive_category(): void
    {
        $category = $this->makeCategory(['is_active' => false]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
            'barcode' => '321321321',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/products/barcode/321321321');

        $response->assertStatus(404);
    }

    // 8. barcode lookup is accessible without authentication
    public function test_barcode_lookup_is_accessible_without_authentication(): void
    {
        $category = $this->makeCategory();
        Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
            'barcode' => '555000111',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/products/barcode/555000111');

        $response->assertOk();
    }

    // 9. barcode lookup returns correct product
    public function test_barcode_lookup_returns_correct_product(): void
    {
        $category = $this->makeCategory();
        Product::create(['category_id' => $category->id, 'name' => 'أ', 'barcode' => '111']);
        $target = Product::create(['category_id' => $category->id, 'name' => 'ب', 'barcode' => '222']);
        Product::create(['category_id' => $category->id, 'name' => 'ج', 'barcode' => '333']);

        $response = $this->getJson('/api/v1/products/barcode/222');

        $response->assertOk();
        $response->assertJsonPath('data.id', $target->id);
        $response->assertJsonPath('data.name', 'ب');
    }

    // 10. barcode lookup does not associate another product's price
    public function test_barcode_lookup_does_not_associate_another_products_price(): void
    {
        $category = $this->makeCategory();

        $productA = Product::create(['category_id' => $category->id, 'name' => 'أ', 'barcode' => 'AAA']);
        $productB = Product::create(['category_id' => $category->id, 'name' => 'ب', 'barcode' => 'BBB']);

        Price::create(['product_id' => $productA->id, 'price' => 10.00, 'effective_from' => now()->subDay()]);
        Price::create(['product_id' => $productB->id, 'price' => 25.00, 'effective_from' => now()->subDay()]);

        $response = $this->getJson('/api/v1/products/barcode/BBB');

        $response->assertOk();
        $response->assertJsonPath('data.current_price.price', '25.00');
    }

    // Regression: route order does not break existing product-by-id route
    public function test_existing_product_by_id_route_still_works(): void
    {
        $category = $this->makeCategory();
        $product = Product::create(['category_id' => $category->id, 'name' => 'منتج', 'is_active' => true]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $product->id);
    }
}
