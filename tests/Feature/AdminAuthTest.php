<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
    }

    public function test_login_returns_token(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertJsonStructure(['token']);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_returns_user_information(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('user.email', 'admin@example.com');
    }

    public function test_login_does_not_expose_password(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertJsonMissingPath('user.password');
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_validates_required_email(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_login_validates_required_password(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_authenticated_admin_can_access_me(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/admin/me');

        $response->assertOk();
        $response->assertJsonPath('id', $user->id);
        $response->assertJsonPath('email', $user->email);
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $response = $this->getJson('/api/v1/admin/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('admin-mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout');

        $response->assertOk();
        $response->assertJsonPath('message', 'Logout successful.');
    }

    // public function test_token_cannot_be_used_after_logout(): void
    // {
    //     $user = User::factory()->create();
    //     $token = $user->createToken('admin-mobile')->plainTextToken;

    //     $this->withHeader('Authorization', "Bearer {$token}")
    //         ->postJson('/api/v1/admin/logout');
        
    //     $this->assertDatabaseCount('personal_access_tokens', 0);
    //     $response = $this->withHeader('Authorization', "Bearer {$token}")
    //         ->getJson('/api/v1/admin/me');

    //     $response->assertStatus(401);
    // }
    public function test_token_cannot_be_used_after_logout(): void
{
    $user = User::factory()->create();

    $token = $user->createToken('admin-mobile')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/logout')
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);

    $this->app['auth']->forgetGuards();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/me');

    $response->assertStatus(401);
}

    public function test_unauthenticated_user_cannot_access_admin_categories(): void
    {
        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_admin_products(): void
    {
        $response = $this->getJson('/api/v1/admin/products');

        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_access_admin_categories(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertOk();
    }

    public function test_authenticated_admin_can_access_admin_products(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/admin/products');

        $response->assertOk();
    }

    public function test_public_categories_endpoint_remains_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
    }

    public function test_public_products_endpoint_remains_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
    }

    public function test_logout_only_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $tokenOne = $user->createToken('admin-mobile')->plainTextToken;
        $user->createToken('admin-mobile-2')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$tokenOne}")
            ->postJson('/api/v1/admin/logout');

        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_second_valid_token_remains_usable_after_logging_out_from_first_token(): void
    {
        $user = User::factory()->create();
        $tokenOne = $user->createToken('admin-mobile')->plainTextToken;
        $tokenTwo = $user->createToken('admin-mobile-2')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$tokenOne}")
            ->postJson('/api/v1/admin/logout');

        $response = $this->withHeader('Authorization', "Bearer {$tokenTwo}")
            ->getJson('/api/v1/admin/me');

        $response->assertOk();
    }
}