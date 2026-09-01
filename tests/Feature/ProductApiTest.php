<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    protected function makeCategory(array $attrs = []): Category
    {
        return Category::create(array_merge(['name' => 'مواد غذائية', 'is_active' => true], $attrs));
    }

    public function test_public_products_returns_active_products(): void
    {
        $category = $this->makeCategory();
        Product::create(['category_id' => $category->id, 'name' => 'سكر', 'is_active' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'ملح', 'is_active' => false]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_inactive_products_are_hidden_from_public_api(): void
    {
        $category = $this->makeCategory();
        Product::create(['category_id' => $category->id, 'name' => 'مخفي', 'is_active' => false]);

        $response = $this->getJson('/api/v1/products');

        $response->assertJsonMissing(['name' => 'مخفي']);
    }

    public function test_product_details_works(): void
    {
        $category = $this->makeCategory();
        $product = Product::create(['category_id' => $category->id, 'name' => 'أرز', 'is_active' => true]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'أرز');
        $response->assertJsonPath('data.category.id', $category->id);
    }

    public function test_inactive_product_details_returns_404(): void
    {
        $category = $this->makeCategory();
        $product = Product::create(['category_id' => $category->id, 'name' => 'معطل', 'is_active' => false]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertNotFound();
    }

    public function test_search_products_by_name(): void
    {
        $category = $this->makeCategory();
        Product::create(['category_id' => $category->id, 'name' => 'سكر أبيض', 'is_active' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'أرز بسمتي', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=' . urlencode('سكر'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'سكر أبيض');
    }

    public function test_filter_products_by_category(): void
    {
        $categoryA = $this->makeCategory(['name' => 'أ']);
        $categoryB = $this->makeCategory(['name' => 'ب']);

        Product::create(['category_id' => $categoryA->id, 'name' => 'منتج أ', 'is_active' => true]);
        Product::create(['category_id' => $categoryB->id, 'name' => 'منتج ب', 'is_active' => true]);

        $response = $this->getJson("/api/v1/products?category_id={$categoryA->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'منتج أ');
    }

    public function test_pagination_works(): void
    {
        $category = $this->makeCategory();

        for ($i = 1; $i <= 25; $i++) {
            Product::create(['category_id' => $category->id, 'name' => "منتج {$i}", 'is_active' => true]);
        }

        $response = $this->getJson('/api/v1/products?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_admin_can_list_products(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        Product::create(['category_id' => $category->id, 'name' => 'نشط', 'is_active' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'معطل', 'is_active' => false]);

        $response = $this->getJson('/api/v1/admin/products');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_product(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();

        $response = $this->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'منتج جديد',
            'barcode' => '1112223334445',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'منتج جديد');
        $response->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('products', ['name' => 'منتج جديد']);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/products', [
            'category_id' => 99999,
            'name' => 'منتج',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('category_id');
    }

    public function test_inactive_category_cannot_be_assigned_to_new_product(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory(['is_active' => false]);

        $response = $this->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'منتج',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('category_id');
    }

    public function test_duplicate_barcode_is_rejected(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        Product::create(['category_id' => $category->id, 'name' => 'منتج 1', 'barcode' => '999888777']);

        $response = $this->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'منتج 2',
            'barcode' => '999888777',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('barcode');
    }

    public function test_invalid_availability_is_rejected(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();

        $response = $this->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'منتج',
            'availability' => 'not_a_valid_value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('availability');
    }

    public function test_admin_can_update_product(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        $product = Product::create(['category_id' => $category->id, 'name' => 'قديم']);

        $response = $this->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'جديد',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'جديد');
    }

    public function test_product_can_be_deactivated(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        $product = Product::create(['category_id' => $category->id, 'name' => 'منتج', 'is_active' => true]);

        $response = $this->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    public function test_product_can_be_reactivated(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        $product = Product::create(['category_id' => $category->id, 'name' => 'منتج', 'is_active' => false]);

        $response = $this->patchJson("/api/v1/admin/products/{$product->id}/activate");

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => true]);
    }

    public function test_delete_product_does_not_physically_delete_it(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        $product = Product::create(['category_id' => $category->id, 'name' => 'منتج', 'is_active' => true]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}");

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_price_history_remains_untouched_when_product_is_deactivated(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        $product = Product::create(['category_id' => $category->id, 'name' => 'منتج', 'is_active' => true]);

        Price::create([
            'product_id' => $product->id,
            'price' => 10.00,
            'effective_from' => now(),
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}");

        $this->assertCount(1, $product->fresh()->prices);
    }

    public function test_product_barcode_remains_stored_after_deactivation(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
            'barcode' => '555444333',
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}");

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'barcode' => '555444333',
        ]);
    }

    public function test_admin_product_response_contains_correct_fields(): void
    {
        $this->actingAsAdmin();

        $category = $this->makeCategory();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج كامل',
            'barcode' => '123123123',
            'sku' => 'SKU1',
            'brand' => 'ماركة',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id', 'category_id', 'name', 'barcode', 'sku', 'brand',
                'image', 'description', 'availability', 'is_active',
                'category', 'created_at', 'updated_at',
            ],
        ]);
    }
}