<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSearchTest extends TestCase
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
        return Category::create(array_merge(['name' => 'فئة ' . uniqid(), 'is_active' => true], $attrs));
    }

    protected function makeProduct(array $attrs = []): Product
    {
        $category = $attrs['category_id'] ?? null ? null : $this->makeCategory();

        return Product::create(array_merge([
            'category_id' => $category?->id,
            'name' => 'منتج',
            'is_active' => true,
        ], $attrs));
    }

    protected function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    // 1. Search by name
    public function test_search_by_product_name(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'سكر أبيض', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'أرز', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=' . urlencode('سكر'));

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'سكر أبيض');
    }

    // 2. Search by barcode
    public function test_search_by_barcode(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'منتج أ', 'barcode' => '111222333', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'منتج ب', 'barcode' => '999888777', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=111222333');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.barcode', '111222333');
    }

    // 3. Search by SKU
    public function test_search_by_sku(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'منتج أ', 'sku' => 'SKU-100', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'منتج ب', 'sku' => 'SKU-200', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=SKU-100');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 4. Search by brand
    public function test_search_by_brand(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'منتج أ', 'brand' => 'Samsung', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'منتج ب', 'brand' => 'LG', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=Samsung');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 5. Search across multiple fields
    public function test_search_across_multiple_fields(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'تلفاز', 'brand' => 'XYZ123', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'XYZ123', 'brand' => 'أخرى', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'غير مطابق', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=XYZ123');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    // 6 & 7. Only active products returned / inactive excluded
    public function test_search_returns_only_active_products(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'نشط', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'معطل', 'is_active' => false]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonMissing(['name' => 'معطل']);
    }

    // 8. Excludes products belonging to inactive categories
    public function test_search_excludes_products_belonging_to_inactive_categories(): void
    {
        $activeCategory = $this->makeCategory();
        $inactiveCategory = $this->makeCategory(['is_active' => false]);

        Product::create(['category_id' => $activeCategory->id, 'name' => 'ظاهر', 'is_active' => true]);
        Product::create(['category_id' => $inactiveCategory->id, 'name' => 'مخفي', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonMissing(['name' => 'مخفي']);
    }

    // 9. Category filtering
    public function test_category_filtering(): void
    {
        $categoryA = $this->makeCategory();
        $categoryB = $this->makeCategory();

        Product::create(['category_id' => $categoryA->id, 'name' => 'أ', 'is_active' => true]);
        Product::create(['category_id' => $categoryB->id, 'name' => 'ب', 'is_active' => true]);

        $response = $this->getJson("/api/v1/products?category_id={$categoryA->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'أ');
    }

    // 10. Availability filtering
    public function test_availability_filtering(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'متوفر', 'availability' => 'available', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'منتهي', 'availability' => 'out_of_stock', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?availability=available');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'متوفر');
    }

    // 11. Invalid availability returns 422
    public function test_invalid_availability_returns_422(): void
    {
        $response = $this->getJson('/api/v1/products?availability=invalid_value');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('availability');
    }

    // 12. Pagination
    public function test_pagination(): void
    {
        $c = $this->makeCategory();
        for ($i = 1; $i <= 25; $i++) {
            Product::create(['category_id' => $c->id, 'name' => "منتج {$i}", 'is_active' => true]);
        }

        $response = $this->getJson('/api/v1/products?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $this->assertEquals(25, $response->json('meta.total'));
    }

    // 13. per_page maximum
    public function test_per_page_maximum_is_enforced(): void
    {
        $c = $this->makeCategory();
        for ($i = 1; $i <= 5; $i++) {
            Product::create(['category_id' => $c->id, 'name' => "منتج {$i}", 'is_active' => true]);
        }

        $response = $this->getJson('/api/v1/products?per_page=500');

        $response->assertStatus(422);
    }

    // 14. per_page minimum
    public function test_per_page_minimum_is_enforced(): void
    {
        $response = $this->getJson('/api/v1/products?per_page=0');

        $response->assertStatus(422);
    }

    // 15. Sorting ascending
    public function test_sorting_ascending_by_name(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'ب', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'أ', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?sort=name');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'أ');
    }

    // 16. Sorting descending
    public function test_sorting_descending_by_name(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'أ', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'ب', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?sort=-name');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'ب');
    }

    // 17. Invalid sort returns 422
    public function test_invalid_sort_returns_422(): void
    {
        $response = $this->getJson('/api/v1/products?sort=price');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sort');
    }

    // 18. Current price is returned
    public function test_current_price_is_returned_in_listing(): void
    {
        $c = $this->makeCategory();
        $product = Product::create(['category_id' => $c->id, 'name' => 'منتج', 'is_active' => true]);
        Price::create(['product_id' => $product->id, 'price' => 25.00, 'effective_from' => now()->subDay()]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('data.0.current_price.price', '25.00');
    }

    // 19. Future price not returned as current
    public function test_future_price_is_not_returned_as_current(): void
    {
        $c = $this->makeCategory();
        $product = Product::create(['category_id' => $c->id, 'name' => 'منتج', 'is_active' => true]);
        Price::create(['product_id' => $product->id, 'price' => 25.00, 'effective_from' => now()->addDays(3)]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('data.0.current_price', null);
    }

    // 20. Expired price not returned as current
    public function test_expired_price_is_not_returned_as_current(): void
    {
        $c = $this->makeCategory();
        $product = Product::create(['category_id' => $c->id, 'name' => 'منتج', 'is_active' => true]);
        Price::create([
            'product_id' => $product->id, 'price' => 25.00,
            'effective_from' => now()->subDays(10), 'effective_until' => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('data.0.current_price', null);
    }

    // 21. No current price returns null
    public function test_product_with_no_current_price_returns_null(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'منتج بلا سعر', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('data.0.current_price', null);
    }

    // 22. Combined search + category
    public function test_combined_search_and_category_filter(): void
    {
        $categoryA = $this->makeCategory();
        $categoryB = $this->makeCategory();

        Product::create(['category_id' => $categoryA->id, 'name' => 'سكر أبيض', 'is_active' => true]);
        Product::create(['category_id' => $categoryB->id, 'name' => 'سكر بني', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=' . urlencode('سكر') . "&category_id={$categoryA->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 23. Combined search + availability
    public function test_combined_search_and_availability_filter(): void
    {
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'سكر متوفر', 'availability' => 'available', 'is_active' => true]);
        Product::create(['category_id' => $c->id, 'name' => 'سكر منتهي', 'availability' => 'out_of_stock', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=' . urlencode('سكر') . '&availability=available');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'سكر متوفر');
    }

    // 24. Combined search + category + availability
    public function test_combined_search_category_and_availability_filter(): void
    {
        $category = $this->makeCategory();
        Product::create(['category_id' => $category->id, 'name' => 'سكر متوفر', 'availability' => 'available', 'is_active' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'سكر منتهي', 'availability' => 'out_of_stock', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=' . urlencode('سكر') . "&category_id={$category->id}&availability=available");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 25. Admin search by name
    public function test_admin_search_by_name(): void
    {
        $this->actingAsAdmin();
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'سكر أبيض']);
        Product::create(['category_id' => $c->id, 'name' => 'أرز']);

        $response = $this->getJson('/api/v1/admin/products?search=' . urlencode('سكر'));

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 26. Admin search by barcode
    public function test_admin_search_by_barcode(): void
    {
        $this->actingAsAdmin();
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'منتج', 'barcode' => '5556667778']);

        $response = $this->getJson('/api/v1/admin/products?search=5556667778');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 27. Admin search by SKU
    public function test_admin_search_by_sku(): void
    {
        $this->actingAsAdmin();
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'منتج', 'sku' => 'ADMIN-SKU-1']);

        $response = $this->getJson('/api/v1/admin/products?search=ADMIN-SKU-1');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 28. Admin search by brand
    public function test_admin_search_by_brand(): void
    {
        $this->actingAsAdmin();
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'منتج', 'brand' => 'UniqueBrandX']);

        $response = $this->getJson('/api/v1/admin/products?search=UniqueBrandX');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 29. Admin can see inactive products
    public function test_admin_can_see_inactive_products(): void
    {
        $this->actingAsAdmin();
        $c = $this->makeCategory();
        Product::create(['category_id' => $c->id, 'name' => 'معطل', 'is_active' => false]);

        $response = $this->getJson('/api/v1/admin/products?search=' . urlencode('معطل'));

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // 30. Public endpoint accessible without auth
    public function test_public_endpoint_remains_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/products?search=test');

        $response->assertOk();
    }

    // 31. Admin endpoint remains protected
    public function test_admin_endpoint_remains_protected(): void
    {
        $response = $this->getJson('/api/v1/admin/products?search=test');

        $response->assertStatus(401);
    }

    // 32. Search does not cause incorrect product/price associations
    public function test_search_does_not_cause_incorrect_product_price_associations(): void
    {
        $c = $this->makeCategory();

        $productA = Product::create(['category_id' => $c->id, 'name' => 'منتج أ', 'is_active' => true]);
        $productB = Product::create(['category_id' => $c->id, 'name' => 'منتج ب', 'is_active' => true]);

        Price::create(['product_id' => $productA->id, 'price' => 10.00, 'effective_from' => now()->subDay()]);
        Price::create(['product_id' => $productB->id, 'price' => 20.00, 'effective_from' => now()->subDay()]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();

        $data = collect($response->json('data'));
        $this->assertEquals('10.00', $data->firstWhere('id', $productA->id)['current_price']['price']);
        $this->assertEquals('20.00', $data->firstWhere('id', $productB->id)['current_price']['price']);
    }
}
