<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    protected function makeCategory(): Category
    {
        return Category::create(['name' => 'مواد غذائية', 'is_active' => true]);
    }

    public function test_creating_product_without_image_still_works(): void
    {
        $this->actingAsAdmin();
        $category = $this->makeCategory();

        $response = $this->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'منتج بدون صورة',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.image', null);
    }

    public function test_creating_product_with_valid_image_works(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $category = $this->makeCategory();

        $file = UploadedFile::fake()->image('product.jpg');

        $response = $this->post('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'منتج بصورة',
            'image' => $file,
        ]);

        $response->assertCreated();
        $product = Product::first();
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_invalid_image_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $category = $this->makeCategory();

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->post('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'منتج',
            'image' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image');
    }

    public function test_updating_product_with_new_image_works(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $category = $this->makeCategory();

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
            'image' => 'products/old.jpg',
        ]);

        Storage::disk('public')->put('products/old.jpg', 'fake-content');

        $newFile = UploadedFile::fake()->image('new.jpg');

        $response = $this->post("/api/v1/admin/products/{$product->id}?_method=PUT", [
            'image' => $newFile,
        ]);

        $response->assertOk();
        $product->refresh();
        $this->assertNotEquals('products/old.jpg', $product->image);
        Storage::disk('public')->assertExists($product->image);
        Storage::disk('public')->assertMissing('products/old.jpg');
    }
}
