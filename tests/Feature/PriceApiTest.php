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

class PriceApiTest extends TestCase
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

    // protected function makeProduct(array $attrs = []): Product
    // {
    //     $category = Category::create(['name' => 'مواد غذائية', 'is_active' => true]);

    //     return Product::create(array_merge([
    //         'category_id' => $category->id,
    //         'name' => 'منتج',
    //         'is_active' => true,
    //     ], $attrs));
    // }
    protected function makeProduct(array $attrs = []): Product
{
    $category = Category::firstOrCreate(
        ['name' => 'مواد غذائية'],
        ['is_active' => true]
    );

    return Product::create(array_merge([
        'category_id' => $category->id,
        'name' => 'منتج',
        'is_active' => true,
    ], $attrs));
}
    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    // ===== AUTH =====

    public function test_unauthenticated_user_cannot_access_admin_price_endpoints(): void
    {
        $product = $this->makeProduct();
        $price = Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => now()->subDay()]);

        $this->getJson("/api/v1/admin/products/{$product->id}/prices")->assertStatus(401);
        $this->postJson("/api/v1/admin/products/{$product->id}/prices", [])->assertStatus(401);
        $this->getJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}")->assertStatus(401);
        $this->putJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}", [])->assertStatus(401);
        $this->deleteJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}")->assertStatus(401);
    }

    public function test_authenticated_admin_can_access_price_endpoints(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();

        $this->getJson("/api/v1/admin/products/{$product->id}/prices")->assertOk();
    }

    // ===== CRUD =====

    public function test_admin_can_create_price(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 10.00,
            'effective_from' => now()->toDateTimeString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('prices', ['product_id' => $product->id, 'price' => 10.00]);
    }

    public function test_admin_can_list_price_history(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();

        Price::create(['product_id' => $product->id, 'price' => 5, 'effective_from' => now()->subDays(5), 'effective_until' => now()->subDay()]);
        Price::create(['product_id' => $product->id, 'price' => 8, 'effective_from' => now()->subDay()]);

        $response = $this->getJson("/api/v1/admin/products/{$product->id}/prices");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.price', '8.00');
    }

    public function test_admin_can_view_a_specific_price(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $price = Price::create(['product_id' => $product->id, 'price' => 9, 'effective_from' => now()->subDay()]);

        $response = $this->getJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $price->id);
    }

    public function test_admin_can_update_a_future_scheduled_price(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $price = Price::create(['product_id' => $product->id, 'price' => 20, 'effective_from' => now()->addDays(5)]);

        $response = $this->putJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}", [
            'price' => 25,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.price', '25.00');
    }

    public function test_future_scheduled_price_can_be_cancelled(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $price = Price::create(['product_id' => $product->id, 'price' => 20, 'effective_from' => now()->addDays(5)]);

        $response = $this->deleteJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('prices', ['id' => $price->id]);
    }

    // ===== PRODUCT RELATIONSHIP =====

    public function test_price_belongs_to_product(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $price = Price::create(['product_id' => $product->id, 'price' => 9, 'effective_from' => now()->subDay()]);

        $this->assertEquals($product->id, $price->product->id);
    }

    public function test_price_from_another_product_cannot_be_accessed_through_nested_route(): void
    {
        $this->actingAsAdmin();
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        $priceB = Price::create(['product_id' => $productB->id, 'price' => 5, 'effective_from' => now()->subDay()]);

        $this->getJson("/api/v1/admin/products/{$productA->id}/prices/{$priceB->id}")->assertStatus(404);
    }

    // ===== VALIDATION =====

    public function test_price_is_required(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'effective_from' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('price');
    }

    public function test_price_must_be_numeric(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 'abc',
            'effective_from' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('price');
    }

    public function test_price_must_be_greater_than_zero(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 0,
            'effective_from' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('price');
    }

    public function test_effective_from_is_required(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 10,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('effective_from');
    }

    public function test_effective_until_must_be_after_effective_from(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 10,
            'effective_from' => now()->toDateTimeString(),
            'effective_until' => now()->subDay()->toDateTimeString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('effective_until');
    }

    // ===== CURRENT PRICE =====

    public function test_current_price_is_detected(): void
    {
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 15, 'effective_from' => now()->subHour()]);

        $response = $this->getJson("/api/v1/products/{$product->id}/price");

        $response->assertOk()->assertJsonPath('data.price', '15.00');
    }

    public function test_expired_price_is_not_current(): void
    {
        $product = $this->makeProduct();
        Price::create([
            'product_id' => $product->id, 'price' => 15,
            'effective_from' => now()->subDays(5), 'effective_until' => now()->subDay(),
        ]);

        $this->getJson("/api/v1/products/{$product->id}/price")->assertJsonPath('data', null);
    }

    public function test_future_price_is_not_current(): void
    {
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 20, 'effective_from' => now()->addDays(3)]);

        $this->getJson("/api/v1/products/{$product->id}/price")->assertJsonPath('data', null);
    }

    public function test_current_price_with_null_effective_until_works(): void
    {
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 11, 'effective_from' => now()->subDay(), 'effective_until' => null]);

        $this->getJson("/api/v1/products/{$product->id}/price")->assertJsonPath('data.price', '11.00');
    }

    public function test_no_current_price_returns_null_on_public_and_404_on_admin(): void
    {
        $product = $this->makeProduct();

        $this->getJson("/api/v1/products/{$product->id}/price")->assertOk()->assertJsonPath('data', null);

        $this->actingAsAdmin();
        $this->getJson("/api/v1/admin/products/{$product->id}/prices/current")->assertStatus(404);
    }

    // ===== OVERLAP (الأمثلة الستة) =====

    public function test_overlap_case_1_rejects(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => '2026-08-10', 'effective_until' => '2026-08-20']);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 12, 'effective_from' => '2026-08-15', 'effective_until' => '2026-08-25',
        ]);

        $response->assertStatus(422);
    }

    public function test_overlap_case_2_allows_touching_boundary(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => '2026-08-10', 'effective_until' => '2026-08-20']);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 12, 'effective_from' => '2026-08-20', 'effective_until' => '2026-08-30',
        ]);

        $response->assertCreated();
    }

    public function test_overlap_case_3_two_infinite_periods_reject(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        // سعر مستقبلي مفتوح (لا يبدأ الآن حتى لا يتم إغلاقه تلقائيًا)
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => '2026-09-10']);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 12, 'effective_from' => '2026-09-20',
        ]);

        $response->assertStatus(422);
    }

    public function test_overlap_case_4_infinite_vs_bounded_rejects(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => '2026-09-10']);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 12, 'effective_from' => '2026-09-20', 'effective_until' => '2026-09-30',
        ]);

        $response->assertStatus(422);
    }

    public function test_overlap_case_5_allows_before(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => '2026-08-10', 'effective_until' => '2026-08-20']);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 12, 'effective_from' => '2026-08-05', 'effective_until' => '2026-08-10',
        ]);

        $response->assertCreated();
    }

    public function test_overlap_case_6_future_non_overlapping_allows(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => now()->subDay()]);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 12, 'effective_from' => now()->addDays(30)->toDateTimeString(),
        ]);

        // السعر الحالي المفتوح يُغلق تلقائيًا عند بداية السعر المجدول - لا تداخل حقيقي
        $response->assertCreated();
    }

    // ===== IMMEDIATE PRICE REPLACEMENT =====

    public function test_creating_immediate_price_closes_previous_current_price(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $first = Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => now()->subDay()]);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 12, 'effective_from' => now()->toDateTimeString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.old_price', '10.00');

        $first->refresh();
        $this->assertNotNull($first->effective_until);
        $this->assertEquals(1, $product->prices()->current()->count());
    }

    // ===== PRODUCT STATE =====

    public function test_cannot_create_price_for_inactive_product(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct(['is_active' => false]);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 10, 'effective_from' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_existing_price_history_remains_after_product_deactivation(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => now()->subDay()]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}");

        $this->assertCount(1, $product->fresh()->prices);
    }

    // ===== HISTORICAL INTEGRITY =====

    public function test_cannot_modify_price_value_of_already_effective_price(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $price = Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => now()->subDay()]);

        $response = $this->putJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}", [
            'price' => 999,
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseHas('prices', ['id' => $price->id, 'price' => 10.00]);
    }

    public function test_historical_price_deletion_is_prevented(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $price = Price::create([
            'product_id' => $product->id, 'price' => 10,
            'effective_from' => now()->subDays(10), 'effective_until' => now()->subDays(5),
        ]);

        $response = $this->deleteJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('prices', ['id' => $price->id]);
    }

    public function test_active_current_price_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $price = Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => now()->subDay()]);

        $response = $this->deleteJson("/api/v1/admin/products/{$product->id}/prices/{$price->id}");

        $response->assertStatus(409);
    }

    // ===== TRANSACTION SAFETY =====

    public function test_failed_price_mutation_does_not_leave_partial_changes(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct();
        $open = Price::create(['product_id' => $product->id, 'price' => 10, 'effective_from' => now()->subDays(2)]);

        // سعر آخر مغلق يتداخل عمدًا مع الفترة الجديدة (بعد إغلاق open) لإجبار فشل داخل الـ transaction
        Price::create([
            'product_id' => $product->id, 'price' => 50,
            'effective_from' => now()->addDays(1), 'effective_until' => now()->addDays(5),
        ]);

        $response = $this->postJson("/api/v1/admin/products/{$product->id}/prices", [
            'price' => 12, 'effective_from' => now()->addDays(2)->toDateTimeString(),
        ]);

        $response->assertStatus(422);

        // السعر المفتوح الأصلي يجب أن يبقى كما هو (لم يُغلق) لأن الـ transaction تراجعت بالكامل
        $open->refresh();
        $this->assertNull($open->effective_until);
    }

    // ===== PUBLIC API =====

    public function test_public_endpoint_returns_current_price(): void
    {
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 7, 'effective_from' => now()->subHour()]);

        $this->getJson("/api/v1/products/{$product->id}/price")->assertJsonPath('data.price', '7.00');
    }

    public function test_public_endpoint_does_not_return_future_price(): void
    {
        $product = $this->makeProduct();
        Price::create(['product_id' => $product->id, 'price' => 7, 'effective_from' => now()->addDays(2)]);

        $this->getJson("/api/v1/products/{$product->id}/price")->assertJsonPath('data', null);
    }

    public function test_public_endpoint_does_not_expose_inactive_product(): void
    {
        $product = $this->makeProduct(['is_active' => false]);
        Price::create(['product_id' => $product->id, 'price' => 7, 'effective_from' => now()->subHour()]);

        $this->getJson("/api/v1/products/{$product->id}/price")->assertStatus(404);
    }
}
