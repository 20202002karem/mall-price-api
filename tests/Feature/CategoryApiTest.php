<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_public_categories_returns_active_categories_only(): void
    {
        Category::create(['name' => 'مواد غذائية', 'is_active' => true]);
        Category::create(['name' => 'مواد تنظيف', 'is_active' => true]);
        Category::create(['name' => 'مغلق', 'is_active' => false]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_public_categories_does_not_return_inactive_categories(): void
    {
        Category::create(['name' => 'نشط', 'is_active' => true]);
        Category::create(['name' => 'غير نشط', 'is_active' => false]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertJsonMissing(['name' => 'غير نشط']);
    }

    public function test_public_category_details_works(): void
    {
        $category = Category::create(['name' => 'مواد غذائية', 'is_active' => true]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $category->id);
        $response->assertJsonPath('data.name', 'مواد غذائية');
    }

    public function test_public_inactive_category_returns_404(): void
    {
        $category = Category::create(['name' => 'غير نشط', 'is_active' => false]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertNotFound();
    }

    public function test_admin_can_list_categories(): void
    {
        $this->actingAsAdmin();

        Category::create(['name' => 'نشط', 'is_active' => true]);
        Category::create(['name' => 'غير نشط', 'is_active' => false]);

        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/categories', [
            'name' => 'بهارات',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'بهارات');
        $response->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('categories', ['name' => 'بهارات']);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        $this->actingAsAdmin();

        Category::create(['name' => 'مواد غذائية']);

        $response = $this->postJson('/api/v1/admin/categories', [
            'name' => 'مواد غذائية',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_admin_can_update_category(): void
    {
        $this->actingAsAdmin();

        $category = Category::create(['name' => 'قديم']);

        $response = $this->putJson("/api/v1/admin/categories/{$category->id}", [
            'name' => 'جديد',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'جديد');
    }

    public function test_admin_can_deactivate_category(): void
    {
        $this->actingAsAdmin();

        $category = Category::create(['name' => 'تصنيف']);

        $response = $this->putJson("/api/v1/admin/categories/{$category->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_cannot_delete_category_containing_products(): void
    {
        $this->actingAsAdmin();

        $category = Category::create(['name' => 'تصنيف']);
        Product::create(['category_id' => $category->id, 'name' => 'منتج']);

        $response = $this->deleteJson("/api/v1/admin/categories/{$category->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_can_delete_empty_category(): void
    {
        $this->actingAsAdmin();

        $category = Category::create(['name' => 'فارغ']);

        $response = $this->deleteJson("/api/v1/admin/categories/{$category->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}