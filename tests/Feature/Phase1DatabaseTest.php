<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase1DatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_be_created(): void
    {
        $category = Category::create(['name' => 'بهارات']);

        $this->assertDatabaseHas('categories', ['name' => 'بهارات']);
        $this->assertTrue($category->is_active);
    }

    public function test_product_can_be_created(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'زيت زيتون',
        ]);

        $this->assertDatabaseHas('products', ['name' => 'زيت زيتون']);
        $this->assertEquals('unknown', $product->availability);
        $this->assertTrue($product->is_active);
    }

    public function test_product_belongs_to_category(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'زيت زيتون',
        ]);

        $this->assertEquals($category->id, $product->category->id);
    }

    public function test_product_can_have_multiple_prices(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'ملح',
        ]);

        Price::create([
            'product_id' => $product->id,
            'price' => 3.00,
            'effective_from' => now()->subDays(10),
            'effective_until' => now()->subDays(1),
        ]);

        Price::create([
            'product_id' => $product->id,
            'price' => 3.50,
            'effective_from' => now()->subDay(),
            'effective_until' => null,
        ]);

        $this->assertCount(2, $product->prices);
    }

    public function test_current_price_is_detected_correctly(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'شاي',
        ]);

        Price::create([
            'product_id' => $product->id,
            'price' => 10.00,
            'effective_from' => now()->subDays(5),
            'effective_until' => now()->subDay(),
        ]);

        Price::create([
            'product_id' => $product->id,
            'price' => 12.00,
            'effective_from' => now()->subDay(),
            'effective_until' => null,
        ]);

        $current = $product->currentPrice;

        $this->assertNotNull($current);
        $this->assertEquals(12.00, $current->price);
    }

    public function test_expired_price_is_not_current(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'قهوة',
        ]);

        Price::create([
            'product_id' => $product->id,
            'price' => 15.00,
            'effective_from' => now()->subDays(10),
            'effective_until' => now()->subDays(5), // انتهى منذ 5 أيام
        ]);

        $current = $product->currentPrice;

        $this->assertNull($current);
    }

    public function test_future_price_is_not_current(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'عصير',
        ]);

        Price::create([
            'product_id' => $product->id,
            'price' => 8.00,
            'effective_from' => now()->addDays(3), // يبدأ بعد 3 أيام
            'effective_until' => null,
        ]);

        $current = $product->currentPrice;

        $this->assertNull($current);
    }

    public function test_product_can_be_deactivated_using_is_active(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'بيض',
        ]);

        $product->update(['is_active' => false]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);

        // السجل لا يزال موجودًا فعليًا في قاعدة البيانات
        $this->assertNotNull(Product::find($product->id));
    }

    public function test_price_history_remains_after_product_deactivation(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'دقيق',
        ]);

        Price::create([
            'product_id' => $product->id,
            'price' => 4.00,
            'effective_from' => now()->subDays(2),
            'effective_until' => null,
        ]);

        $product->update(['is_active' => false]);

        $this->assertCount(1, $product->fresh()->prices);
    }

    public function test_barcode_must_be_unique(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);

        Product::create([
            'category_id' => $category->id,
            'name' => 'منتج أول',
            'barcode' => '1234567890123',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Product::create([
            'category_id' => $category->id,
            'name' => 'منتج ثاني',
            'barcode' => '1234567890123',
        ]);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);

        Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('categories')->where('id', $category->id)->delete();
    }

    public function test_cannot_delete_product_with_price_history(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
        ]);

        Price::create([
            'product_id' => $product->id,
            'price' => 5.00,
            'effective_from' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('products')->where('id', $product->id)->delete();
    }

    public function test_price_belongs_to_product(): void
    {
        $category = Category::create(['name' => 'مواد غذائية']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'منتج',
        ]);

        $price = Price::create([
            'product_id' => $product->id,
            'price' => 5.00,
            'effective_from' => now(),
        ]);

        $this->assertEquals($product->id, $price->product->id);
    }

    public function test_audit_log_relationship_with_user_works(): void
    {
        $user = User::factory()->create();

        $log = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'product_created',
            'model_type' => 'Product',
            'model_id' => 1,
            'new_values' => ['name' => 'منتج تجريبي'],
        ]);

        $this->assertEquals($user->id, $log->user->id);
        $this->assertIsArray($log->new_values);
    }
}
